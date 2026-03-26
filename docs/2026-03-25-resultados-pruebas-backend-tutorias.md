# Resultados de Pruebas - Backend Tutor/Tutoría

Fecha: 25-03-2026  
Ventana de ejecución principal: 12:21:39 -> 13:21:39 (Europe/Madrid)

## Resumen ejecutivo

- Estado final de la batería agresiva de 1 hora: **PASS**
- Script ejecutado:
  - `acelerador_panel/backend/tests/run_aggressive_battery.php`
- Reporte generado:
  - `acelerador_panel/backend/tests/results/aggressive_battery_2026-03-25_1h.json`

## Comando ejecutado

```bash
php acelerador_panel/backend/tests/run_aggressive_battery.php --duration-seconds=3600 --progress-interval=60 --report-file=acelerador_panel/backend/tests/results/aggressive_battery_2026-03-25_1h.json
```

## Métricas globales

| Métrica | Valor |
|---|---|
| Duración objetivo | 3600 s |
| Duración real | 3600 s |
| Operaciones totales | 73.541.838 |
| Operaciones por segundo | 20.428,29 ops/s |
| Asersiones ejecutadas | 1.009.962.423 |
| Chequeos de invariantes | 294.167 |
| Errores inesperados | 0 |
| Memoria pico | 230 MB |
| Tutorías totales en simulación | 20.000 |

## Desglose por operación

| Operación | OK | Errores esperados | Errores inesperados |
|---|---:|---:|---:|
| `create_tutoria` | 20.000 | 0 | 0 |
| `sync_profesores` | 11.913.232 | 1.323.014 | 0 |
| `list_profesores` | 8.830.796 | 0 | 0 |
| `add_profesores` | 17.765.198 | 11.628.616 | 0 |
| `remove_profesor` | 5.074.225 | 5.220.807 | 0 |
| `validators_negative` | 2.207.576 | 0 | 0 |
| `get_profesor_detail` | 2.899.536 | 2.982.784 | 0 |
| `get_tutoria` | 2.940.268 | 735.786 | 0 |

## Errores esperados validados

| Código | Conteo |
|---|---:|
| `ASSIGNMENT_DUPLICATE` | 8.689.579 |
| `ASSIGNMENT_NOT_FOUND` | 5.953.481 |
| `PROFESOR_NOT_FOUND` | 6.512.161 |
| `TUTORIA_NOT_FOUND` | 735.786 |

Interpretación:
- Los errores esperados confirman que el backend está aplicando reglas de negocio de forma activa.
- No hubo errores inesperados ni aserciones fallidas.

## Incidencia y corrección aplicada antes de la corrida final

- Hubo una corrida previa abortada por memoria al crecer demasiado el estado de prueba y por una aserción de diff en `sync` mal formulada.
- Correcciones aplicadas en el runner:
  - límite de tutorías (`maxTutorias=20000`)
  - muestreo optimizado sin `shuffle` masivo
  - corrección de validación del diff `added/removed/unchanged`
  - límite de almacenamiento de errores inesperados en reporte
- Verificación posterior:
  - prueba corta 60s en PASS
  - prueba de 1h completa en PASS

## Conclusión

- El backend Tutor/Tutoría queda validado con una batería agresiva de 1 hora, sin fallos inesperados, con alta carga de operaciones y comprobaciones de invariantes activas.

