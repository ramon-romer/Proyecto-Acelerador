param(
    [string]$ProbePath = "mcp-server\ocr_probe.php",
    [string]$SamplesRoot = "vendor\smalot\pdfparser\samples",
    [string]$OutputDir = "mcp-server\resultados",
    [int]$Iterations = 3
)

$ErrorActionPreference = "Stop"

$requiredKeys = @(
    "tipo_documento",
    "numero",
    "fecha",
    "total_bi",
    "iva",
    "total_a_pagar",
    "texto_preview"
)

function Resolve-BinaryPath {
    param(
        [string]$EnvVar,
        [string[]]$Candidates
    )

    $envPath = [Environment]::GetEnvironmentVariable($EnvVar, "Process")
    if (-not [string]::IsNullOrWhiteSpace($envPath) -and (Test-Path $envPath)) {
        return (Resolve-Path $envPath).Path
    }

    foreach ($candidate in $Candidates) {
        if (Test-Path $candidate) {
            return (Resolve-Path $candidate).Path
        }
    }

    return $null
}

function Invoke-JsonCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$CommandParts
    )

    $raw = & $CommandParts[0] $CommandParts[1..($CommandParts.Count - 1)] 2>&1
    $exitCode = $LASTEXITCODE
    $rawText = ($raw | Out-String).Trim()

    try {
        $json = $rawText | ConvertFrom-Json
        return [PSCustomObject]@{
            ExitCode = $exitCode
            IsJson = $true
            Json = $json
            Raw = $rawText
        }
    } catch {
        return [PSCustomObject]@{
            ExitCode = $exitCode
            IsJson = $false
            Json = $null
            Raw = $rawText
        }
    }
}

function Get-MissingKeys {
    param([object]$JsonObj)

    $missing = @()
    foreach ($k in $requiredKeys) {
        if (-not ($JsonObj.PSObject.Properties.Name -contains $k)) {
            $missing += $k
        }
    }
    return $missing
}

function Percentile {
    param(
        [double[]]$Values,
        [double]$P
    )

    if ($Values.Count -eq 0) {
        return 0
    }

    $sorted = $Values | Sort-Object
    $idx = [Math]::Ceiling(($P / 100.0) * $sorted.Count) - 1
    if ($idx -lt 0) { $idx = 0 }
    if ($idx -ge $sorted.Count) { $idx = $sorted.Count - 1 }
    return [Math]::Round([double]$sorted[$idx], 2)
}

$pdftoppmCandidates = @(
    "mcp-server\.tools\poppler\poppler-25.07.0\Library\bin\pdftoppm.exe",
    "C:\poppler\Library\bin\pdftoppm.exe",
    "C:\poppler\bin\pdftoppm.exe"
)
$tesseractCandidates = @(
    "C:\Program Files\Tesseract-OCR\tesseract.exe",
    "C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
    "mcp-server\.tools\tesseract\tesseract.exe"
)
$tessdataCandidates = @(
    "mcp-server\.tools\tessdata",
    "C:\Program Files\Tesseract-OCR\tessdata",
    "C:\Program Files (x86)\Tesseract-OCR\tessdata"
)

$pdftoppmPath = Resolve-BinaryPath -EnvVar "PDFTOPPM_PATH" -Candidates $pdftoppmCandidates
$tesseractPath = Resolve-BinaryPath -EnvVar "TESSERACT_PATH" -Candidates $tesseractCandidates
$tessdataPath = Resolve-BinaryPath -EnvVar "TESSDATA_PREFIX" -Candidates $tessdataCandidates

if ($null -eq $pdftoppmPath) {
    throw "No se encontro pdftoppm. Configura PDFTOPPM_PATH o instala Poppler."
}
if ($null -eq $tesseractPath) {
    throw "No se encontro tesseract. Configura TESSERACT_PATH o instala Tesseract."
}
if ($null -eq $tessdataPath) {
    throw "No se encontro TESSDATA_PREFIX con modelos OCR."
}
if (-not (Test-Path (Join-Path $tessdataPath "spa.traineddata"))) {
    throw "No existe spa.traineddata en TESSDATA_PREFIX=$tessdataPath"
}

$prevErrorPreference = $ErrorActionPreference
$ErrorActionPreference = "Continue"

[Environment]::SetEnvironmentVariable("PDFTOPPM_PATH", $pdftoppmPath, "Process")
[Environment]::SetEnvironmentVariable("TESSERACT_PATH", $tesseractPath, "Process")
[Environment]::SetEnvironmentVariable("TESSDATA_PREFIX", $tessdataPath, "Process")

$pdftoppmVersionRaw = & $pdftoppmPath -v 2>&1
$pdftoppmVersion = ($pdftoppmVersionRaw | Out-String).Trim()
$tesseractVersionRaw = & $tesseractPath --version 2>&1
$tesseractVersion = ($tesseractVersionRaw | Select-Object -First 1 | Out-String).Trim()

$langsRaw = & $tesseractPath --list-langs 2>&1
$ErrorActionPreference = $prevErrorPreference
$langsText = ($langsRaw | Out-String)
$spaDetected = $langsText -match "(?m)^\s*spa\s*$"

if (-not $spaDetected) {
    throw "Tesseract no detecta idioma spa con TESSDATA_PREFIX=$tessdataPath"
}

$ocrTargetRelative = @(
    "Document2_pdfcreator_nocompressed.pdf",
    "Document3_pdfcreator_nocompressed.pdf",
    "bugs\Issue665.pdf",
    "bugs\Issue727.pdf",
    "grouped-by-generator\SimpleImage_Generated_by_Inkscape-0.92_PDF-v1.4.pdf",
    "grouped-by-generator\SimpleImage_Generated_by_Inkscape-0.92_PDF-v1.5.pdf"
)

$fullSet = @()
foreach ($rel in $ocrTargetRelative) {
    $path = Join-Path $SamplesRoot $rel
    if (Test-Path $path) {
        $fullSet += (Resolve-Path $path).Path
    }
}

if ($fullSet.Count -eq 0) {
    throw "No se encontraron archivos OCR target en $SamplesRoot"
}

$runs = @()
$forcedOcrRuns = @()
$durationMs = @()

for ($i = 1; $i -le $Iterations; $i++) {
    foreach ($file in $fullSet) {
        $sw = [System.Diagnostics.Stopwatch]::StartNew()
        $full = Invoke-JsonCommand -CommandParts @("php", $ProbePath, "full", $file)
        $sw.Stop()
        $ms = [Math]::Round($sw.Elapsed.TotalMilliseconds, 2)
        $durationMs += $ms

        $entry = [ordered]@{
            iteration = $i
            file = $file
            mode = "full"
            duration_ms = $ms
            exit_code = $full.ExitCode
            status = ""
            error = $null
            missing_keys = @()
            preview_length = $null
        }

        if (-not $full.IsJson) {
            $entry.status = "invalid_json"
            $entry.error = $full.Raw
        } elseif ($full.Json.error) {
            $entry.status = "error"
            $entry.error = $full.Json.error
        } else {
            $missing = Get-MissingKeys -JsonObj $full.Json
            if ($missing.Count -gt 0) {
                $entry.status = "missing_keys"
                $entry.missing_keys = $missing
            } else {
                $entry.status = "ok"
            }
            $entry.preview_length = if ($null -eq $full.Json.texto_preview) { 0 } else { $full.Json.texto_preview.Length }
        }
        $runs += [PSCustomObject]$entry

        $swOcr = [System.Diagnostics.Stopwatch]::StartNew()
        $forced = Invoke-JsonCommand -CommandParts @("php", $ProbePath, "forced_ocr", $file)
        $swOcr.Stop()
        $msOcr = [Math]::Round($swOcr.Elapsed.TotalMilliseconds, 2)

        $forcedEntry = [ordered]@{
            iteration = $i
            file = $file
            mode = "forced_ocr"
            duration_ms = $msOcr
            exit_code = $forced.ExitCode
            status = ""
            error = $null
            ocr_len = 0
            ocr_preview = ""
        }

        if (-not $forced.IsJson) {
            $forcedEntry.status = "invalid_json"
            $forcedEntry.error = $forced.Raw
        } elseif ($forced.Json.error) {
            $forcedEntry.status = "error"
            $forcedEntry.error = $forced.Json.error
        } else {
            $forcedEntry.ocr_len = if ($null -eq $forced.Json.ocr_len) { 0 } else { [int]$forced.Json.ocr_len }
            $forcedEntry.ocr_preview = if ($null -eq $forced.Json.ocr_preview) { "" } else { [string]$forced.Json.ocr_preview }
            $forcedEntry.status = if ($forcedEntry.ocr_len -gt 0) { "ok" } else { "empty_ocr" }
        }

        $forcedOcrRuns += [PSCustomObject]$forcedEntry
    }
}

$fullOk = @($runs | Where-Object { $_.status -eq "ok" }).Count
$fullErr = @($runs | Where-Object { $_.status -ne "ok" }).Count
$forcedOk = @($forcedOcrRuns | Where-Object { $_.status -eq "ok" }).Count
$forcedEmpty = @($forcedOcrRuns | Where-Object { $_.status -eq "empty_ocr" }).Count
$forcedHardErr = @($forcedOcrRuns | Where-Object { $_.status -eq "error" -or $_.status -eq "invalid_json" }).Count

$summary = [ordered]@{
    files_tested = $fullSet.Count
    iterations = $Iterations
    total_full_runs = $runs.Count
    full_ok = $fullOk
    full_non_ok = $fullErr
    total_forced_ocr_runs = $forcedOcrRuns.Count
    forced_ocr_ok = $forcedOk
    forced_ocr_empty = $forcedEmpty
    forced_ocr_hard_errors = $forcedHardErr
    duration_ms_avg = if ($durationMs.Count -gt 0) { [Math]::Round((($durationMs | Measure-Object -Average).Average), 2) } else { 0 }
    duration_ms_p95 = Percentile -Values $durationMs -P 95
}

$report = [ordered]@{
    generated_at = (Get-Date).ToString("s")
    binaries = [ordered]@{
        pdftoppm_path = $pdftoppmPath
        pdftoppm_version = $pdftoppmVersion
        tesseract_path = $tesseractPath
        tesseract_version = $tesseractVersion
        tessdata_prefix = $tessdataPath
        spa_detected = $spaDetected
    }
    summary = $summary
    targets = $fullSet
    full_runs = $runs
    forced_ocr_runs = $forcedOcrRuns
}

if (-not (Test-Path $OutputDir)) {
    New-Item -Path $OutputDir -ItemType Directory | Out-Null
}

$jsonPath = Join-Path $OutputDir "validacion_ocr_agresiva_report.json"
$mdPath = Join-Path $OutputDir "validacion_ocr_agresiva_report.md"

$report | ConvertTo-Json -Depth 8 | Set-Content -Path $jsonPath -Encoding UTF8

$md = @()
$md += "# Validacion OCR agresiva"
$md += ""
$md += "- Generado: $($report.generated_at)"
$md += "- Archivos target: $($summary.files_tested)"
$md += "- Iteraciones por archivo: $($summary.iterations)"
$md += "- Full OK / total: $($summary.full_ok) / $($summary.total_full_runs)"
$md += "- Forced OCR OK / total: $($summary.forced_ocr_ok) / $($summary.total_forced_ocr_runs)"
$md += "- Forced OCR vacio (warning): $($summary.forced_ocr_empty)"
$md += "- Forced OCR errores duros: $($summary.forced_ocr_hard_errors)"
$md += "- Duracion media full (ms): $($summary.duration_ms_avg)"
$md += "- Duracion p95 full (ms): $($summary.duration_ms_p95)"
$md += ""
$md += "## Binarios usados"
$md += ""
$md += "- pdftoppm: $($report.binaries.pdftoppm_path)"
$md += "- tesseract: $($report.binaries.tesseract_path)"
$md += "- tessdata_prefix: $($report.binaries.tessdata_prefix)"
$md += "- spa_detected: $($report.binaries.spa_detected)"

$md -join "`n" | Set-Content -Path $mdPath -Encoding UTF8

$hardFail = ($summary.full_non_ok -gt 0) -or ($summary.forced_ocr_hard_errors -gt 0)

Write-Output "Reporte JSON: $jsonPath"
Write-Output "Reporte MD:   $mdPath"
Write-Output "HardFail:     $hardFail"
if ($hardFail) {
    exit 1
}
exit 0
