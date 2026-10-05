# Asistente Alma

Asistente para construir páginas WordPress/Elementor a partir de diseños de Figma, con supervisión humana.
Proyecto de Mejora de Alma Industria Creativa E.I.R.L. (Arequipa).

Tiene dos piezas que trabajan juntas:

| Pieza | Dónde corre | Qué hace |
|---|---|---|
| **Asistente** (agente de código + MCP) | Windows, en la carpeta de cada cliente | Lee el Figma, construye en Elementor sección por sección (en borrador) y registra cada sección |
| **Panel** (este repo, Laravel) | WSL Ubuntu, http://127.0.0.1:8090 | Muestra el avance en vivo, aprueba o pide correcciones, guarda tokens, conexiones y tiempos, arma el mensaje de QA |

Nada se publica solo: el asistente trabaja en borrador y cada sección se aprueba en el panel.

---

## 1. Requisitos

**En Windows**
- Windows 10/11 con WSL 2 y Ubuntu.
- Un agente de código compatible con MCP: Claude Code (con el que se probó todo el flujo), Codex, Cursor u otro.
- Node.js 20.19 o superior (lo usan los MCP que se instalan con `npx`).
- Figma de escritorio con el plugin **figwright**. No está en la Community: se descarga el zip de la última versión en [GitHub Releases](https://github.com/awdr74100/figwright/releases) y se importa en Figma (Plugins › Development › Import plugin from manifest). **El plugin no se actualiza solo:** cuando salga una versión nueva, reemplaza los archivos en la misma carpeta y reinicia Figma; su pestaña Debug muestra si coincide con el servidor.
- Chrome o Edge reciente (el panel usa transiciones entre páginas; en otros navegadores funciona sin animación).

**En WSL Ubuntu**
- PHP 8.3 o superior con sus extensiones, Composer 2, Git y `unzip`. En una Ubuntu nueva faltan casi todas; instálalas
  con el prefijo de tu versión de PHP (`php -v`), por ejemplo para 8.3:

  ```bash
  sudo apt install php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-curl php8.3-xml php8.3-zip unzip git
  ```

  Sin `xml`/`zip`/`unzip` falla `composer install`; sin `sqlite3` falla la base de datos; sin `mbstring` y `curl` fallan
  las pruebas y la comprobación de sitios. Tras instalar una extensión, reinicia `php artisan serve`.

**En el WordPress de cada cliente** (staging, nunca producción)
- WordPress 6.8 o superior.
- Elementor 4.3 o superior con **Elementor MCP** activado (Elementor › Elementor MCP).
- JetEngine (Crocoblock), solo si el sitio usa contenido dinámico.

## 2. MCP, skills y herramientas

| Herramienta | Para qué | Cómo se agrega | Alcance |
|---|---|---|---|
| **figwright** (MCP) | Leer páginas, variables y estructura del Figma | Registro por agente en la sección 2.1 (con `@latest` se actualiza solo al arrancar) | Todas las carpetas |
| **Elementor MCP** (oficial) | Construir en el sitio del cliente | En WordPress: Elementor › Elementor MCP › activar › elegir tu agente › Generate Prompt. Registro por agente en la sección 2.1 | Un servidor por sitio, con el nombre del proyecto (ej. `elementor-cota`) |
| **JetEngine MCP** (opcional) | Tipos de contenido y campos dinámicos | Según la documentación de Crocoblock, en la carpeta del cliente | Solo esa carpeta |
| **Framelink** (opcional) | Leer el Figma sin la app abierta, vía API REST | `claude mcp add framelink --scope user -e FIGMA_API_KEY=<token> -- cmd /c npx -y figma-developer-mcp --stdio` (útil solo con asiento Dev/Full) | Todas las carpetas |
| **Skill `alma-figma`** y puente `alma.ps1` | Pasar lo leído del Figma al panel y hablar con el panel desde Windows | `scripts\instalar-windows.ps1` (paso 3.3) | Todas las carpetas y agentes |
| **Skill `impeccable`** (opcional) | Solo para quien modifique el diseño del panel | — | — |

El MCP oficial de Figma (Dev Mode) puede quedar desactivado: con asientos View/Collab tiene un cupo de pocas llamadas al mes
y devuelve código React, que no sirve para Elementor.

### 2.1 Registrar los MCP según tu agente

Cada agente tiene su propio formato: copiar el de otro es la causa más común de que "no aparezcan las herramientas".
El panel también genera estos bloques ya rellenos para cada proyecto (Conexión › "Configurar en tu agente").

**Credenciales del sitio.** La contraseña de aplicación nunca va en el panel ni en archivos del repo. Guárdala como
variable de entorno de tu usuario de Windows, con el encabezado completo, y que el agente la lea de ahí:

```powershell
# "usuario:contraseña de aplicación" en Base64 (los datos salen del prompt que genera Elementor)
$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('usuario:xxxx xxxx xxxx xxxx xxxx xxxx'))
[Environment]::SetEnvironmentVariable('ALMA_COTA_AUTH', "Basic $b64", 'User')   # reinicia el agente después
```

**Claude Code** (`~/.claude.json`, por comandos):

```powershell
claude mcp add figwright --scope user -- cmd /c npx -y '@figwright/mcp@latest'
# Elementor: pega el prompt que generó Elementor dentro de la carpeta del cliente (lo registra solo). Comprueba con:
claude mcp list
```

**Codex** (`~/.codex/config.toml`):

```toml
[mcp_servers.figwright]
command = "cmd"
args = ["/c", "npx", "-y", "@figwright/mcp@latest"]

[mcp_servers.elementor-cota]                  # uno por sitio, con el nombre que muestra el panel
url = "http://localhost:8883/wp-json/…"       # el endpoint del prompt de Elementor
env_http_headers = { "Authorization" = "ALMA_COTA_AUTH" }   # nombre de la variable, no el secreto
```

Comprueba con `codex mcp list`. Si un sitio usa el servidor de otro proyecto (por ejemplo `picnicplus-elementor`
apuntando a otro puerto), el agente no verá el del proyecto actual: cada sitio necesita su propia entrada.

**OpenCode** (`~/.config/opencode/opencode.json` o `opencode.json` en la carpeta del cliente):

```json
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "figwright": { "type": "local", "command": ["npx", "-y", "@figwright/mcp@latest"] },
    "elementor-cota": {
      "type": "remote",
      "url": "http://localhost:8883/wp-json/…",
      "headers": { "Authorization": "{env:ALMA_COTA_AUTH}" }
    }
  }
}
```

En OpenCode `command` es una lista y lleva `"type"`; el formato `command` + `args` de Claude no funciona ahí.

**No inicies figwright a mano** en otra consola: lo arranca el agente al abrirse. Varias sesiones pueden compartirlo
(una hace de principal y las demás de seguidoras).

## 3. Instalación (una sola vez por equipo)

**3.1 Clonar y preparar el panel** (terminal de Ubuntu):

```bash
mkdir -p ~/proyectos && cd ~/proyectos
git clone https://github.com/Rhomory/Asistente-Alma-Panel.git asistente-alma-panel
cd asistente-alma-panel
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan test          # debe salir todo en verde
# opcional, solo para la demo del documento SENATI: php artisan db:seed --class=DemoSeeder
```

**3.2 Encender el panel** (deja esta terminal abierta mientras trabajas):

```bash
cd ~/proyectos/asistente-alma-panel && php artisan serve
```

Abre http://127.0.0.1:8090 en Windows. El puerto sale de `SERVER_PORT` en `.env`; si ves "running on 8000",
tu `.env` es anterior: agrega `SERVER_PORT=8090` o usa `php artisan serve --port=8090`.

**3.3 Instalar el lado Windows** (PowerShell, una vez por equipo y otra si mueves el panel):

```powershell
powershell -ExecutionPolicy Bypass -File "\\wsl.localhost\Ubuntu\home\<usuario>\<ruta>\asistente-alma-panel\scripts\instalar-windows.ps1" -RegistrarFigwright
```

Usa la ruta real de tu panel en Ubuntu (las rutas de WSL distinguen mayúsculas). El instalador:
- deduce la distro y la ruta del panel desde dónde está el script, sin rutas fijas;
- instala el puente en `%USERPROFILE%\.alma\alma.ps1` con su `alma.config.json`;
- copia la skill a `~\.claude\skills\alma-figma` (Claude Code) y `~\.agents\skills\alma-figma` (Codex y OpenCode);
- con `-RegistrarFigwright`, registra figwright en Claude Code y Codex si falta;
- termina con un diagnóstico de todo.

Para revisar el entorno en cualquier momento: `powershell -NoProfile -File "$HOME\.alma\alma.ps1" diagnostico`.
La ruta del panel también se puede forzar con la variable de entorno `ALMA_PANEL` (y `ALMA_DISTRO`).

## 4. Uso con un proyecto nuevo

1. **Crea el proyecto en el panel** (Nuevo proyecto). Puedes pegar ahí el prompt de Elementor: el panel toma el
   sitio y el nombre del servidor, y guarda una copia **sin la contraseña**.
2. **Crea una carpeta para el cliente en Windows**, por ejemplo `C:\Users\<tú>\clientes\ecocreations`.
3. **Descarga su `AGENTS.md`** desde el panel (botón "AGENTS.md" en el proyecto) y guárdalo en esa carpeta.
   Es el formato abierto que leen los agentes de código: así el agente sabe qué proyecto es, cómo hablar con el panel y el flujo.
   - **Codex, Cursor y otros** leen `AGENTS.md` solos.
   - **Claude Code** lee `CLAUDE.md`: crea uno al lado con una sola línea, `@AGENTS.md`, que importa el otro archivo.
4. **Registra el servidor MCP de Elementor de ese sitio** en tu agente, con el nombre que muestra el panel
   (sección 2.1). Comprueba que aparezcan figwright y ese servidor (`claude mcp list`, `codex mcp list`…).
5. **Abre el archivo en Figma de escritorio** con el plugin figwright corriendo.
6. **Primer mensaje**, por ejemplo:
   > Lee el Figma del proyecto y cárgalo en el panel.

   Después, para construir:
   > Construye la página Inicio siguiendo la guía.

Mientras el asistente trabaja, deja el panel abierto: se recarga solo con cada sección registrada,
y ahí apruebas, pides correcciones y, al final, copias el mensaje de QA para el canal.

En un chat nuevo de la misma carpeta no hace falta repetir nada: el `AGENTS.md` vuelve a cargarse. Basta con decir
qué toca hoy (por ejemplo, "revisa la cola de correcciones y sigue con Nosotros").

## 5. Comandos del panel

En Ubuntu se usan con `php artisan …`; desde Windows, con
`powershell -NoProfile -File "$HOME\.alma\alma.ps1" …`. Pasa los JSON siempre con `--archivo=`, nunca en línea.

| Comando | Para qué |
|---|---|
| `guia:prompt "<Proyecto>" "<Página>"` | Guía actualizada: tokens, plan de secciones con su ID de Figma y reglas |
| `registro:figma "<Proyecto>" --archivo=figma.json` | Cargar páginas, secciones (con ID de Figma y URL) y tokens |
| `registro:add "<Proyecto>" "<Página>" "<Sección>" <min> --asistente=<min> --dev=<min>` | Registrar una sección construida |
| `registro:cola "<Proyecto>"` | Correcciones pendientes de ese proyecto |
| `conexion:comprobar` | Versiones de WordPress, Elementor y JetEngine de cada sitio |
| `jev:sugerencias` | Revisión automática con Jev (necesita `OPENROUTER_API_KEY` en `.env`) |

## 6. Problemas frecuentes

- **Primero, siempre:** `alma.ps1 diagnostico`. Revisa distro, ruta del panel, extensiones de PHP, web del panel,
  figwright y skills, y dice qué hacer con cada fallo.
- **figwright no conecta:** abre Figma de escritorio y ejecuta el plugin figwright; luego reinicia tu agente.
  No lances `npx @figwright/mcp` a mano: si su proceso se cae, reinicia el agente.
- **Las herramientas de figwright no aparecen en el agente:** está mal registrado para ese agente; revisa el formato en la sección 2.1.
- **"is a PAGE, not a frame/layer":** `get_design_context` no acepta páginas. La skill actualizada lee primero los marcos
  de cada página (`scan_nodes_by_types` con la página como raíz) y luego sus secciones.
- **El agente no encuentra la skill:** debe estar en una carpeta propia (`skills\alma-figma\SKILL.md`), no suelta en
  `skills\`. Vuelve a ejecutar el instalador, que lo corrige.
- **Las secciones salen "0 de 0" tras leer el Figma:** se usó la skill anterior, que enviaba solo el número de secciones.
  Vuelve a leer el Figma con la skill actualizada: carga cada sección con su nombre y su ID.
- **Un sitio `localhost` sale "Sin respuesta":** el panel corre en WSL y prueba el host de Windows automáticamente.
  Si aun así falla, revisa que el servidor local esté encendido y que el firewall de Windows permita a WSL,
  o define `ALMA_HOST_WINDOWS=<ip>` en `.env`.
- **El asistente no ve el servidor de Elementor:** se registró en otra carpeta. Abre tu agente en la carpeta del cliente y vuelve a pegar el prompt.
- **El panel no cambia al registrar:** confirma que `php artisan serve` siga corriendo; la recarga en vivo consulta el panel cada 4 s.

## 7. Seguridad

- Las contraseñas de aplicación de WordPress y los tokens de Figma viven solo en la configuración MCP de tu agente.
  Nunca en el panel, en archivos del repo, en el `AGENTS.md` ni en registros. Detalle en `SEGURIDAD.md`.
- El panel es local (127.0.0.1) y no tiene inicio de sesión: no lo expongas a la red.

## 8. Documentación

- `AGENTS.md`: contexto para agentes que modifiquen el código del panel (`CLAUDE.md` solo lo importa, para Claude Code).
- `PRODUCT.md` y `DESIGN.md`: propósito del producto y sistema de diseño.
- `SEGURIDAD.md`: manejo de credenciales.
- `docs/GUIA-PLATAFORMA.pdf` y `docs/diagrama-flujo.png`: guía de uso y diagrama del flujo.
