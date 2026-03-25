# Backend Tutor/Tutoría - Acelerador Panel

Backend REST en PHP modular para gestión de tutorías y asignaciones de profesores.

## Documentación extendida

- Índice: `acelerador_panel/backend/docs/00-indice-documentacion.md`
- Arquitectura: `acelerador_panel/backend/docs/01-arquitectura-modular-tutorias.md`
- API y contratos: `acelerador_panel/backend/docs/02-api-rest-contratos.md`
- Integración BD/frontend: `acelerador_panel/backend/docs/03-integracion-bd-frontend.md`
- Operación y MCP: `acelerador_panel/backend/docs/04-operacion-validacion-y-mcp.md`

## Estructura

- `public/`: entrypoint HTTP (`index.php`)
- `config/`: configuración de app, BD y mapeo de esquema SQL
- `src/Presentation`: controllers, rutas y validaciones
- `src/Application`: casos de uso, DTOs y mappers de salida
- `src/Domain`: entidades e interfaces de repositorio/servicios
- `src/Infrastructure`: persistencia MySQL, repositorios SQL, auth de sesión y publisher nulo
- `tests/`: smoke tests de casos de uso
- `tools/inspect_schema.php`: inspección rápida del esquema real de BD

## Arranque rápido (local)

1. Ajusta variables de entorno opcionales (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PORT`, `DB_CHARSET`).
2. Arranca servidor:

```bash
php -S localhost:8081 -t acelerador_panel/backend/public
```

3. Usa endpoints bajo `http://localhost:8081/api/tutorias`.

## Contrato de respuesta

- Éxito: `{"data": {...}, "meta": {...}, "error": null}`
- Error: `{"data": null, "meta": {...}, "error": {"code":"...","message":"...","details":[]}}`

`meta` incluye siempre `requestId` y `timestamp`.

## Endpoints implementados

- `POST /api/tutorias`
- `GET /api/tutorias/{tutoriaId}`
- `GET /api/tutorias/{tutoriaId}/profesores`
- `GET /api/tutorias/{tutoriaId}/profesores/{profesorId}`
- `POST /api/tutorias/{tutoriaId}/profesores`
- `DELETE /api/tutorias/{tutoriaId}/profesores/{profesorId}`
- `PUT /api/tutorias/{tutoriaId}/profesores`

## Autenticación y permisos

- Reutiliza sesión existente (`$_SESSION['nombredelusuario']`).
- Requiere perfil `TUTOR` en `tbl_profesor`.
- Restringe operación a tutorías del tutor autenticado.

## Integración con BD existente

- El mapeo está en `config/schema.php`.
- Antes de integrar en entorno final, ejecutar:

```bash
php acelerador_panel/backend/tools/inspect_schema.php
```

## Extensión MCP (fase final)

- El backend no depende de MCP.
- Punto de extensión ya creado: `AssignmentEventPublisherInterface`.
- Implementación actual: `NullAssignmentEventPublisher` (sin efecto).
- Para MCP futuro, crear implementación nueva del publisher sin tocar casos de uso.
