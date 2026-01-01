try {
  $loginBody = @{ email = 'admin@teste.com'; password = 'password' } | ConvertTo-Json
  $login = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/v1/auth/login' -Method Post -ContentType 'application/json' -Body $loginBody -ErrorAction Stop
} catch {
  Write-Output "LOGIN_ERROR: $($_.Exception.Message)"
  exit 1
}

Write-Output "LOGIN_RESPONSE:"
$login | ConvertTo-Json -Depth 5 | Write-Output

$token = $login.token
if (-not $token) {
  Write-Output "No token received"
  exit 1
}

try {
  $summary = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/v1/dashboard/summary' -Method Get -Headers @{ Authorization = "Bearer $token" } -ErrorAction Stop
} catch {
  Write-Output "SUMMARY_ERROR: $($_.Exception.Message)"
  exit 1
}

Write-Output "SUMMARY_RESPONSE:"
$summary | ConvertTo-Json -Depth 5 | Write-Output
