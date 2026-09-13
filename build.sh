#!/usr/bin/env bash
# =============================================================================
# COMPILATION CSS — CLI standalone Tailwind (AUCUN Node.js requis)
# =============================================================================
# Le binaire build/tailwindcss.exe embarque tout le moteur Tailwind v4.
#
# Usage :
#   ./build.sh          → compile public/assets/css/app.css (minifié)
#   ./build.sh --watch  → recompile à chaque modification (développement)
#
# Source de vérité : build/tailwind.input.css
#   (@source : vues Blade, JS applicatif, Enums PHP — les badges de statut
#    vivent dans OrderStatus::badgeClass() et sont donc scannés !)
# =============================================================================

set -e

CLI="build/tailwindcss.exe"        # Windows / Git Bash
[ -f "$CLI" ] || CLI="build/tailwindcss"   # variante Linux/macOS

if [ ! -f "$CLI" ]; then
    echo "❌ CLI Tailwind introuvable dans build/."
    echo "   Téléchargez-le : https://github.com/tailwindlabs/tailwindcss/releases"
    echo "   (tailwindcss-windows-x64.exe ou tailwindcss-linux-x64)"
    exit 1
fi

if [ "$1" = "--watch" ]; then
    echo "👀 Mode watch : recompilation à chaque modification… (Ctrl+C pour quitter)"
    exec "$CLI" -i build/tailwind.input.css -o public/assets/css/app.css --watch
else
    echo "🧵 Compilation Tailwind (standalone, sans Node)…"
    "$CLI" -i build/tailwind.input.css -o public/assets/css/app.css --minify
    echo "✅ public/assets/css/app.css généré ($(du -h public/assets/css/app.css | cut -f1))."
fi
