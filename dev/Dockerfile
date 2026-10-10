# ProofOfPen local dev — mirrors Hostinger shared hosting:
# Apache 2 + mod_rewrite + PHP 8 + PDO MySQL. Repo root = web root.
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite
# Hostinger serves .htaccess per-dir; the stock image already allows it, but
# be explicit so the local container behaves like the shared host.
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf || true
