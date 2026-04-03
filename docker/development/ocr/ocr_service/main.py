from __future__ import annotations

import html
import json
import os
from pathlib import Path
from tempfile import TemporaryDirectory

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import HTMLResponse

from ocr_service.extractor import OcrExtractor
from ocr_service.fields import default_required_fields_from_env, normalize_required_fields
from ocr_service.openrouter_client import OpenRouterClient
from ocr_service.orchestrator import OcrOrchestrator, OcrProcessOptions
from ocr_service.template_generator import (
    TemplateSpec,
    generate_starter_template_from_sample,
)

app = FastAPI(title="InvoiceShelf OCR Sidecar")


def _parse_template_dirs() -> list[Path]:
    configured_template_dirs = os.getenv("OCR_TEMPLATE_DIRS")

    if not configured_template_dirs:
        default_template_dir = Path(os.getenv("OCR_TEMPLATE_DIR", "/app/templates"))
        return [default_template_dir]

    return [
        Path(item.strip())
        for item in configured_template_dirs.split(",")
        if item.strip()
    ]


def _build_extractor() -> OcrExtractor:
    template_dirs = _parse_template_dirs()
    writable_template_dir = Path(os.getenv("OCR_TEMPLATE_DIR", str(template_dirs[-1])))

    return OcrExtractor(
        template_dir=template_dirs[0],
        template_dirs=template_dirs,
        writable_template_dir=writable_template_dir,
    )


extractor = _build_extractor()
openrouter_client = OpenRouterClient()
orchestrator = OcrOrchestrator(extractor, openrouter_client)


def _page_layout(title: str, body: str) -> str:
    return f"""<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{html.escape(title)}</title>
    <style>
      :root {{
        color-scheme: light;
        --bg: #f3efe4;
        --card: #fffaf0;
        --line: #d3c6a6;
        --ink: #1f2937;
        --muted: #6b7280;
        --accent: #a84f1d;
        --accent-soft: #f4d6b8;
      }}
      * {{
        box-sizing: border-box;
      }}
      body {{
        margin: 0;
        font-family: Georgia, "Times New Roman", serif;
        color: var(--ink);
        background:
          radial-gradient(circle at top right, rgba(168, 79, 29, 0.15), transparent 30%),
          linear-gradient(180deg, #f7f1e4 0%, var(--bg) 100%);
      }}
      main {{
        max-width: 900px;
        margin: 0 auto;
        padding: 32px 20px 48px;
      }}
      .card {{
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 24px;
        box-shadow: 0 20px 50px rgba(31, 41, 55, 0.08);
      }}
      h1, h2 {{
        margin: 0 0 12px;
      }}
      p {{
        color: var(--muted);
        line-height: 1.5;
      }}
      .nav {{
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
      }}
      .nav a, button {{
        border: 0;
        border-radius: 999px;
        background: var(--accent);
        color: white;
        padding: 10px 18px;
        font-size: 14px;
        text-decoration: none;
        cursor: pointer;
      }}
      .nav a.secondary {{
        background: var(--accent-soft);
        color: var(--ink);
      }}
      form {{
        display: grid;
        gap: 14px;
      }}
      label {{
        display: grid;
        gap: 6px;
        font-weight: 600;
      }}
      input, textarea {{
        width: 100%;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 12px 14px;
        font: inherit;
        background: white;
      }}
      textarea {{
        min-height: 100px;
        resize: vertical;
      }}
      .grid {{
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }}
      pre {{
        overflow: auto;
        padding: 18px;
        border-radius: 16px;
        border: 1px solid var(--line);
        background: #1f2937;
        color: #f9fafb;
        font-size: 13px;
        line-height: 1.5;
      }}
      .notice {{
        margin: 16px 0;
        padding: 14px 16px;
        border-left: 4px solid var(--accent);
        border-radius: 12px;
        background: var(--accent-soft);
      }}
    </style>
  </head>
  <body>
    <main>{body}</main>
  </body>
</html>"""


def _validate_upload(file: UploadFile) -> tuple[str, str]:
    original_name = Path(file.filename or "receipt").name
    suffix = Path(original_name).suffix.lower()

    if suffix not in extractor.SUPPORTED_EXTENSIONS:
        raise HTTPException(
            status_code=422,
            detail="Unsupported file type. Please upload PDF, JPG, or PNG.",
        )

    return original_name, suffix


async def _store_upload(file: UploadFile) -> tuple[str, bytes]:
    original_name, _suffix = _validate_upload(file)
    file_bytes = await file.read()

    if not file_bytes:
        raise HTTPException(
            status_code=422,
            detail="Uploaded file is empty.",
        )

    return original_name, file_bytes


def _extract_from_bytes(
    file_name: str,
    file_bytes: bytes,
    country_code: str,
    required_fields: tuple[str, ...] | None = None,
    openrouter_enabled: bool = False,
    auto_generate_templates: bool = False,
) -> dict[str, object]:
    normalized_required_fields = required_fields or default_required_fields_from_env()

    with TemporaryDirectory() as temporary_directory:
        input_path = Path(temporary_directory) / file_name
        input_path.write_bytes(file_bytes)
        return orchestrator.extract(
            input_path,
            OcrProcessOptions(
                country_code=country_code,
                required_fields=normalized_required_fields,
                openrouter_enabled=openrouter_enabled,
                auto_generate_templates=auto_generate_templates,
            ),
        )


def _render_json_result_page(title: str, payload: dict[str, object]) -> HTMLResponse:
    return HTMLResponse(
        _page_layout(
            title,
            f"""
            <div class="nav">
              <a href="/">OCR Playground</a>
              <a class="secondary" href="/template-generator">Template Generator</a>
            </div>
            <section class="card">
              <h1>{html.escape(title)}</h1>
              <p>The response below is the exact JSON payload produced by the OCR sidecar.</p>
              <pre>{html.escape(json.dumps(payload, indent=2, ensure_ascii=False))}</pre>
            </section>
            """,
        )
    )


@app.get("/", response_class=HTMLResponse)
def playground() -> HTMLResponse:
    return HTMLResponse(
        _page_layout(
            "OCR Playground",
            """
            <div class="nav">
              <a href="/">OCR Playground</a>
              <a class="secondary" href="/template-generator">Template Generator</a>
            </div>
            <section class="card">
              <h1>OCR Playground</h1>
              <p>Upload a PDF, JPG, or PNG and open the JSON response on a separate page.</p>
              <form action="/playground/result" method="post" enctype="multipart/form-data">
                <label>
                  Invoice file
                  <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required />
                </label>
                <label>
                  Country code
                  <input type="text" name="country_code" value="NL" maxlength="2" />
                </label>
                <label>
                  Required fields
                  <input type="text" name="required_fields" value="invoice_number,date,amount,currency_code" />
                </label>
                <label>
                  OpenRouter fallback
                  <input type="text" name="openrouter_enabled" value="false" />
                </label>
                <label>
                  Auto-generate templates
                  <input type="text" name="auto_generate_templates" value="false" />
                </label>
                <button type="submit">Run OCR</button>
              </form>
            </section>
            """,
        )
    )


@app.get("/template-generator", response_class=HTMLResponse)
def template_generator_page() -> HTMLResponse:
    return HTMLResponse(
        _page_layout(
            "Template Generator",
            """
            <div class="nav">
              <a class="secondary" href="/">OCR Playground</a>
              <a href="/template-generator">Template Generator</a>
            </div>
            <section class="card">
              <h1>Starter Template Generator</h1>
              <p>Upload a sample invoice and enter the label text printed on it. The sidecar will generate and save a starter <code>template.yml</code> under the writable OCR template directory.</p>
              <form action="/template-generator/result" method="post" enctype="multipart/form-data">
                <div class="grid">
                  <label>
                    Sample invoice
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required />
                  </label>
                  <label>
                    Country code
                    <input type="text" name="country_code" value="NL" maxlength="2" />
                  </label>
                  <label>
                    Issuer
                    <input type="text" name="issuer" placeholder="Acme B.V." required />
                  </label>
                  <label>
                    Currency code
                    <input type="text" name="currency_code" value="EUR" maxlength="3" />
                  </label>
                </div>
                <div class="grid">
                  <label>
                    Invoice number label
                    <input type="text" name="invoice_number_label" placeholder="Factuurnummer" required />
                  </label>
                  <label>
                    Date label
                    <input type="text" name="date_label" placeholder="Factuurdatum" required />
                  </label>
                  <label>
                    Amount label
                    <input type="text" name="amount_label" placeholder="Totaal incl. btw" required />
                  </label>
                  <label>
                    Currency label
                    <input type="text" name="currency_label" placeholder="Optional" />
                  </label>
                </div>
                <label>
                  Extra keywords
                  <textarea name="keywords" placeholder="One keyword per line"></textarea>
                </label>
                <button type="submit">Generate Starter Template</button>
              </form>
            </section>
            """,
        )
    )


@app.get("/health")
def health() -> dict[str, int | str]:
    return {
        "status": "ok",
        "templates": extractor.template_count(),
    }


@app.post("/extract")
async def extract(
    file: UploadFile = File(...),
    country_code: str = Form("NL"),
    required_fields: str = Form("invoice_number,date,amount,currency_code"),
    openrouter_enabled: str = Form("false"),
    auto_generate_templates: str = Form("false"),
) -> dict[str, object]:
    original_name, file_bytes = await _store_upload(file)

    try:
        normalized_required_fields = normalize_required_fields(required_fields)
    except ValueError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    return _extract_from_bytes(
        original_name,
        file_bytes,
        country_code,
        required_fields=normalized_required_fields,
        openrouter_enabled=_to_bool(openrouter_enabled),
        auto_generate_templates=_to_bool(auto_generate_templates),
    )


@app.post("/playground/result", response_class=HTMLResponse)
async def playground_result(
    file: UploadFile = File(...),
    country_code: str = Form("NL"),
    required_fields: str = Form("invoice_number,date,amount,currency_code"),
    openrouter_enabled: str = Form("false"),
    auto_generate_templates: str = Form("false"),
) -> HTMLResponse:
    original_name, file_bytes = await _store_upload(file)

    try:
        normalized_required_fields = normalize_required_fields(required_fields)
    except ValueError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    payload = _extract_from_bytes(
        original_name,
        file_bytes,
        country_code,
        required_fields=normalized_required_fields,
        openrouter_enabled=_to_bool(openrouter_enabled),
        auto_generate_templates=_to_bool(auto_generate_templates),
    )

    return _render_json_result_page("OCR JSON Result", payload)


@app.post("/template-generator/result", response_class=HTMLResponse)
async def template_generator_result(
    file: UploadFile = File(...),
    issuer: str = Form(...),
    invoice_number_label: str = Form(...),
    date_label: str = Form(...),
    amount_label: str = Form(...),
    country_code: str = Form("NL"),
    currency_code: str = Form("EUR"),
    currency_label: str = Form(""),
    keywords: str = Form(""),
) -> HTMLResponse:
    original_name, file_bytes = await _store_upload(file)
    keyword_lines = tuple(
        keyword.strip()
        for keyword in keywords.splitlines()
        if keyword.strip()
    )

    with TemporaryDirectory() as temporary_directory:
        input_path = Path(temporary_directory) / original_name
        input_path.write_bytes(file_bytes)
        result = generate_starter_template_from_sample(
            sample_path=input_path,
            template_dir=extractor.writable_template_dir,
            extractor=extractor,
            spec=TemplateSpec(
                issuer=issuer.strip(),
                invoice_number_label=invoice_number_label.strip(),
                date_label=date_label.strip(),
                amount_label=amount_label.strip(),
                country_code=country_code.strip().upper() or "NL",
                currency_code=currency_code.strip().upper() or "EUR",
                currency_label=currency_label.strip() or None,
                keywords=keyword_lines,
            ),
        )

    notices = [
        f"<div class=\"notice\"><strong>Saved:</strong> {html.escape(str(result.output_path))}</div>",
    ]

    if result.missing_labels:
        notices.append(
            "<div class=\"notice\"><strong>Labels not found in OCR text:</strong> "
            + html.escape(", ".join(result.missing_labels))
            + "</div>"
        )

    return HTMLResponse(
        _page_layout(
            "Starter Template Result",
            f"""
            <div class="nav">
              <a class="secondary" href="/">OCR Playground</a>
              <a href="/template-generator">Template Generator</a>
            </div>
            <section class="card">
              <h1>Starter Template Result</h1>
              <p>The template below was generated from your uploaded sample and written into the writable OCR template directory.</p>
              {''.join(notices)}
              <h2>Generated template.yml</h2>
              <pre>{html.escape(result.content)}</pre>
              <h2>OCR text preview</h2>
              <pre>{html.escape(result.ocr_text or 'No OCR text was extracted from this sample.')}</pre>
            </section>
            """,
        )
    )


def _to_bool(value: str) -> bool:
    return value.strip().lower() in {"1", "true", "yes", "on"}
