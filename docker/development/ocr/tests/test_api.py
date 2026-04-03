from pathlib import Path

from fastapi.testclient import TestClient

from ocr_service.main import app
from ocr_service import main


client = TestClient(app)


def test_health_endpoint(monkeypatch) -> None:
    monkeypatch.setattr(main.extractor, "template_count", lambda: 3)

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {
        "status": "ok",
        "templates": 3,
    }


def test_extract_rejects_unsupported_file_type() -> None:
    response = client.post(
        "/extract",
        files={
            "file": ("invoice.gif", b"gif89a", "image/gif"),
        },
        data={"country_code": "NL"},
    )

    assert response.status_code == 422
    assert response.json()["detail"] == "Unsupported file type. Please upload PDF, JPG, or PNG."


def test_extract_rejects_empty_file() -> None:
    response = client.post(
        "/extract",
        files={
            "file": ("invoice.pdf", b"", "application/pdf"),
        },
        data={"country_code": "NL"},
    )

    assert response.status_code == 422
    assert response.json()["detail"] == "Uploaded file is empty."


def test_playground_page_renders_forms() -> None:
    response = client.get("/")

    assert response.status_code == 200
    assert "OCR Playground" in response.text
    assert "/playground/result" in response.text
    assert "/template-generator" in response.text


def test_extract_delegates_to_extractor(monkeypatch) -> None:
    expected_response = {
        "status": "success",
        "message": "Invoice fields extracted successfully.",
        "fields": {
            "invoice_date": {"value": "2026-04-02", "confidence": 0.98},
        },
        "unmapped_fields": {},
    }

    def fake_extract(input_path: Path, country_code: str) -> dict:
        assert input_path.suffix == ".pdf"
        assert country_code == "NL"
        return expected_response

    monkeypatch.setattr(main.extractor, "extract", fake_extract)

    response = client.post(
        "/extract",
        files={
            "file": ("invoice.pdf", b"%PDF-1.7", "application/pdf"),
        },
        data={"country_code": "NL"},
    )

    assert response.status_code == 200
    assert response.json() == expected_response


def test_playground_result_renders_json_payload(monkeypatch) -> None:
    expected_response = {
        "status": "success",
        "message": "Invoice fields extracted successfully.",
        "fields": {
            "invoice_number": {"value": "DEMO-42", "confidence": 0.98},
        },
        "unmapped_fields": {},
    }

    monkeypatch.setattr(main, "_extract_from_bytes", lambda *_args, **_kwargs: expected_response)

    response = client.post(
        "/playground/result",
        files={
            "file": ("invoice.pdf", b"%PDF-1.7", "application/pdf"),
        },
        data={"country_code": "NL"},
    )

    assert response.status_code == 200
    assert "&quot;status&quot;: &quot;success&quot;" in response.text
    assert "&quot;invoice_number&quot;" in response.text


def test_template_generator_page_renders_form() -> None:
    response = client.get("/template-generator")

    assert response.status_code == 200
    assert "Starter Template Generator" in response.text
    assert "/template-generator/result" in response.text


def test_template_generator_result_writes_template(monkeypatch, tmp_path: Path) -> None:
    monkeypatch.setattr(main.extractor, "template_dir", tmp_path)
    monkeypatch.setattr(
        main.extractor,
        "_extract_ocr_text",
        lambda *_args, **_kwargs: (
            "Acme B.V.\nKvK 12345678\nFactuurnummer: ACME-42\nFactuurdatum: 03-04-2026\n"
            "Totaal incl. btw: EUR 123,45"
        ),
    )

    response = client.post(
        "/template-generator/result",
        files={
            "file": ("invoice.png", b"png-data", "image/png"),
        },
        data={
            "issuer": "Acme B.V.",
            "invoice_number_label": "Factuurnummer",
            "date_label": "Factuurdatum",
            "amount_label": "Totaal incl. btw",
            "country_code": "NL",
            "currency_code": "EUR",
            "currency_label": "",
            "keywords": "KvK 12345678",
        },
    )

    created_template = tmp_path / "nl" / "acme_b_v" / "template.yml"

    assert response.status_code == 200
    assert "Starter Template Result" in response.text
    assert created_template.exists()
    assert "Factuurnummer" in created_template.read_text(encoding="utf-8")
