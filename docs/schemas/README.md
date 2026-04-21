# Clasificacion de Schemas JSON (Acelerador)

## Canonico oficial de dominio

- `contrato-canonico-aneca-v1.schema.json`

Este es el unico contrato canonico oficial del proyecto.

## Tecnicos internos (`meritos/scraping`)

- `processing-job.v1.schema.json`
- `processing-cache.v1.schema.json`
- `api-response.v1.schema.json`

Son contratos tecnicos de infraestructura local del modulo, no de dominio canonico.

Incluyen trazabilidad tecnica para convergencia ANECA en runtime sin romper compatibilidad:

- `aneca_canonical_path`
- `aneca_canonical_ready`
- `aneca_canonical_validation_status`

## Legacy / transitorio

- `pipeline-result-legacy.v1.schema.json`

Representa la salida heredada del pipeline actual. Debe retirarse cuando la salida del pipeline sea ANECA canonica de forma nativa.
