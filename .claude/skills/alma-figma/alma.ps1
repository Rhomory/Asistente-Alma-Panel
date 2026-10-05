# Puente Windows -> panel Asistente Alma (Laravel en WSL Ubuntu).
# Uso: powershell -File alma.ps1 registro:figma "ECOCREATIONS" --archivo=C:\ruta\figma.json
# Las rutas de Windows en --archivo= se traducen a /mnt/c/... para WSL.
$panel = if ($env:ALMA_PANEL) { $env:ALMA_PANEL } else { '/home/romino/proyectos/asistente-alma-panel' }
$distro = if ($env:ALMA_DISTRO) { $env:ALMA_DISTRO } else { 'Ubuntu' }
$argumentos = @(foreach ($a in $args) {
    if ($a -like '--archivo=*') {
        $ruta = $a.Substring(10).Replace('\', '/')
        '--archivo=' + (wsl -d $distro -- wslpath -a $ruta).Trim()
    } else { $a }
})
wsl -d $distro --cd $panel -- php artisan @argumentos
exit $LASTEXITCODE
