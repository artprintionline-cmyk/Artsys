#!/usr/bin/env bash
set -euo pipefail

echo "[ERP] Instalador (Linux/macOS)"

command -v php >/dev/null || { echo "ERRO: php não encontrado"; exit 1; }
command -v composer >/dev/null || { echo "ERRO: composer não encontrado"; exit 1; }
command -v node >/dev/null || { echo "ERRO: node não encontrado"; exit 1; }
command -v npm >/dev/null || { echo "ERRO: npm não encontrado"; exit 1; }

echo "[1/4] Backend: composer install"
pushd "erp-api" >/dev/null
[ -f .env ] || { [ -f .env.example ] && cp .env.example .env; }
composer install --no-interaction

echo "[2/4] Backend: instalar (sqlite + migrate + seed)"
php artisan erp:install --sqlite --force
popd >/dev/null

echo "[3/4] Frontend: npm install"
pushd "erp-frontend" >/dev/null
npm install

echo "[4/4] Frontend: build (vai para erp-api/public)"
npm run build
popd >/dev/null

echo "OK. Para iniciar: ./start-backend.sh"
