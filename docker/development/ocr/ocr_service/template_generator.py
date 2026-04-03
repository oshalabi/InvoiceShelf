from __future__ import annotations

import argparse
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from ocr_service.extractor import OcrExtractor
from ocr_service.fields import DEFAULT_REQUIRED_FIELDS

GENERIC_ISSUER_VALUES = {
    "",
    "n/a",
    "na",
    "onbekend",
    "supplier",
    "unknown",
    "vendor",
}
DATE_VALUE_PATTERN = r"([0-9]{2}[./-][0-9]{2}[./-][0-9]{4})"
INVOICE_NUMBER_VALUE_PATTERN = r"((?=[A-Z0-9/._-]*\d)[A-Z0-9][A-Z0-9/._-]+)"
AMOUNT_VALUE_PATTERN = r"([0-9]{1,3}(?:[.,][0-9]{3})*[.,][0-9]{2}|[0-9]+[.,][0-9]{2})"


@dataclass(frozen=True)
class TemplateSpec:
    issuer: str
    invoice_number_label: str
    date_label: str
    amount_label: str
    country_code: str = "NL"
    currency_code: str = "EUR"
    currency_label: str | None = None
    keywords: tuple[str, ...] = ()


@dataclass(frozen=True)
class TemplateGenerationResult:
    output_path: Path
    content: str
    ocr_text: str
    keywords: tuple[str, ...]
    missing_labels: tuple[str, ...]


@dataclass(frozen=True)
class GeneratedTemplateDefinition:
    issuer: str
    keywords: tuple[str, ...]
    fields: dict[str, str]


def generate_starter_template_from_sample(
    sample_path: Path,
    template_dir: Path,
    extractor: OcrExtractor,
    spec: TemplateSpec,
    output_path: Path | None = None,
) -> TemplateGenerationResult:
    if sample_path.suffix.lower() not in extractor.SUPPORTED_EXTENSIONS:
        raise ValueError("Unsupported file type. Please upload PDF, JPG, or PNG.")

    text_sources = collect_text_sources(sample_path, extractor, spec.country_code)
    ocr_text = text_sources[0] if text_sources else ""
    resolved_issuer = resolve_issuer(spec.issuer, text_sources)
    missing_labels = tuple(
        label_name
        for label_name, label_value in (
            ("invoice_number_label", spec.invoice_number_label),
            ("date_label", spec.date_label),
            ("amount_label", spec.amount_label),
            ("currency_label", spec.currency_label or ""),
        )
        if label_value and not any(contains_phrase(text, label_value) for text in text_sources)
    )
    definition = build_validated_template_definition(
        spec=spec,
        resolved_issuer=resolved_issuer,
        text_sources=text_sources,
        extractor=extractor,
    )
    content = render_template_content(definition)
    destination = output_path or default_template_path(template_dir, spec.country_code, resolved_issuer)
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text(content, encoding="utf-8")

    return TemplateGenerationResult(
        output_path=destination,
        content=content,
        ocr_text=ocr_text,
        keywords=definition.keywords,
        missing_labels=missing_labels,
    )


def collect_text_sources(sample_path: Path, extractor: OcrExtractor, country_code: str) -> list[str]:
    text_sources: list[str] = []
    ocr_text = extractor._extract_ocr_text(
        sample_path,
        extractor._language_for_country(country_code.upper()),
    )

    if ocr_text.strip():
        text_sources.append(ocr_text)

    if sample_path.suffix.lower() == ".pdf":
        pdf_text = extractor._load_input_text(sample_path, "pdftotext")

        if pdf_text.strip():
            text_sources.append(pdf_text)

    return text_sources


def build_validated_template_definition(
    spec: TemplateSpec,
    resolved_issuer: str,
    text_sources: list[str],
    extractor: OcrExtractor,
    required_fields: tuple[str, ...] | None = None,
) -> GeneratedTemplateDefinition:
    required_fields = required_fields or DEFAULT_REQUIRED_FIELDS
    fields = {
        "invoice_number": choose_best_pattern(
            build_invoice_number_patterns(spec),
            text_sources,
        ),
        "date": choose_best_pattern(
            build_date_patterns(spec),
            text_sources,
        ),
        "amount": choose_best_pattern(
            build_amount_patterns(spec),
            text_sources,
        ),
        "currency_code": choose_best_pattern(
            build_currency_patterns(spec),
            text_sources,
        ),
    }
    keyword_candidates = (
        dedupe_patterns(list(spec.keywords))
        if spec.keywords
        else build_keyword_candidates(resolved_issuer, text_sources)
    )
    keywords = choose_valid_keywords(
        keyword_candidates,
        fields,
        resolved_issuer,
        text_sources,
        extractor,
        required_fields,
    )

    if not validate_template_definition(
        resolved_issuer,
        keywords,
        fields,
        text_sources,
        extractor,
        required_fields,
    ) and validate_template_definition(
        resolved_issuer,
        (),
        fields,
        text_sources,
        extractor,
        required_fields,
    ):
        keywords = ()

    return GeneratedTemplateDefinition(
        issuer=resolved_issuer,
        keywords=keywords,
        fields=fields,
    )


def default_template_path(template_dir: Path, country_code: str, issuer: str) -> Path:
    supplier_directory = template_dir / country_code.lower() / slugify(issuer)
    supplier_directory.mkdir(parents=True, exist_ok=True)

    index = 0

    while True:
        file_name = "template.yml" if index == 0 else f"template_{index}.yml"
        candidate = supplier_directory / file_name

        if not candidate.exists():
            return candidate

        index += 1


def default_ai_template_path(template_dir: Path, country_code: str, issuer: str) -> Path:
    supplier_directory = template_dir / country_code.lower() / slugify(issuer)
    supplier_directory.mkdir(parents=True, exist_ok=True)

    index = 0

    while True:
        file_name = "template_ai.yml" if index == 0 else f"template_ai_{index}.yml"
        candidate = supplier_directory / file_name

        if not candidate.exists():
            return candidate

        index += 1


def render_template_content(definition: GeneratedTemplateDefinition) -> str:
    lines = [
        f"issuer: {yaml_quote(definition.issuer)}",
        "keywords:",
        *[f"  - {yaml_quote(keyword)}" for keyword in definition.keywords],
        "fields:",
        *[
            f"  {field_name}: {yaml_quote(pattern)}"
            for field_name, pattern in definition.fields.items()
        ],
        "options:",
        "  date_formats:",
        "    - '%d-%m-%Y'",
        "    - '%d/%m/%Y'",
        "    - '%Y-%m-%d'",
        "  remove_whitespace: true",
    ]

    return "\n".join(lines) + "\n"


def choose_valid_keywords(
    candidate_keywords: tuple[str, ...],
    fields: dict[str, str],
    issuer: str,
    text_sources: list[str],
    extractor: OcrExtractor,
    required_fields: tuple[str, ...],
) -> tuple[str, ...]:
    unique_keywords = dedupe_patterns(list(candidate_keywords))

    if not unique_keywords:
        return ()

    chosen_keywords: list[str] = []

    for keyword in unique_keywords:
        if not any(re.search(keyword, text, re.MULTILINE | re.IGNORECASE) for text in text_sources):
            continue

        trial_keywords = tuple([*chosen_keywords, keyword])

        if validate_template_definition(issuer, trial_keywords, fields, text_sources, extractor, required_fields):
            chosen_keywords.append(keyword)

    if chosen_keywords:
        return tuple(chosen_keywords[:3])

    if validate_template_definition(issuer, (), fields, text_sources, extractor, required_fields):
        return ()

    return unique_keywords[:1]


def validate_template_definition(
    issuer: str,
    keywords: tuple[str, ...],
    fields: dict[str, str],
    text_sources: list[str],
    extractor: OcrExtractor,
    required_fields: tuple[str, ...] | None = None,
) -> bool:
    required_fields = required_fields or DEFAULT_REQUIRED_FIELDS
    template_definition: dict[str, Any] = {
        "issuer": issuer,
        "keywords": list(keywords),
        "fields": fields,
    }

    for text in text_sources:
        payload = extractor._match_template_definition(template_definition, text)

        if not payload:
            continue

        payload = extractor._repair_payload(payload, text)

        if set(required_fields).issubset(payload.keys()):
            return True

    return False


def validate_ai_template_definition(
    definition: GeneratedTemplateDefinition,
    sample_path: Path,
    extractor: OcrExtractor,
    country_code: str,
    required_fields: tuple[str, ...],
) -> bool:
    text_sources = collect_text_sources(sample_path, extractor, country_code)

    if not validate_template_definition(
        issuer=definition.issuer,
        keywords=definition.keywords,
        fields=definition.fields,
        text_sources=text_sources,
        extractor=extractor,
        required_fields=required_fields,
    ):
        return False

    for keyword in definition.keywords:
        if keyword_looks_invoice_specific(keyword):
            return False

    return True


def choose_best_pattern(patterns: tuple[str, ...], text_sources: list[str]) -> str:
    scored_patterns = [
        (
            sum(1 for text in text_sources if re.search(pattern, text, re.MULTILINE | re.IGNORECASE)),
            -index,
            pattern,
        )
        for index, pattern in enumerate(patterns)
    ]

    return max(scored_patterns)[2]


def build_keyword_candidates(issuer: str, text_sources: list[str]) -> tuple[str, ...]:
    suggestions: list[str] = []
    seen: set[str] = set()
    scoring_lines: list[tuple[int, str]] = []

    if issuer and not is_generic_issuer(issuer) and any(contains_phrase(text, issuer) for text in text_sources):
        issuer_pattern = case_insensitive_pattern(flexible_pattern(issuer))
        suggestions.append(issuer_pattern)
        seen.add(issuer_pattern)

    for text in text_sources:
        for raw_line in text.splitlines():
            line = raw_line.strip()

            if not line:
                continue

            score, pattern = keyword_candidate_from_line(line)

            if not pattern or pattern in seen:
                continue

            scoring_lines.append((score, pattern))

    for _score, pattern in sorted(scoring_lines, key=lambda item: item[0], reverse=True):
        if pattern in seen:
            continue

        suggestions.append(pattern)
        seen.add(pattern)

        if len(suggestions) >= 3:
            break

    if suggestions:
        return tuple(suggestions)

    fallback_issuer = detect_issuer(text_sources)

    if fallback_issuer:
        return (case_insensitive_pattern(flexible_pattern(fallback_issuer)),)

    return (case_insensitive_pattern(r"factu\W*ur"),)


def keyword_candidate_from_line(line: str) -> tuple[int, str | None]:
    lowered_line = line.lower()

    if len(line) > 100:
        return (0, None)

    if looks_like_value_line(line):
        return (0, None)

    if any(marker in lowered_line for marker in ("http://", "https://", "www.")):
        domain = extract_domain_from_text(line)

        if domain:
            return (6, case_insensitive_pattern(re.escape(domain)))

    email_match = re.search(r"[A-Z0-9._%+-]+@([A-Z0-9.-]+\.[A-Z]{2,})", line, re.IGNORECASE)

    if email_match:
        return (6, case_insensitive_pattern(re.escape(email_match.group(1).lower())))

    tax_match = re.search(r"\bNL[0-9A-Z]{9,14}\b", line, re.IGNORECASE)

    if tax_match:
        return (7, case_insensitive_pattern(re.escape(tax_match.group(0).upper())))

    kvk_match = re.search(r"\b[0-9]{8}\b", line)

    if "kvk" in lowered_line and kvk_match:
        return (6, case_insensitive_pattern(r"KvK\s*(?:nr)?[:.]?\s*" + re.escape(kvk_match.group(0))))

    iban_match = re.search(r"\b[A-Z]{2}[0-9A-Z]{13,30}\b", line)

    if iban_match:
        return (5, case_insensitive_pattern(re.escape(iban_match.group(0))))

    if looks_like_company_name(line):
        return (5, case_insensitive_pattern(flexible_pattern(line)))

    return (0, None)


def build_invoice_number_patterns(spec: TemplateSpec) -> tuple[str, ...]:
    patterns = list(
        label_patterns(
            [
                spec.invoice_number_label,
                "Factuurnummer",
                "Factuur nummer",
                "Invoice number",
                "Factuur",
            ],
            INVOICE_NUMBER_VALUE_PATTERN,
        )
    )
    patterns.extend(
        spanning_label_patterns(
            [
                spec.invoice_number_label,
                "Factuurnummer",
                "Factuur nummer",
                "Invoice number",
            ],
            INVOICE_NUMBER_VALUE_PATTERN,
            max_span=120,
        )
    )
    patterns.append(
        case_insensitive_pattern(
            r"(?:Factuurdatum|Invoice\s*date)\s*[:#-]?\s*[0-9]{2}[./-][0-9]{2}[./-][0-9]{4}\s+"
            + INVOICE_NUMBER_VALUE_PATTERN
        )
    )

    return dedupe_patterns(patterns)


def build_date_patterns(spec: TemplateSpec) -> tuple[str, ...]:
    return dedupe_patterns([
        *label_patterns(
            [
                spec.date_label,
                "Factuurdatum",
                "Invoice date",
                "Datum",
            ],
            DATE_VALUE_PATTERN,
        ),
        *spanning_label_patterns(
            [
                spec.date_label,
                "Factuurdatum",
                "Invoice date",
            ],
            DATE_VALUE_PATTERN,
            max_span=160,
        ),
    ])


def build_amount_patterns(spec: TemplateSpec) -> tuple[str, ...]:
    summary_labels = [
        spec.amount_label,
        "Totaal incl. BTW",
        "Totaal incl btw",
        "Te betalen",
        "Totaal bedrag",
        "Totaal",
        "Subtotaal",
    ]

    return dedupe_patterns([
        *label_patterns(summary_labels, r"(?:EUR|€)?\s*" + AMOUNT_VALUE_PATTERN),
        *spanning_label_patterns(summary_labels, r"(?:EUR|€)?\s*" + AMOUNT_VALUE_PATTERN, max_span=120),
    ])


def build_currency_patterns(spec: TemplateSpec) -> tuple[str, ...]:
    preferred_currency = spec.currency_code.strip().upper() or "EUR"
    patterns = list(
        label_patterns(
            [
                spec.currency_label,
                "Valuta",
                spec.amount_label,
                "Totaal incl. BTW",
                "Totaal bedrag",
                "Te betalen",
            ],
            currency_capture_pattern(preferred_currency),
        )
    )
    patterns.extend(
        spanning_label_patterns(
            [
                spec.currency_label,
                "Valuta",
                spec.amount_label,
                "Totaal incl. BTW",
                "Totaal bedrag",
                "Te betalen",
            ],
            currency_capture_pattern(preferred_currency),
            max_span=120,
        )
    )
    patterns.append(case_insensitive_pattern(currency_capture_pattern(preferred_currency)))

    return dedupe_patterns(patterns)


def label_patterns(labels: list[str | None], value_pattern: str) -> tuple[str, ...]:
    patterns: list[str] = []

    for label in labels:
        if not label:
            continue

        patterns.append(case_insensitive_pattern(label_capture_pattern(label, value_pattern)))

    return tuple(patterns)


def spanning_label_patterns(
    labels: list[str | None],
    value_pattern: str,
    max_span: int,
) -> tuple[str, ...]:
    patterns: list[str] = []

    for label in labels:
        if not label:
            continue

        patterns.append(case_insensitive_pattern(spanning_label_capture_pattern(label, value_pattern, max_span)))

    return tuple(patterns)


def dedupe_patterns(patterns: list[str]) -> tuple[str, ...]:
    seen: set[str] = set()
    unique_patterns: list[str] = []

    for pattern in patterns:
        if not pattern or pattern in seen:
            continue

        unique_patterns.append(pattern)
        seen.add(pattern)

    return tuple(unique_patterns)


def resolve_issuer(issuer: str, text_sources: list[str]) -> str:
    stripped_issuer = issuer.strip()

    if stripped_issuer and not is_generic_issuer(stripped_issuer):
        return stripped_issuer

    detected_issuer = detect_issuer(text_sources)

    if detected_issuer:
        return detected_issuer

    return stripped_issuer or "Generated Supplier"


def detect_issuer(text_sources: list[str]) -> str | None:
    best_score = -1
    best_line: str | None = None

    for text in text_sources:
        for index, raw_line in enumerate(text.splitlines()[:25]):
            line = raw_line.strip()

            if not line:
                continue

            score = issuer_line_score(line, index)

            if score > best_score:
                best_score = score
                best_line = line

    return best_line


def issuer_line_score(line: str, index: int) -> int:
    lowered_line = line.lower()

    if len(line) < 4:
        return -10

    if any(marker in lowered_line for marker in ("factuur", "btw", "kvk", "iban", "@", "www.", "http")):
        return -10

    if re.search(r"\d", line) and len(line) > 24:
        return -5

    score = max(0, 15 - index)

    if looks_like_company_name(line):
        score += 10

    if re.search(r"\b(b\.?v\.?|vof|shop|company|holding|services?)\b", lowered_line, re.IGNORECASE):
        score += 10

    if len(line.split()) <= 5:
        score += 2

    return score


def looks_like_company_name(line: str) -> bool:
    lowered_line = line.lower()

    if len(line) < 3 or len(line) > 60:
        return False

    if any(marker in lowered_line for marker in ("adres", "factuur", "btw", "kvk", "iban", "omschrijving")):
        return False

    if "@" in line or "www." in lowered_line:
        return False

    if looks_like_value_line(line):
        return False

    return bool(re.search(r"[A-Za-z]", line))


def looks_like_value_line(line: str) -> bool:
    if re.search(DATE_VALUE_PATTERN, line):
        return True

    if re.search(AMOUNT_VALUE_PATTERN, line):
        return True

    if len(re.findall(r"\d+", line)) >= 3 and not re.search(r"\b(b\.?v\.?|vof|shop|company|holding|services?)\b", line, re.IGNORECASE):
        return True

    if any(marker in line.lower() for marker in ("betaling", "bestelnummer", "leveringsnummer")) and re.search(r"\d", line):
        return True

    return False


def keyword_looks_invoice_specific(keyword: str) -> bool:
    if re.search(DATE_VALUE_PATTERN, keyword):
        return True

    if re.search(AMOUNT_VALUE_PATTERN, keyword):
        return True

    if len(re.findall(r"\d", keyword)) >= 8:
        return True

    if re.fullmatch(r"[A-Z0-9._/-]+", keyword, re.IGNORECASE) and re.search(r"\d", keyword):
        return True

    if any(marker in keyword.lower() for marker in ("factuurnummer", "invoice_number", "bestelnummer", "leveringsnummer")):
        return True

    return False


def is_generic_issuer(value: str) -> bool:
    return normalize_space(value).lower() in GENERIC_ISSUER_VALUES


def contains_phrase(text: str, phrase: str) -> bool:
    return normalize_space(phrase).lower() in normalize_space(text).lower()


def normalize_space(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def extract_domain_from_text(line: str) -> str | None:
    domain_match = re.search(r"(?:https?://)?(?:www\.)?([A-Z0-9.-]+\.[A-Z]{2,})", line, re.IGNORECASE)

    if not domain_match:
        return None

    return domain_match.group(1).lower()


def case_insensitive_pattern(pattern: str) -> str:
    return pattern if pattern.startswith("(?i)") else f"(?i){pattern}"


def suggest_keywords(issuer: str, ocr_text: str) -> tuple[str, ...]:
    return build_keyword_candidates(issuer, [ocr_text])


def label_capture_pattern(label: str, value_pattern: str) -> str:
    return f"(?<!\\w){flexible_pattern(label)}(?!\\w)\\s*[:#-]?\\s*{value_pattern}"


def spanning_label_capture_pattern(label: str, value_pattern: str, max_span: int) -> str:
    return f"(?<!\\w){flexible_pattern(label)}(?!\\w)[\\s\\S]{{0,{max_span}}}?{value_pattern}"


def currency_capture_pattern(currency_code: str) -> str:
    normalized_code = currency_code.strip().upper() or "EUR"

    if normalized_code == "EUR":
        return "((?:EUR|€))"

    return f"(({re.escape(normalized_code)}))"


def flexible_pattern(text: str) -> str:
    stripped_text = text.strip()

    if not stripped_text:
        return ""

    return re.escape(stripped_text).replace(r"\ ", r"\s+").replace(r"\-", r"[-–—]?")


def slugify(text: str) -> str:
    normalized = re.sub(r"[^a-z0-9]+", "_", text.lower())
    return normalized.strip("_") or "supplier"


def yaml_quote(value: str) -> str:
    return "'" + value.replace("'", "''") + "'"


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Generate an invoice2data starter template.")
    parser.add_argument("sample", type=Path, help="Path to a sample invoice file.")
    parser.add_argument("--issuer", required=True, help="Supplier or issuer name.")
    parser.add_argument("--invoice-number-label", required=True, help="Label used for invoice number.")
    parser.add_argument("--date-label", required=True, help="Label used for invoice date.")
    parser.add_argument("--amount-label", required=True, help="Label used for invoice total amount.")
    parser.add_argument("--country-code", default="NL", help="Country code used for OCR language selection.")
    parser.add_argument("--currency-code", default="EUR", help="Currency code to capture.")
    parser.add_argument("--currency-label", help="Optional dedicated label used for currency.")
    parser.add_argument(
        "--keyword",
        action="append",
        dest="keywords",
        default=[],
        help="Optional extra template keyword. Repeat the flag for multiple keywords.",
    )
    parser.add_argument(
        "--template-dir",
        type=Path,
        default=Path("/app/templates"),
        help="Base template directory.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        help="Explicit output path for the generated template.",
    )

    return parser.parse_args()


def main() -> int:
    arguments = parse_arguments()
    extractor = OcrExtractor(arguments.template_dir)
    result = generate_starter_template_from_sample(
        sample_path=arguments.sample,
        template_dir=arguments.template_dir,
        extractor=extractor,
        spec=TemplateSpec(
            issuer=arguments.issuer,
            invoice_number_label=arguments.invoice_number_label,
            date_label=arguments.date_label,
            amount_label=arguments.amount_label,
            country_code=arguments.country_code,
            currency_code=arguments.currency_code,
            currency_label=arguments.currency_label,
            keywords=tuple(arguments.keywords),
        ),
        output_path=arguments.output,
    )

    print(f"Template written to: {result.output_path}")

    if result.missing_labels:
        print(
            "Labels not found in OCR/PDF text: "
            + ", ".join(result.missing_labels)
        )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
