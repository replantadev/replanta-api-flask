# Script de prueba para el endpoint REST API de Replanta
# Uso: .\test-api-endpoint.ps1 -Domain "tudominio.com"

param(
    [Parameter(Mandatory=$true)]
    [string]$Domain
)

# Limpiar el dominio
$Domain = $Domain.ToLower().Trim() -replace '^www\.', ''

Write-Host ""
Write-Host "🔍 Probando API de Replanta para el dominio: $Domain" -ForegroundColor Cyan
Write-Host ("-" * 60) -ForegroundColor Gray

# URL del endpoint
$url = "https://replanta.net/wp-json/replanta/v1/check_domain"

Write-Host "📡 Endpoint: $url" -ForegroundColor Yellow
Write-Host "📋 Dominio a verificar: $Domain" -ForegroundColor Yellow
Write-Host ("-" * 60) -ForegroundColor Gray

# Preparar datos
$body = @{
    domain = $Domain
} | ConvertTo-Json

# Headers
$headers = @{
    "Content-Type" = "application/json"
}

# Ejecutar petición
Write-Host "⏳ Enviando petición..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri $url -Method Post -Body $body -Headers $headers -ErrorAction Stop
    
    Write-Host "✅ Petición exitosa" -ForegroundColor Green
    Write-Host ("-" * 60) -ForegroundColor Gray
    
    # Mostrar respuesta
    Write-Host "📦 Respuesta JSON:" -ForegroundColor Cyan
    $response | ConvertTo-Json -Depth 10 | Write-Host
    Write-Host ("-" * 60) -ForegroundColor Gray
    
    # Interpretar resultado
    if ($response.hosted -eq $true) {
        Write-Host "✅ DOMINIO ALOJADO EN REPLANTA" -ForegroundColor Green
        Write-Host ""
        Write-Host "📊 Información del dominio:" -ForegroundColor Cyan
        Write-Host "  • Servidor: $($response.server)" -ForegroundColor White
        Write-Host "  • Estado: $($response.status)" -ForegroundColor White
        Write-Host "  • Árboles plantados: $($response.trees_planted)" -ForegroundColor White
        Write-Host "  • CO2 evitado: $($response.co2_evaded) kg" -ForegroundColor White
        Write-Host "  • Fecha emisión: $($response.fecha_emision)" -ForegroundColor White
        Write-Host "  • Validez: $($response.validez)" -ForegroundColor White
        Write-Host ""
        Write-Host "🎉 El sello-replanta se mostrará correctamente en este dominio" -ForegroundColor Green
    }
    else {
        Write-Host "❌ DOMINIO NO ALOJADO EN REPLANTA" -ForegroundColor Red
        if ($response.message) {
            Write-Host "  Mensaje: $($response.message)" -ForegroundColor Yellow
        }
        Write-Host ""
        Write-Host "⚠️  El sello-replanta NO se mostrará en este dominio" -ForegroundColor Yellow
        Write-Host "  Verifica que el dominio esté en la base de datos de dominios-reseller" -ForegroundColor White
    }
}
catch {
    Write-Host "❌ Error en la petición" -ForegroundColor Red
    Write-Host "Detalles: $($_.Exception.Message)" -ForegroundColor Red
    
    if ($_.ErrorDetails.Message) {
        Write-Host "Respuesta del servidor:" -ForegroundColor Yellow
        Write-Host $_.ErrorDetails.Message
    }
}

Write-Host ("-" * 60) -ForegroundColor Gray
Write-Host ""
