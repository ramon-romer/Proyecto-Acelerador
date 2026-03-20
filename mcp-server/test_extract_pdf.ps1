param(
    [string]$ScriptPath = "mcp-server\extract_pdf.php",
    [string]$SamplePdf = "mcp-server\pdf\prueba.pdf",
    [string]$SamplesRoot = "vendor\smalot\pdfparser\samples",
    [string]$OutputDir = "mcp-server\resultados"
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

function Invoke-PdfScript {
    param(
        [string]$TargetPath,
        [switch]$SkipPathArgument
    )

    $stdoutFile = Join-Path $env:TEMP ("pdf_stdout_" + [guid]::NewGuid().ToString("N") + ".txt")
    $stderrFile = Join-Path $env:TEMP ("pdf_stderr_" + [guid]::NewGuid().ToString("N") + ".txt")

    $prevErrorPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = "Continue"
        if ($SkipPathArgument) {
            & php $ScriptPath 1> $stdoutFile 2> $stderrFile
            $exitCode = $LASTEXITCODE
        } else {
            & php $ScriptPath $TargetPath 1> $stdoutFile 2> $stderrFile
            $exitCode = $LASTEXITCODE
        }
        $stdoutRaw = if (Test-Path $stdoutFile) { Get-Content $stdoutFile -Raw } else { "" }
        $stderrRaw = if (Test-Path $stderrFile) { Get-Content $stderrFile -Raw } else { "" }
        $stdoutText = $(if ($null -eq $stdoutRaw) { "" } else { [string]$stdoutRaw }).Trim()
        $stderrText = $(if ($null -eq $stderrRaw) { "" } else { [string]$stderrRaw }).Trim()
    } finally {
        $ErrorActionPreference = $prevErrorPreference
        if (Test-Path $stdoutFile) { Remove-Item $stdoutFile -Force }
        if (Test-Path $stderrFile) { Remove-Item $stderrFile -Force }
    }

    try {
        $json = $stdoutText | ConvertFrom-Json
        return [PSCustomObject]@{
            ExitCode  = $exitCode
            Stdout = $stdoutText
            Stderr = $stderrText
            RawOutput = $stdoutText
            Json      = $json
            IsJson    = $true
        }
    } catch {
        return [PSCustomObject]@{
            ExitCode  = $exitCode
            Stdout = $stdoutText
            Stderr = $stderrText
            RawOutput = if ($stdoutText -ne "") { $stdoutText } else { $stderrText }
            Json      = $null
            IsJson    = $false
        }
    }
}

function Get-ErrorType {
    param([string]$Message)

    if ($Message -like "*OCR no esta disponible*") { return "ocr_missing" }
    if ($Message -like "*corrupted*" -or $Message -like "*xref*") { return "corrupted" }
    if ($Message -like "*Secured pdf file*") { return "secured_pdf" }
    if ($Message -like "*No se proporciono la ruta del archivo PDF*") { return "missing_arg" }
    if ($Message -like "*El archivo no existe*") { return "file_not_found" }
    return "other_error"
}

function Test-ContractKeys {
    param([object]$JsonObj)

    $missing = @()
    foreach ($k in $requiredKeys) {
        if (-not ($JsonObj.PSObject.Properties.Name -contains $k)) {
            $missing += $k
        }
    }
    return $missing
}

$lintRaw = & php -l $ScriptPath 2>&1
$lintExit = $LASTEXITCODE
$lintOutput = ($lintRaw | Out-String).Trim()

$edgeCases = @()

$caseNoArg = Invoke-PdfScript -TargetPath "" -SkipPathArgument
$edgeCases += [PSCustomObject]@{
    Name = "no_argument"
    ExitCode = $caseNoArg.ExitCode
    IsJson = $caseNoArg.IsJson
    Result = if ($caseNoArg.IsJson -and $caseNoArg.Json.error) { "error_expected" } else { "unexpected" }
    ErrorType = if ($caseNoArg.IsJson -and $caseNoArg.Json.error) { Get-ErrorType -Message $caseNoArg.Json.error } else { "invalid_json" }
    Details = if ($caseNoArg.IsJson) { $caseNoArg.Json.error } else { $caseNoArg.RawOutput }
}

$caseNotFound = Invoke-PdfScript -TargetPath "Z:\no-existe\archivo.pdf"
$edgeCases += [PSCustomObject]@{
    Name = "file_not_found"
    ExitCode = $caseNotFound.ExitCode
    IsJson = $caseNotFound.IsJson
    Result = if ($caseNotFound.IsJson -and $caseNotFound.Json.error) { "error_expected" } else { "unexpected" }
    ErrorType = if ($caseNotFound.IsJson -and $caseNotFound.Json.error) { Get-ErrorType -Message $caseNotFound.Json.error } else { "invalid_json" }
    Details = if ($caseNotFound.IsJson) { $caseNotFound.Json.error } else { $caseNotFound.RawOutput }
}

$corruptedPath = Join-Path $SamplesRoot "corrupted.pdf"
if (Test-Path $corruptedPath) {
    $caseCorrupted = Invoke-PdfScript -TargetPath $corruptedPath
    $edgeCases += [PSCustomObject]@{
        Name = "corrupted_pdf"
        ExitCode = $caseCorrupted.ExitCode
        IsJson = $caseCorrupted.IsJson
        Result = if ($caseCorrupted.IsJson -and $caseCorrupted.Json.error) { "error_expected" } else { "unexpected" }
        ErrorType = if ($caseCorrupted.IsJson -and $caseCorrupted.Json.error) { Get-ErrorType -Message $caseCorrupted.Json.error } else { "invalid_json" }
        Details = if ($caseCorrupted.IsJson) { $caseCorrupted.Json.error } else { $caseCorrupted.RawOutput }
    }
}

$caseSample = Invoke-PdfScript -TargetPath $SamplePdf
$sampleMissingKeys = @()
$sampleResult = "unexpected"
$sampleErrorType = "none"
$sampleDetails = ""

if ($caseSample.IsJson -and $null -eq $caseSample.Json.error) {
    $sampleMissingKeys = Test-ContractKeys -JsonObj $caseSample.Json
    $sampleResult = if ($sampleMissingKeys.Count -eq 0) { "ok" } else { "missing_keys" }
    $sampleDetails = "preview_len=$($caseSample.Json.texto_preview.Length)"
} elseif ($caseSample.IsJson -and $caseSample.Json.error) {
    $sampleResult = "error"
    $sampleErrorType = Get-ErrorType -Message $caseSample.Json.error
    $sampleDetails = $caseSample.Json.error
} else {
    $sampleResult = "invalid_json"
    $sampleErrorType = "invalid_json"
    $sampleDetails = $caseSample.RawOutput
}

$edgeCases += [PSCustomObject]@{
    Name = "sample_pdf"
    ExitCode = $caseSample.ExitCode
    IsJson = $caseSample.IsJson
    Result = $sampleResult
    ErrorType = $sampleErrorType
    MissingKeys = $sampleMissingKeys
    Details = $sampleDetails
}

$libraryCheckRaw = @'
<?php
require 'mcp-server/extract_pdf.php';
$p = new PdfProcessor();
$r = $p->procesarPdf('mcp-server/pdf/prueba.pdf');
echo json_encode(array_keys($r), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
'@ | php
$libraryCheckExit = $LASTEXITCODE
$libraryOutput = ($libraryCheckRaw | Out-String).Trim()

$libraryMode = [PSCustomObject]@{
    ExitCode = $libraryCheckExit
    Output = $libraryOutput
    WorksWithoutMcp = $false
}

try {
    $libKeys = $libraryOutput | ConvertFrom-Json
    $libraryMode.WorksWithoutMcp = @($libKeys).Count -eq 7
} catch {
    $libraryMode.WorksWithoutMcp = $false
}

$sampleFiles = @()
if (Test-Path $SamplesRoot) {
    $sampleFiles = Get-ChildItem -Path $SamplesRoot -Recurse -File -Filter *.pdf
}

$allResults = @()
$summary = [ordered]@{
    total = 0
    ok = 0
    errors = 0
    invalid_json = 0
    ocr_missing = 0
    corrupted = 0
    secured_pdf = 0
    other_error = 0
    success_missing_keys = 0
}

foreach ($pdf in $sampleFiles) {
    $run = Invoke-PdfScript -TargetPath $pdf.FullName
    $entry = [ordered]@{
        file = $pdf.FullName
        exit_code = $run.ExitCode
        status = ""
        error_type = $null
        error = $null
        missing_keys = @()
        preview_length = $null
    }

    $summary.total++

    if (-not $run.IsJson) {
        $entry.status = "invalid_json"
        $summary.invalid_json++
        $summary.errors++
        $allResults += [PSCustomObject]$entry
        continue
    }

    if ($run.Json.error) {
        $entry.status = "error"
        $entry.error = $run.Json.error
        $entry.error_type = Get-ErrorType -Message $run.Json.error
        $summary.errors++
        if ($entry.error_type -eq "ocr_missing") { $summary.ocr_missing++ }
        elseif ($entry.error_type -eq "corrupted") { $summary.corrupted++ }
        elseif ($entry.error_type -eq "secured_pdf") { $summary.secured_pdf++ }
        else { $summary.other_error++ }
        $allResults += [PSCustomObject]$entry
        continue
    }

    $missing = Test-ContractKeys -JsonObj $run.Json
    if ($missing.Count -gt 0) {
        $entry.status = "missing_keys"
        $entry.missing_keys = $missing
        $summary.success_missing_keys++
        $summary.errors++
    } else {
        $entry.status = "ok"
        $summary.ok++
    }

    if ($run.Json.PSObject.Properties.Name -contains "texto_preview" -and $null -ne $run.Json.texto_preview) {
        $entry.preview_length = $run.Json.texto_preview.Length
    } else {
        $entry.preview_length = 0
    }

    $allResults += [PSCustomObject]$entry
}

$canUseWithoutMcp = $libraryMode.WorksWithoutMcp -and $sampleResult -eq "ok"

$report = [ordered]@{
    generated_at = (Get-Date).ToString("s")
    script_path = (Resolve-Path $ScriptPath).Path
    lint = [ordered]@{
        exit_code = $lintExit
        output = $lintOutput
        ok = ($lintExit -eq 0)
    }
    edge_cases = $edgeCases
    sample_sweep = [ordered]@{
        root = if (Test-Path $SamplesRoot) { (Resolve-Path $SamplesRoot).Path } else { $SamplesRoot }
        summary = $summary
    }
    without_mcp = [ordered]@{
        usable = $canUseWithoutMcp
        cli_command = "php mcp-server/extract_pdf.php <ruta.pdf>"
        library_example = "require 'mcp-server/extract_pdf.php'; (new PdfProcessor())->procesarPdf('ruta.pdf');"
        library_probe = $libraryMode
    }
    details = $allResults
}

if (-not (Test-Path $OutputDir)) {
    New-Item -Path $OutputDir -ItemType Directory | Out-Null
}

$jsonPath = Join-Path $OutputDir "validacion_extract_pdf_report.json"
$mdPath = Join-Path $OutputDir "validacion_extract_pdf_report.md"

$report | ConvertTo-Json -Depth 8 | Set-Content -Path $jsonPath -Encoding UTF8

$md = @()
$md += "# Validacion extract_pdf.php"
$md += ""
$md += "- Generado: $($report.generated_at)"
$md += "- Lint: $($report.lint.ok)"
$md += "- Uso sin MCP (CLI + libreria): $($report.without_mcp.usable)"
$md += ""
$md += "## Resumen barrido samples"
$md += ""
$md += "- Total: $($summary.total)"
$md += "- OK: $($summary.ok)"
$md += "- Errores: $($summary.errors)"
$md += "- OCR no disponible: $($summary.ocr_missing)"
$md += "- Corruptos: $($summary.corrupted)"
$md += "- Protegidos: $($summary.secured_pdf)"
$md += "- Otros errores: $($summary.other_error)"
$md += "- Casos con claves faltantes: $($summary.success_missing_keys)"
$md += ""
$md += "## Interface sin MCP"
$md += ""
$bt = [char]96
$md += "- CLI: $bt$($report.without_mcp.cli_command)$bt"
$md += "- Libreria: $bt$($report.without_mcp.library_example)$bt"
$md += "- Probe libreria exit code: $($libraryMode.ExitCode)"
$md += "- Probe libreria output: $bt$($libraryMode.Output)$bt"

$md -join "`n" | Set-Content -Path $mdPath -Encoding UTF8

Write-Output "Reporte JSON: $jsonPath"
Write-Output "Reporte MD:   $mdPath"
Write-Output "Uso sin MCP:  $canUseWithoutMcp"
