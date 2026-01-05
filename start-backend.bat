@echo off
setlocal

set "HOST=127.0.0.1"
set "PORT=8000"

if not "%ERP_BACKEND_HOST%"=="" set "HOST=%ERP_BACKEND_HOST%"
if not "%ERP_BACKEND_PORT%"=="" set "PORT=%ERP_BACKEND_PORT%"

set "BACKEND_DIR=%~dp0erp-api"
if not "%ERP_BACKEND_DIR%"=="" set "BACKEND_DIR=%ERP_BACKEND_DIR%"

set "PHP_EXE=php"
if not "%ERP_PHP%"=="" set "PHP_EXE=%ERP_PHP%"

echo [ERP] Iniciando backend em http://%HOST%:%PORT%
pushd "%BACKEND_DIR%"
"%PHP_EXE%" artisan serve --host=%HOST% --port=%PORT%
popd
