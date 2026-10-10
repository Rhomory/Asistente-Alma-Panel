# Auditoría del flujo: arranque de proyecto, instalación y Figma

Octubre 2026. Revisa tres cosas: cómo empieza un proyecto, qué tan fácil es clonar e instalar el panel y cómo
conviene conectar y ordenar el Figma para que construir sea rápido. Cada punto dice qué pasa hoy, por qué
importa y qué hacer. Lo marcado como **hecho** ya está en el repo.

## Resumen

| # | Hallazgo | Impacto | Estado |
|---|---|---|---|
| 1 | El `AGENTS.md` generado llevaba la ruta `Design\vN` rota (carácter de control invisible) | Alto: el agente leía mal la carpeta del diseño | **Hecho** (con prueba) |
| 2 | El formulario pedía pegar el prompt de Elementor en el agente *y* en el panel | Medio: paso repetido y confuso | **Hecho** |
| 3 | URLs de proyecto con número (`/proyectos/1`) | Bajo | **Hecho**: `/proyectos/sitio-bodega-andina` |
| 4 | figwright se registra con `@latest`, pero el plugin de Figma se actualiza a mano | Alto: si sale una versión nueva, servidor y plugin quedan desfasados y la conexión falla sin aviso claro | Propuesto |
| 5 | Primer arranque del agente: aprobar `.mcp.json` (Claude Code) y marcar la carpeta de confianza (Codex) a mano | Medio | Propuesto |
| 6 | No hay una vista de "qué falta para empezar" en el proyecto | Medio | Propuesto |
| 7 | Instalar el panel son ~10 comandos en dos sistemas | Medio | Propuesto |
| 8 | `composer setup` ejecuta `npm install` y `npm run build`, que el panel no usa | Medio: falla en una Ubuntu sin Node | Propuesto |
| 9 | Al construir, cada sección se vuelve a pedir a Figma aunque ya se haya leído | Medio: tokens y tiempo en correcciones | Propuesto |
| 10 | La calidad del Figma (auto layout, variables, nombres) decide cuánto tarda construir | Alto | Guía abajo |

## 1. Arranque de un proyecto

**Hoy:** crear proyecto → pegar prompt de Elementor → el panel crea la carpeta, la configuración de los cuatro
agentes y la credencial → "Abrir terminal" → aprobar MCP la primera vez → abrir el archivo en Figma → escribir
"Lee el Figma del proyecto y guárdalo en el panel" → revisar → construir.

**Mejoras propuestas, en orden:**

1. **Tarjeta "Para empezar" en el proyecto.** Una lista con marca automática: carpeta creada, credencial en
   Windows, sitio responde (`conexion:comprobar` al guardar), Figma leído (hay `Design\v1`), primera página
   construida. Cada paso pendiente con su botón o el mensaje exacto para el agente. Quita la duda de "¿y ahora qué?".
2. **Aprobación automática del primer arranque.**
   - Claude Code: escribir `.claude/settings.local.json` con `enableAllProjectMcpServers: true` en la carpeta del
     proyecto, para no aprobar `.mcp.json` a mano.
   - Codex: el puente puede añadir la carpeta como confiable en `~/.codex/config.toml` (`trust_level = "trusted"`).
   - Verificar ambas claves en la documentación vigente de cada agente antes de implementarlo.
3. **Archivo de Figma por nombre.** Guardar el nombre del archivo (sale del enlace de Figma) y ponerlo en el
   `AGENTS.md`, para que el agente haga `use_file` con ese nombre y no lea otro archivo abierto por error.
4. **Primer mensaje listo para copiar.** Un botón "Copiar primer mensaje" en el proyecto con el texto exacto
   ("Comprueba la conexión, lee el Figma y guárdalo en el panel"). Menos variación entre personas.

## 2. Clonar e instalar

**Hoy:** instalar extensiones de PHP, clonar, `composer install`, copiar `.env`, `key:generate`, crear la base,
migrar, probar, encender el servidor y luego ejecutar el instalador de Windows con una ruta UNC larga.

**Propuesta: un solo comando por lado.**

1. `bash scripts/instalar.sh` en Ubuntu: revisa PHP y extensiones (y dice el `apt install` exacto si falta algo),
   instala dependencias, crea `.env`, clave y base, migra, corre las pruebas y, al final, llama al instalador de
   Windows desde WSL (`powershell.exe -File …`), que ya deduce las rutas solo. Resultado: clonar + un comando.
2. Arreglar `composer setup`: quitar `npm install` y `npm run build` (el panel usa `public/css/panel.css`
   directo, sin Vite). Así sirve como alternativa en una Ubuntu sin Node.
3. Encender el panel sin recordar la ruta: `alma.ps1 panel` (abre el servidor en WSL y el navegador), o un
   acceso directo que cree el instalador.
4. El empaquetado como kit `npx` queda como idea para después.

## 3. Conexión con Figma

**Cómo debe quedar:**

- **Una versión fija de figwright** en servidor y plugin. Cambiar `@figwright/mcp@latest` por la versión del
  plugin instalado (hoy 0.6.0) en la configuración que genera el panel y en el instalador, y actualizar los dos
  juntos. `ping` ya informa el desfase (`versionSkew`): el diagnóstico debería mostrarlo en rojo.
- **Figma de escritorio con el plugin abierto** en el archivo del proyecto. Si hay varios archivos abiertos, el
  agente debe hacer `use_file` con el nombre exacto (punto 1.3).
- **Lectura en dos niveles, como ya hace la skill:** inventario barato (marcos de cada página con
  `detail: "minimal"`) al leer, y detalle completo (`detail: "full"`) solo de la sección que se construye.
- **Framelink** (API REST) solo si el equipo tiene asiento Dev o Full; con asientos de visor el cupo no alcanza.

## 4. Qué Figma construye más rápido

El tiempo de construcción depende sobre todo del archivo. Un Figma ordenado se traduce casi uno a uno a
Elementor; uno desordenado obliga al agente a adivinar medidas y a corregir. Pedir al diseñador:

| Regla en Figma | Por qué acelera |
|---|---|
| Una página de Figma (o un marco de primer nivel) por página del sitio, con el **mismo nombre** que tendrá en WordPress | El inventario sale directo y las URLs se arman solas |
| Cada sección como **hijo directo** del marco de página, con nombre claro ("Hero", "Servicios", "Testimonios") | El panel guarda su ID y al construir se va directo, sin recorrer el árbol |
| **Auto layout** en todo (filas, columnas, espaciados) | Se traduce a contenedores flex de Elementor con su gap y padding; sin él hay que deducir posiciones |
| Colores y tipografías como **variables y estilos** (primario, secundario, texto, fondo; títulos y cuerpo) | Pasan a los colores y fuentes globales de Elementor una sola vez; nada de valores sueltos |
| Elementos repetidos (tarjetas, botones) como **componentes** | `get_design_context` resume las instancias repetidas: menos lectura y un solo patrón de widget |
| Versión **escritorio y móvil** del mismo marco, con nombre "Inicio — Escritorio" / "Inicio — Móvil" | El responsive sale del diseño y no de suposiciones |
| Sin grupos anidados ni capas ocultas sobrantes; imágenes reales en los rellenos | Árbol más corto y exportación limpia de imágenes |

**Qué archivo guarda el panel y qué falta:**

- Hoy, por versión: `Design\vN\lectura.json` (páginas, secciones con su ID y tokens) y `capturas\` (una imagen
  por página). Es lo correcto para planificar y es liviano.
- **Propuesta:** al construir una sección por primera vez, guardar su detalle de Figma en
  `Design\vN\secciones\<id>.json`. En una corrección o reintento el agente lee ese archivo en vez de volver a
  pedirlo a Figma, y si el Figma cambia, la versión nueva tendrá su propia carpeta.
- **Propuesta:** un `tokens-elementor.json` con el mapeo token → color o fuente global de Elementor, para
  aplicar el kit del sitio una vez al inicio y que cada sección solo use referencias.

## 5. Siguientes pasos sugeridos

1. Fijar la versión de figwright y mostrar el desfase en el diagnóstico (punto 4 del resumen).
2. Tarjeta "Para empezar" y primer mensaje copiable en el proyecto.
3. `scripts/instalar.sh` y `composer setup` sin npm.
4. Caché de secciones (`Design\vN\secciones\`) y mapeo de tokens a Elementor.
5. Compartir la tabla de la sección 4 con diseño como requisito del Figma que se entrega.
