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

# ServerName explicite : sans ça Apache tente de résoudre le FQDN du
# conteneur à chaque démarrage (warning AH00558), ce qui peut ralentir
# le boot sur le réseau privé de Railway.
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Le conteneur Railway a peu de RAM. mod_php charge tout l'interpréteur PHP
# dans CHAQUE processus Apache : avec les réglages par défaut de
# mpm_prefork (StartServers 5, MaxRequestWorkers 150), Apache peut démarrer
# trop de workers et se faire tuer par le kernel (OOM kill). Le process
# disparaît alors sans laisser de log, et les requêtes suivantes échouent
# instantanément (502 / "Application failed to respond") jusqu'au restart.
# On réduit donc le nombre de workers pour un footprint mémoire adapté.
RUN { \
        echo '<IfModule mpm_prefork_module>'; \
        echo '    StartServers          2'; \
        echo '    MinSpareServers       1'; \
        echo '    MaxSpareServers       3'; \
        echo '    MaxRequestWorkers     20'; \
        echo '    MaxConnectionsPerChild 500'; \
        echo '</IfModule>'; \
    } > /etc/apache2/mods-available/mpm_prefork.conf

# Installe Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Définit le répertoire de travail
WORKDIR /var/www/html

# Copie les fichiers du projet dans le conteneur
COPY . /var/www/html

# Installe les dépendances PHP via Composer
# --no-dev : les paquets de dev (phpunit, debugbar, etc.) alourdissent le
# vendor/ et l'autoload pour rien en prod, et augmentent le risque d'OOM
# une fois chargés par mod_php dans chaque worker Apache.
# --optimize-autoloader : autoload en tableau statique (plus rapide, moins
# de stat() disque à chaque requête) au lieu du classmap dynamique.
RUN composer install --ignore-platform-reqs --no-dev --optimize-autoloader
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
