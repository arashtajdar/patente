FROM php:8.2-apache

# Install needed packages and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql curl

WORKDIR /var/www/html
COPY . /var/www/html

# Expose Apache on port 8080 optionally (Railway maps automatically)
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Suppress ServerName warning
RUN bash -lc 'echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf' \
    && a2enconf servername

# Healthcheck
HEALTHCHECK CMD curl --fail http://localhost/ || exit 1


