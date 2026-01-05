@echo off
setlocal

set "HOST=127.0.0.1"
set "PORT=8000"

if not "%ERP_BACKEND_HOST%"=="" set "HOST=%ERP_BACKEND_HOST%"
if not "%ERP_BACKEND_PORT%"=="" set "PORT=%ERP_BACKEND_PORT%"

set "BACKEND_DIR=%~dp0erp-api"
if not "%ERP_BACKEND_DIR%"=="" set "BACKEND_DIR=%ERP_BACKEND_DIR%"

set "PHP_EXE=%ERP_PHP%"
if "%PHP_EXE%"=="" (
	echo [ERP] ERRO: ERP_PHP nao definido. Este aplicativo exige PHP embutido.
	exit /b 1
)

if not exist "%PHP_EXE%" (
	echo [ERP] ERRO: php.exe nao encontrado em: %PHP_EXE%
	exit /b 1
)

echo [ERP] Iniciando backend em http://%HOST%:%PORT%
pushd "%BACKEND_DIR%"
"%PHP_EXE%" artisan serve --host=%HOST% --port=%PORT%
popd
