#!/usr/bin/env bash
set -euo pipefail

echo "[ERP] Iniciando backend em http://127.0.0.1:8000"
cd "$(dirname "$0")/erp-api"
php artisan serve --host=127.0.0.1 --port=8000
