<#
setup-windows-laravel.ps1
Script para preparar ambiente Laravel + PostgreSQL + Sanctum no Windows (Chocolatey)
USO (PowerShell como Administrador):
.\setup-windows-laravel.ps1 -ProjectDir .\backend -DbName erp_saas -DbUser erp_user -DbPassword secret

Aviso: este script instala pacotes via Chocolatey e fará alterações no sistema. Execute apenas se concordar.
#>
param(
    [string]$ProjectDir = ".\backend",
    [string]$DbName = "erp_saas",
    [string]$DbUser = "erp_user",
    [string]$DbPassword = "secret"
)

function Require-Admin {
    $current = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($current)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        Write-Error "Este script precisa ser executado como Administrador. Pare e reabra o PowerShell como administrador."
        exit 1
    }
}

Require-Admin

# 1) Instalar Chocolatey (se necessário)
if (-not (Get-Command choco -ErrorAction SilentlyContinue)) {
    Write-Host "Instalando Chocolatey..."
    Set-ExecutionPolicy Bypass -Scope Process -Force
    iwr https://chocolatey.org/install.ps1 -UseBasicParsing | iex
} else {
    Write-Host "Chocolatey já instalado"
}

# 2) Instalar PHP e Composer e PostgreSQL
Write-Host "Instalando PHP, Composer e PostgreSQL (pode demorar)..."
choco install -y php composer postgresql --no-progress

# Reiniciar o ambiente do PowerShell não é feito aqui; assume que comandos seguirão funcionando

# 3) Verificar PHP/Composer
Write-Host "Verificando php e composer..."
php --version
composer --version

# 4) Criar base de dados PostgreSQL
Write-Host "Criando banco de dados PostgreSQL (se já existir, ignora error)..."
# O serviço PostgreSQL instalado pelo choco disponibiliza comandos; usar createdb/psql via caminho do instalador.
# Tentar usar psql no PATH
$psql = Get-Command psql -ErrorAction SilentlyContinue
if ($psql) {
    & psql -U postgres -c "CREATE DATABASE $DbName;" 2>$null
    & psql -U postgres -c "CREATE USER $DbUser WITH PASSWORD '$DbPassword';" 2>$null
    & psql -U postgres -c "GRANT ALL PRIVILEGES ON DATABASE $DbName TO $DbUser;" 2>$null
} else {
    Write-Warning "Não encontrei psql no PATH. Crie o DB manualmente com pgAdmin ou adicione psql ao PATH."
}

# 5) Criar projeto Laravel
if (-not (Test-Path -Path $ProjectDir)) {
    Write-Host "Criando projeto Laravel em $ProjectDir ..."
    composer create-project laravel/laravel "$ProjectDir" --prefer-dist
} else {
    Write-Host "Diretório $ProjectDir já existe — assumindo que é o projeto Laravel."
}

Push-Location $ProjectDir

# 6) Copiar esqueleto do workspace (app, database, routes, .env.example, etc.)
$workspaceRoot = "$(Split-Path -Parent $PSScriptRoot)"
Write-Host "Workspace root: $workspaceRoot"
# Copiar apenas se existir arquivos no workspace (a:/Sistema Saas)
$source = Join-Path $workspaceRoot '..'  # guard fallback
# Instead assume files are relative: the script resides in workspace root a:\Sistema Saas
$source = "${PSScriptRoot}"
Write-Host "Copiando arquivos do esqueleto de $source para $PWD ..."
# Lista de paths para copiar (somente se existirem)
$items = @('app','database','routes','.env.example','.github')
foreach ($item in $items) {
    $src = Join-Path $source $item
    if (Test-Path $src) {
        Write-Host "Copiando $item..."
        robocopy $src (Join-Path $PWD $item) /MIR | Out-Null
    }
}

# 7) Instalar dependências do Laravel e Sanctum
Write-Host "Instalando dependências do composer..."
composer install
Write-Host "Instalando Laravel Sanctum..."
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider" --tag="migrations" 2>$null
php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider" 2>$null

# 8) Atualizar .env
if (Test-Path ".env.example") {
    Copy-Item .env.example .env -Force
}

# Substituir DB_* no .env
(Get-Content .env) -replace 'DB_CONNECTION=.*','DB_CONNECTION=pgsql' |
    Set-Content .env
(Get-Content .env) -replace 'DB_HOST=.*','DB_HOST=127.0.0.1' |
    Set-Content .env
(Get-Content .env) -replace 'DB_PORT=.*','DB_PORT=5432' |
    Set-Content .env
(Get-Content .env) -replace 'DB_DATABASE=.*',"DB_DATABASE=$DbName" |
    Set-Content .env
(Get-Content .env) -replace 'DB_USERNAME=.*',"DB_USERNAME=$DbUser" |
    Set-Content .env
(Get-Content .env) -replace 'DB_PASSWORD=.*',"DB_PASSWORD=$DbPassword" |
    Set-Content .env

php artisan key:generate

# 9) Criar seeders (Empresa + User) se não existirem
$seedersDir = Join-Path $PWD 'database\seeders'
if (-not (Test-Path $seedersDir)) { New-Item -ItemType Directory -Path $seedersDir -Force | Out-Null }

$empresaSeeder = @"
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empresa;

class EmpresaSeeder extends Seeder
{
    public function run()
    {
        Empresa::create(['nome' => 'Empresa X', 'cnpj' => '', 'status' => true]);
    }
}
"@

$userSeeder = @"
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Empresa;

class UserSeeder extends Seeder
{
    public function run()
    {
        $empresa = Empresa::first();
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
            'empresa_id' => $empresa->id,
            'status' => true,
        ]);
    }
}
"@

$databaseSeeder = @"
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            EmpresaSeeder::class,
            UserSeeder::class,
        ]);
    }
}
"@

# Salvar seeders
Set-Content -Path (Join-Path $seedersDir 'EmpresaSeeder.php') -Value $empresaSeeder -Force
Set-Content -Path (Join-Path $seedersDir 'UserSeeder.php') -Value $userSeeder -Force
Set-Content -Path (Join-Path $seedersDir 'DatabaseSeeder.php') -Value $databaseSeeder -Force

# 10) Rodar migrations e seeders
Write-Host "Rodando migrations..."
php artisan migrate --force
Write-Host "Rodando seeders (IMPORTANTE para criar usuários de teste)..."
php artisan db:seed --force

# 11) Final
Write-Host "Setup finalizado. Inicie o servidor com: php artisan serve"
Write-Host "Credenciais de teste: admin@example.com / secret"

Pop-Location
