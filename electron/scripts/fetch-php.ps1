param(
  [string]$SourceDir = "",
  [string]$ZipUrl = "",
  [string]$OutDir = ""
)

$ErrorActionPreference = "Stop"

function Copy-PhpDir([string]$From, [string]$To) {
  if (-not (Test-Path -LiteralPath $From)) {
    throw "SourceDir não existe: $From"
  }
  if (-not (Test-Path -LiteralPath (Join-Path $From 'php.exe'))) {
    throw "php.exe não encontrado em SourceDir: $From"
  }

  if (-not (Test-Path -LiteralPath $To)) {
    New-Item -ItemType Directory -Force -Path $To | Out-Null
  }

  Write-Host "Copiando PHP de '$From' para '$To'..."
  Copy-Item -Path (Join-Path $From '*') -Destination $To -Recurse -Force
}

$electronDir = Split-Path -Parent $PSScriptRoot
$defaultOut = Join-Path $electronDir "php"
if ([string]::IsNullOrWhiteSpace($OutDir)) { $OutDir = $defaultOut }

if (-not [string]::IsNullOrWhiteSpace($SourceDir)) {
  Copy-PhpDir -From $SourceDir -To $OutDir
  Write-Host "OK. Verifique: $OutDir\php.exe"
  exit 0
}

if ([string]::IsNullOrWhiteSpace($ZipUrl)) {
  Write-Host "Nenhum SourceDir/ZipUrl informado." -ForegroundColor Yellow
  Write-Host "Opção A (recomendado): copie um PHP já instalado:" -ForegroundColor Yellow
  Write-Host "  powershell -ExecutionPolicy Bypass -File scripts/fetch-php.ps1 -SourceDir 'C:\\php'" -ForegroundColor Yellow
  Write-Host "Opção B: baixe um ZIP oficial do PHP Windows e informe a URL:" -ForegroundColor Yellow
  Write-Host "  powershell -ExecutionPolicy Bypass -File scripts/fetch-php.ps1 -ZipUrl 'https://windows.php.net/downloads/releases/php-8.x.x-nts-Win32-vs16-x64.zip'" -ForegroundColor Yellow
  exit 1
}

$temp = Join-Path $env:TEMP ("php-bundle-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Force -Path $temp | Out-Null

$zipPath = Join-Path $temp "php.zip"
Write-Host "Baixando PHP de: $ZipUrl"
Invoke-WebRequest -Uri $ZipUrl -OutFile $zipPath

Write-Host "Extraindo para: $OutDir"
if (-not (Test-Path -LiteralPath $OutDir)) {
  New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
}

Expand-Archive -LiteralPath $zipPath -DestinationPath $OutDir -Force

if (-not (Test-Path -LiteralPath (Join-Path $OutDir 'php.exe'))) {
  throw "Falha: php.exe não foi encontrado após extrair. Verifique se o ZIP é do PHP correto."
}

Write-Host "OK. PHP embutido preparado em: $OutDir"
Write-Host "Dica: confirme extensões SQLite em runtime:" 
Write-Host "  & '$OutDir\php.exe' -c '$OutDir\php.ini' -m | Select-String -Pattern 'pdo_sqlite|sqlite3'"
