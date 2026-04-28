<?php

require_once __DIR__ . '/LegacyPipelineResultValidator.php';

/**
 * Alias transitorio por compatibilidad hacia LegacyPipelineResultValidator.
 *
 * Este nombre queda desaconsejado para codigo nuevo para evitar ambiguedad
 * arquitectonica sobre el contrato principal del pipeline.
 */
class PipelineResultValidator extends LegacyPipelineResultValidator
{
}
