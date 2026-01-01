# Teste automático: login e GET /api/v1/clientes

$base = 'http://127.0.0.1:8000'
$body = @{ email = 'admin@teste.com'; password = 'password' } | ConvertTo-Json

Write-Output "POST $base/api/v1/auth/login"
try {
    $resp = Invoke-RestMethod -Uri "$base/api/v1/auth/login" -Method POST -ContentType 'application/json' -Body $body -Headers @{ Accept = 'application/json' }
} catch {
    Write-Error "Falha no login: $($_.Exception.Message)"
    exit 1
}

$token = $resp.token
Write-Output "TOKEN: $token"

Write-Output "GET $base/api/v1/clientes"
try {
    $clientes = Invoke-RestMethod -Uri "$base/api/v1/clientes" -Method GET -Headers @{ Authorization = "Bearer $token"; Accept = 'application/json' }
    $clientes | ConvertTo-Json -Depth 6 | Write-Output
} catch {
    Write-Error "Falha no GET /clientes: $($_.Exception.Message)"
    exit 2
}

Write-Output "OK"
