FROM php:8.0-apache

# Installa dipendenze e estensioni PHP
RUN apt-get update && apt-get install -y \
    nano \
    git \
    unzip \
    libzip-dev \
    default-mysql-client \
    && docker-php-ext-install \
    pdo_mysql \
    zip

# Abilita mod_rewrite per Apache
RUN a2enmod rewrite

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configurazione PHP per sviluppo
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && echo "display_errors = On" >> "$PHP_INI_DIR/php.ini" \
    && echo "error_reporting = E_ALL" >> "$PHP_INI_DIR/php.ini" \
    && echo "log_errors = On" >> "$PHP_INI_DIR/php.ini"

# Imposta directory di lavoro
WORKDIR /var/www/html

# Copia lo script di inizializzazione
COPY ./scripts/init-project.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/init-project.sh

# Comando di avvio
CMD ["/usr/local/bin/init-project.sh"]