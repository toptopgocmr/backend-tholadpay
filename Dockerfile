FROM php:8.1-apache

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

# Active mod_rewrite (nécessaire pour le routing Laravel via public/.htaccess)
RUN a2enmod rewrite

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
# (voir docker-entrypoint.sh) une fois que Railway a injecté les vraies
# variables.
# RUN    php artisan route:cache
RUN    chmod 777 -R /var/www/html/storage/
RUN    chown -R www-data:www-data /var/www/

# Laravel sert depuis public/, pas depuis la racine du projet.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}/!g" /etc/apache2/apache2.conf \
    && printf '<Directory %s>\n\tAllowOverride All\n</Directory>\n' "${APACHE_DOCUMENT_ROOT}" > /etc/apache2/conf-available/laravel-docroot.conf \
    && a2enconf laravel-docroot

RUN chmod +x /var/www/html/docker-entrypoint.sh

# Port par défaut pour un run local (docker run) hors Railway.
EXPOSE 80

# IMPORTANT : Railway assigne dynamiquement un port via la variable $PORT et
# route le trafic public vers CE port precis. C'est docker-entrypoint.sh qui
# reconfigure Apache pour écouter dessus au démarrage (les variables d'env
# Railway ne sont dispo qu'à ce moment-là, pas au build), regénère le cache
# de config Laravel, puis lance Apache au premier plan. Apache (mod_php)
# gère nativement plusieurs requêtes en parallèle, contrairement à
# "php artisan serve" (mono-thread) utilisé précédemment, qui timeoutait
# dès que le navigateur chargeait plusieurs assets (CSS/JS/images) en même
# temps.
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
