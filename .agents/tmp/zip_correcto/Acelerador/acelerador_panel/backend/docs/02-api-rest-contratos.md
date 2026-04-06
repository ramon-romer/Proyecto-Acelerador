# API REST y Contratos JSON - Tutorías

Fecha: 25-03-2026

## 1. Convención de respuesta

### Respuesta exitosa

```json
{
  "data": {},
  "meta": {
    "requestId": "abc123",
    "timestamp": "2026-03-25T12:00:00+01:00"
  },
  "error": null
}
```

### Respuesta con error

```json
{
  "data": null,
  "meta": {
    "requestId": "abc123",
    "timestamp": "2026-03-25T12:00:00+01:00"
  },
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Detalle del error",
    "details": []
  }
}
```

## 2. Endpoints implementados

## POST `/api/tutorias`

- Finalidad: crear tutoría para el tutor autenticado.
- Request body:

```json
{
  "nombre": "Tutoría 2026",
  "descripcion": "Opcional",
  "profesorIds": [12, 34]
}
```

- Respuesta: `201`
- Errores típicos: `401`, `403`, `404`, `409`, `422`

## GET `/api/tutorias/{tutoriaId}`

- Finalidad: obtener detalle de tutoría.
- Respuesta: `200`
- Errores típicos: `401`, `403`, `404`

## GET `/api/tutorias/{tutoriaId}/profesores`

- Finalidad: listar profesores asignados.
- Query params:
  - `page` (opcional, default 1)
  - `pageSize` (opcional, default 20)
  - `search` (opcional)
- Respuesta: `200` con paginación en `meta.pagination`.
- Errores típicos: `401`, `403`, `404`, `422`

## GET `/api/tutorias/{tutoriaId}/profesores/{profesorId}`

- Finalidad: detalle de profesor asignado en la tutoría.
- Respuesta: `200`
- Errores típicos: `401`, `403`, `404`

## POST `/api/tutorias/{tutoriaId}/profesores`

- Finalidad: añadir profesores a tutoría.
- Request body aceptado:

```json
{ "profesorId": 12 }
```

o

```json
{ "profesorIds": [12, 34] }
```

- Respuesta: `201`
- Errores típicos: `401`, `403`, `404`, `409`, `422`

## DELETE `/api/tutorias/{tutoriaId}/profesores/{profesorId}`

- Finalidad: eliminar una asignación.
- Respuesta: `200`
- Errores típicos: `401`, `403`, `404`

## PUT `/api/tutorias/{tutoriaId}/profesores`

- Finalidad: sincronizar lista completa de asignados.
- Request body:

```json
{ "profesorIds": [12, 34, 56] }
```

- Respuesta: `200` con `added`, `removed`, `unchanged`.
- Errores típicos: `401`, `403`, `404`, `422`

## 3. Códigos de error frecuentes

- `UNAUTHORIZED`: no hay sesión válida.
- `FORBIDDEN`: usuario sin perfil `TUTOR` o acceso no autorizado.
- `VALIDATION_ERROR`: payload/parámetros inválidos.
- `TUTORIA_NOT_FOUND`: tutoría inexistente o no pertenece al tutor.
- `PROFESOR_NOT_FOUND`: profesor inexistente.
- `ASSIGNMENT_NOT_FOUND`: asignación inexistente.
- `ASSIGNMENT_DUPLICATE`: intento de crear asignación ya existente.
- `DB_QUERY_ERROR`: fallo SQL de infraestructura.
- `INTERNAL_ERROR`: error no controlado.

