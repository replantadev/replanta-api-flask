# SSH Tunnel para acceder a CyberPanel y OLS admin sin exponer puertos
# Uso: .\ssh-tunnel.ps1 -ServerIP 1.2.3.4
# Luego abrir: https://localhost:8090 (CyberPanel) / https://localhost:7080 (OLS)

param(
    [Parameter(Mandatory=$true)]
    [string]$ServerIP,

    [string]$User = "replanta",
    [string]$KeyPath = "$env:USERPROFILE\.ssh\replanta_hetzner",
    [int]$CyberPanelPort = 8090,
    [int]$OLSAdminPort = 7080
)

if (-not (Test-Path $KeyPath)) {
    Write-Host "No se encuentra la clave SSH en: $KeyPath" -ForegroundColor Red
    Write-Host "Ejecuta primero: .\generate-ssh-key.ps1" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "=== Abriendo SSH tunnel a $ServerIP ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "  CyberPanel admin  -> https://localhost:$CyberPanelPort" -ForegroundColor Green
Write-Host "  OLS admin         -> https://localhost:$OLSAdminPort" -ForegroundColor Green
Write-Host ""
Write-Host "  Pulsa Ctrl+C para cerrar el tunnel." -ForegroundColor Yellow
Write-Host ""

# Abre el navegador automaticamente despues de 2 segundos
Start-Job -ScriptBlock {
    Start-Sleep 2
    Start-Process "https://localhost:$using:CyberPanelPort"
} | Out-Null

# Establece el tunnel (bloquea hasta Ctrl+C)
ssh `
    -N `
    -o StrictHostKeyChecking=accept-new `
    -o ServerAliveInterval=30 `
    -L "${CyberPanelPort}:localhost:${CyberPanelPort}" `
    -L "${OLSAdminPort}:localhost:${OLSAdminPort}" `
    -i $KeyPath `
    "$User@$ServerIP"
