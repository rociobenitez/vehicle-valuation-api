# Integración con Motorflash

## Objetivo

Obtener una **valoración base (`estimation`)** de un vehículo a partir de sus atributos (marca, modelo, versión, año, puertas, km), usando la API de Motorflash.

Las llamadas a la API se realizan **server-to-server**; el frontend nunca tiene acceso a credenciales ni tokens.

## Acceso a la API

- La documentación oficial y el acceso requieren **credenciales de partner** (`client_id` / `client_secret`).
- Las credenciales se inyectan mediante constantes de entorno (no incluidas en el repositorio).
- No incluir credenciales reales en commits, issues ni documentación pública.

## Endpoints utilizados

Base URL:

```
https://apimf.motorflash.com/api/
```

Endpoints principales:

- **Token**: `/token`
- **Catálogo Jato** (marcas, modelos, años, puertas, versiones): `/jato`
- **Tasación**: `/getEstimation`

> Rutas y campos exactos pueden variar según contrato. Consultar documentación oficial con credenciales activas.

## Flujo de integración

### 1. Autenticación (backend)

- Se realiza `POST /api/token` enviando:
  - `client_id`
  - `client_secret`

- Se obtiene `access_token`.
- El token se usa como:

  ```
  Authorization: Bearer <access_token>
  ```

- El token se cachea en servidor y se renueva cuando expira o ante error de autorización.

### 2. Catálogos (backend, cacheables)

Se utiliza el endpoint `/api/jato` para consultar datos estructurados del catálogo:

- Marcas
- Modelos
- Años disponibles (rango MIN/MAX)
- Número de puertas
- Versiones (incluye `ID` = `id_jato`)

Ejemplo ilustrativo:

```http
POST /api/jato
Authorization: Bearer <access_token>
Content-Type: application/json

{
  "entrada": {
    "fecha": "20240101",
    "marca": "Audi",
    "modelo": "A6"
  },
  "salida": ["fecha"]
}
```

Respuesta (recortada):

```json
{
  "httpCode": "200",
  "httpMessage": "Ok",
  "data": {
    "fecha": {
      "MIN": 2007,
      "MAX": 2026
    }
  }
}
```

### Normalización aplicada

- Cuando la API devuelve rangos `MIN/MAX`, se expanden en backend a un array explícito.
- Las versiones se normalizan a estructura interna:

```json
[{ "id": "600821720130501", "name": "Cabrio 1.4 TFSI 125cv Attraction" }]
```

- Se aplican mecanismos de cacheo (transients) para reducir latencia y consumo de API.

### 3. Tasación (backend)

Una vez seleccionada la versión, se llama a:

```
POST /api/getEstimation
```

Payload mínimo requerido:

```json
{
  "vehicleId": "600821720130501",
  "registrationDate": "2022-01-01",
  "plate": " ",
  "numKm": "3000"
}
```

> `vehicleId` corresponde al `ID` devuelto por `/api/jato`.
> `plate` se envía como cadena vacía con espacio según requisitos de API.

Respuesta ilustrativa:

```json
{
  "httpCode": "200",
  "httpMessage": "Ok",
  "estimation": 40000,
  "details": {
    "averagePrice": 39500
  }
}
```

## Gestión de errores

### Errores funcionales (ej. falta de comparables)

Ejemplo:

```json
{
  "httpCode": 404,
  "httpMessage": "Insufficient matches to perform an accurate Motorflash appraisal."
}
```

Tratamiento:

- Se interpreta como **error funcional**, no técnico.
- Se devuelve al frontend `success:false`.
- Se muestra mensaje neutro:

  > “No hemos podido calcular la tasación de tu vehículo.”

### Errores técnicos

- Token inválido o expirado → renovar token y reintentar una vez.
- HTTP 5xx / timeout → registrar error (`service=motorflash`, `type=upstream_error`) y responder con mensaje neutro.
- Respuesta malformada → registrar en log y abortar cálculo.

## Presentación (frontend)

- El backend devuelve exclusivamente:

  ```json
  { "estimation": 40000 }
  ```

- El frontend:
  - Muestra la tasación base.
  - Aplica reglas de negocio locales (ajustes adicionales).
  - No reenvía datos sensibles a terceros.

## Diferencias relevantes respecto a Autobiz

- Autenticación basada en `client_id/client_secret` en lugar de JWT por usuario.
- Endpoint único `/jato` para múltiples catálogos.
- Tasación vía `vehicleId` (`id_jato`) en vez de `versionId`.
