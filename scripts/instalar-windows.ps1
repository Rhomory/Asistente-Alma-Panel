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

# 2d. figwright instalado en %USERPROFILE%\.alma\mcp y lanzado con node: arranca en ~0,5 s en vez de los 3-5 s
#     de "npx -y" (que consulta el registro de npm en cada arranque) y funciona sin red.
$mcpDir = Join-Path $destAlma 'mcp'
$pkgFig = Join-Path $mcpDir 'node_modules\@figwright\mcp\package.json'
function Version-Local { if (Test-Path $pkgFig) { (Get-Content $pkgFig -Raw | ConvertFrom-Json).version } }
if ((Version-Local) -ne $Figwright -and (Get-Command npm -ErrorAction SilentlyContinue)) {
    New-Item -ItemType Directory -Force $mcpDir | Out-Null
    npm install --prefix $mcpDir $paqueteFigwright --no-audit --no-fund --loglevel=error 2>&1 | Out-Null
}
$comandoFigwright = @('npx', '-y', $paqueteFigwright)   # respaldo si no se pudo instalar
if ((Version-Local) -eq $Figwright) {
    $binFig = (Get-Content $pkgFig -Raw | ConvertFrom-Json).bin.'figwright-mcp'
    $entradaFig = [IO.Path]::GetFullPath((Join-Path (Split-Path $pkgFig) $binFig))
    $comandoFigwright = @('node', $entradaFig)
    Write-Host "  figwright $Figwright instalado en $mcpDir (arranque rápido con node)"
} else {
    Write-Host "  No pude instalar figwright en $mcpDir; los agentes usarán npx (más lento)." -ForegroundColor Yellow
}

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
    # En Claude Code y Codex, npx necesita "cmd /c" en Windows; node no.
    $comandoWin = if ($comandoFigwright[0] -eq 'node') { $comandoFigwright } else { @('cmd', '/c') + $comandoFigwright }
    $huella = $comandoFigwright[-1]   # ruta de index.mjs o paquete con versión: distingue una entrada vieja

    if (Get-Command claude -ErrorAction SilentlyContinue) {
        $actual = (claude mcp get figwright 2>$null) -join "`n"
        if ($LASTEXITCODE -ne 0) {
            claude mcp add figwright --scope user -- @comandoWin | Out-Null
            Write-Host "  figwright $Figwright registrado en Claude Code"
        } elseif ($actual -notmatch [regex]::Escape($huella)) {
            claude mcp remove figwright --scope user 2>$null | Out-Null
            claude mcp add figwright --scope user -- @comandoWin | Out-Null
            Write-Host "  figwright de Claude Code actualizado ($Figwright, $($comandoWin[0]))"
        } else { Write-Host "  Claude Code ya tiene figwright $Figwright" }
    }

    $toml = "$HOME\.codex\config.toml"
    if (Test-Path (Split-Path $toml)) {
        $texto = if (Test-Path $toml) { Get-Content $toml -Raw } else { '' }
        $argsToml = ($comandoWin[1..($comandoWin.Count - 1)] | ForEach-Object { "'$_'" }) -join ', '   # literales TOML: sin escapar \
        $bloque = "[mcp_servers.figwright]`ncommand = `"$($comandoWin[0])`"`nargs = [$argsToml]`n"
        if ($texto -notmatch '\[mcp_servers\.figwright\]') {
            Add-Content -Encoding UTF8 $toml "`n$bloque"
            Write-Host "  figwright $Figwright agregado a ~\.codex\config.toml"
        } elseif (-not $texto.Contains($huella)) {
            # Reemplaza la sección completa hasta el siguiente encabezado [..]
            $texto = [regex]::Replace($texto, '(?ms)^\[mcp_servers\.figwright\]\s*\r?\n.*?(?=^\[|\z)', { param($m) $bloque + "`n" })
            [IO.File]::WriteAllText($toml, $texto, (New-Object Text.UTF8Encoding $false))
            Write-Host "  figwright de Codex actualizado ($Figwright, $($comandoWin[0]))"
        } else { Write-Host "  Codex ya tiene figwright $Figwright" }
    }

    # OpenCode: se agrega o corrige solo la entrada "figwright"; los demás servidores no se tocan.
    $oc = "$HOME\.config\opencode\opencode.json"
    if ((Get-Command opencode -ErrorAction SilentlyContinue) -or (Test-Path $oc)) {
        try {
            $conf = if (Test-Path $oc) { Get-Content $oc -Raw | ConvertFrom-Json } else { [pscustomobject]@{ '$schema' = 'https://opencode.ai/config.json' } }
            if (-not $conf.PSObject.Properties['mcp']) { $conf | Add-Member -NotePropertyName mcp -NotePropertyValue ([pscustomobject]@{}) }
            $entrada = [pscustomobject]@{ type = 'local'; command = $comandoFigwright; enabled = $true }
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
