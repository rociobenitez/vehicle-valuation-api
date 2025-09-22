# Documento resumen plantilla de página de tasación

**Nombre del archivo:** `page-tasacion.php`

**Qué hace (resumen):**
Plantilla WordPress que muestra el flujo del tasador online: guía al usuario por pasos (marca → modelo → versión → año → puertas → kilómetros) y muestra/lanza el paso final con Gravity Forms (propietario, preguntas y confirmación). Integra llamadas a la API de Autobiz desde el frontend (JS) y rellena campos ocultos en GF con el resumen y el importe calculado (o mensaje descriptivo cuando no hay cotización).

**Tecnologías y dependencias:**

- WordPress (plantilla de página).
- ACF Pro (campos: `form` (ID del GF), `text_bottom`, opcionalmente `step`).
- Gravity Forms (formulario embebido por shortcode con `ajax="true"`).
- JavaScript personalizado (archivo externo) que gestiona selects, carga de datos por AJAX y lógica de tasación.

**IDs y campos clave:**

- Selects y paneles: `brand-select`, `model-select`, `version-select`, `year-select`, `doors-select`, `mileage`, `licensePlate`.
- Botones de navegación: `next-1`, `next-2`, `next-3`, `next-4`, `next-5`, `next-6`, `prev-step-2`...`prev-step-7`, `btn-starter`.
- Contenedores/pasos: `step-1` ... `step-7`.
- Gravity Forms (campos ocultos que el JS usa): `input_5_13` (resumen de datos), `input_5_14` (importe base o mensaje), `input_5_29` (importe final o mensaje).
  _(Si cambias los IDs de GF, actualiza también la constante GF_IDS en tu JS.)_

**Accesibilidad y mejoras aplicadas:**

- Labels accesibles para selects/inputs (visualmente ocultos para no romper diseño).
- `aria-describedby` y `role="alert"`/`aria-live` en mensajes que cambian dinámicamente.
- `<noscript>` con advertencia para usuarios sin JS.
- `aria-controls` en botones Next/Prev para indicar la relación con el panel objetivo.
- `role="list"` y `role="listitem"` en el stepper.

**Recomendaciones / siguientes pasos:**

1. **Actualizar el JS para manipular `aria-current`**: cuando cambie el step visible, también actualizar `aria-current` (poner `"step"` en el actual y removerlo en los demás) para mejorar aún más la experiencia con AT.
2. **i18n de mensajes dinámicos**: si la API devuelve mensajes en castellano o en otro idioma, considera internacionalizarlos o mapearlos para consistencia.
3. **Validación en servidor (GF hooks)**: además de la validación cliente, valida en servidor que el importe final sea numérico cuando sea necesario, o documenta que puede llevar un mensaje.
