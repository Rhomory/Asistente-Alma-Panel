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
    [string]$Proyectos,   # carpeta base de los proyectos en Windows (por defecto %USERPROFILE%\AlmaProyectos)
    [string]$Figwright,   # versión fija de figwright; por defecto la de ALMA_FIGWRIGHT en el .env del panel, o 0.6.0
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

# 2a. Comando "alma" para el uso diario (alma panel, alma diagnostico, alma registro:cola "Cota"…).
#     Va en .alma\bin, solo con el .cmd: así PowerShell no intenta ejecutar alma.ps1 directo (fallaría con la
#     política de ejecución por defecto) y siempre pasa por -ExecutionPolicy Bypass.
$binAlma = Join-Path $destAlma 'bin'
New-Item -ItemType Directory -Force $binAlma | Out-Null
Set-Content -Encoding ASCII (Join-Path $binAlma 'alma.cmd') '@powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0..\alma.ps1" %*'
Remove-Item -ErrorAction SilentlyContinue (Join-Path $destAlma 'alma.cmd')
$rutaUsuario = [Environment]::GetEnvironmentVariable('Path', 'User')
$partes = @(($rutaUsuario -split ';') | Where-Object { $_ -and $_ -ne $destAlma })   # quita la ruta de una versión anterior
if (-not ($partes -contains $binAlma) -or $partes.Count -ne @($rutaUsuario -split ';' | Where-Object { $_ }).Count) {
    [Environment]::SetEnvironmentVariable('Path', (@($partes | Where-Object { $_ -ne $binAlma }) + $binAlma) -join ';', 'User')
    Write-Host "  Comando 'alma' disponible (abre una terminal nueva para usarlo)"
} else { Write-Host "  Comando 'alma' disponible" }

# 2b. Carpeta de proyectos (fuera del panel) y rutas en el .env del panel
if (-not $Proyectos) { $Proyectos = Join-Path $HOME 'AlmaProyectos' }
New-Item -ItemType Directory -Force $Proyectos | Out-Null
$proyectosWsl = (wsl -d $Distro -- wslpath -a ($Proyectos.Replace('\', '/'))).Trim()
$envPanel = Join-Path $raiz '.env'
if (Test-Path $envPanel) {
    $lineas = @(@(Get-Content $envPanel) | Where-Object { $_ -notmatch '^(ALMA_PROYECTOS_DIR|ALMA_PUENTE)=' })
    $lineas += "ALMA_PROYECTOS_DIR=$proyectosWsl"
    $lineas += "ALMA_PUENTE='$destAlma\alma.ps1'"   # comillas simples: .env no interpreta las barras invertidas
    [IO.File]::WriteAllLines($envPanel, $lineas, (New-Object Text.UTF8Encoding $false))
    wsl -d $Distro --cd $Panel -- php artisan config:clear 2>$null | Out-Null
    Write-Host "  Proyectos en $Proyectos (el panel crea ahí una carpeta por proyecto)"
} else {
    Write-Host "  No encontré ${envPanel}: crea el .env del panel y vuelve a ejecutar el instalador." -ForegroundColor Yellow
}

# 2c. Versión fija de figwright: debe coincidir con la del plugin de Figma (que se actualiza a mano)
if (-not $Figwright -and (Test-Path $envPanel)) {
    $linea = Get-Content $envPanel | Where-Object { $_ -match '^ALMA_FIGWRIGHT=' } | Select-Object -First 1
    if ($linea) { $Figwright = ($linea -split '=', 2)[1].Trim().Trim('"', "'") }
}
if (-not $Figwright) { $Figwright = '0.6.0' }
$paqueteFigwright = "@figwright/mcp@$Figwright"
Write-Host "  figwright fijado en $Figwright (actualiza el plugin de Figma a la misma versión)"

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

# 4. figwright en los agentes, con la versión fija (registra lo que falta y corrige lo que tenga otra versión)
if ($RegistrarFigwright) {
    if (Get-Command claude -ErrorAction SilentlyContinue) {
        $actual = (claude mcp get figwright 2>$null) -join "`n"
        if ($LASTEXITCODE -ne 0) {
            claude mcp add figwright --scope user -- cmd /c npx -y $paqueteFigwright | Out-Null
            Write-Host "  figwright $Figwright registrado en Claude Code"
        } elseif ($actual -notmatch [regex]::Escape($paqueteFigwright)) {
            claude mcp remove figwright --scope user 2>$null | Out-Null
            claude mcp add figwright --scope user -- cmd /c npx -y $paqueteFigwright | Out-Null
            Write-Host "  figwright de Claude Code actualizado a la versión fija $Figwright"
        } else { Write-Host "  Claude Code ya tiene figwright $Figwright" }
    }

    $toml = "$HOME\.codex\config.toml"
    if (Test-Path (Split-Path $toml)) {
        $texto = if (Test-Path $toml) { Get-Content $toml -Raw } else { '' }
        if ($texto -notmatch '\[mcp_servers\.figwright\]') {
            Add-Content -Encoding UTF8 $toml "`n[mcp_servers.figwright]`ncommand = `"cmd`"`nargs = [`"/c`", `"npx`", `"-y`", `"$paqueteFigwright`"]`n"
            Write-Host "  figwright $Figwright agregado a ~\.codex\config.toml"
        } elseif ($texto -match '@figwright/mcp@(?!' + [regex]::Escape($Figwright) + '")[^"]+') {
            $texto = [regex]::Replace($texto, '@figwright/mcp@[^"]+', $paqueteFigwright)
            [IO.File]::WriteAllText($toml, $texto, (New-Object Text.UTF8Encoding $false))
            Write-Host "  figwright de Codex actualizado a la versión fija $Figwright"
        } else { Write-Host "  Codex ya tiene figwright $Figwright" }
    }

    # OpenCode: se agrega o corrige solo la entrada "figwright"; los demás servidores no se tocan.
    $oc = "$HOME\.config\opencode\opencode.json"
    if ((Get-Command opencode -ErrorAction SilentlyContinue) -or (Test-Path $oc)) {
        try {
            $conf = if (Test-Path $oc) { Get-Content $oc -Raw | ConvertFrom-Json } else { [pscustomobject]@{ '$schema' = 'https://opencode.ai/config.json' } }
            if (-not $conf.PSObject.Properties['mcp']) { $conf | Add-Member -NotePropertyName mcp -NotePropertyValue ([pscustomobject]@{}) }
            $entrada = [pscustomobject]@{ type = 'local'; command = @('npx', '-y', $paqueteFigwright); enabled = $true }
            $previa = $conf.mcp.PSObject.Properties['figwright']
            if ($previa -and (($previa.Value.command -join ' ') -eq ($entrada.command -join ' '))) {
                Write-Host "  OpenCode ya tiene figwright $Figwright"
            } else {
                if ($previa) { $conf.mcp.figwright = $entrada } else { $conf.mcp | Add-Member -NotePropertyName figwright -NotePropertyValue $entrada }
                New-Item -ItemType Directory -Force (Split-Path $oc) | Out-Null
                if (Test-Path $oc) { Copy-Item -Force $oc "$oc.respaldo" }
                [IO.File]::WriteAllText($oc, ($conf | ConvertTo-Json -Depth 20), (New-Object Text.UTF8Encoding $false))
                Write-Host "  figwright $Figwright $(if ($previa) {'actualizado'} else {'agregado'}) en OpenCode (copia previa: opencode.json.respaldo)"
            }
        } catch {
            Write-Host "  OpenCode: no pude leer $oc (¿tiene comentarios?). Agrega a mano el bloque figwright del README." -ForegroundColor Yellow
        }
    }
}

# 5. Diagnóstico
Write-Host ''
& (Join-Path $destAlma 'alma.ps1') diagnostico
