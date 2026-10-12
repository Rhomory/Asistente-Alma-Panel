---
name: alma-figma
description: Lee un archivo de Figma de un proyecto de Alma y carga en el panel Asistente Alma sus páginas, secciones (con su ID de Figma) y tokens como una versión del diseño (diseno:guardar). Úsala siempre que abras o revises un archivo de Figma de un proyecto, o cuando pidan "leer el Figma", "detectar páginas", "cargar tokens" o preparar un proyecto para construir.
---

# Puente Figma → panel Asistente Alma

Cada lectura del Figma se guarda en el panel como **memoria del diseño**: páginas, secciones y el ID de Figma
de cada una. Al construir, el agente va directo a cada ID (con `guia:prompt`) en vez de volver a recorrer el
archivo. El panel abierto en el navegador se recarga solo.

## Antes de empezar

- **Un solo proyecto.** Trabaja solo en el proyecto indicado en el `AGENTS.md` de esta carpeta. Usa su nombre
  exacto en cada comando; no mires ni cargues datos de otros proyectos.
- **Puente al panel.** Desde Windows, los comandos del panel van por
  `powershell -NoProfile -ExecutionPolicy Bypass -File "$HOME\.alma\alma.ps1" <comando>` (en Ubuntu, dentro del panel: `php artisan <comando>`).
  Si falla, ejecuta `alma.ps1 diagnostico`, muestra el resultado y detente: no inventes rutas ni otros caminos.
- **figwright.** Llama a `ping`: si `plugin` es `null`, pide abrir Figma de escritorio y ejecutar
  Plugins › Development › Figwright. Si hay varios archivos abiertos, `list_files` y luego `use_file` con el del proyecto.

## Leer el diseño (pocos tokens)

1. `get_pages` → lista de páginas de Figma (`id`, `name`).
2. **Marcos y secciones en una sola llamada (lo más liviano):** pide al desarrollador que en Figma abra la página
   con el diseño del sitio y pulse **Ctrl+A** (selecciona solo los marcos de primer nivel, no lo de adentro).
   Luego `get_design_context` **sin `nodeId`** (usa la selección), con `depth: 1` y `detail: "minimal"`.
   Cada marco es una página del sitio (Inicio, Nosotros…) y sus hijos directos son sus **secciones**
   (Hero, Servicios…): guarda nombre e `id` de cada uno. Repite por cada página de Figma con diseño del sitio.
   - Si el desarrollador no está, o el archivo es pequeño: `scan_nodes_by_types` con `root: <id de la página>` y
     `types: ["FRAME", "SECTION"]`, y quédate con los nodos cuyo `parent.id` es la página. Ojo: es recursivo y en
     archivos grandes devuelve miles de nodos.
3. Tokens: `get_variable_defs` (variables con sus modos) y `get_styles`. Asigna el rol de cada color:
   primario, secundario, fondo, texto, acento.

**Errores conocidos, no los repitas:**
- `get_design_context` y `get_nodes_info` **no aceptan IDs de página** ("is a PAGE, not a frame/layer"): pásales marcos.
- **Nunca** uses `get_document` ni `get_node` para el inventario: serializan el árbol completo sin límite.
- Nada de `get_screenshot` en esta etapa.

## Cargar en el panel

Escribe el JSON en `Design\lectura-borrador.json` dentro de la carpeta del proyecto (fuera de ella el agente pide
permiso) y pásalo con `--archivo=`. Nunca lo mandes en línea: las comillas entre PowerShell y WSL se rompen.
Usa estas claves en español tal cual; si el comando avisa que omitió algo, corrígelo y vuelve a guardar.

```json
{
  "figma": "https://www.figma.com/design/<id>/<nombre>",
  "paginas": [
    { "nombre": "Inicio", "figma_id": "12:3", "url": "/",
      "secciones": [ { "nombre": "Hero", "figma_id": "12:4" }, { "nombre": "Servicios", "figma_id": "12:9" } ] },
    { "nombre": "Nosotros", "figma_id": "15:1", "url": "/nosotros",
      "secciones": [ { "nombre": "Historia", "figma_id": "15:2" } ] }
  ],
  "tokens": [
    { "tipo": "color", "valor": "#2F6B4F", "nota": "primario" },
    { "tipo": "tipografia", "valor": "Poppins", "nota": "títulos" },
    { "tipo": "espaciado", "valor": "16 px", "nota": "base" }
  ]
}
```

Guárdalo como **nueva versión del diseño**:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "$HOME\.alma\alma.ps1" diseno:guardar "<Proyecto>" --archivo=Design\lectura-borrador.json
```

- Crea `Design\vN\` en la carpeta del proyecto (`lectura.json` + `capturas\`) y te dice la ruta de `capturas`.
- La primera versión queda en uso al instante. Las siguientes quedan como "nuevas": el panel no cambia hasta que
  el desarrollador pulse "Usar esta versión". Sigue trabajando con la versión en uso.
- `url` es la ruta de la página en el sitio (`/` para Inicio). Si no la sabes, omítela: el panel la arma desde el nombre.
- Tipos de token: `color`, `tipografia`, `espaciado`, `otro`. Colores en hexadecimal.
- Si repites la misma lectura no se crea otra versión. La lectura guardada queda en `Design\vN\lectura.json`.

## Capturas de referencia

Con la ruta que imprimió `diseno:guardar`, exporta una captura de cada marco de página:
`save_screenshots` con `nodeIds: [<ids de los marcos de página>]`, `outDir: "<carpeta>\Design\vN\capturas"`, `scale: 1`.
Sirven al construir para comparar visualmente; las medidas y colores salen siempre de `get_design_context`.

## Al final

Avisa qué versión se guardó y cuántas páginas, secciones y tokens tiene. Recuerda que en el panel se decide con el
switch qué páginas se maquetan. Para construir, pide `guia:prompt "<Proyecto>" "<Página>"`: trae cada sección con su
ID de Figma y la carpeta de capturas de la versión en uso.

## Reglas

- Nunca pegues tokens de acceso de Figma ni contraseñas en el JSON, en mensajes ni en archivos.
- Usa el nombre exacto del proyecto. No crees variantes ("Cota v2", "cotav1", "Cota copia"): un Figma nuevo se guarda como `Design\vN` del mismo proyecto. `--forzar-variante` solo si el desarrollador lo confirma tras explicarle el riesgo.
- No inventes páginas, secciones ni colores: si un rol no es claro, usa `"nota": "sin rol"` y dilo.
