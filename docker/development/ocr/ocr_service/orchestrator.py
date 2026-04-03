from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Any

from ocr_service.extractor import OcrExtractor
from ocr_service.fields import public_field_name
from ocr_service.openrouter_client import OpenRouterClient
from ocr_service.template_generator import (
    GeneratedTemplateDefinition,
    default_ai_template_path,
    render_template_content,
    validate_ai_template_definition,
)


@dataclass(frozen=True)
class OcrProcessOptions:
    country_code: str = "NL"
    required_fields: tuple[str, ...] = ("invoice_number", "date", "amount", "currency_code")
    openrouter_enabled: bool = False
    auto_generate_templates: bool = False


class OcrOrchestrator:
    def __init__(self, extractor: OcrExtractor, openrouter_client: OpenRouterClient) -> None:
        self.extractor = extractor
        self.openrouter_client = openrouter_client

    def extract(self, file_path: Path, options: OcrProcessOptions) -> dict[str, Any]:
        local_response = self.extractor.extract(
            file_path,
            country_code=options.country_code,
            required_fields=options.required_fields,
        )

        if local_response["status"] == "success":
            return local_response

        if not options.openrouter_enabled or not self.openrouter_client.is_configured():
            return local_response

        openrouter_extraction = self.openrouter_client.extract_fields(
            file_path,
            country_code=options.country_code,
            required_fields=options.required_fields,
        )
        openrouter_response = self._build_openrouter_response(openrouter_extraction, options)

        if local_response["status"] == "partial":
            return self._merge_missing_required_fields(local_response, openrouter_response, options)

        if local_response["status"] == "failed" and options.auto_generate_templates:
            generated_template = self.openrouter_client.generate_template_definition(
                file_path,
                country_code=options.country_code,
                required_fields=options.required_fields,
            )

            if self._store_generated_template(generated_template, file_path, options):
                retry_response = self.extractor.extract(
                    file_path,
                    country_code=options.country_code,
                    required_fields=options.required_fields,
                )

                if retry_response["status"] != "failed":
                    return retry_response

        if openrouter_response["status"] != "failed":
            return openrouter_response

        return local_response

    def _build_openrouter_response(
        self,
        openrouter_extraction: dict[str, Any] | None,
        options: OcrProcessOptions,
    ) -> dict[str, Any]:
        if not openrouter_extraction:
            return self.extractor._failed_response(  # noqa: SLF001
                options.country_code,
                options.required_fields,
            )

        payload = dict(openrouter_extraction.get("fields", {}))
        issuer = openrouter_extraction.get("issuer")

        if issuer:
            payload["issuer"] = issuer

        response = self.extractor._build_response(  # noqa: SLF001
            payload=payload,
            source_reader="openrouter",
            country_code=options.country_code,
            required_fields=options.required_fields,
            field_confidences=openrouter_extraction.get("confidence", {}),
        )

        model = openrouter_extraction.get("model")

        if model:
            response["unmapped_fields"]["openrouter_model"] = {
                "value": model,
                "confidence": 1.0,
            }

        return response

    def _merge_missing_required_fields(
        self,
        local_response: dict[str, Any],
        openrouter_response: dict[str, Any],
        options: OcrProcessOptions,
    ) -> dict[str, Any]:
        merged_fields = dict(local_response.get("fields", {}))

        for field_name in options.required_fields:
            public_name = public_field_name(field_name)

            if public_name in merged_fields:
                continue

            openrouter_field = openrouter_response.get("fields", {}).get(public_name)

            if openrouter_field:
                merged_fields[public_name] = openrouter_field

        merged_unmapped_fields = dict(local_response.get("unmapped_fields", {}))

        for key, value in openrouter_response.get("unmapped_fields", {}).items():
            if key == "source_reader":
                merged_unmapped_fields["fallback_source_reader"] = value
                continue

            if key == "missing_required_fields":
                continue

            merged_unmapped_fields.setdefault(key, value)

        missing_required_fields = [
            public_field_name(field_name)
            for field_name in options.required_fields
            if public_field_name(field_name) not in merged_fields
        ]

        if missing_required_fields:
            merged_status = "partial"
            merged_message = "Invoice matched a template, but some required fields are missing."
            merged_unmapped_fields["missing_required_fields"] = {
                "value": missing_required_fields,
                "confidence": 1.0,
            }
        else:
            merged_status = "success"
            merged_message = "Invoice fields extracted successfully."
            merged_unmapped_fields.pop("missing_required_fields", None)

        return {
            "status": merged_status,
            "message": merged_message,
            "fields": merged_fields,
            "unmapped_fields": merged_unmapped_fields,
        }

    def _store_generated_template(
        self,
        generated_template: dict[str, Any] | None,
        sample_path: Path,
        options: OcrProcessOptions,
    ) -> bool:
        definition = self._normalize_generated_template(generated_template, options.required_fields)

        if definition is None:
            return False

        if not validate_ai_template_definition(
            definition=definition,
            sample_path=sample_path,
            extractor=self.extractor,
            country_code=options.country_code,
            required_fields=options.required_fields,
        ):
            return False

        output_path = default_ai_template_path(
            self.extractor.writable_template_dir,
            options.country_code,
            definition.issuer,
        )
        output_path.parent.mkdir(parents=True, exist_ok=True)
        output_path.write_text(render_template_content(definition), encoding="utf-8")

        return True

    def _normalize_generated_template(
        self,
        generated_template: dict[str, Any] | None,
        required_fields: tuple[str, ...],
    ) -> GeneratedTemplateDefinition | None:
        if not isinstance(generated_template, dict):
            return None

        issuer = generated_template.get("issuer")
        keywords = generated_template.get("keywords")
        fields = generated_template.get("fields")

        if not isinstance(issuer, str) or not issuer.strip():
            return None

        if not isinstance(keywords, list) or not all(isinstance(keyword, str) for keyword in keywords):
            return None

        if not isinstance(fields, dict):
            return None

        normalized_fields: dict[str, str] = {}

        for field_name in required_fields:
            field_pattern = fields.get(field_name)

            if not isinstance(field_pattern, str) or not field_pattern.strip():
                return None

            normalized_fields[field_name] = field_pattern.strip()

        normalized_keywords = tuple(keyword.strip() for keyword in keywords if keyword.strip())

        if not normalized_keywords:
            return None

        return GeneratedTemplateDefinition(
            issuer=issuer.strip(),
            keywords=normalized_keywords,
            fields=normalized_fields,
        )
