#!/usr/bin/env bash
# =============================================================================
# ÉTAPE 0 — WRAPPER DE LANCEMENT (Windows / Git Bash)
# =============================================================================
# PROBLÈME RÉSOLU : Git Bash (MSYS2) convertit les variables d'environnement
# ressemblant à des chemins. `SESSION_PATH=/` devient
# `C:/Program Files (x86)/Git/` AVANT que PHP/Laravel ne le lise — et dotenv
# n'écrase jamais une vraie variable d'environnement → cookies invalides,
# erreurs 500 sur chaque route web.
#
# SOLUTION : unset de SESSION_PATH dans l'environnement du shell de lancement.
# Laravel retombe alors sur la valeur propre de .env (`SESSION_PATH=/`).
#
# Usage :
#   ./serve.sh          → serveur web seul
#   ./serve.sh --dev    → serveur + worker de files d'attente (notifications)
# =============================================================================

# 1) Neutralise la variable corrompue par MSYS (etActive d'autres si besoin)
unset SESSION_PATH
export MSYS_NO_PATHCONV=1      # empêche la conversion des arguments type chemin
export MSYS2_ARG_CONV_EXCL="*"

# 2) S'assure que .env existe (copie depuis l'exemple sinon)
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
    php artisan migrate --force
    php artisan db:seed --force
fi

# 3) Lance le serveur (avec ou sans worker de files d'attente)
if [ "$1" = "--dev" ]; then
    echo "🚀 Démarrage : serveur web + worker de notifications en arrière-plan…"
    php artisan queue:work --tries=1 --timeout=0 &
    exec php artisan serve
else
    exec php artisan serve
fi
