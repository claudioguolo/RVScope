FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        postgresql-client \
    && docker-php-ext-install pdo_pgsql pgsql intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/apache.conf /etc/apache2/conf-available/ci4.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-custom.ini
RUN a2enconf ci4


#habilitar modulos e site SSL
RUN a2enmod rewrite ssl
RUN grep -q '^Listen 8443$' /etc/apache2/ports.conf || echo 'Listen 8443' >> /etc/apache2/ports.conf
RUN a2dissite default-ssl || true

#habilityar conf custom's SSL
COPY docker/php/apache-ssl.conf /etc/apache2/conf-available/ci4-ssl.conf
RUN a2enconf ci4-ssl

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
