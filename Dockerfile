FROM php:8.2-apache

# Install ekstensi PHP yang diperlukan
RUN docker-php-ext-install pdo pdo_mysql

# Fix: nonaktifkan mpm_event/mpm_worker, aktifkan mpm_prefork + rewrite
RUN a2dismod mpm_event mpm_worker; \
    a2enmod mpm_prefork rewrite

# Set DocumentRoot ke public_html/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public_html

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Izinkan .htaccess override
RUN sed -i 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

# Salin semua file proyek ke container
COPY . /var/www/html/

# Set permission file
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80
