from __future__ import annotations

from dataclasses import dataclass
import logging
from pathlib import Path
from time import perf_counter
from typing import Any

from ocr_service.extractor import OcrExtractor
from ocr_service.fields import public_field_name
from ocr_service.logger import get_logger, log_event
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
        self.logger = get_logger("ocr_service.orchestrator")

    def extract(self, file_path: Path, options: OcrProcessOptions) -> dict[str, Any]:
        started_at = perf_counter()
        log_event(
            self.logger,
            logging.INFO,
            "orchestrator.extract.started",
            file_name=file_path.name,
            country_code=options.country_code,
            required_fields=options.required_fields,
            openrouter_enabled=options.openrouter_enabled,
            auto_generate_templates=options.auto_generate_templates,
        )

        local_response = self.extractor.extract(
            file_path,
            country_code=options.country_code,
            required_fields=options.required_fields,
        )

        log_event(
            self.logger,
            logging.INFO,
            "orchestrator.local.completed",
            file_name=file_path.name,
            status=local_response.get("status"),
        )

        if local_response["status"] == "success":
            log_event(
                self.logger,
                logging.INFO,
                "orchestrator.extract.completed",
                file_name=file_path.name,
                final_source="local",
                status=local_response.get("status"),
                duration_ms=round((perf_counter() - started_at) * 1000, 2),
            )
            return local_response

        if not options.openrouter_enabled or not self.openrouter_client.is_configured():
            log_event(
                self.logger,
                logging.INFO,
                "orchestrator.extract.completed",
                file_name=file_path.name,
                final_source="local",
                status=local_response.get("status"),
                reason="openrouter_disabled_or_unconfigured",
                duration_ms=round((perf_counter() - started_at) * 1000, 2),
            )
            return local_response

        openrouter_extraction = self.openrouter_client.extract_fields(
            file_path,
            country_code=options.country_code,
            required_fields=options.required_fields,
        )
        openrouter_response = self._build_openrouter_response(openrouter_extraction, options)

        log_event(
            self.logger,
            logging.INFO,
            "orchestrator.openrouter_extract.completed",
            file_name=file_path.name,
            status=openrouter_response.get("status"),
        )

        if local_response["status"] == "partial":
            merged_response = self._merge_missing_required_fields(local_response, openrouter_response, options)
            log_event(
                self.logger,
                logging.INFO,
                "orchestrator.extract.completed",
                file_name=file_path.name,
                final_source="merged",
                status=merged_response.get("status"),
                duration_ms=round((perf_counter() - started_at) * 1000, 2),
            )
            return merged_response

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
                    log_event(
                        self.logger,
                        logging.INFO,
                        "orchestrator.extract.completed",
                        file_name=file_path.name,
                        final_source="generated_template_retry",
                        status=retry_response.get("status"),
                        duration_ms=round((perf_counter() - started_at) * 1000, 2),
                    )
                    return retry_response

        if openrouter_response["status"] != "failed":
            log_event(
                self.logger,
                logging.INFO,
                "orchestrator.extract.completed",
                file_name=file_path.name,
                final_source="openrouter",
                status=openrouter_response.get("status"),
                duration_ms=round((perf_counter() - started_at) * 1000, 2),
            )
            return openrouter_response

        log_event(
            self.logger,
            logging.WARNING,
            "orchestrator.extract.completed",
            file_name=file_path.name,
            final_source="local",
            status=local_response.get("status"),
            duration_ms=round((perf_counter() - started_at) * 1000, 2),
        )
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
            log_event(
                self.logger,
                logging.WARNING,
                "orchestrator.template_generation.rejected",
                file_name=sample_path.name,
                reason="invalid_template_payload",
            )
            return False

        if not validate_ai_template_definition(
            definition=definition,
            sample_path=sample_path,
            extractor=self.extractor,
            country_code=options.country_code,
            required_fields=options.required_fields,
        ):
            log_event(
                self.logger,
                logging.WARNING,
                "orchestrator.template_generation.rejected",
                file_name=sample_path.name,
                issuer=definition.issuer,
                reason="validation_failed",
            )
            return False

        output_path = default_ai_template_path(
            self.extractor.writable_template_dir,
            options.country_code,
            definition.issuer,
        )
        output_path.parent.mkdir(parents=True, exist_ok=True)
        output_path.write_text(render_template_content(definition), encoding="utf-8")

        log_event(
            self.logger,
            logging.INFO,
            "orchestrator.template_generation.saved",
            file_name=sample_path.name,
            issuer=definition.issuer,
            output_path=output_path,
        )

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
