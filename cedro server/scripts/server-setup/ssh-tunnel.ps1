# SSH Tunnel para acceder a paneles de administracion sin exponer puertos
# Uso: .\ssh-tunnel.ps1 -ServerIP 1.2.3.4 [-Panel cyberpanel|grafana|all]
#
# Puertos tuneados:
#   8090  -> CyberPanel admin   (https://localhost:8090)
#   7080  -> OLS admin          (https://localhost:7080)
#   3001  -> Grafana            (http://localhost:3001)
#   9090  -> Prometheus         (http://localhost:9090)

param(
    [Parameter(Mandatory=$true)]
    [string]$ServerIP,

    [string]$User = "replanta",
    [string]$KeyPath = "$env:USERPROFILE\.ssh\replanta_hetzner",
    [ValidateSet("cyberpanel", "grafana", "all")]
    [string]$Panel = "all"
)

if (-not (Test-Path $KeyPath)) {
    Write-Host "No se encuentra la clave SSH en: $KeyPath" -ForegroundColor Red
    Write-Host "Ejecuta primero: .\generate-ssh-key.ps1" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "=== Abriendo SSH tunnel a $ServerIP ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "  CyberPanel admin  -> https://localhost:8090" -ForegroundColor Green
Write-Host "  OLS admin         -> https://localhost:7080" -ForegroundColor Green
Write-Host "  Grafana           -> http://localhost:3001" -ForegroundColor Green
Write-Host "  Prometheus        -> http://localhost:9090" -ForegroundColor Green
Write-Host ""
Write-Host "  Pulsa Ctrl+C para cerrar el tunnel." -ForegroundColor Yellow
Write-Host ""

$OpenURL = switch ($Panel) {
    "cyberpanel" { "https://localhost:8090" }
    "grafana"    { "http://localhost:3001" }
    "all"        { "https://localhost:8090" }
}

# Abre el navegador automaticamente despues de 2 segundos
Start-Job -ScriptBlock {
    Start-Sleep 2
    Start-Process $using:OpenURL
} | Out-Null

# Establece el tunnel (bloquea hasta Ctrl+C)
ssh `
    -N `
    -o StrictHostKeyChecking=accept-new `
    -o ServerAliveInterval=30 `
    -L "8090:localhost:8090" `
    -L "7080:localhost:7080" `
    -L "3001:localhost:3001" `
    -L "9090:localhost:9090" `
    "$User@$ServerIP"
