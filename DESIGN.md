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
- Motion: sin transiciones en menú, barra, pestañas, cajas, botones ni diálogo (cambian al instante). Solo el texto del contenido aparece con un fundido de opacidad de 0,35 s, sin desplazarse. `prefers-reduced-motion` lo desactiva.
- El panel se recarga solo cuando la consola escribe (huella en `/estado/version`); si hay un formulario
  a medio escribir, muestra un aviso en lugar de recargar.
