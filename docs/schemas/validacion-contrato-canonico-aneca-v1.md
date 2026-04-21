# Validacion automatica del contrato canonico ANECA v1

Este documento describe como ejecutar la validacion del contrato canonico ANECA (ruta SIN MCP) contra salidas reales del evaluador.

Importante:

- Este flujo valida el **contrato canonico oficial de dominio**.
- Es independiente de contratos tecnicos internos de `meritos/scraping` (jobs/cache/API/legacy pipeline).

## Script de validacion

- Ubicacion: `evaluador/tests/validate_canonical_schema.php`
- Schema objetivo por defecto: `docs/schemas/contrato-canonico-aneca-v1.schema.json`
- JSON objetivo por defecto: `evaluador/output/json/*.json`

## E2E de convergencia en `meritos/scraping`

- Ubicacion: `meritos/scraping/tools/validate_aneca_canonical_adapter.php`
- Cubre:
  - `PDF -> pipeline -> adaptador -> *.aneca.canonico.json`
  - validacion schema canonico ANECA
  - validacion de calidad/estado (`aneca_canonical_ready`)
  - trazabilidad en job/cache y consumo preferente ANECA con fallback legacy

## Comando de ejecucion (desde raiz del repo)

Con PHP en PATH:

```bash
php evaluador/tests/validate_canonical_schema.php
php meritos/scraping/tools/validate_aneca_canonical_adapter.php
```

Con XAMPP (Windows):

```powershell
C:\xampp\php\php.exe evaluador\tests\validate_canonical_schema.php
C:\xampp\php\php.exe meritos\scraping\tools\validate_aneca_canonical_adapter.php
```

## Opciones

```bash
php evaluador/tests/validate_canonical_schema.php --schema=<ruta_schema> --dir=<directorio_jsons>
```

## Dependencias

- PHP CLI (8.2 probado en este repositorio).
- No requiere librerias adicionales de Composer para validar.

## Interpretacion del resultado

- Por cada archivo se muestra:
  - `[PASS] <archivo>` si cumple el schema.
  - `[FAIL] <archivo>` con lista de errores si incumple.
- El resumen final informa:
  - `passed=<n> failed=<n> total=<n>`
- Codigo de salida:
  - `0` si todos cumplen.
  - `1` si hay incumplimientos (util para CI/regresion).
  - `2` si hay error de ejecucion (schema/directorio inexistente, JSON invalido no procesable, etc.).
