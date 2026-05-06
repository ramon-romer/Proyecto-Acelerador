<?php

require_once __DIR__ . '/../Pipeline.php';
require_once __DIR__ . '/EvaluationProviderInterface.php';

class LocalEvaluationProvider implements EvaluationProviderInterface
{
    private const PROVIDER_ID = 'local_pipeline';

    private $pipeline;

    public function __construct(?Pipeline $pipeline = null)
    {
        $this->pipeline = $pipeline ?? new Pipeline();
    }

    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array
    {
        return $this->pipeline->procesar($pdfPath, $phaseCallback);
    }

    public function getProviderId(): string
    {
        return self::PROVIDER_ID;
    }
}
