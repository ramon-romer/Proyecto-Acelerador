<?php

interface EvaluationProviderInterface
{
    public function evaluate(string $pdfPath, ?callable $phaseCallback = null): array;

    public function getProviderId(): string;
}
