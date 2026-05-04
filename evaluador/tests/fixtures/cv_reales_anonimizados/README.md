# CV reales anonimizados

Dataset derivado de salidas reales anonimizadas para pruebas internas del evaluador.

## Reglas de privacidad
- No contiene archivos originales.
- No debe contener datos personales reales conocidos.
- Si hay duda de reidentificacion, marcar REVISION_MANUAL y excluir del dataset final.
- No mezclar con cv_sinteticos.

## Uso y limites
- Util para regresion funcional de parseo/extraccion.
- Requiere revision manual antes de ampliar con nuevos casos.
- No sirve para inferencia estadistica real ni para conclusiones poblacionales.
- Cobertura limitada: actualmente no incluye la rama SALUD.
- Baja diversidad real: muestra pequena y no representativa.

## Alta de nuevos casos
- Cualquier alta nueva requiere escaneo PII automatizado.
- Cualquier alta nueva requiere revision manual obligatoria antes de versionar.
- Si hay duda de PII residual, marcar `REVISION_MANUAL` y no versionar el caso.
