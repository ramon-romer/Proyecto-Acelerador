# Matriz nivel-ventana (Acelerador)

## Packs operativos

### C0: Critico/base
1. `php -v`
2. `Get-ChildItem -Path acelerador_panel/backend -Recurse -File -Filter *.php | ForEach-Object { php -l $_.FullName }`
3. `php acelerador_panel/backend/tests/run_usecases_smoke.php`
4. `php mcp-server/tests/unit_extract_pdf.php`
5. `php acelerador_panel/backend/tools/inspect_schema.php`
6. `rg -n -F "POST /api/tutorias" acelerador_panel/backend/docs/02-api-rest-contratos.md acelerador_panel/backend/src/Presentation/Routes/TutoriaRoutes.php`
7. `rg -n -F "POST /extract-pdf" mcp-server/README.md mcp-server/server.php`
8. `rg -n "JSON Schema" docs/estado-tecnico-mvp.md docs/estrategia-testing-mvp.md`

### C1: Integracion principal
1. `powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf.ps1 -OutputDir $env:TEMP\acelerador_mcp_test_out`
2. `php mcp-server/worker_jobs.php --once`

### C2: Regresion backend tutorias
1. `php acelerador_panel/backend/tests/run_aggressive_battery.php --duration-seconds=<SEGUNDOS> --progress-interval=<INTERVALO> --report-file=$env:TEMP\acelerador_aggressive_<TAG>.json`

Regla de `progress-interval`:
1. `5` si `SEGUNDOS <= 300`
2. `30` si `300 < SEGUNDOS <= 1800`
3. `60` si `SEGUNDOS > 1800`

### C3: OCR/Parsing profundo
1. `powershell -NoProfile -ExecutionPolicy Bypass -File mcp-server/test_extract_pdf_ocr_aggressive.ps1 -Iterations <N> -OutputDir $env:TEMP\acelerador_mcp_ocr_out`

### C4: Estabilidad prolongada MCP (solo ventanas largas)
1. `1..5 | ForEach-Object { php mcp-server/tests/unit_extract_pdf.php }`

## Seleccion por nivel y ventana

| Ventana | standard | medio | agresivo |
|---|---|---|---|
| `15m` | `C0` | `C0 + C1` | `C0 + C1 + C2(120s) + C3(N=1)` |
| `30m` | `C0 + C1` | `C0 + C1 + C2(300s)` | `C0 + C1 + C2(900s) + C3(N=3)` |
| `45m` | `C0 + C1` | `C0 + C1 + C2(900s) + C3(N=1)` | `C0 + C1 + C2(1800s) + C3(N=5)` |
| `1h` | `C0 + C1 + C2(300s)` | `C0 + C1 + C2(1800s) + C3(N=3)` | `C0 + C1 + C2(2700s) + C3(N=8)` |
| `6h` | `C0 + C1 + C2(1800s)` | `C0 + C1 + C2(7200s) + C3(N=12)` | `C0 + C1 + C2(18000s) + C3(N=20) + C4` |

## Regla de recorte por tiempo real
1. Si el tiempo real remanente no alcanza para el siguiente pack, omitirlo.
2. Reportar siempre la omision y el motivo.
3. Nunca omitir `C0`.
