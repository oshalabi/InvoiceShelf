FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    OCR_DEBUG=false \
    OCR_TEMPLATE_DIR=/app/data/templates \
    OCR_TEMPLATE_DIRS=/app/templates-default,/app/data/templates \
    OCR_OPENROUTER_FALLBACK=false \
    OCR_AUTO_GENERATE_TEMPLATES=false

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-eng \
        tesseract-ocr-nld \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt /app/requirements.txt

RUN pip install --no-cache-dir -r /app/requirements.txt

COPY ocr_service /app/ocr_service
COPY templates /app/templates-default

RUN mkdir -p /app/data/templates

EXPOSE 8080

CMD ["uvicorn", "ocr_service.main:app", "--host", "0.0.0.0", "--port", "8080"]
