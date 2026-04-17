FROM php:8.2-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

CMD ["php", "-v"]

