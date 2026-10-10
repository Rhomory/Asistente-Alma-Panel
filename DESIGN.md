# Sistema de diseño — Panel Asistente Alma

Rediseño de octubre 2026. Tema oscuro como principal y variante clara; el usuario elige con el
botón de la barra superior (se recuerda en `localStorage`, clave `alma-tema`; `?tema=claro` lo fija).

## Escena
Desarrolladores y Technical Lead de la agencia, en escritorio, con el panel abierto horas junto a la
consola y Figma (ambos oscuros). Por eso el oscuro es el principal; el claro sirve para revisar con luz de día.

## Tokens (public/css/panel.css, OKLCH)
- Neutros teñidos hacia el lavanda de la marca (matiz 285): `--bg`, `--side`, `--surface`, `--surface-2`,
  `--hover`, `--line`, `--line-2`; tinta `--ink`, `--ink-2`, `--ink-3` (todas ≥ 4.5:1 sobre su fondo).
- Acento `--ora` (naranja de marca, ~#F2915F): solo acción primaria, selección actual y estados. Texto sobre
  naranja: `--on-ora` (oscuro), nunca blanco.
- Estados: `--ok` aprobada/conectado · `--lav` en construcción/asistente · `--warn` por revisar ·
  `--ora` QA solicitado · `--err` sin respuesta. Cada uno tiene `-ink` (texto) y `-soft` (fondo).

## Tipografía
Una sola familia: Poppins (fallback Segoe UI). Escala fija: cuerpo 13.5px, h1 22px, h2 15px, datos 13px.
Código y prompts en Cascadia Code / Consolas.

## Componentes
- `.caja` + `.caja-cab` + `.caja-cuerpo`: superficie base; sin cajas anidadas.
- `.resumen`: franja de 6 cifras pequeñas (no tarjetas KPI grandes).
- `.tag` con punto: `e-<estado>` para estados de página/sección (parcial `panel/partes/estado`).
- `.btn` (secundario), `.btn.p` (primario naranja), `.btn.fant`, `.btn.ok`, `.btn.chico`.
- `input.switch` para incluir/excluir páginas y tokens.
- `.seg` barra segmentada por sección; `.barra-prog` avance apilado.
- Íconos: componente `<x-ic n="…" />` (trazo 1.8, estilo lineal).

## Reglas
- El naranja señala acción o lo actual, nunca decoración.
- Sin bordes laterales de color, sin texto con degradado, sin tarjetas de métrica gigantes.
- Motion (estilo dashboard): al abrir una vista los bloques entran en cascada (fundido + 6 px, 60 ms entre bloques), las filas se encienden una tras otra, las cifras cuentan hasta su valor y las barras crecen; avisos como toast abajo al centro y diálogo con pop leve. Menú lateral y barra superior nunca se animan. No se repite en recargas automáticas; `prefers-reduced-motion` lo desactiva.
- Menú lateral: cápsula flotante de vidrio (radio 24 px, separada 12 px del borde), plegable a un riel de iconos de 88 px con el botón junto al logo; cambia al instante y se recuerda (localStorage alma-menu). Cada ítem lleva su icono en un círculo de color (lavanda, verde, ámbar; naranja solo en "Nuevo proyecto"). El activo es una cápsula en relieve hecha solo con sombras internas, con el icono en naranja; el hover usa la misma forma y solo cambia el fondo. Plegado, los proyectos se ven por iniciales y las insignias se vuelven puntos dentro del círculo, sin salirse. Al pie: cambiar tema y el avatar "R" (solo visual hasta que exista el inicio de sesión).
- Movimiento: plegar el menú anima el ancho del riel y desliza el contenido ya en su forma final (View Transitions, 0,3 s, curva suave); entre páginas, fundido de 0,2 s con menú y barra quietos; al cambiar de tema, fundido de 0,45 s de toda la pantalla. La cascada de entrada solo al abrir el panel. Todo se apaga con reducir movimiento.
- Buscadores (barra superior y registro): la lupa despliega una cápsula de búsqueda hacia la izquierda (0,42 s, salida suave); el primer clic abre, el segundo busca, Escape cierra; atajo "/" en la barra.
- Inicio (concepto A2): saludo con fecha, chip "Hoy · N pendientes", titular en dos líneas (la segunda en naranja), cola en cápsulas, gráfico de minutos por página frente a la base de 480 (la última página en naranja), proyectos con riel de avance y porcentaje, dos cifras (asistente y frente a la base) y actividad con puntos de color. Barra superior: estado del asistente y botones redondos para crear, buscar en el registro (popover) y avisos con punto ámbar si hay cola. Fondo con las luces del concepto A2.
- Vista de página: tablero de obra (Planificada, En construcción, Por revisar, Aprobada) con pestañas de las páginas del proyecto; tarjetas en relieve, las aprobadas como cápsulas compactas, y el acceso a Solicitar QA al pie de Aprobada.
- Vidrio solo en el menú lateral y la barra superior, sobre luces suaves de la paleta en el fondo. Botones en cápsula con relieve; el principal en naranja con brillo propio. Tarjetas opacas para que el texto se lea siempre.
- El panel se recarga solo cuando la consola escribe (huella en `/estado/version`); si hay un formulario
  a medio escribir, muestra un aviso en lugar de recargar.
