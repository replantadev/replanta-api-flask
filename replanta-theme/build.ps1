# Build & package replanta-theme as a ZIP ready to upload via wp-admin > Apariencia > Temas > Subir tema
$ErrorActionPreference = 'Stop'
$ThemeDir = $PSScriptRoot
$Out = Join-Path $ThemeDir 'replanta-theme.zip'

Write-Host "==> Replanta Theme packager" -ForegroundColor Green

# Optional: try to build the React admin UI (skips on failure, fallback HTML still works)
$pnpm = Get-Command pnpm -ErrorAction SilentlyContinue
if ($pnpm) {
    Write-Host "==> pnpm install + build"
    Push-Location $ThemeDir
    try {
        pnpm install --silent
        pnpm build
    } catch {
        Write-Warning "Build failed — continuing with fallback (HTML installer will be used)."
    } finally {
        Pop-Location
    }
} else {
    Write-Warning "pnpm no encontrado. Empaquetando sin build (la UI fallback sigue funcionando)."
}

if (Test-Path $Out) { Remove-Item $Out -Force }

$exclude = @('node_modules','.git','.vscode','*.zip','build.ps1','tsconfig.tsbuildinfo')
$staging = Join-Path $env:TEMP ("replanta-theme-stage-" + [Guid]::NewGuid())
New-Item -ItemType Directory -Path $staging | Out-Null
$dest = Join-Path $staging 'replanta-theme'

Write-Host "==> Copying files to staging: $dest"
robocopy $ThemeDir $dest /E /XD node_modules .git .vscode /XF *.zip build.ps1 tsconfig.tsbuildinfo | Out-Null

Write-Host "==> Zipping"
Compress-Archive -Path $dest -DestinationPath $Out -Force

Remove-Item $staging -Recurse -Force
Write-Host "==> Done: $Out" -ForegroundColor Green
Write-Host "Subir en wp-admin > Apariencia > Temas > Añadir nuevo > Subir tema"
