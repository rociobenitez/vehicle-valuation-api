# Documento resumen `main.js`

## Propósito general

**Script cliente** que guía el flujo de tasación de un vehículo en la interfaz:

- Selector **marca** → **modelo** → **versión** → **año** → **puertas** → **kilómetros**
- Obtiene la cotización desde la API de **Autobiz** mediante llamadas **AJAX**
- Integra el paso final con **Gravity Forms (GF)** rellenando campos ocultos con la información recopilada y la cotización
- Calcula una valoración final aplicando **reglas de negocio locales**

## Responsabilidades principales

### Interacción con DOM

- Gestiona todos los **select** del formulario y botones de navegación entre pasos (**mostrar/ocultar pasos**)
- Habilita/deshabilita botones **"Siguiente"** según la validez de la selección o input de km
- Maneja el **stepper visual** (barras de progreso)

### UX adaptativa para selects

- Detecta si el dispositivo es táctil (`isTouch`) y adapta el comportamiento:
  - En **móvil**: quita `size` y usa el control nativo
  - En **escritorio**: aplica `size` (máx. `6`) y clase `is-listbox`
- Autoselección si un select solo tiene una opción "real"
- Añade **placeholder** solo en touch para preservar usabilidad nativa

### Comunicación con backend (AJAX)

- `fetchData(action, dataPayload)` realiza llamadas **POST urlencoded** con `X-WP-Nonce`
- `loadToSelect(action, payload, selectId, placeholder)` normaliza la respuesta (acepta arrays o elemento único) y rellena selects con `{id, name}`
- Maneja errores con mensajes sencillos (`alert`) y logging en consola

### Reglas de negocio y validaciones

- `blockedBrands` — **marcas no tasables** (ej.: Tesla, Jaguar, Land Rover)
- `YEAR_MAX_AGE` — **antigüedad máxima** para ser elegible (`9` años)
- `VALUATION_MAX_KM` — **kilómetros máximos** (`140000`)
- Validación de año y mensaje si no es elegible (**oculta botón siguiente**)

### Integración con Gravity Forms

- Recoge datos seleccionados y los formatea (`formatFormData`) para rellenar un campo oculto en GF (`input_5_13`)
- Llama a la API `calcular_tasacion` con `make`, `model`, `version`, `doors`, `year`, `mileage`
- Inserta el **importe base** en `input_5_14` y el **importe final** en `input_5_29`
- Si la API no devuelve cotización, escribe un **mensaje descriptivo** en los campos GF en vez de `0`. `computeFinalValuation` detecta esto y lo preserva

### Cálculo final (lado cliente)

- Aplica **penalizaciones locales** (p.ej. Volvo `-500€`) y descuentos por estado/extras
- Evita que el **importe final** sea negativo
- Si la base es un **mensaje de error** (no numérico), no aplica cálculos y propaga el mensaje al campo final

## Puntos a tener en cuenta / decisiones técnicas

- **Centralización de IDs GF**: los campos de GF se agrupan en `GF_IDS` para facilitar mantenimiento
- **Robustez**: uso de optional chaining y chequeos defensivos (`if (!el) return`) para evitar errores si el DOM no contiene elementos
- **Compatibilidad**: se emplean features modernas ampliamente soportadas (`const`/`let`, arrow functions, optional chaining, nullish coalescing) en navegadores modernos (Chrome, Firefox, Edge, Safari recientes)
- **No se modifica la lógica de negocio original**: se conserva comportamiento UX y reglas tal cual, salvo la mejora para mostrar mensajes descriptivos cuando no hay cotización

## Extensiones recomendadas

- **Campo de estado en GF** para distinguir no-data (mensaje de error) de `0` válido
- **Logs remotos** (por ejemplo envío a Sentry) para monitorizar errores de `fetchData`
- **Tests E2E** que simulen la respuesta de la API y verifiquen que el mensaje descriptivo se propaga correctamente
- **i18n** para mensajes de error si la app debe soportar otros idiomas
