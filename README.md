# Vehicle Valuation Component

## Resumen técnico

Componente independiente que implementa la interfaz y la lógica cliente/servidor para un **tasador online de vehículos**. El repositorio contiene únicamente la parte del _theme_ implicada en el flujo de tasación (selección marca → modelo → versión → año → puertas → km → cálculo y envío) para poder demostrar la funcionalidad de forma aislada dentro de un entorno WordPress.

![Formulario de tasación - Paso 1](img/valuation-form-step-brands.png)

> Nota: el proyecto puede integrar (opcionalmente) una API externa de valoración de vehículos (p. ej. Autobiz) para obtener la cotización base. No se incluyen claves ni credenciales en el repositorio.

## Estado

- Código probado en entorno de desarrollo.
- Objetivo: demostración funcional y referencia técnica, no despliegue directo como producto final.

## Características principales

- Interfaz multi-step (marca → modelo → versión → año → puertas → km → formulario).
- Carga dinámica de selects mediante llamadas AJAX (Fetch API).
- Integración con Gravity Forms para la captura de datos del propietario y envío final.
- Reglas de negocio implementadas en cliente (filtros por marca, validación edad/km, penalizaciones).
- Manejo de casos sin cotización: mensajes descriptivos y trazabilidad en campos ocultos de GF.
- Accesibilidad básica: labels accesibles, `aria-live` para mensajes dinámicos.
- Código modular, con constantes para IDs de campos de GF y utilidades reutilizables.

## Estructura del repositorio

```plaintext
/
├─ README.md
├─ LICENSE
├─ .gitignore
├─ img/                      # Screenshots y recursos gráficos
├─ docs/                     # Documentación detallada
└─ theme/
    ├─ assets/
    │ ├─ js/
    │ │ └─ main.js           # Lógica principal del tasador
    │ └─ css/
    │   └─ main.css
    ├─ functions.php         # Integración mínima
    └─ page-valuation.php
```

## Requisitos / Stack

- WordPress (mínimo 5.x para compatibilidad con GF/ACF modernas)
- PHP 7.4+ (recomendado 8.0+)
- Gravity Forms
- ACF Pro (opcional: si quieres cargar campos desde ACF)
- Bootstrap (estilos usados; se pueden sustituir)
- Navegadores modernos (IntersectionObserver, Fetch API — Polyfill si es necesario)

## Instalación y puesta en marcha (desarrollador)

1. Clona el repo en tu entorno local:

   ```bash
   git clone git@github.com:rociobenitez/vehicle-valuation-api.git
   ```

2. Copia el código de los archivos de la carpeta `theme/` en tu theme de WordPress (en `wp-content/themes/tu-theme/`).
3. Habilita el theme en WordPress (apariencia → temas).
4. Importa el formulario de Gravity Forms de ejemplo.
5. Asegúrate de no dejar claves en `functions.php`. Si el script requiere `ajax_object` o `nonce`, configúralos mediante `wp_localize_script` desde `functions.php`.

## Variables sensibles y buenas prácticas

- Nunca subir:

  - Claves API (Autobiz, reCaptcha, etc.).
  - Nonces de producción.
  - Datos reales de usuarios o exóticos (DB dumps con emails, etc.).

- Mantén un `config/.env.example` con las variables necesarias, pero **no** el `.env` real.
- Usa `wp-config.php` fuera del control de versiones para credenciales de producción.

## Anonimización y borrado de metadatos

- Reemplaza cualquier mención de marca/empresa en archivos, imágenes o comentarios (si buscas anonimato).
- Si ya commiteaste secretos, usa herramientas como \[BFG Repo-Cleaner] o `git filter-repo` para eliminarlos del historial antes de publicar.

## Contribuciones y documentación

- La carpeta `docs/` contiene documentación por archivo.
- Si añades cambios, actualiza `docs/` o crea un `CHANGELOG.md` (opcional).

## Licencia

Este proyecto está publicado bajo la **MIT License**.

## Agradecimientos

Este componente se desarrolló dentro del equipo de desarrollo de **MKTmedianet** para un cliente de la agencia. La implementación corresponde a una extracción aislada de la funcionalidad del tasador (interfaz multi-step, integración con API externa de valuación y enlace a Gravity Forms) realizada por el equipo técnico de MKTmedianet para facilitar demostraciones y pruebas.

_Nota: la versión publicada en este repositorio contiene únicamente el código del theme necesario para la demostración; credenciales y datos privados del proyecto original han sido omitidos._
