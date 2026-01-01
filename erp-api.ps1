<#
create-erp-api.ps1
Script para criar projeto Laravel `erp-api` em A:\Sistema Saas usando Composer (Windows).

Uso (PowerShell):
  Abra PowerShell (não precisa ser Administrador) e execute este script na pasta A:\Sistema Saas:
    .\create-erp-api.ps1

Requisitos:
 - PHP instalado e acessível via `php` (XAMPP PHP pode ser adicionado ao PATH)
 - Composer instalado e acessível via `composer`

O script:
 - executa `composer create-project laravel/laravel erp-api`
 - copia `.env.example` para `.env`
 - ajusta `.env` para PostgreSQL (apenas placeholders)
 - executa `php artisan key:generate`
 - remove a pasta `resources/views` (não cria views Blade)

Se algo falhar, execute os comandos manualmente conforme instruções no README.
#>

Write-Host "Iniciando criação do projeto Laravel 'erp-api' em A:\Sistema Saas..."

# Verifica composer
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Error "Composer não encontrado. Instale o Composer e garanta que 'composer' esteja no PATH."
    exit 1
}

# Verifica php
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Error "PHP não encontrado. Garanta que o PHP (XAMPP) esteja no PATH ou instale o PHP."
    exit 1
}

$projectDir = Join-Path (Get-Location) 'erp-api'
if (Test-Path $projectDir) {
    Write-Host "Diretório $projectDir já existe. Abortando para evitar sobrescrita." -ForegroundColor Yellow
    exit 1
}

Write-Host "Executando: composer create-project laravel/laravel erp-api --prefer-dist"
composer create-project laravel/laravel "erp-api" --prefer-dist

if (-not (Test-Path $projectDir)) {
    Write-Error "Falha ao criar o projeto. Verifique a saída acima para erros de Composer."
    exit 1
}

Set-Location $projectDir

if (Test-Path '.env.example') {
    Copy-Item .env.example .env -Force
    Write-Host "Copiado .env.example -> .env"
    (Get-Content .env) -replace 'DB_CONNECTION=.*','DB_CONNECTION=pgsql' | Set-Content .env
    (Get-Content .env) -replace 'DB_HOST=.*','DB_HOST=127.0.0.1' | Set-Content .env
    (Get-Content .env) -replace 'DB_PORT=.*','DB_PORT=5432' | Set-Content .env
    (Get-Content .env) -replace 'DB_DATABASE=.*','DB_DATABASE=erp_saas' | Set-Content .env
    (Get-Content .env) -replace 'DB_USERNAME=.*','DB_USERNAME=erp_user' | Set-Content .env
    (Get-Content .env) -replace 'DB_PASSWORD=.*','DB_PASSWORD=secret' | Set-Content .env
    Write-Host ".env atualizado com placeholders para PostgreSQL (edite conforme seu ambiente)."
}

Write-Host "Gerando APP_KEY (php artisan key:generate)"
php artisan key:generate

# Remover views Blade para manter API-only
if (Test-Path 'resources\views') {
    Remove-Item -Recurse -Force 'resources\views'
    Write-Host "Diretório resources/views removido (API-only)."
}

Write-Host "Projeto criado com sucesso em: $projectDir"
Write-Host "Próximos comandos a executar dentro de $projectDir:"
Write-Host "  composer install";
Write-Host "  php artisan migrate";
Write-Host "  php artisan serve";

Write-Host "Observações: ajuste .env com credenciais reais do PostgreSQL antes de rodar migrate."
