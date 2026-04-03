from pathlib import Path

from ocr_service.extractor import OcrExtractor
from ocr_service.orchestrator import OcrOrchestrator, OcrProcessOptions
from ocr_service import orchestrator as orchestrator_module


class FakeOpenRouterClient:
    def __init__(self, extraction_response=None, template_response=None) -> None:
        self.extraction_response = extraction_response
        self.template_response = template_response
        self.extraction_calls = 0
        self.template_calls = 0

    def is_configured(self) -> bool:
        return True

    def extract_fields(self, *_args, **_kwargs):
        self.extraction_calls += 1
        return self.extraction_response

    def generate_template_definition(self, *_args, **_kwargs):
        self.template_calls += 1
        return self.template_response


def test_orchestrator_returns_local_success_without_openrouter_fallback(monkeypatch, tmp_path: Path) -> None:
    extractor = OcrExtractor(tmp_path)
    client = FakeOpenRouterClient()
    service = OcrOrchestrator(extractor, client)
    invoice_path = tmp_path / "invoice.pdf"
    invoice_path.write_bytes(b"%PDF-1.7")

    expected_response = {
        "status": "success",
        "message": "Invoice fields extracted successfully.",
        "fields": {
            "invoice_number": {"value": "INV-42", "confidence": 0.98},
            "invoice_date": {"value": "2026-04-03", "confidence": 0.98},
            "total_amount": {"value": 123.45, "confidence": 0.98},
            "currency_code": {"value": "EUR", "confidence": 0.98},
        },
        "unmapped_fields": {},
    }

    monkeypatch.setattr(extractor, "extract", lambda *_args, **_kwargs: expected_response)

    response = service.extract(invoice_path, OcrProcessOptions(openrouter_enabled=True))

    assert response == expected_response
    assert client.extraction_calls == 0
    assert client.template_calls == 0


def test_orchestrator_merges_missing_required_fields_from_openrouter(monkeypatch, tmp_path: Path) -> None:
    extractor = OcrExtractor(tmp_path)
    client = FakeOpenRouterClient(
        extraction_response={
            "fields": {
                "currency_code": "EUR",
            },
            "confidence": {
                "currency_code": 0.92,
            },
            "issuer": "Acme B.V.",
            "model": "openrouter/model",
        }
    )
    service = OcrOrchestrator(extractor, client)
    invoice_path = tmp_path / "invoice.pdf"
    invoice_path.write_bytes(b"%PDF-1.7")

    monkeypatch.setattr(
        extractor,
        "extract",
        lambda *_args, **_kwargs: {
            "status": "partial",
            "message": "Invoice matched a template, but some required fields are missing.",
            "fields": {
                "invoice_number": {"value": "INV-42", "confidence": 0.98},
                "invoice_date": {"value": "2026-04-03", "confidence": 0.98},
                "total_amount": {"value": 123.45, "confidence": 0.98},
            },
            "unmapped_fields": {
                "source_reader": {"value": "tesseract", "confidence": 1.0},
                "missing_required_fields": {"value": ["currency_code"], "confidence": 1.0},
            },
        },
    )

    response = service.extract(
        invoice_path,
        OcrProcessOptions(
            openrouter_enabled=True,
            required_fields=("invoice_number", "date", "amount", "currency_code"),
        ),
    )

    assert response["status"] == "success"
    assert response["fields"]["currency_code"]["value"] == "EUR"
    assert response["unmapped_fields"]["fallback_source_reader"]["value"] == "openrouter"


def test_orchestrator_saves_validated_ai_template_and_retries_local_extraction(monkeypatch, tmp_path: Path) -> None:
    extractor = OcrExtractor(tmp_path)
    client = FakeOpenRouterClient(
        extraction_response={
            "fields": {
                "invoice_number": "INV-42",
                "date": "2026-04-03",
                "amount": 123.45,
                "currency_code": "EUR",
            },
            "confidence": {
                "invoice_number": 0.91,
                "date": 0.90,
                "amount": 0.94,
                "currency_code": 0.95,
            },
            "issuer": "Acme B.V.",
            "model": "openrouter/model",
        },
        template_response={
            "issuer": "Acme B.V.",
            "keywords": ["(?i)Acme\\s+B\\.V\\."],
            "fields": {
                "invoice_number": "(?i)Factuurnummer\\s*[:#-]?\\s*((?=[A-Z0-9/._-]*\\d)[A-Z0-9][A-Z0-9/._-]+)",
                "date": "(?i)Factuurdatum\\s*[:#-]?\\s*([0-9]{2}-[0-9]{2}-[0-9]{4})",
                "amount": "(?i)Totaal\\s*[:#-]?\\s*(?:EUR|€)?\\s*([0-9.,]+)",
                "currency_code": "(?i)((?:EUR|€))",
            },
            "options": {
                "date_formats": ["%d-%m-%Y"],
                "remove_whitespace": True,
            },
        },
    )
    service = OcrOrchestrator(extractor, client)
    invoice_path = tmp_path / "invoice.pdf"
    invoice_path.write_bytes(b"%PDF-1.7")

    responses = iter([
        {
            "status": "failed",
            "message": "No matching invoice template found for this document.",
            "fields": {},
            "unmapped_fields": {},
        },
        {
            "status": "success",
            "message": "Invoice fields extracted successfully.",
            "fields": {
                "invoice_number": {"value": "INV-42", "confidence": 0.98},
                "invoice_date": {"value": "2026-04-03", "confidence": 0.98},
                "total_amount": {"value": 123.45, "confidence": 0.98},
                "currency_code": {"value": "EUR", "confidence": 0.98},
            },
            "unmapped_fields": {},
        },
    ])

    monkeypatch.setattr(extractor, "extract", lambda *_args, **_kwargs: next(responses))
    monkeypatch.setattr(orchestrator_module, "validate_ai_template_definition", lambda **_kwargs: True)

    response = service.extract(
        invoice_path,
        OcrProcessOptions(
            openrouter_enabled=True,
            auto_generate_templates=True,
        ),
    )

    created_templates = list(tmp_path.rglob("template_ai*.yml"))

    assert response["status"] == "success"
    assert created_templates
    assert client.template_calls == 1


def test_orchestrator_returns_openrouter_response_when_ai_template_validation_fails(monkeypatch, tmp_path: Path) -> None:
    extractor = OcrExtractor(tmp_path)
    client = FakeOpenRouterClient(
        extraction_response={
            "fields": {
                "invoice_number": "INV-42",
                "date": "2026-04-03",
                "amount": 123.45,
                "currency_code": "EUR",
            },
            "confidence": {
                "invoice_number": 0.91,
                "date": 0.90,
                "amount": 0.94,
                "currency_code": 0.95,
            },
            "issuer": "Acme B.V.",
            "model": "openrouter/model",
        },
        template_response={
            "issuer": "Acme B.V.",
            "keywords": ["INV-42"],
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
        },
    )
    service = OcrOrchestrator(extractor, client)
    invoice_path = tmp_path / "invoice.pdf"
    invoice_path.write_bytes(b"%PDF-1.7")

    monkeypatch.setattr(
        extractor,
        "extract",
        lambda *_args, **_kwargs: {
            "status": "failed",
            "message": "No matching invoice template found for this document.",
            "fields": {},
            "unmapped_fields": {},
        },
    )
    monkeypatch.setattr(orchestrator_module, "validate_ai_template_definition", lambda **_kwargs: False)

    response = service.extract(
        invoice_path,
        OcrProcessOptions(
            openrouter_enabled=True,
            auto_generate_templates=True,
        ),
    )

    assert response["status"] == "success"
    assert response["fields"]["invoice_number"]["value"] == "INV-42"
    assert list(tmp_path.rglob("template_ai*.yml")) == []
