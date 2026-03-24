# Estrategia de Testing y Calidad - MVP

## Objetivo
- Reducir al mínimo la probabilidad de fallo visible en presentación del MVP.
- Validar no solo módulos aislados, sino comportamiento de sistema completo.
- Mantener una ejecución larga configurable (hasta ~8h) más una suite crítica corta de demo.

## Alcance de testing por niveles

### Nivel 1 - Unitarias rápidas
- **Hecho observado en código**: existe suite unitaria principal en `mcp-server/tests/unit_extract_pdf.php`.
- Cobertura objetivo:
  - extracción de campos
  - contrato JSON base
  - parseo de argumentos
  - ruta DB con SQLite
- **Estado**: implementado

### Nivel 2 - Integración backend/API/cola
- Endpoints:
  - `POST /extract-pdf`
  - `POST /extract-data`
  - `GET /jobs/{job_id}`
- Worker async:
  - `php mcp-server/worker_jobs.php --once`
  - `php mcp-server/worker_jobs.php --loop`
- **Estado**: parcialmente integrado (hay validaciones, falta sistematización e2e transversal)

### Nivel 3 - E2E funcional (entrada -> JSON usable)
- Flujos objetivo:
  - PDF binario/multipart -> resultado estructurado
  - Fuente DB -> resultado estructurado
  - PDF grande -> encolado async -> recuperación por job
- **Estado**: parcialmente integrado

### Nivel 4 - Suite crítica de demo
- Propósito: blindar 3-5 flujos imprescindibles de presentación.
- Ejemplos propuestos:
  - extracción PDF estándar
  - extracción DB con config
  - ruta async completa con `job_id`
  - control de error predecible (ej. límites)
  - compatibilidad de contrato mínimo consumible por frontend
- **Estado**: pendiente (definida como línea de trabajo)

## Mega batería vs suite crítica
- **Mega batería (~8h configurable)**:
  - orientada a robustez e intermitencias.
  - puede incluir iteraciones intensivas y carga de OCR/DB.
  - uso recomendado: nocturno o pre-release.
- **Suite crítica demo (rápida)**:
  - orientada a decisión go/no-go antes de presentar.
  - ejecución corta y repetible en ventana de entrega.

## Priorización de fallos

### Bloqueante del MVP
- Ruptura de flujos críticos de demo.
- Incompatibilidad de contrato JSON entre componentes.
- Fallos de integración frontend-backend en caminos esenciales.

### Importante pero no bloqueante
- Degradación de rendimiento fuera de casos demo.
- Inconsistencias entre rutas paralelas no usadas en la presentación.
- Cobertura insuficiente de escenarios no críticos.

### Mejora post-MVP
- Optimización avanzada de tiempos de ejecución.
- Ampliación de matriz de casos extremos.
- Refactors de infraestructura de test.

## Política de cambios supervisados
- **Decisión del día**: autoaplicar solo cambios triviales, seguros y reversibles.
- **Revisión manual obligatoria** para cambios que afecten:
  - contratos JSON
  - JSON Schema
  - evaluadores/matching
  - persistencia
  - MCP
  - integración entre módulos
  - integración frontend-backend

## Propuesta operativa de ejecución

### Cadencia
- Pre-merge:
  - suite unitaria rápida + smoke de integración.
- Diaria/nocturna:
  - batería extendida configurable (hasta ~8h).
- Pre-demo:
  - suite crítica demo obligatoria + validación manual de puntos sensibles.

### Criterios de salida (go/no-go)
- Go:
  - 0 fallos en suite crítica demo.
  - 0 fallos bloqueantes en contratos/flujo API.
- No-go:
  - cualquier fallo bloqueante del MVP.
  - incertidumbre en contratos no resuelta para consumidores críticos.

## Riesgos residuales y mitigaciones
- **Riesgo (bloqueante MVP)**: ausencia de JSON Schema formal.
  - Mitigación: cerrar schema/contratos antes de integración final de equipos.
- **Riesgo (importante no bloqueante)**: coexistencia de rutas técnicas paralelas.
  - Mitigación: definir ruta principal y plan explícito de convergencia.
- **Riesgo (importante no bloqueante)**: dependencia de frontend externo al repo.
  - Mitigación: contrato de integración y test de handshake backend-frontend.
- **Riesgo (mejora post-MVP)**: deuda técnica por heterogeneidad PDO/mysqli.
  - Mitigación: normalización progresiva tras entrega MVP.
