# Genera la SSH key para el servidor Cedro Hetzner
# Ejecutar una sola vez desde PowerShell en tu maquina local

$keyPath = "$env:USERPROFILE\.ssh\replanta_hetzner"

if (Test-Path $keyPath) {
    Write-Host "Ya existe una clave en $keyPath" -ForegroundColor Yellow
    Write-Host "Clave publica:" -ForegroundColor Cyan
    Get-Content "$keyPath.pub"
    exit 0
}

Write-Host "Creando directorio .ssh si no existe..." -ForegroundColor Cyan
New-Item -ItemType Directory -Force "$env:USERPROFILE\.ssh" | Out-Null

Write-Host "Generando clave ed25519 para replanta-cedro..." -ForegroundColor Cyan
ssh-keygen -t ed25519 -C "cedro-hetzner-replanta" -f $keyPath

Write-Host ""
Write-Host "=== CLAVE PUBLICA (pegar en Hetzner) ===" -ForegroundColor Green
Get-Content "$keyPath.pub"
Write-Host "=========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Copia el texto de arriba y pegalo en Hetzner > SSH Keys al crear el servidor." -ForegroundColor Yellow
