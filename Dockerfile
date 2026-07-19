FROM php:8.1

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    default-mysql-client 

RUN docker-php-source extract && \
    apt-get install -y \
    libpq-dev \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd && \
    docker-php-ext-install \
    pdo_mysql && \
    docker-php-source delete


# Installe Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Définit le répertoire de travail
WORKDIR /var/www/html

# Copie les fichiers du projet dans le conteneur
COPY . /var/www/html

# Installe les dépendances PHP via Composer
RUN composer install --ignore-platform-reqs
RUN php artisan config:cache
# RUN    php artisan route:cache 
RUN    chmod 777 -R /var/www/html/storage/ 
RUN    chown -R www-data:www-data /var/www/

# Expose le port 8000 pour le serveur artisan
EXPOSE 8000

# Exécute la commande artisan serve lors du démarrage du conteneur
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# FROM php:8.0-apache-buster as production

# ENV APP_ENV=production
# ENV APP_DEBUG=false

# # RUN docker-php-ext-configure opcache --enable-opcache && \
# #     docker-php-ext-install pdo pdo_mysql
# # COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# COPY --from=build /app /var/www/html
# # COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
# COPY .env.prod /var/www/html/.env

# RUN php artisan config:cache && \
#     php artisan route:cache && \
#     chmod 777 -R /var/www/html/storage/ && \
#     chown -R www-data:www-data /var/www/ && \
#     a2enmod rewrite