from __future__ import annotations

import os
from typing import Iterable


SUPPORTED_REQUIRED_FIELDS = (
    "invoice_number",
    "date",
    "amount",
    "currency_code",
)

DEFAULT_REQUIRED_FIELDS = (
    "invoice_number",
    "date",
    "amount",
    "currency_code",
)

PUBLIC_FIELD_NAMES = {
    "invoice_number": "invoice_number",
    "date": "invoice_date",
    "amount": "total_amount",
    "currency_code": "currency_code",
}


def normalize_required_fields(value: str | Iterable[str] | None) -> tuple[str, ...]:
    raw_values: list[str] = []

    if value is None:
        raw_values = list(DEFAULT_REQUIRED_FIELDS)
    elif isinstance(value, str):
        raw_values = [item.strip() for item in value.split(",")]
    else:
        raw_values = [str(item).strip() for item in value]

    normalized_values: list[str] = []
    seen: set[str] = set()

    for raw_value in raw_values:
        if not raw_value:
            continue

        if raw_value not in SUPPORTED_REQUIRED_FIELDS:
            raise ValueError(f"Unsupported OCR required field: {raw_value}")

        if raw_value in seen:
            continue

        normalized_values.append(raw_value)
        seen.add(raw_value)

    if not normalized_values:
        raise ValueError("At least one OCR required field must be configured.")

    return tuple(normalized_values)


def default_required_fields_from_env() -> tuple[str, ...]:
    return normalize_required_fields(os.getenv("OCR_REQUIRED_FIELDS"))


def public_field_name(field_name: str) -> str:
    return PUBLIC_FIELD_NAMES[field_name]
