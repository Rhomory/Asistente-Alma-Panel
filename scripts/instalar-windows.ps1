# Instalador del lado Windows del Asistente Alma. Se ejecuta UNA vez por equipo (y otra si mueves el panel):
#
#   powershell -ExecutionPolicy Bypass -File "\\wsl.localhost\Ubuntu\home\<usuario>\<ruta>\asistente-alma-panel\scripts\instalar-windows.ps1"
#
# Qué hace:
#  1. Detecta la distro y la ruta del panel a partir de dónde está este script (o usa -Distro / -Panel).
#  2. Instala el puente en %USERPROFILE%\.alma\alma.ps1 con su alma.config.json (sin rutas fijas en el script).
#  3. Copia la skill alma-figma a ~\.claude\skills (Claude Code) y ~\.agents\skills (Codex, OpenCode),
#     cada una en su propia carpeta alma-figma\.
#  4. Con -RegistrarFigwright, registra figwright en Claude Code y Codex si no lo tienen (OpenCode: muestra el bloque).
#  5. Ejecuta el diagnóstico.
param(
    [string]$Distro,
    [string]$Panel,
    [switch]$RegistrarFigwright
)

$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$raiz = Split-Path $PSScriptRoot -Parent   # carpeta del panel vista desde Windows

# 1. Distro y ruta del panel: \\wsl.localhost\<distro>\home\... o \\wsl$\<distro>\home\...
if ((-not $Distro -or -not $Panel) -and $raiz -match '^\\\\wsl(?:\.localhost|\$)\\([^\\]+)(\\.*)$') {
    if (-not $Distro) { $Distro = $Matches[1] }
    if (-not $Panel) { $Panel = $Matches[2].Replace('\', '/') }
}
if (-not $Distro -or -not $Panel) {
    Write-Host 'No pude deducir la distro ni la ruta del panel.' -ForegroundColor Yellow
    Write-Host 'Ejecuta el script desde \\wsl.localhost\<distro>\... o pasa -Distro Ubuntu -Panel /home/<usuario>/<ruta-del-panel>'
    exit 2
}
wsl -d $Distro -- test -f "$Panel/artisan" 2>$null
if ($LASTEXITCODE -ne 0) { Write-Host "No hay un panel en $Panel ($Distro)." -ForegroundColor Yellow; exit 2 }
Write-Host "Panel: $Panel   Distro: $Distro" -ForegroundColor Cyan

# 2. Puente + configuración
$destAlma = Join-Path $HOME '.alma'
New-Item -ItemType Directory -Force $destAlma | Out-Null
Copy-Item -Force (Join-Path $PSScriptRoot 'alma.ps1') (Join-Path $destAlma 'alma.ps1')
[ordered]@{ panel = $Panel; distro = $Distro } | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $destAlma 'alma.config.json')
Write-Host "  Puente instalado en $destAlma\alma.ps1"

# 3. Skill en una carpeta propia por agente (evita que SKILL.md quede suelto en skills\)
$skillOrigen = Join-Path $raiz '.claude\skills\alma-figma\SKILL.md'
foreach ($base in @("$HOME\.claude\skills", "$HOME\.agents\skills")) {
    $dest = Join-Path $base 'alma-figma'
    New-Item -ItemType Directory -Force $dest | Out-Null
    Copy-Item -Force $skillOrigen (Join-Path $dest 'SKILL.md')
    Write-Host "  Skill instalada en $dest"
}
$suelto = "$HOME\.claude\skills\SKILL.md"
if ((Test-Path $suelto) -and (Select-String -Path $suelto -Pattern 'name: alma-figma' -Quiet)) {
    Remove-Item $suelto
    Write-Host "  Se quitó un SKILL.md suelto de una instalación anterior ($suelto)"
}

# 4. figwright en los agentes (opcional)
if ($RegistrarFigwright) {
    if (Get-Command claude -ErrorAction SilentlyContinue) {
        $ya = claude mcp get figwright 2>$null
        if ($LASTEXITCODE -ne 0) { claude mcp add figwright --scope user -- cmd /c npx -y '@figwright/mcp@latest' | Out-Null; Write-Host '  figwright registrado en Claude Code' }
        else { Write-Host '  Claude Code ya tiene figwright' }
    }
    $toml = "$HOME\.codex\config.toml"
    if (Test-Path (Split-Path $toml)) {
        if (-not ((Test-Path $toml) -and (Select-String -Path $toml -Pattern 'mcp_servers\.figwright' -Quiet))) {
            Add-Content -Encoding UTF8 $toml "`n[mcp_servers.figwright]`ncommand = `"cmd`"`nargs = [`"/c`", `"npx`", `"-y`", `"@figwright/mcp@latest`"]`n"
            Write-Host '  figwright agregado a ~\.codex\config.toml'
        } else { Write-Host '  Codex ya tiene figwright' }
    }
    Write-Host '  OpenCode: agrega el bloque "figwright" del README (seccion MCP por agente) a ~\.config\opencode\opencode.json'
}

# 5. Diagnóstico
Write-Host ''
& (Join-Path $destAlma 'alma.ps1') diagnostico
