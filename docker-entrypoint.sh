#!/bin/sh
set -e

# Railway assigne un port dynamique via $PORT et route le trafic public
# vers celui-ci. Apache écoute par défaut sur le port 80 : on remplace ce
# port dans la conf Apache au démarrage du conteneur (les variables
# d'environnement Railway ne sont dispo qu'à ce moment-là, pas au build).
#
# Le "Target Port" configuré côté Railway (Settings > Networking) pour le
# domaine public est fixé à 8000 (voir capture du 20/07). Si $PORT n'est
# pas injecté par Railway (cas courant quand le port public est fixé
# manuellement plutôt qu'en mode auto), on retombe donc sur 8000 et non 80,
# sinon Apache écoute sur le mauvais port et le proxy public renvoie
# "connection refused" (502) alors que le healthcheck interne, lui, passe.
PORT="${PORT:-8000}"

sed -ri "s/Listen [0-9]+/Listen 0.0.0.0:${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/*.conf

# Les vraies variables d'env Railway (DB_HOST, APP_KEY, etc.) ne sont
# disponibles qu'au runtime : on regénère le cache de config maintenant
# plutôt qu'au build, sinon Laravel garderait des valeurs vides/nulles.
php artisan config:clear
php artisan config:cache

# Railway réactive parfois mpm_event/mpm_worker en plus de mpm_prefork déjà
# actif dans l'image php:8.1-apache (mod_php exige prefork). Deux MPM actifs
# en même temps font planter Apache au démarrage avec :
#   AH00534: apache2: Configuration error: More than one MPM loaded.
# On force donc explicitement un seul MPM (prefork) juste avant de lancer Apache.
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

exec apache2-foreground
