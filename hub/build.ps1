# =============================================================================
# build.ps1 — Replanta Hub deploy pipeline
# =============================================================================
# Uso:
#   .\build.ps1                    # lint + build ZIP (no deploy)
#   .\build.ps1 -Deploy            # lint + ZIP + git + release + self-update
#   .\build.ps1 -Deploy -Version 2.4.0   # fuerza version concreta
#
# Requiere:
#   $env:SAPWOO_GH_TOKEN   — GitHub PAT (scope: repo + releases)
#   $env:HUB_DEPLOY_TOKEN  — Token REST Hub produccion (rphub_deploy_token en DB)
#   php.exe en PATH        — para lint
# =============================================================================

param(
    [switch]$Deploy,
    [string]$Version = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ─────────────────────────────────────────────────────────────────────────────
#  Paths
# ─────────────────────────────────────────────────────────────────────────────
$PluginDir  = $PSScriptRoot
$PluginFile = Join-Path $PluginDir "replanta-hub.php"
$BuildTmp   = Join-Path $env:TEMP "replanta-hub-build"
$ZipOut     = Join-Path $env:TEMP "replanta-hub-RELEASE.zip"

$GhRepo         = "replantadev/hub"
$GhToken        = $env:SAPWOO_GH_TOKEN
$HubDeployToken = $env:HUB_DEPLOY_TOKEN
$HubUrl         = "https://replanta.net"

# ─────────────────────────────────────────────────────────────────────────────
#  Helpers
# ─────────────────────────────────────────────────────────────────────────────
function Step { param([string]$msg) Write-Host "`n==> $msg" -ForegroundColor Cyan }
function OK   { param([string]$msg) Write-Host "    OK  $msg" -ForegroundColor Green }
function Warn { param([string]$msg) Write-Host "    WARN $msg" -ForegroundColor Yellow }
function Fail { param([string]$msg) Write-Host "`n    ERR $msg" -ForegroundColor Red; exit 1 }

function GhApi {
    param([string]$Method, [string]$Path, $Body = $null)
    $uri = "https://api.github.com$Path"
    $headers = @{
        Authorization = "token $GhToken"
        Accept        = "application/vnd.github.v3+json"
        "User-Agent"  = "replantadev-build/hub"
    }
    $params = @{ Method = $Method; Uri = $uri; Headers = $headers; ContentType = "application/json" }
    if ($null -ne $Body) { $params.Body = ($Body | ConvertTo-Json -Depth 10) }
    try { return Invoke-RestMethod @params }
    catch [System.Net.WebException] {
        $resp   = $_.Exception.Response
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        Fail "GH API $Method $Path => $($resp.StatusCode) $($reader.ReadToEnd())"
    }
}

function GitExec {
    param([string]$WorkDir, [string[]]$GitArgs)
    $result = & git -C $WorkDir @GitArgs
    if ($LASTEXITCODE -ne 0) { Fail "git $GitArgs => exit $LASTEXITCODE" }
    return $result
}

# ─────────────────────────────────────────────────────────────────────────────
#  1. Leer version actual
# ─────────────────────────────────────────────────────────────────────────────
Step "Leyendo version actual"
$phpRaw = Get-Content $PluginFile -Raw
if ($phpRaw -match "define\s*\(\s*'RPHUB_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\)") {
    $CurrentVersion = $Matches[1]
} else {
    Fail "No se pudo leer RPHUB_VERSION en $PluginFile"
}
if (-not $Version) { $Version = $CurrentVersion }
OK "Version: $CurrentVersion  ->  destino: $Version"

# ─────────────────────────────────────────────────────────────────────────────
#  2. PHP lint
# ─────────────────────────────────────────────────────────────────────────────
Step "PHP lint"
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
$phpBin = if ($phpCmd) { $phpCmd.Source } else { $null }
if (-not $phpBin) { Warn "php no encontrado en PATH - saltando lint" }
else {
    $lintErrors = 0
    Get-ChildItem -Path $PluginDir -Filter "*.php" -Recurse |
        Where-Object { $_.FullName -notmatch '\\vendor\\' } |
        ForEach-Object {
            $out = & php -l $_.FullName 2>&1
            if ($LASTEXITCODE -ne 0) {
                Write-Host "    LINT: $($_.Name)" -ForegroundColor Red
                Write-Host "    $out" -ForegroundColor Red
                $lintErrors++
            }
        }
    if ($lintErrors -gt 0) { Fail "$lintErrors error(es) de sintaxis" }
    OK "Todos los archivos PHP OK"
}

# ─────────────────────────────────────────────────────────────────────────────
#  3. Bump version en plugin file (si cambia)
# ─────────────────────────────────────────────────────────────────────────────
if ($Version -ne $CurrentVersion) {
    Step "Bumping version $CurrentVersion -> $Version"
    $phpRaw = $phpRaw -replace "(?m)(^\s*\*\s*Version:\s*)$([regex]::Escape($CurrentVersion))", "`${1}$Version"
    $phpRaw = $phpRaw -replace "define\s*\(\s*'RPHUB_VERSION'\s*,\s*'$([regex]::Escape($CurrentVersion))'\s*\)", "define('RPHUB_VERSION', '$Version')"
    [System.IO.File]::WriteAllText($PluginFile, $phpRaw, [System.Text.UTF8Encoding]::new($false))
    OK "replanta-hub.php actualizado"
}

# ─────────────────────────────────────────────────────────────────────────────
#  4. Build ZIP
# ─────────────────────────────────────────────────────────────────────────────
Step "Construyendo ZIP"
if (Test-Path $BuildTmp) { Remove-Item $BuildTmp -Recurse -Force }
if (Test-Path $ZipOut)   { Remove-Item $ZipOut -Force }

$exclude    = @('build.ps1', '.git', '.gitignore', 'node_modules', '.DS_Store', 'Thumbs.db', '*.zip',
                'CLAUDE.md', '*.md', 'composer.json', 'composer.lock', 'config-sample.php',
                'admin-test.php', 'diagnostics.php', 'emergency-fix.php', '*.sql',
                'DESARROLLO-AUTONOMO-RESUMEN.md', 'ERROR-RESOLUTION-COMPLETE.md',
                'PHASE-*.md', 'RESUMEN-*.md', 'TEST_*.md', 'GUIA_*.md', 'TODO.md')
$destPlugin = Join-Path $BuildTmp "replanta-hub"
New-Item -ItemType Directory -Path $destPlugin -Force | Out-Null

Get-ChildItem -Path $PluginDir -Force |
    Where-Object {
        $name = $_.Name
        -not ($exclude | Where-Object { $name -like $_ })
    } |
    ForEach-Object {
        Copy-Item -Path $_.FullName -Destination $destPlugin -Recurse -Force
    }

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($BuildTmp, $ZipOut)
$zipSize = [math]::Round((Get-Item $ZipOut).Length / 1KB, 1)
OK "ZIP creado: $ZipOut ($zipSize KB)"

if (-not $Deploy) {
    Write-Host "`n  Build completado (sin deploy). Usa -Deploy para publicar.`n" -ForegroundColor Yellow
    exit 0
}

# ─────────────────────────────────────────────────────────────────────────────
#  5. Validar tokens
# ─────────────────────────────────────────────────────────────────────────────
if (-not $GhToken)        { Fail "Falta `$env:SAPWOO_GH_TOKEN" }
if (-not $HubDeployToken) { Fail "Falta `$env:HUB_DEPLOY_TOKEN" }

# ─────────────────────────────────────────────────────────────────────────────
#  6. Git commit + push
# ─────────────────────────────────────────────────────────────────────────────
Step "Git commit en plugin repo"
GitExec $PluginDir @("add", "replanta-hub.php", "inc", "assets", "build.ps1", "CHANGELOG.md")
$status = & git -C $PluginDir status --porcelain
if ($status) {
    GitExec $PluginDir @("commit", "-m", "v$Version")
    OK "Commit creado"
} else {
    OK "Nada que commitear"
}
GitExec $PluginDir @("push")
OK "Push completado"

# ─────────────────────────────────────────────────────────────────────────────
#  7. Tag + GitHub Release
# ─────────────────────────────────────────────────────────────────────────────
Step "Tag v$Version"
$existingTag = & git -C $PluginDir tag -l "v$Version" 2>&1
if ($existingTag -eq "v$Version") {
    Warn "Tag v$Version ya existe - saltando tag/release"
} else {
    GitExec $PluginDir @("tag", "v$Version")
    GitExec $PluginDir @("push", "origin", "v$Version")
    OK "Tag v$Version publicado"

    Step "GitHub Release v$Version"
    $release = GhApi "POST" "/repos/$GhRepo/releases" @{
        tag_name   = "v$Version"
        name       = "v$Version"
        body       = "Replanta Hub v$Version"
        draft      = $false
        prerelease = $false
    }
    OK "Release creada: $($release.html_url)"

    Step "Subiendo ZIP al release"
    $uploadUrl  = $release.upload_url -replace '\{.*\}', ''
    $uploadUrl += "?name=replanta-hub-$Version.zip"
    $zipBytes   = [System.IO.File]::ReadAllBytes($ZipOut)
    $uploadHdrs = @{
        Authorization  = "token $GhToken"
        Accept         = "application/vnd.github.v3+json"
        "Content-Type" = "application/zip"
        "User-Agent"   = "replantadev-build/hub"
    }
    Invoke-RestMethod -Method Post -Uri $uploadUrl -Headers $uploadHdrs -Body $zipBytes | Out-Null
    OK "ZIP subido al release"
}

# ─────────────────────────────────────────────────────────────────────────────
#  8. Notificar Hub produccion para que se auto-actualice
# ─────────────────────────────────────────────────────────────────────────────
Step "Auto-update Hub produccion"
try {
    $selfUpdateUrl = "$HubUrl/wp-json/replanta-hub/v1/self-update"
    $response = Invoke-RestMethod -Method Post -Uri $selfUpdateUrl `
        -Headers @{ "X-Deploy-Token" = $HubDeployToken; "User-Agent" = "replantadev-build/hub" } `
        -ContentType "application/json" `
        -TimeoutSec 60
    OK "Hub actualizado: $($response.message)"
} catch {
    Warn "self-update fallo: $_  (verifica manualmente en $HubUrl/wp-admin)"
}

# ─────────────────────────────────────────────────────────────────────────────
Write-Host "`n  Deploy completado: Replanta Hub v$Version" -ForegroundColor Green
Write-Host "  Release: https://github.com/$GhRepo/releases/tag/v$Version`n" -ForegroundColor Green
exit 0
