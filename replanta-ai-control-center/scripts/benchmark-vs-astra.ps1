param(
    [Parameter(Mandatory = $true)]
    [string]$ReplantaUrl,

    [Parameter(Mandatory = $true)]
    [string]$AstraUrl,

    [string]$OutDir = ".\\benchmark-output"
)

$ErrorActionPreference = "Stop"

function Invoke-LighthouseRun {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Url,
        [Parameter(Mandatory = $true)]
        [string]$OutputPath
    )

    $lighthouseCmd = Get-Command lighthouse -ErrorAction SilentlyContinue
    if (-not $lighthouseCmd) {
        throw "Lighthouse CLI no está instalado. Instala con: npm i -g lighthouse"
    }

    & lighthouse $Url --output json --output-path $OutputPath --quiet --chrome-flags="--headless"
}

function Read-LighthouseSummary {
    param([string]$Path)

    $json = Get-Content -Raw -Path $Path | ConvertFrom-Json

    return [PSCustomObject]@{
        performance   = [math]::Round(($json.categories.performance.score * 100), 2)
        accessibility = [math]::Round(($json.categories.accessibility.score * 100), 2)
        seo           = [math]::Round(($json.categories.seo.score * 100), 2)
        bestPractices = [math]::Round(($json.categories.'best-practices'.score * 100), 2)
        fcpMs         = [math]::Round($json.audits.'first-contentful-paint'.numericValue, 0)
        lcpMs         = [math]::Round($json.audits.'largest-contentful-paint'.numericValue, 0)
        cls           = [math]::Round($json.audits.'cumulative-layout-shift'.numericValue, 3)
        tbtMs         = [math]::Round($json.audits.'total-blocking-time'.numericValue, 0)
    }
}

if (-not (Test-Path $OutDir)) {
    New-Item -ItemType Directory -Path $OutDir | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$replantaJson = Join-Path $OutDir "replanta-$timestamp.json"
$astraJson = Join-Path $OutDir "astra-$timestamp.json"
$summaryPath = Join-Path $OutDir "summary-$timestamp.txt"

Write-Host "Running Lighthouse for Replanta..."
Invoke-LighthouseRun -Url $ReplantaUrl -OutputPath $replantaJson

Write-Host "Running Lighthouse for Astra..."
Invoke-LighthouseRun -Url $AstraUrl -OutputPath $astraJson

$replanta = Read-LighthouseSummary -Path $replantaJson
$astra = Read-LighthouseSummary -Path $astraJson

$lines = @(
    "Benchmark timestamp: $timestamp",
    "Replanta URL: $ReplantaUrl",
    "Astra URL: $AstraUrl",
    "",
    "Replanta:",
    "  Performance:   $($replanta.performance)",
    "  Accessibility: $($replanta.accessibility)",
    "  SEO:           $($replanta.seo)",
    "  Best Practices:$($replanta.bestPractices)",
    "  FCP(ms):       $($replanta.fcpMs)",
    "  LCP(ms):       $($replanta.lcpMs)",
    "  CLS:           $($replanta.cls)",
    "  TBT(ms):       $($replanta.tbtMs)",
    "",
    "Astra:",
    "  Performance:   $($astra.performance)",
    "  Accessibility: $($astra.accessibility)",
    "  SEO:           $($astra.seo)",
    "  Best Practices:$($astra.bestPractices)",
    "  FCP(ms):       $($astra.fcpMs)",
    "  LCP(ms):       $($astra.lcpMs)",
    "  CLS:           $($astra.cls)",
    "  TBT(ms):       $($astra.tbtMs)",
    "",
    "Delta Replanta - Astra:",
    "  Performance:   $([math]::Round(($replanta.performance - $astra.performance), 2))",
    "  Accessibility: $([math]::Round(($replanta.accessibility - $astra.accessibility), 2))",
    "  SEO:           $([math]::Round(($replanta.seo - $astra.seo), 2))",
    "  LCP(ms):       $([math]::Round(($replanta.lcpMs - $astra.lcpMs), 0))",
    "  CLS:           $([math]::Round(($replanta.cls - $astra.cls), 3))",
    "  TBT(ms):       $([math]::Round(($replanta.tbtMs - $astra.tbtMs), 0))"
)

$lines | Out-File -FilePath $summaryPath -Encoding UTF8
$lines | ForEach-Object { Write-Host $_ }

Write-Host ""
Write-Host "Saved files:"
Write-Host "- $replantaJson"
Write-Host "- $astraJson"
Write-Host "- $summaryPath"
