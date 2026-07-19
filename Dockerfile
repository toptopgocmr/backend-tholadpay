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
# NE PAS faire "config:cache" ici : à ce stade les variables d'environnement
# Railway (DB_HOST, APP_KEY, etc.) n'existent pas encore, donc le cache
# figerait des valeurs vides/nulles et les vraies variables d'env seraient
# ignorées au démarrage du conteneur. Le cache est régénéré au runtime
# (voir CMD plus bas) une fois que Railway a injecté les vraies variables.
# RUN    php artisan route:cache
RUN    chmod 777 -R /var/www/html/storage/
RUN    chown -R www-data:www-data /var/www/

# Port par défaut pour un run local (docker run) hors Railway.
EXPOSE 8000

# Au démarrage du conteneur (les vraies variables d'env Railway sont dispo ici) :
# on nettoie un éventuel cache figé, on regénère la config avec les vraies
# valeurs, puis on lance le serveur.
# IMPORTANT : Railway assigne dynamiquement un port via la variable $PORT et
# route le trafic public vers CE port precis. Le serveur doit donc écouter
# dessus (et non sur un 8000 en dur), sinon le healthcheck et toutes les
# requêtes échouent avec "service unavailable" même si le conteneur démarre
# bien. ${PORT:-8000} retombe sur 8000 si $PORT n'existe pas (run local).
CMD php artisan config:clear && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

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