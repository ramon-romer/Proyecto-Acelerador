<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Persistence;

use Acelerador\PanelBackend\Application\Interfaces\TransactionManagerInterface;

final class MysqliTransactionManager implements TransactionManagerInterface
{
    private MysqliDatabase $database;

    public function __construct(MysqliDatabase $database)
    {
        $this->database = $database;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function run(callable $callback)
    {
        return $this->database->transaction($callback);
    }
}

