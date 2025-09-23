# Integración con Autobiz

## Objetivo

Obtener una **valoración base** de un vehículo (cotización) a partir de sus atributos (marca, modelo, versión, año, puertas, km), usando la API de Autobiz.
Las llamadas a la API se realizan **server-to-server**; el front nunca ve credenciales ni tokens.

## Aceso a la API

- La documentación completa y el portal requieren **usuario/contraseña** de partner.
- No incluir credenciales en esta doc, issues ni commits.

## Dominios de la API

- **Users** (JWT): `https://apiv2.autobiz.com/users/`
- **Referential** (catálogos): `https://apiv2.autobiz.com/referential/`
- **Quotation** (tasación): `https://apiv2.autobiz.com/quotation/`

> Rutas y campos exactos pueden variar por contrato. Consultar la doc oficial con credenciales.

## Flujo

1. **Auth (backend)**

   - Intercambiar credenciales del partner en `/users` y obtener **JWT** con expiración.
   - Cachea hasta expiración y renueva ante 401/403.

2. **Catálogos (backend, cacheable)**

   - Solicitar **marcas / modelos / versiones / atributos** a `/referential`.
   - Normalizar y **cachear** para reducir latencia y consumo de API.

3. **Cotización (backend)**

   - Recibir del front los IDs/valores seleccionados (marca, modelo, versión, año, puertas, km).
   - Llamar a `/quotation` con payload mínimo requerido.
   - Devolver al front **solo** lo necesario (p. ej., `quotation` numérica).

4. **Presentación (frontend)**

   - Mostrar la **tasación base** y aplica reglas locales (si procede).
   - No reenviar PPI a terceros.

## Ejemplos ilustrativos

### Autenticación

```http
POST /users/v1/auth HTTP/1.1
Content-Type: application/json

{
    "Username": "<SECRET:autobiz.username>",
    "Password": "<SECRET:autobiz.password>"
}
```

**Respuesta (200)**

```json
{
  "accessToken": "<JWT>",
  "typeToken": "Bearer",
  "expiresOn": "2017-03-14T15:52:00+01:00"
}
```

### Catálogo

```http
GET /referential/v1/makes[/:makeId] HTTP/1.1
Authorization: Bearer <JWT>
```

**Respuesta (200)**

```json
{
  "id": "59",
  "name": "CITROEN"
}
```

### Cotización

```http
GET /quotation/v1/version/:versionId/year/:year/mileage/:mileage/quotation HTTP/1.1
Authorization: Bearer <JWT>
Content-Type: application/json

{
  "makeId": "seat",
  "modelId": "leon",
  "versionId": "1.5-tsi-150",
  "year": 2019,
  "doors": 5,
  "mileage": 82000
}
```

**Respuesta (200, recortada)**

```json
{
  "_quotation": {
    "tradeIn": 4500
  }
}
```

> Los nombres de campos exactos pueden variar.

## Gestión de errores

- **401/403** → renovar token y reintentar **una vez**.
- **4xx funcional (p. ej. datos inválidos)** → devuelve mensaje neutro al front:
  “No hemos podido calcular la tasación de tu vehículo”.
- **5xx / timeout** → registra `service=autobiz`, `error=upstream_unavailable`, responde con el mismo mensaje neutro.
