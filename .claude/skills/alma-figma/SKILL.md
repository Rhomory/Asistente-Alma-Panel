---
name: alma-figma
description: Lee un archivo de Figma de un proyecto de Alma y carga sus páginas y tokens en el panel Asistente Alma con registro:figma. Úsala siempre que abras o revises un archivo de Figma de un proyecto, o cuando el usuario pida "leer el Figma", "detectar páginas", "cargar tokens" o preparar un proyecto para construir.
---

# Puente Figma → panel Asistente Alma

Cada vez que leas un archivo de Figma de un proyecto, deja lo leído en el panel. El panel abierto en el
navegador se recarga solo y muestra las páginas y tokens nuevos.

## Dónde corre el panel

El panel es Laravel en WSL Ubuntu (`/home/romino/proyectos/asistente-alma-panel`). Hay dos modos:

- **Desde Windows** (lo habitual: Claude Code en la carpeta del cliente, con figwright y el MCP de Elementor):
  usa el puente `alma.ps1` que está junto a esta skill.
  ```powershell
  powershell -NoProfile -File "$HOME\.claude\skills\alma-figma\alma.ps1" registro:figma "<Proyecto>" --archivo=C:\ruta\figma.json
  ```
  Sirve para cualquier comando del panel: `registro:cola`, `registro:add`, `conexion:comprobar`…
- **Dentro del repo en Ubuntu**: `php artisan registro:figma "<Proyecto>" --archivo=ruta.json`.

## Pasos

1. **Proyecto.** El nombre debe coincidir con el del panel. Si dudas, lista los proyectos:
   `alma.ps1 tinker --execute="echo App\Models\Proyecto::pluck('nombre')"`.
2. **Leer el Figma gastando pocos tokens** (con figwright; requiere Figma desktop abierto con el plugin). Pide solo lo necesario, en este orden, y no repitas lecturas:
   - Páginas: `get_pages`. Luego, por cada página, `get_design_context` con `nodeId` de la página, `depth: 1` y `detail: "minimal"` (solo id, nombre y tipo). Los marcos de primer nivel son las secciones.
   - Tokens: `get_variable_defs` (variables locales con sus modos) y `get_styles`. Asigna el rol de cada color: primario, secundario, fondo, texto, acento.
   - **Nunca** uses `get_document` ni `get_node` para inventario: serializan el subárbol completo, sin límite ni deduplicación. Nada de `get_screenshot` en esta etapa.
   - Al construir, una sección a la vez: `get_design_context` con `detail: "full"` (deja `dedupeComponents` activo). Si devuelve un `sectionPlan`, pide cada subsección por separado.
3. **Escribir el JSON** en un archivo temporal (en Windows: `%TEMP%\alma-figma.json`) con este formato:
   ```json
   {
     "figma": "https://www.figma.com/design/<id>/<nombre>",
     "paginas": [{"nombre": "Inicio", "secciones": 7}, {"nombre": "Contacto", "secciones": 3}],
     "tokens": [
       {"tipo": "color", "valor": "#2F6B4F", "nota": "primario"},
       {"tipo": "tipografia", "valor": "Poppins", "nota": "títulos"},
       {"tipo": "espaciado", "valor": "16 px", "nota": "base"}
     ]
   }
   ```
   Tipos válidos: `color`, `tipografia`, `espaciado`, `otro`. Colores en hexadecimal.
4. **Cargar:** ejecuta `registro:figma` con `--archivo=` (modo Windows o Ubuntu, ver arriba). El comando no duplica páginas ni tokens, así que repetirlo es seguro.
5. **Avisar** cuántas páginas y tokens se cargaron, y recordar que en el panel se decide con el switch qué páginas se maquetan.

## Reglas

- Nunca pegues tokens de acceso de Figma en el JSON, en mensajes ni en archivos; solo el enlace del archivo.
- No inventes páginas ni colores: si un rol no es claro, usa `"nota": "sin rol"` y dilo.
- Borra el JSON temporal al terminar.
