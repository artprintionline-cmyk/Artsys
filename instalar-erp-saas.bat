@echo off
setlocal

REM Instalador local do ERP SaaS (Windows)
REM Executa o PowerShell script na mesma pasta.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar-erp-saas.ps1"

echo.
echo Finalizado. Se houver erros, abra o PowerShell e rode novamente para ver detalhes.
pause
