# Sistema de diseño — Panel Asistente Alma

Heredado del mockup Figma del Proyecto de Mejora (identidad ya comprometida; se preserva).

## Tokens (public/css/panel.css `:root`)
- Fondo: `--cream #FBF7EC` · panel lateral: `--panel #F3EDDF`
- Tinta: `--ink #171717` · secundario: `--gray #5F5F5F` · líneas: `--line #E2DCCC`
- Acento (acciones/actual): `--ora #F2915F`, oscuro `--orad #C9622B`
- Secundario de datos: `--lav #DCD2F0`, oscuro `--lavd #6B5CA8`
- Neutro de datos: `--grow #ECECEC` · éxito: `--ok #2F7D32` / `--okbg #DFF2DC`

## Tipografía
Una sola familia: Poppins (fallback Segoe UI). Escala fija rem, cuerpo 14px,
títulos h1 22px / h3 14.5px. Datos en tablas 13px.

## Vocabulario de componentes
- KPI cards (lavanda / naranja / gris) — mismas tres del mockup Pantalla 5.
- Tablas: encabezado tinta con texto blanco, cebra suave `#FAF6EC`.
- Tags de estado: `asistente` (durazno), `mixto` (lavanda), `desarrollador` (gris),
  estados de página `aprobada/construyendo/pendiente`.
- Botones: `.btn.p` naranja (primario), `.btn.s` borde tinta (secundario).
- Gráficos (Chart.js): naranja = trabajo real/asistente, lavanda = desarrollador,
  gris cálido = línea base; sin animación (los datos llegan por recarga).

## Reglas
- El naranja señala acción/actual, nunca decoración.
- Motion: transiciones 150 ms ease-out solo en estados; respetar prefers-reduced-motion.
- Texto sobre tarjetas de color: nunca gris — tinta o blanco con transparencia.
