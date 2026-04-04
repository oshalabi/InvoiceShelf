from __future__ import annotations

import base64
import json
import logging
import mimetypes
import os
from pathlib import Path
from time import perf_counter
from typing import Any
from urllib import error, request

from ocr_service.logger import get_logger, log_event


class OpenRouterClient:
    def __init__(self) -> None:
        self.logger = get_logger("ocr_service.openrouter")
        self.base_url = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1/chat/completions")
        self.api_key = os.getenv("OPENROUTER_API_KEY", "")
        self.model = os.getenv("OPENROUTER_MODEL", "")
        self.template_model = os.getenv("OPENROUTER_TEMPLATE_MODEL") or self.model
        self.timeout = float(os.getenv("OPENROUTER_TIMEOUT", "30"))
        self.require_zdr = self._env_flag("OPENROUTER_REQUIRE_ZDR", default=True)
        self.http_referer = os.getenv("OPENROUTER_HTTP_REFERER", "")
        self.app_name = os.getenv("OPENROUTER_APP_NAME", "InvoiceShelf OCR")
        self.pdf_engine = os.getenv("OPENROUTER_PDF_ENGINE", "mistral-ocr")

        log_event(
            self.logger,
            logging.DEBUG,
            "openrouter.config.loaded",
            base_url=self.base_url,
            model_configured=bool(self.model),
            template_model_configured=bool(self.template_model),
            require_zdr=self.require_zdr,
            pdf_engine=self.pdf_engine,
        )

    def is_configured(self) -> bool:
        return bool(self.api_key and self.model)

    def extract_fields(
        self,
        file_path: Path,
        country_code: str,
        required_fields: tuple[str, ...],
    ) -> dict[str, Any] | None:
        if not self.is_configured():
            log_event(
                self.logger,
                logging.INFO,
                "openrouter.extract.skipped",
                file_name=file_path.name,
                reason="client_not_configured",
            )
            return None

        log_event(
            self.logger,
            logging.INFO,
            "openrouter.extract.started",
            file_name=file_path.name,
            country_code=country_code,
            required_fields=required_fields,
            model=self.model,
        )

        payload = {
            "model": self.model,
            "stream": False,
            "messages": [
                {
                    "role": "user",
                    "content": [
                        {
                            "type": "text",
                            "text": self._extraction_prompt(country_code, required_fields),
                        },
                        self._file_content_part(file_path),
                    ],
                }
            ],
            "response_format": {
                "type": "json_schema",
                "json_schema": {
                    "name": "invoice_field_extraction",
                    "strict": True,
                    "schema": self._extraction_schema(required_fields),
                },
            },
        }
        payload.update(self._optional_provider_payload(file_path))

        response_payload = self._request(payload)

        if response_payload is None:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.extract.failed",
                file_name=file_path.name,
                reason="request_failed",
            )
            return None

        content = self._extract_response_content(response_payload)

        if content is None:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.extract.failed",
                file_name=file_path.name,
                reason="missing_response_content",
            )
            return None

        try:
            parsed_content = json.loads(content)
        except json.JSONDecodeError:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.extract.failed",
                file_name=file_path.name,
                reason="invalid_json_content",
            )
            return None

        if not isinstance(parsed_content, dict):
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.extract.failed",
                file_name=file_path.name,
                reason="non_object_content",
            )
            return None

        values = parsed_content.get("fields", {})
        confidences = parsed_content.get("confidence", {})
        issuer = parsed_content.get("issuer")

        if not isinstance(values, dict) or not isinstance(confidences, dict):
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.extract.failed",
                file_name=file_path.name,
                reason="invalid_field_payload",
            )
            return None

        log_event(
            self.logger,
            logging.INFO,
            "openrouter.extract.completed",
            file_name=file_path.name,
            field_names=sorted(values.keys()),
            model=self.model,
        )

        return {
            "fields": values,
            "confidence": confidences,
            "issuer": issuer if isinstance(issuer, str) else None,
            "model": self.model,
        }

    def generate_template_definition(
        self,
        file_path: Path,
        country_code: str,
        required_fields: tuple[str, ...],
    ) -> dict[str, Any] | None:
        if not self.api_key or not self.template_model:
            log_event(
                self.logger,
                logging.INFO,
                "openrouter.template_generation.skipped",
                file_name=file_path.name,
                reason="client_not_configured",
            )
            return None

        log_event(
            self.logger,
            logging.INFO,
            "openrouter.template_generation.started",
            file_name=file_path.name,
            country_code=country_code,
            required_fields=required_fields,
            model=self.template_model,
        )

        payload = {
            "model": self.template_model,
            "stream": False,
            "messages": [
                {
                    "role": "user",
                    "content": [
                        {
                            "type": "text",
                            "text": self._template_prompt(country_code, required_fields),
                        },
                        self._file_content_part(file_path),
                    ],
                }
            ],
            "response_format": {
                "type": "json_schema",
                "json_schema": {
                    "name": "invoice_template_generation",
                    "strict": True,
                    "schema": self._template_schema(required_fields),
                },
            },
        }
        payload.update(self._optional_provider_payload(file_path))

        response_payload = self._request(payload)

        if response_payload is None:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.template_generation.failed",
                file_name=file_path.name,
                reason="request_failed",
            )
            return None

        content = self._extract_response_content(response_payload)

        if content is None:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.template_generation.failed",
                file_name=file_path.name,
                reason="missing_response_content",
            )
            return None

        try:
            parsed_content = json.loads(content)
        except json.JSONDecodeError:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.template_generation.failed",
                file_name=file_path.name,
                reason="invalid_json_content",
            )
            return None

        if not isinstance(parsed_content, dict):
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.template_generation.failed",
                file_name=file_path.name,
                reason="non_object_content",
            )
            return None

        log_event(
            self.logger,
            logging.INFO,
            "openrouter.template_generation.completed",
            file_name=file_path.name,
            keyword_count=len(parsed_content.get("keywords", [])) if isinstance(parsed_content.get("keywords"), list) else 0,
            model=self.template_model,
        )

        return parsed_content

    def _request(self, payload: dict[str, Any]) -> dict[str, Any] | None:
        started_at = perf_counter()
        request_payload = json.dumps(payload).encode("utf-8")
        http_request = request.Request(
            self.base_url,
            data=request_payload,
            method="POST",
            headers=self._headers(),
        )

        try:
            with request.urlopen(http_request, timeout=self.timeout) as response:
                status_code = getattr(response, "status", response.getcode())
                raw_payload = response.read().decode("utf-8")
        except error.HTTPError as exception:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.request.failed",
                status_code=exception.code,
                reason="http_error",
                duration_ms=round((perf_counter() - started_at) * 1000, 2),
            )
            return None
        except error.URLError:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.request.failed",
                reason="url_error",
                duration_ms=round((perf_counter() - started_at) * 1000, 2),
            )
            return None
        except TimeoutError:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.request.failed",
                reason="timeout",
                duration_ms=round((perf_counter() - started_at) * 1000, 2),
            )
            return None

        log_event(
            self.logger,
            logging.INFO,
            "openrouter.request.completed",
            status_code=status_code,
            duration_ms=round((perf_counter() - started_at) * 1000, 2),
        )

        try:
            parsed_payload = json.loads(raw_payload)
        except json.JSONDecodeError:
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.request.failed",
                reason="invalid_json_response",
            )
            return None

        if not isinstance(parsed_payload, dict):
            log_event(
                self.logger,
                logging.WARNING,
                "openrouter.request.failed",
                reason="non_object_response",
            )
            return None

        return parsed_payload

    def _headers(self) -> dict[str, str]:
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }

        if self.http_referer:
            headers["HTTP-Referer"] = self.http_referer

        if self.app_name:
            headers["X-Title"] = self.app_name

        return headers

    def _optional_provider_payload(self, file_path: Path) -> dict[str, Any]:
        provider_payload: dict[str, Any] = {
            "provider": {
                "require_parameters": True,
            }
        }

        if self.require_zdr:
            provider_payload["provider"]["zdr"] = True

        if file_path.suffix.lower() == ".pdf":
            provider_payload["plugins"] = [
                {
                    "id": "file-parser",
                    "pdf": {
                        "engine": self.pdf_engine,
                    },
                }
            ]

        return provider_payload

    def _file_content_part(self, file_path: Path) -> dict[str, Any]:
        mime_type = mimetypes.guess_type(file_path.name)[0] or "application/octet-stream"
        data_url = self._data_url(file_path, mime_type)

        if file_path.suffix.lower() == ".pdf":
            return {
                "type": "file",
                "file": {
                    "filename": file_path.name,
                    "file_data": data_url,
                },
            }

        return {
            "type": "image_url",
            "image_url": {
                "url": data_url,
            },
        }

    def _data_url(self, file_path: Path, mime_type: str) -> str:
        encoded_file = base64.b64encode(file_path.read_bytes()).decode("ascii")
        return f"data:{mime_type};base64,{encoded_file}"

    def _extract_response_content(self, payload: dict[str, Any]) -> str | None:
        choices = payload.get("choices")

        if not isinstance(choices, list) or not choices:
            return None

        first_choice = choices[0]

        if not isinstance(first_choice, dict):
            return None

        message = first_choice.get("message")

        if not isinstance(message, dict):
            return None

        content = message.get("content")

        if isinstance(content, str):
            return content

        if isinstance(content, list):
            parts = []

            for item in content:
                if not isinstance(item, dict):
                    continue

                text = item.get("text")

                if isinstance(text, str):
                    parts.append(text)

            if parts:
                return "".join(parts)

        return None

    def _extraction_prompt(self, country_code: str, required_fields: tuple[str, ...]) -> str:
        return (
            "Extract invoice data from this document.\n"
            f"Country code: {country_code}.\n"
            "Return only structured JSON matching the schema.\n"
            "Use null when a field is not clearly visible.\n"
            "Rules:\n"
            "- invoice_number is the invoice or tax-invoice identifier, not order or delivery number.\n"
            "- date is the invoice issue date, not order, due, or delivery date.\n"
            "- amount is the final gross amount payable, inclusive of tax when present.\n"
            "- currency_code must be an uppercase ISO-4217 code.\n"
            f"Required fields: {', '.join(required_fields)}.\n"
            "Also include issuer when clearly visible."
        )

    def _template_prompt(self, country_code: str, required_fields: tuple[str, ...]) -> str:
        return (
            "Generate an invoice2data-compatible starter template definition for this invoice.\n"
            f"Country code: {country_code}.\n"
            "Return only structured JSON matching the schema.\n"
            "Template rules:\n"
            "- Generate stable supplier-level keywords.\n"
            "- Do not use invoice numbers, dates, order numbers, delivery numbers, or totals as keywords.\n"
            "- Prefer issuer, VAT number, KvK number, domain names, or stable company identifiers.\n"
            "- fields must only target the required invoice fields.\n"
            "- Use regex patterns suitable for invoice2data and capture the field value in the first capture group.\n"
            "- options.remove_whitespace must be true.\n"
            f"Required fields: {', '.join(required_fields)}."
        )

    def _extraction_schema(self, required_fields: tuple[str, ...]) -> dict[str, Any]:
        field_properties = {
            field_name: {"type": ["string", "number", "null"]}
            for field_name in required_fields
        }
        confidence_properties = {
            field_name: {"type": "number", "minimum": 0, "maximum": 1}
            for field_name in required_fields
        }

        return {
            "type": "object",
            "additionalProperties": False,
            "properties": {
                "issuer": {"type": ["string", "null"]},
                "fields": {
                    "type": "object",
                    "additionalProperties": False,
                    "properties": field_properties,
                    "required": list(required_fields),
                },
                "confidence": {
                    "type": "object",
                    "additionalProperties": False,
                    "properties": confidence_properties,
                    "required": list(required_fields),
                },
            },
            "required": ["fields", "confidence", "issuer"],
        }

    def _template_schema(self, required_fields: tuple[str, ...]) -> dict[str, Any]:
        field_properties = {
            field_name: {"type": "string"}
            for field_name in required_fields
        }

        return {
            "type": "object",
            "additionalProperties": False,
            "properties": {
                "issuer": {"type": "string"},
                "keywords": {
                    "type": "array",
                    "items": {"type": "string"},
                    "minItems": 1,
                },
                "fields": {
                    "type": "object",
                    "additionalProperties": False,
                    "properties": field_properties,
                    "required": list(required_fields),
                },
                "options": {
                    "type": "object",
                    "additionalProperties": False,
                    "properties": {
                        "date_formats": {
                            "type": "array",
                            "items": {"type": "string"},
                            "minItems": 1,
                        },
                        "remove_whitespace": {"type": "boolean"},
                    },
                    "required": ["date_formats", "remove_whitespace"],
                },
            },
            "required": ["issuer", "keywords", "fields", "options"],
        }

    def _env_flag(self, name: str, default: bool) -> bool:
        value = os.getenv(name)

        if value is None:
            return default

        return value.strip().lower() in {"1", "true", "yes", "on"}
