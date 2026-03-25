<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Application\Interfaces;

interface TransactionManagerInterface
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function run(callable $callback);
}

