FROM php:8.2-apache

# Instalación de dependencias de sistema (Poppler y Tesseract para PDF y OCR)
RUN apt-get update && apt-get install -y \
    poppler-utils \
    tesseract-ocr \
    tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP necesarias para MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Configuración de Apache
RUN sed -i 's/Options Indexes FollowSymLinks/Options FollowSymLinks/' /etc/apache2/apache2.conf
RUN a2enmod rewrite

# Copiar el código del proyecto
COPY . /var/www/html/

# Asegurar permisos para el servidor web y scripts
RUN chown -R www-data:www-data /var/www/html/ && \
    chmod +x /var/www/html/entrypoint.sh

# Exponer puertos: 80 (Apache) y 5000 (MCP Server)
EXPOSE 80 5000

# Usar el script de entrada personalizado para arrancar ambos servicios
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
