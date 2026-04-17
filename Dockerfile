FROM php:8.2-apache

# NUEVO: Instalamos las extensiones para que PHP pueda hablar con la base de datos
RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN sed -i 's/Options Indexes FollowSymLinks/Options FollowSymLinks/' /etc/apache2/apache2.conf
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/