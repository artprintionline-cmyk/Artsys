@echo off
setlocal

echo [ERP] Instalador (Windows)

where php >nul 2>nul
if errorlevel 1 (
  echo ERRO: PHP nao encontrado no PATH.
  exit /b 1
)

where composer >nul 2>nul
if errorlevel 1 (
  echo ERRO: Composer nao encontrado no PATH.
  exit /b 1
)

where node >nul 2>nul
if errorlevel 1 (
  echo ERRO: Node nao encontrado no PATH.
  exit /b 1
)

where npm >nul 2>nul
if errorlevel 1 (
  echo ERRO: npm nao encontrado no PATH.
  exit /b 1
)

echo [1/4] Backend: composer install
pushd "erp-api"
if not exist .env (
  if exist .env.example copy .env.example .env >nul
)
composer install --no-interaction

echo [2/4] Backend: instalar (sqlite + migrate + seed)
php artisan erp:install --sqlite --force
popd

echo [3/4] Frontend: npm install
pushd "erp-frontend"
npm install
echo [4/4] Frontend: build (vai para erp-api\public)
npm run build
popd

echo OK. Para iniciar: start-backend.bat
exit /b 0
