<#
gerar-instalador-exe.ps1
Gera um instalador .exe (wrapper) a partir do script instalar-erp-saas.ps1 usando ps2exe.

Requisitos:
- PowerShell 5.1+ ou PowerShell 7+
- Acesso ao PowerShell Gallery para instalar o módulo ps2exe (uma vez)

Uso:
  PowerShell -ExecutionPolicy Bypass -File .\gerar-instalador-exe.ps1

Saída:
  .\dist\ERP-SaaS-Installer.exe

Aviso:
- Esse .exe NÃO inclui PHP/Node/Composer. Ele só executa o mesmo fluxo do script.
- Alguns antivírus podem alertar por ser um executável gerado a partir de script.
#>

[CmdletBinding()]
param(
    [string]$InputScript,
    [string]$OutputDir,
    [string]$OutputName = 'ERP-SaaS-Installer.exe'
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($PSScriptRoot)) {
    $scriptRoot = (Get-Location).Path
} else {
    $scriptRoot = $PSScriptRoot
}

if ([string]::IsNullOrWhiteSpace($InputScript)) {
    $candidateA = Join-Path $scriptRoot 'instalar-erp-saas.ps1'
    $candidateB = Join-Path $scriptRoot 'setup-windows-laravel.ps1'

    if (Test-Path $candidateA) {
        $InputScript = $candidateA
    } elseif (Test-Path $candidateB) {
        Write-Host "Aviso: '$candidateA' não encontrado; usando '$candidateB' como script de entrada." -ForegroundColor Yellow
        $InputScript = $candidateB
    } else {
        $ps1s = @(Get-ChildItem -Path $scriptRoot -Filter '*.ps1' -File -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName)
        $ps1List = if ($ps1s.Count -gt 0) { ($ps1s -join "`n - ") } else { '(nenhum .ps1 encontrado)' }
        throw "Nenhum script de entrada padrão encontrado. Crie 'instalar-erp-saas.ps1' ou informe -InputScript. Scripts .ps1 no diretório:`n - $ps1List"
    }
}

if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    $OutputDir = Join-Path $scriptRoot 'dist'
}

if (-not (Test-Path $InputScript)) {
    throw "Script de entrada não encontrado: $InputScript"
}

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

$outFile = Join-Path $OutputDir $OutputName

# Instala ps2exe se necessário
if (-not (Get-Module -ListAvailable -Name ps2exe)) {
    Write-Host "Módulo ps2exe não encontrado. Instalando em CurrentUser..." -ForegroundColor Cyan

    # Evita prompts comuns do PowerShellGet (repo não confiável / confirmação).
    try {
        $repo = Get-PSRepository -Name PSGallery -ErrorAction SilentlyContinue
        if ($repo -and $repo.InstallationPolicy -ne 'Trusted') {
            Set-PSRepository -Name PSGallery -InstallationPolicy Trusted
        }
    } catch {
        # Se falhar (ambiente restrito), segue e deixa o PowerShell pedir confirmação.
    }

    # Garante NuGet provider
    if (-not (Get-PackageProvider -Name NuGet -ErrorAction SilentlyContinue)) {
        Install-PackageProvider -Name NuGet -Scope CurrentUser -Force -Confirm:$false | Out-Null
    }

    Install-Module -Name ps2exe -Scope CurrentUser -Force -AllowClobber -Confirm:$false
}

Import-Module ps2exe -Force

Write-Host "Gerando EXE em: $outFile" -ForegroundColor Cyan

# Compila o próprio script (o .exe executa o mesmo conteúdo do .ps1)
Invoke-ps2exe -inputFile $InputScript -outputFile $outFile -noConsole:$false

Write-Host "OK. Gerado: $outFile" -ForegroundColor Green
