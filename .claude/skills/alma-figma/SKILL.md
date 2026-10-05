---
name: alma-figma
description: Lee un archivo de Figma de un proyecto de Alma y carga sus páginas y tokens en el panel con registro:figma. Úsala siempre que abras o revises un archivo de Figma de un proyecto, o cuando el usuario pida "leer el Figma", "detectar páginas" o "cargar tokens".
---

# Puente Figma → panel Asistente Alma

Cada vez que leas un archivo de Figma de un proyecto, deja lo leído en el panel. El panel
abierto en el navegador se recarga solo y muestra las páginas y tokens nuevos.

## Pasos

1. Identifica el proyecto: el nombre debe coincidir con el del panel (`php artisan tinker --execute="App\Models\Proyecto::pluck('nombre')"` si dudas).
2. Lee el archivo con el MCP de Figma:
   - **Páginas:** cada marco de primer nivel que represente una página del sitio (Inicio, Nosotros…). Cuenta sus secciones (bloques horizontales de primer nivel dentro del marco).
   - **Tokens:** variables o estilos de color (con su rol: primario, secundario, fondo, texto, acento), tipografías (familia y uso: títulos, cuerpo) y la escala de espaciado.
3. Carga todo de una vez:

```bash
php artisan registro:figma "<Proyecto>" --json='{
  "figma": "https://www.figma.com/design/<id>/<nombre>",
  "paginas": [{"nombre": "Inicio", "secciones": 7}, {"nombre": "Contacto", "secciones": 3}],
  "tokens": [
    {"tipo": "color", "valor": "#2F6B4F", "nota": "primario"},
    {"tipo": "tipografia", "valor": "Poppins", "nota": "títulos"},
    {"tipo": "espaciado", "valor": "16 px", "nota": "base"}
  ]
}'
```

   Tipos válidos: `color`, `tipografia`, `espaciado`, `otro`. Los colores van en hexadecimal.
   Si el JSON es largo, guárdalo en un archivo temporal y usa `--archivo=ruta.json`.
4. Avisa al usuario cuántas páginas y tokens se cargaron y recuérdale que en el panel decide con el switch qué páginas se maquetan.

## Reglas

- No repitas a mano lo que ya existe: el comando no duplica páginas ni tokens.
- Nunca pegues tokens de acceso de Figma en el JSON ni en los mensajes; solo el enlace del archivo.
- No inventes páginas ni colores: si un rol no es claro, usa `"nota": "sin rol"` y avísalo.
