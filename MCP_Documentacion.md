# **Documentación del Proyecto: Integración del MCP con Procesamiento de PDFs**

## **1. Introducción**
El objetivo de este proyecto es procesar archivos PDF para extraer texto y almacenarlo en un archivo JSON. El sistema está diseñado para manejar PDFs de texto plano y, en el futuro, se integrará con un sistema de scraping para procesar PDFs que contengan imágenes o sean híbridos (combinación de texto e imágenes).

El proyecto utiliza el **Model Context Protocol (MCP)** para estructurar y gestionar las tareas relacionadas con el procesamiento de los PDFs.

---

## **2. Requisitos del Proyecto**
### **Software necesario**
- **XAMPP**: Para ejecutar un servidor local con Apache y MySQL.
- **Composer**: Herramienta de gestión de dependencias para PHP.
- **PHP**: Versión compatible con las librerías utilizadas (recomendado PHP 7.4 o superior).

### **Librerías utilizadas**
1. **setasign/fpdi**:
   - Permite manejar y manipular archivos PDF.
   - Se utiliza para procesar PDFs y preparar su contenido.
2. **smalot/pdfparser**:
   - Librería para extraer texto de PDFs de texto plano.
   - Es la herramienta principal para la extracción de texto en este proyecto.

### **Estructura del proyecto**
El proyecto tiene la siguiente estructura de carpetas y archivos:
```
Proyecto-Acelerador/
├── mcp-server/
│   ├── config.json
│   ├── extract_pdf.php
│   ├── pdf/
│   │   └── prueba.pdf
│   ├── resultados/
│   │   └── resultados.json
├── meritos/
│   ├── config.php
│   ├── ...
├── vendor/
│   ├── autoload.php
│   ├── ...
└── composer.json
```

---

## **3. Descripción del Script: `extract_pdf.php`**
El script `extract_pdf.php` es el componente principal para el procesamiento de PDFs. Su propósito es extraer texto de un archivo PDF y guardar el resultado en un archivo JSON.

### **3.1. Funcionalidades**
1. **Carga de dependencias**:
   - Utiliza el archivo `vendor/autoload.php` generado por Composer para cargar las librerías necesarias.
2. **Extracción de texto**:
   - Usa la librería `Smalot\PdfParser` para extraer texto de PDFs de texto plano.
3. **Creación de carpeta de resultados**:
   - Si no existe, crea una carpeta llamada `resultados` en la misma ubicación del script.
4. **Almacenamiento del resultado**:
   - Guarda el texto extraído en un archivo JSON llamado `resultados.json` dentro de la carpeta `resultados`.

### **3.2. Código del Script**
```php
<?php
require __DIR__ . '/../vendor/autoload.php'; // Carga las dependencias de Composer
use Smalot\PdfParser\Parser;

function extractTextFromPDF($filePath) {
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        return $text;
    } catch (Exception $e) {
        return "Error al procesar el PDF: " . $e->getMessage();
    }
}

// Ejemplo de uso
if (isset($argv[1])) {
    $filePath = $argv[1];

    if (!file_exists($filePath)) {
        echo json_encode(["error" => "El archivo no existe"]);
        exit;
    }

    $text = extractTextFromPDF($filePath);

    // Crear la carpeta "resultados" si no existe
    $resultadosDir = __DIR__ . '/resultados';
    if (!is_dir($resultadosDir)) {
        mkdir($resultadosDir, 0777, true);
    }

    // Guardar el resultado en un archivo JSON
    $resultFilePath = $resultadosDir . '/resultados.json';
    file_put_contents($resultFilePath, json_encode(["text" => $text], JSON_PRETTY_PRINT));

    // Mostrar mensaje de éxito
    echo json_encode(["message" => "Resultado guardado en 'resultados/resultados.json'"]);
} else {
    echo json_encode(["error" => "No se proporcionó la ruta del archivo"]);
}
```

---

## **4. Ejecución del Script**
### **4.1. Preparación**
1. **Coloca el PDF de prueba**:
   - Copia el archivo PDF que deseas procesar en la carpeta `mcp-server/pdf/`.

2. **Instala las dependencias**:
   - Asegúrate de que las dependencias estén instaladas ejecutando:
     ```bash
     composer install
     ```

### **4.2. Ejecución**
1. Abre una terminal y navega a la carpeta `mcp-server`:
   ```bash
   cd C:\Users\Basilio\Documents\GitHub\Proyecto-Acelerador\mcp-server
   ```
2. Ejecuta el script con el siguiente comando:
   ```bash
   php extract_pdf.php pdf/tu_archivo_prueba.pdf
   ```

### **4.3. Resultado**
- El texto extraído del PDF se guardará en el archivo:
  ```
  C:\Users\Basilio\Documents\GitHub\Proyecto-Acelerador\mcp-server\resultados\resultados.json
  ```
- El archivo tendrá un formato JSON similar al siguiente:
  ```json
  {
      "text": "Texto extraído de la página 1\nTexto extraído de la página 2\n..."
  }
  ```

---

## **5. Limitaciones y Futuras Mejoras**
### **5.1. Limitaciones actuales**
- El script solo funciona con PDFs de texto plano.
- No puede procesar PDFs que contengan imágenes o sean híbridos (combinación de texto e imágenes).

### **5.2. Futuras mejoras**
1. **Integración con un sistema de scraping**:
   - Si el texto no puede ser extraído con `Smalot\PdfParser`, el script puede integrarse con un sistema de scraping desarrollado por otros miembros del equipo.
   - Esto permitirá procesar PDFs de imágenes o híbridos.

2. **Detección automática del tipo de PDF**:
   - Implementar una lógica para determinar si el PDF contiene texto plano o si necesita ser procesado con OCR.

3. **Soporte para OCR**:
   - Usar herramientas como `Tesseract OCR` o servicios como `Google Vision API` para extraer texto de imágenes en PDFs.

---

## **6. Integración con el MCP**
El script está diseñado para ser parte del flujo del MCP. Esto significa que:
1. **Entrada**:
   - El script recibe como entrada la ruta de un archivo PDF.
2. **Procesamiento**:
   - Extrae el texto del PDF utilizando `Smalot\PdfParser`.
   - En el futuro, se integrará con un sistema de scraping para manejar PDFs de imágenes o híbridos.
3. **Salida**:
   - El texto extraído se guarda en un archivo JSON en la carpeta `resultados`.

---

## **7. Recomendaciones**
- **Pruebas**:
  - Realiza pruebas con diferentes tipos de PDFs (texto plano, híbridos, imágenes) para identificar casos en los que el script actual no funcione.
- **Colaboración**:
  - Coordina con el equipo encargado del scraping para definir cómo se integrará su solución con este script.
- **Documentación continua**:
  - Actualiza esta documentación a medida que se implementen nuevas funcionalidades o se realicen cambios en el flujo.
---

## ACTUALIZACION 2026-03-23 (ROBUSTEZ Y ESCALABILIDAD)

### Estado actual
Se reforzo el flujo de extraccion para trabajar de forma estable con PDFs y bases de datos, incluyendo casos de alto volumen.

### Mejoras implementadas
1. Extraccion PDF robusta:
- Fallback de binarios OCR en entorno local (`mcp-server/.tools`) y rutas globales.
- Resolucion automatica de `TESSDATA_PREFIX`.
- OCR por lotes de paginas para reducir picos de memoria y disco.
- Limites configurables para OCR:
  - `MAX_OCR_PAGES` (default 400)
  - `OCR_BATCH_SIZE` (default 10)

2. Estrategia sync/async para PDFs:
- Diagnostico previo para decidir procesamiento sincrono o asincrono.
- Umbrales configurables:
  - `MAX_SYNC_PDF_BYTES` (default 15MB)
  - `MAX_SYNC_PAGES` (default 80)
- Si supera umbrales, se encola job y se devuelve `job_id`.

3. API de procesamiento:
- `POST /extract-pdf`: recibe PDF y procesa sync/async segun diagnostico.
- `POST /extract-data`: entrada unificada por JSON para `fuente.tipo=pdf` o `fuente.tipo=db`.
- `GET /jobs/{job_id}`: consulta estado y resultado/error de jobs.

4. Worker asincrono:
- Nuevo script `mcp-server/worker_jobs.php`.
- Modos:
  - `--once` para procesar una tanda.
  - `--loop` para procesamiento continuo.

5. Robustez en base de datos:
- Lectura iterativa (`fetch`) en lugar de `fetchAll`.
- Limites de seguridad:
  - `max_rows` (maximo 10000)
  - `max_text_chars` / `MAX_DB_TEXT_CHARS` (default 2000000)
  - `query_timeout_seconds` / `MAX_DB_QUERY_SECONDS` (default 30)
- Manejo de errores controlado cuando se exceden limites.

6. Robustez de entrada JSON:
- Tolerancia a BOM y a `Content-Type` imperfecto cuando el body es JSON valido.

7. Configuracion multi-fuente:
- Config activo con defaults seguros:
  - `mcp-server/resultados/fuente_db_config.json`
- Plantillas por fuente:
  - `mcp-server/resultados/fuente_db_config_aneca.example.json`
  - `mcp-server/resultados/fuente_db_config_dialnet.example.json`

### Validacion realizada
- Lint OK en scripts principales (`extract_pdf.php`, `server.php`, `worker_jobs.php`).
- Unit tests OK (`passed=13 failed=0`).
- Validado flujo async con PDF grande real (encolado, proceso de worker y consulta por `job_id`).
- Validado endpoint unificado `extract-data` para `db` y `pdf`.

### Pendiente (proxima fase)
Se deja preparado para implementar cuando esten definidas las reglas de negocio y contratos:
- Orquestacion por criterios (`ORCID`, `DOI`, `rama`).
- Reglas por fuente (ANECA, Dialnet y PDFs origen X/Y).
- Matching consolidado y salida JSON unificada por evidencia.
- Contratos de entrada/salida y JSON Schema formal.

### Referencia tecnica
Para trazabilidad detallada de esta sesion:
- `mcp-server/REGISTRO_TECNICO_2026-03-23.md`

### Actualizacion de referencias (2026-03-24)
Documentacion consolidada MVP disponible en:
- `docs/2026-03-24-resumen-trabajo.md`
- `docs/estado-tecnico-mvp.md`
- `docs/estrategia-testing-mvp.md`
