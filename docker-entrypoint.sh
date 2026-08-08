#!/bin/sh
set -e

# Retour en arriere : coder le port en dur (8000) a casse le healthcheck
# interne de Railway (diagnostic Railway confirme : "Apache to bind to port
# 8000 while Railway's healthcheck probed a different port"). Railway
# injecte donc bien une vraie valeur de $PORT et l'utilise en interne — on
# doit la respecter, pas la court-circuiter. Le vrai probleme est ailleurs :
# le "Target Port" fixe (8000) configure cote Railway (Settings > Networking
# > domaine public) ne correspond visiblement pas a cette valeur de $PORT,
# ce qui casse le routage du trafic PUBLIC (le healthcheck interne, lui,
# passe). On logue la valeur brute recue pour aller corriger le Target Port
# dans les reglages Railway en consequence.
echo "docker-entrypoint: valeur brute de \$PORT recue de Railway = '${PORT:-<non definie>}'"
PORT="${PORT:-8000}"

sed -ri "s/Listen [0-9]+/Listen 0.0.0.0:${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/*.conf

# Les vraies variables d'env Railway (DB_HOST, APP_KEY, etc.) ne sont
# disponibles qu'au runtime : on regénère le cache de config maintenant
# plutôt qu'au build, sinon Laravel garderait des valeurs vides/nulles.
php artisan config:clear
php artisan config:cache

# AJOUT (2026-08-08) : plusieurs migrations (colonnes DigitWace sur
# transactions/cashes, puis le reseau de transfert interne) sont restees
# EN ATTENTE en production faute d'etre lancees manuellement apres deploiement
# -> chaque creation de transaction echouait en 500 (Column not found), car
# le code deploye referencait des colonnes absentes de la base reelle. On
# lance donc les migrations a chaque demarrage du conteneur : --force est
# necessaire car APP_ENV=production sinon Laravel demande une confirmation
# interactive impossible ici. Sans danger a relancer meme quand il n'y a
# rien de nouveau (Laravel ne rejoue jamais une migration deja appliquee,
# via la table `migrations`).
php artisan migrate --force

# Railway réactive parfois mpm_event/mpm_worker en plus de mpm_prefork déjà
# actif dans l'image php:8.1-apache (mod_php exige prefork). Deux MPM actifs
# en même temps font planter Apache au démarrage avec :
#   AH00534: apache2: Configuration error: More than one MPM loaded.
# On force donc explicitement un seul MPM (prefork) juste avant de lancer Apache.
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

exec apache2-foreground
