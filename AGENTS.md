# Asistente Alma — panel (contexto para agentes que trabajan en ESTE código)

Este repo es el **panel de supervisión**, no el lugar donde se construyen sitios. Los sitios se construyen
desde la carpeta de cada cliente con el `AGENTS.md` que genera el panel (botón "AGENTS.md" en cada proyecto).

## Qué es
Laravel 13 + SQLite, PHP 8.3, sin build de front (CSS plano en `public/css/panel.css`, Blade en
`resources/views`). Corre en WSL Ubuntu: `php artisan serve` → http://127.0.0.1:8090. Uso local, sin login.

La consola (el agente: Claude Code, Codex, Cursor…) escribe; el panel supervisa, aprueba y administra:
- `registro:add`, `registro:cola`, `registro:paginas`, `registro:tokens`, `registro:figma`, `guia:prompt`,
  `conexion:comprobar`, `jev:sugerencias` (`app/Console/Commands`).
- Controladores: `PanelController` (inicio, proyecto, registro), `FlujoController` (página, plan, aprobación,
  QA), `GestionController` (catálogo, eliminar proyecto), `ConexionController`, `GuiaController`, `EstadoController`
  (huella para la recarga en vivo).
- Lógica de apoyo en `app/Support`: `EntornoWP` (versiones de WP/Elementor/JetEngine en paralelo),
  `PromptElementor` (sanea el prompt de Elementor), `PromptGuia` (guía, prompt y AGENTS.md del cliente).

## Reglas al cambiar código
- Pruebas: `php artisan test` debe quedar en verde; agrega pruebas para cada función nueva (`tests/Feature`).
- Diseño: seguir `DESIGN.md` (tokens OKLCH, tema oscuro y claro, naranja solo para acción). Contexto de producto en `PRODUCT.md`.
- Seguridad: nunca guardar contraseñas de aplicación ni tokens en la base, en archivos versionados ni en logs
  (ver `SEGURIDAD.md`). El prompt de Elementor se guarda saneado.
- Commits: solo título, sin descripción.
- Textos de la interfaz en español, concisos y sin nombres de personas reales; datos de ejemplo ficticios (Arequipa).
- No instalar paquetes nuevos (Laravel Boost incluido) sin que el usuario lo pida.
