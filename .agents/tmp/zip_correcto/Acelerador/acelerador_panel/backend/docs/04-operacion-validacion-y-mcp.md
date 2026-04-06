# Operación, Validación y Extensión MCP

Fecha: 25-03-2026

## 1. Ejecución local

## Servidor API

```bash
php -S localhost:8081 -t acelerador_panel/backend/public
```

## Configuración de BD

- Archivo: `config/database.php`
- Variables opcionales:
  - `DB_HOST`
  - `DB_USER`
  - `DB_PASS`
  - `DB_NAME`
  - `DB_PORT`
  - `DB_CHARSET`

## 2. Validación técnica

## Lint sintáctico

```bash
Get-ChildItem -Path acelerador_panel/backend -Recurse -File -Filter *.php | % { php -l $_.FullName }
```

## Smoke tests de casos de uso

```bash
php acelerador_panel/backend/tests/run_usecases_smoke.php
```

## Inspección de esquema real

```bash
php acelerador_panel/backend/tools/inspect_schema.php
```

## 3. Reglas de negocio validadas

- existencia de tutoría
- existencia de profesor
- control de duplicados de asignación
- control de pertenencia tutor -> tutoría
- sincronización en bloque con diff (`added`, `removed`, `unchanged`)
- transacciones para operaciones masivas

## 4. Evolución MCP (fase final)

## Estado actual

- El backend funciona sin MCP.
- Hay desacoplamiento por interfaz:
  - `Domain/Interfaces/AssignmentEventPublisherInterface.php`
- Implementación activa:
  - `Infrastructure/Events/NullAssignmentEventPublisher.php`

## Estrategia de incorporación

1. Crear implementación MCP del publisher.
2. Inyectarla en el bootstrap (`public/index.php`).
3. Mantener casos de uso y endpoints sin cambios.
4. Validar que contratos REST se conservan.

