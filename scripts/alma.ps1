# Puente Windows -> panel Asistente Alma (Laravel en WSL).
# Lo instala scripts/instalar-windows.ps1 en %USERPROFILE%\.alma\alma.ps1. Compatible con Windows PowerShell 5.1.
#
#   powershell -NoProfile -File "$HOME\.alma\alma.ps1" <comando artisan> [argumentos]
#   powershell -NoProfile -File "$HOME\.alma\alma.ps1" diagnostico
#
# Dónde está el panel (en este orden): variables ALMA_PANEL / ALMA_DISTRO, luego alma.config.json
# junto a este script (lo escribe el instalador) y, si no hay nada, el valor por defecto.
# Las rutas de Windows en --archivo= se traducen solas a /mnt/c/... para WSL.

$ErrorActionPreference = 'Continue'  # 'Stop' rompe los comandos nativos que escriben en stderr (PowerShell 5.1)
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$cfg = $null
$archivoConfig = Join-Path $PSScriptRoot 'alma.config.json'
if (Test-Path $archivoConfig) { $cfg = Get-Content $archivoConfig -Raw | ConvertFrom-Json }

$panel = $env:ALMA_PANEL
if (-not $panel -and $cfg) { $panel = $cfg.panel }
if (-not $panel) { $panel = '/home/romino/proyectos/asistente-alma-panel' }

$distro = $env:ALMA_DISTRO
if (-not $distro -and $cfg) { $distro = $cfg.distro }
if (-not $distro) { $distro = 'Ubuntu' }

function Escribir($ok, $texto, $ayuda) {
    if ($ok) { Write-Host "  [OK] $texto" -ForegroundColor Green }
    else {
        Write-Host "  [X]  $texto" -ForegroundColor Yellow
        if ($ayuda) { Write-Host "       -> $ayuda" }
    }
}

function Panel-Existe {
    wsl -d $distro -- test -f "$panel/artisan" 2>$null
    return ($LASTEXITCODE -eq 0)
}

function Diagnostico {
    Write-Host "Asistente Alma - diagnostico" -ForegroundColor Cyan
    Write-Host "  Distro: $distro   Panel: $panel"
    Write-Host ""

    $distros = (wsl -l -q) -replace "`0", '' | Where-Object { $_.Trim() }
    Escribir ($distros -contains $distro) "WSL tiene la distro '$distro'" "Distros disponibles: $($distros -join ', '). Define ALMA_DISTRO o vuelve a ejecutar el instalador."

    $hayPanel = Panel-Existe
    Escribir $hayPanel "El panel existe en $panel" "Ejecuta scripts/instalar-windows.ps1 desde la carpeta del panel, o define ALMA_PANEL con la ruta de Ubuntu."

    if ($hayPanel) {
        $php = (wsl -d $distro --cd $panel -- php -r "echo PHP_VERSION;" 2>$null)
        Escribir ($LASTEXITCODE -eq 0) "PHP $php en WSL" "Instala PHP 8.3 o superior en Ubuntu (ver README, seccion Requisitos)."
        $mods = (wsl -d $distro --cd $panel -- php -m 2>$null) | ForEach-Object { $_.Trim().ToLower() }
        foreach ($m in 'pdo_sqlite', 'mbstring', 'curl', 'xml', 'dom', 'zip') {
            Escribir ($mods -contains $m) "Extension PHP $m" "sudo apt install php-$(if ($m -eq 'pdo_sqlite') {'sqlite3'} elseif ($m -eq 'dom') {'xml'} else {$m})"
        }
        $cola = wsl -d $distro --cd $panel -- php artisan registro:cola 2>&1
        Escribir ($LASTEXITCODE -eq 0) "Los comandos del panel responden" (($cola | Select-Object -Last 1) -as [string])

        $puerto = (wsl -d $distro --cd $panel -- sh -c "grep -E '^SERVER_PORT=' .env | cut -d= -f2" 2>$null)
        if (-not $puerto) { $puerto = '8000' }
        $web = $false
        try { $r = Invoke-WebRequest -UseBasicParsing -TimeoutSec 3 "http://127.0.0.1:$($puerto.Trim())/estado/version"; $web = ($r.StatusCode -eq 200) } catch {}
        Escribir $web "Panel web en http://127.0.0.1:$($puerto.Trim())" "En Ubuntu: cd $panel && php artisan serve"
    }

    $relay = $false
    try { $relay = [bool](Get-NetTCPConnection -LocalPort 3055 -State Listen -ErrorAction Stop) } catch {}
    Escribir $relay "figwright escuchando en 127.0.0.1:3055" "Lo inicia tu agente al arrancar si figwright esta en su configuracion MCP. No lo lances a mano en otra consola."

    Write-Host ""
    Write-Host "  Skills y MCP por agente:" -ForegroundColor Cyan
    Escribir (Test-Path "$HOME\.claude\skills\alma-figma\SKILL.md") "Skill para Claude Code (~\.claude\skills\alma-figma)" "Vuelve a ejecutar el instalador."
    Escribir (Test-Path "$HOME\.agents\skills\alma-figma\SKILL.md") "Skill para Codex y OpenCode (~\.agents\skills\alma-figma)" "Vuelve a ejecutar el instalador."
    $claude = (Test-Path "$HOME\.claude.json") -and (Select-String -Path "$HOME\.claude.json" -Pattern '"figwright"' -Quiet)
    $codex = (Test-Path "$HOME\.codex\config.toml") -and (Select-String -Path "$HOME\.codex\config.toml" -Pattern 'mcp_servers\.figwright' -Quiet)
    $oc = "$HOME\.config\opencode\opencode.json"
    $opencode = (Test-Path $oc) -and (Select-String -Path $oc -Pattern '"figwright"' -Quiet)
    Write-Host ("  figwright registrado -> Claude Code: {0} | Codex: {1} | OpenCode: {2}" -f $(if ($claude) {'si'} else {'no'}), $(if ($codex) {'si'} else {'no'}), $(if ($opencode) {'si'} else {'no'}))
    Write-Host "       (basta con el agente que uses; ejemplos de configuracion en el README, seccion MCP por agente)"
}

if ($args.Count -eq 0 -or @('ayuda', '-h', '--help') -contains $args[0]) {
    Write-Host 'Uso: alma.ps1 <comando artisan> [argumentos]   |   alma.ps1 diagnostico'
    Write-Host 'Ej.: alma.ps1 registro:cola "Cota"'
    exit 0
}
if ($args[0] -eq 'diagnostico') { Diagnostico; exit 0 }

if (-not (Panel-Existe)) {
    Write-Host "No encuentro el panel en '$panel' (distro '$distro')." -ForegroundColor Yellow
    Write-Host "Solucion: ejecuta scripts\instalar-windows.ps1 desde la carpeta del panel, o define ALMA_PANEL con la ruta de Ubuntu."
    Write-Host "Revisa todo con: alma.ps1 diagnostico"
    exit 2
}

$argumentos = @(foreach ($a in $args) {
    if ($a -like '--archivo=*') {
        $ruta = $a.Substring(10).Replace('\', '/')
        '--archivo=' + (wsl -d $distro -- wslpath -a $ruta).Trim()
    } else { $a }
})
wsl -d $distro --cd $panel -- php artisan @argumentos
exit $LASTEXITCODE
