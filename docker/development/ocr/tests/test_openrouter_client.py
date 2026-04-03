import json
from pathlib import Path

from ocr_service.openrouter_client import OpenRouterClient


def test_openrouter_extract_fields_builds_pdf_request_with_zdr(monkeypatch, tmp_path: Path) -> None:
    client = OpenRouterClient()
    invoice_path = tmp_path / "invoice.pdf"
    invoice_path.write_bytes(b"%PDF-1.7")
    captured_payload = {}

    monkeypatch.setenv("OPENROUTER_API_KEY", "test-key")
    monkeypatch.setenv("OPENROUTER_MODEL", "openrouter/model")
    client = OpenRouterClient()

    def fake_request(payload):
        captured_payload["payload"] = payload
        return {
            "choices": [
                {
                    "message": {
                        "content": json.dumps({
                            "issuer": "Acme B.V.",
                            "fields": {
                                "invoice_number": "INV-42",
                                "date": "2026-04-03",
                                "amount": 123.45,
                                "currency_code": "EUR",
                            },
                            "confidence": {
                                "invoice_number": 0.91,
                                "date": 0.92,
                                "amount": 0.93,
                                "currency_code": 0.94,
                            },
                        })
                    }
                }
            ]
        }

    monkeypatch.setattr(client, "_request", fake_request)

    response = client.extract_fields(
        invoice_path,
        country_code="NL",
        required_fields=("invoice_number", "date", "amount", "currency_code"),
    )

    assert response["fields"]["invoice_number"] == "INV-42"
    assert captured_payload["payload"]["provider"]["zdr"] is True
    assert captured_payload["payload"]["provider"]["require_parameters"] is True
    assert captured_payload["payload"]["plugins"][0]["pdf"]["engine"] == "mistral-ocr"
    assert captured_payload["payload"]["messages"][0]["content"][1]["type"] == "file"


def test_openrouter_extract_fields_builds_image_request(monkeypatch, tmp_path: Path) -> None:
    monkeypatch.setenv("OPENROUTER_API_KEY", "test-key")
    monkeypatch.setenv("OPENROUTER_MODEL", "openrouter/model")
    client = OpenRouterClient()
    invoice_path = tmp_path / "invoice.png"
    invoice_path.write_bytes(b"\x89PNG")
    captured_payload = {}

    def fake_request(payload):
        captured_payload["payload"] = payload

        return {
            "choices": [
                {
                    "message": {
                        "content": json.dumps({
                            "issuer": None,
                            "fields": {
                                "invoice_number": None,
                                "date": None,
                                "amount": None,
                                "currency_code": None,
                            },
                            "confidence": {
                                "invoice_number": 0,
                                "date": 0,
                                "amount": 0,
                                "currency_code": 0,
                            },
                        })
                    }
                }
            ]
        }

    monkeypatch.setattr(client, "_request", fake_request)

    client.extract_fields(
        invoice_path,
        country_code="NL",
        required_fields=("invoice_number", "date", "amount", "currency_code"),
    )

    assert captured_payload["payload"]["messages"][0]["content"][1]["type"] == "image_url"
    assert "plugins" not in captured_payload["payload"]


def test_openrouter_generate_template_definition_uses_template_model(monkeypatch, tmp_path: Path) -> None:
    monkeypatch.setenv("OPENROUTER_API_KEY", "test-key")
    monkeypatch.setenv("OPENROUTER_MODEL", "openrouter/extract-model")
    monkeypatch.setenv("OPENROUTER_TEMPLATE_MODEL", "openrouter/template-model")
    client = OpenRouterClient()
    invoice_path = tmp_path / "invoice.pdf"
    invoice_path.write_bytes(b"%PDF-1.7")
    captured_payload = {}

    def fake_request(payload):
        captured_payload["payload"] = payload

        return {
            "choices": [
                {
                    "message": {
                        "content": json.dumps({
                            "issuer": "Acme B.V.",
                            "keywords": ["(?i)Acme\\s+B\\.V\\."],
                            "fields": {
                                "invoice_number": "(INV-42)",
                                "date": "(2026-04-03)",
                                "amount": "(123.45)",
                                "currency_code": "(EUR)",
                            },
                            "options": {
                                "date_formats": ["%Y-%m-%d"],
                                "remove_whitespace": True,
                            },
                        })
                    }
                }
            ]
        }

    monkeypatch.setattr(client, "_request", fake_request)

    response = client.generate_template_definition(
        invoice_path,
        country_code="NL",
        required_fields=("invoice_number", "date", "amount", "currency_code"),
    )

    assert response["issuer"] == "Acme B.V."
    assert captured_payload["payload"]["model"] == "openrouter/template-model"
