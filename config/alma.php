<?php

/*
 * Asistente Alma: dónde viven las carpetas de los proyectos y el puente con Windows.
 * Los escribe scripts/instalar-windows.ps1 en .env; sin ellos, el panel funciona igual pero sin carpetas.
 */
return [
    // Carpeta base de los proyectos, vista desde WSL (ej. /mnt/c/Users/Romino/AlmaProyectos).
    'proyectos_dir' => env('ALMA_PROYECTOS_DIR'),

    // Puente de Windows (ej. C:\Users\Romino\.alma\alma.ps1): abre carpetas/terminales y guarda credenciales.
    'puente' => env('ALMA_PUENTE'),

    // Versión fija de figwright (servidor MCP). Debe coincidir con la del plugin instalado en Figma, que se
    // actualiza a mano: con "@latest" el servidor se adelanta al plugin y la conexión falla. Se suben juntos.
    'figwright' => env('ALMA_FIGWRIGHT', '0.6.0'),
];
