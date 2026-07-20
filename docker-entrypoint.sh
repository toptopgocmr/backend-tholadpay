#!/bin/sh
set -e

# Le fallback "${PORT:-8000}" n'a pas suffi : le 502 "connection refused"
# persistait, ce qui veut dire que Railway injecte bien une valeur de $PORT
# au conteneur, mais probablement PAS 8000 — alors que le "Target Port" fixé
# côté Railway (Settings > Networking > domaine public) est lui bien 8000.
# Comme Railway route TOUJOURS le trafic public vers ce Target Port fixe
# (peu importe la valeur réelle de $PORT), on ignore désormais $PORT et on
# force Apache à écouter en dur sur 8000 pour être certain que ça matche.
# Si vous changez un jour le Target Port dans Railway, changez cette valeur
# en conséquence.
PORT="8000"
echo "docker-entrypoint: Apache va ecouter sur le port ${PORT} (fixe, doit matcher le Target Port Railway)"

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
