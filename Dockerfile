FROM php:8.2-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY . /app

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN if [ ! -f /app/vendor/autoload.php ]; then \
        composer install --no-interaction --prefer-dist --optimize-autoloader; \
    fi

RUN mkdir -p \
    /app/meritos/scraping/output/cache \
    /app/meritos/scraping/output/jobs \
    /app/meritos/scraping/output/logs \
    /app/meritos/scraping/output/images \
    /app/meritos/scraping/output/json \
    /app/meritos/scraping/output/text \
    /app/meritos/scraping/output/smoke \
    /app/meritos/scraping/pdfs \
    && chmod -R 0777 /app/meritos/scraping/output /app/meritos/scraping/pdfs

CMD ["php", "-v"]

