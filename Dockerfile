FROM php:8.2-apache

# Install ekstensi PHP yang diperlukan
RUN docker-php-ext-install pdo pdo_mysql

# Fix MPM conflict: nonaktifkan mpm_event, aktifkan mpm_prefork
RUN a2dismod mpm_event mpm_worker; \
    a2enmod mpm_prefork rewrite

# Salin semua file proyek ke container
COPY . /var/www/html/

# Tulis Apache VirtualHost config yang mengarah ke public_html/
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public_html\n\
    <Directory /var/www/html/public_html>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes +FollowSymLinks\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# Set permission file
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80
