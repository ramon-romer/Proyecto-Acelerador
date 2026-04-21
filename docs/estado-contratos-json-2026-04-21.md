# Estado de Contratos JSON - 2026-04-21

## 1. Resumen ejecutivo

- El contrato canonico oficial del proyecto queda fijado de forma explicita en:
  - `docs/schemas/contrato-canonico-aneca-v1.schema.json`
- Los contratos de `meritos/scraping` se reclasifican como:
  - tecnicos internos (`processing-job`, `processing-cache`, `api-response`);
  - legacy/transitorio (`pipeline-result-legacy`).
- Se deja implementada una capa de adaptacion para normalizar la salida legacy del pipeline al contrato canonico ANECA, sin romper el runtime actual:
  - `meritos/scraping/src/AnecaCanonicalAdapter.php`
  - Integracion en `meritos/scraping/src/Pipeline.php`.

## 2. Archivos modificados

- `docs/contratos-json-acelerador-v1.md`
- `docs/schemas/validacion-contrato-canonico-aneca-v1.md`
- `docs/schemas/processing-job.v1.schema.json`
- `docs/schemas/processing-cache.v1.schema.json`
- `docs/schemas/api-response.v1.schema.json`
- `meritos/scraping/src/Pipeline.php`
- `meritos/scraping/src/AnecaExtractor.php`
- `meritos/scraping/src/PipelineResultValidator.php`
- `meritos/scraping/src/JsonSchemaLiteValidator.php`
- `meritos/scraping/tools/validate_json_contracts.php` (wrapper deprecado)

## 3. Archivos nuevos

- `docs/schemas/README.md`
- `docs/schemas/pipeline-result-legacy.v1.schema.json`
- `meritos/scraping/src/AnecaCanonicalAdapter.php`
- `meritos/scraping/tools/validate_scraping_technical_contracts.php`
- `meritos/scraping/tools/validate_aneca_canonical_adapter.php`
- `docs/estado-contratos-json-2026-04-21.md`

## 4. Archivos eliminados

- `docs/schemas/pipeline-result.v1.schema.json`
  - Sustituido por `docs/schemas/pipeline-result-legacy.v1.schema.json` para evitar tratarlo como canonico.

## 5. Contratos vigentes tras la reclasificacion

### Canonico oficial de dominio

- `docs/schemas/contrato-canonico-aneca-v1.schema.json`

### Tecnicos internos (modulo `meritos/scraping`)

- `docs/schemas/processing-job.v1.schema.json`
- `docs/schemas/processing-cache.v1.schema.json`
- `docs/schemas/api-response.v1.schema.json`

### Legacy/transitorio

- `docs/schemas/pipeline-result-legacy.v1.schema.json`

## 6. Estado final de arquitectura de contratos

1. El contrato ANECA es la unica referencia canonica de dominio.
2. Las salidas tecnicas locales no se presentan como canonicas.
3. Se separa validacion canonica de validaciones tecnicas:
   - Canonica: `evaluador/tests/validate_canonical_schema.php`
   - Tecnica local scraping: `meritos/scraping/tools/validate_scraping_technical_contracts.php`
4. Se mantiene compatibilidad runtime actual sin ruptura abrupta.

## 7. Decision sobre `pipeline-result.v1.schema.json`

Decision tomada: **renombrado/reclasificado a legacy/transitorio**.

- Archivo retirado:
  - `docs/schemas/pipeline-result.v1.schema.json`
- Archivo vigente equivalente (legacy):
  - `docs/schemas/pipeline-result-legacy.v1.schema.json`

Justificacion con codigo real:

- La salida actual del pipeline sigue siendo el formato legacy con campos:
  - `tipo_documento`, `numero`, `fecha`, `total_bi`, `iva`, `total_a_pagar`, `texto_preview`
  - definidos por `meritos/scraping/src/AnecaExtractor.php`.
- `Pipeline.php` inyecta ademas `archivo_pdf`, `paginas_detectadas`, `txt_generado`, `json_generado`:
  - ver `meritos/scraping/src/Pipeline.php` (asignaciones de `$datos[...]` antes de persistir JSON).
- Esa forma no coincide con el contrato canonico ANECA por bloques (`bloque_1..4`, `metadatos_extraccion`, `texto_extraido`), por lo que no puede etiquetarse como canonica.

## 8. Riesgos y pendientes

- Riesgo principal:
  - La respuesta runtime de `subir.php` y `api_cv_procesar.php` sigue exponiendo `resultado_json` legacy.
- Mitigacion ya implementada:
  - Se genera JSON canonico ANECA paralelo en `Pipeline.php` mediante `AnecaCanonicalAdapter`.
- Pendiente recomendado:
  1. Exponer en API/worker un campo opcional con salida canonica ANECA (sin romper clientes actuales).
  2. Planificar migracion de consumidores a contrato canonico ANECA.
  3. Retirar progresivamente el payload legacy cuando no existan consumidores dependientes.

