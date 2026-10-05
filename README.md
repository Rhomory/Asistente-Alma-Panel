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
- PHP 8.3 o superior con las extensiones `sqlite3`, `curl`, `mbstring` y `xml`.
- Composer 2 y Git.

**En el WordPress de cada cliente** (staging, nunca producción)
- WordPress 6.8 o superior.
- Elementor 4.3 o superior con **Elementor MCP** activado (Elementor › Elementor MCP).
- JetEngine (Crocoblock), solo si el sitio usa contenido dinámico.

## 2. MCP, skills y herramientas

| Herramienta | Para qué | Cómo se agrega | Alcance |
|---|---|---|---|
| **figwright** (MCP) | Leer páginas, variables y estructura del Figma | Claude Code: `claude mcp add figwright --scope user -- cmd /c npx -y @figwright/mcp@latest` (con `@latest` se actualiza solo al arrancar). Otros agentes: `npx -y @figwright/mcp@latest` en su configuración MCP | Todas las carpetas |
| **Elementor MCP** (oficial) | Construir en el sitio del cliente | En WordPress: Elementor › Elementor MCP › activar › elegir tu agente › Generate Prompt. Pega ese prompt en tu agente **dentro de la carpeta del cliente** | Solo esa carpeta (un servidor por sitio, ej. `elementor-ecocreations`) |
| **JetEngine MCP** (opcional) | Tipos de contenido y campos dinámicos | Según la documentación de Crocoblock, en la carpeta del cliente | Solo esa carpeta |
| **Framelink** (opcional) | Leer el Figma sin la app abierta, vía API REST | `claude mcp add framelink --scope user -e FIGMA_API_KEY=<token> -- cmd /c npx -y figma-developer-mcp --stdio` (útil solo con asiento Dev/Full) | Todas las carpetas |
| **Skill `alma-figma`** | Pasar lo leído del Figma al panel y hablar con el panel desde Windows (`alma.ps1`) | Copiarla a tu carpeta de skills (paso 3.4) | Todas las carpetas |
| **Skill `impeccable`** (opcional) | Solo para quien modifique el diseño del panel | — | — |

El MCP oficial de Figma (Dev Mode) puede quedar desactivado: con asientos View/Collab tiene un cupo de pocas llamadas al mes
y devuelve código React, que no sirve para Elementor.

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
```

**3.2 Encender el panel** (deja esta terminal abierta mientras trabajas):

```bash
cd ~/proyectos/asistente-alma-panel && php artisan serve
```

Abre http://127.0.0.1:8090 en Windows.

**3.3 Registrar figwright** (PowerShell, una vez): ver la tabla del paso 2.

**3.4 Instalar la skill** (PowerShell):

```powershell
$ubuntu = (wsl -d Ubuntu -- whoami).Trim()   # tu usuario de Ubuntu (ej. romino); las rutas de WSL distinguen mayúsculas
Copy-Item -Recurse -Force "\\wsl.localhost\Ubuntu\home\$ubuntu\proyectos\asistente-alma-panel\.claude\skills\alma-figma" "$HOME\.claude\skills\"
```

`alma.ps1` asume el panel en `/home/romino/proyectos/asistente-alma-panel`. Si en tu equipo está en otra ruta,
define la variable de entorno `ALMA_PANEL` con esa ruta de Ubuntu.

## 4. Uso con un proyecto nuevo

1. **Crea el proyecto en el panel** (Nuevo proyecto). Puedes pegar ahí el prompt de Elementor: el panel toma el
   sitio y el nombre del servidor, y guarda una copia **sin la contraseña**.
2. **Crea una carpeta para el cliente en Windows**, por ejemplo `C:\Users\<tú>\clientes\ecocreations`.
3. **Descarga su `AGENTS.md`** desde el panel (botón "AGENTS.md" en el proyecto) y guárdalo en esa carpeta.
   Es el formato abierto que leen los agentes de código: así el agente sabe qué proyecto es, cómo hablar con el panel y el flujo.
   - **Codex, Cursor y otros** leen `AGENTS.md` solos.
   - **Claude Code** lee `CLAUDE.md`: crea uno al lado con una sola línea, `@AGENTS.md`, que importa el otro archivo.
4. **Abre tu agente en esa carpeta** y pega el prompt de Elementor del sitio para registrar su servidor MCP
   (en Elementor elige tu agente al generar el prompt). Comprueba que aparezcan figwright y el servidor del sitio
   (en Claude Code: `claude mcp list`).
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
`powershell -NoProfile -File "$HOME\.claude\skills\alma-figma\alma.ps1" …`.

| Comando | Para qué |
|---|---|
| `guia:prompt "<Proyecto>" "<Página>"` | Guía actualizada: tokens activos, plan de secciones y reglas |
| `registro:figma "<Proyecto>" --archivo=figma.json` | Cargar páginas y tokens leídos del Figma |
| `registro:add "<Proyecto>" "<Página>" "<Sección>" <min> --asistente=<min> --dev=<min>` | Registrar una sección construida |
| `registro:cola` | Correcciones pendientes |
| `conexion:comprobar` | Versiones de WordPress, Elementor y JetEngine de cada sitio |
| `jev:sugerencias` | Revisión automática con Jev (necesita `OPENROUTER_API_KEY` en `.env`) |

## 6. Problemas frecuentes

- **figwright no conecta:** abre Figma de escritorio y ejecuta el plugin figwright; luego reinicia tu agente.
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
