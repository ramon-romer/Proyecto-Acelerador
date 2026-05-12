<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Auth;

use Acelerador\PanelBackend\Domain\Entities\TutorContext;
use Acelerador\PanelBackend\Domain\Interfaces\TutorContextProviderInterface;
use Acelerador\PanelBackend\Infrastructure\Persistence\MysqliDatabase;
use Acelerador\PanelBackend\Infrastructure\Persistence\SchemaMap;
use Acelerador\PanelBackend\Infrastructure\Persistence\SqlIdentifier;
use Acelerador\PanelBackend\Shared\Exceptions\ApiException;

final class SessionTutorContextProvider implements TutorContextProviderInterface
{
    private MysqliDatabase $db;
    private SchemaMap $schema;
    private string $sessionUserKey;

    public function __construct(MysqliDatabase $db, SchemaMap $schema, string $sessionUserKey)
    {
        $this->db = $db;
        $this->schema = $schema;
        $this->sessionUserKey = $sessionUserKey;
    }

    public function requireTutor(): TutorContext
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $correo = $_SESSION[$this->sessionUserKey] ?? null;
        if (!is_string($correo) || trim($correo) === '') {
            throw new ApiException(401, 'UNAUTHORIZED', 'Sesión no iniciada.');
        }

        $profesorTable = SqlIdentifier::quote($this->schema->table('profesor'));
        $idCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'id'));
        $nombreCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'nombre'));
        $apellidosCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'apellidos'));
        $correoCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'correo'));
        $perfilCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'perfil'));

        $sql = "SELECT {$idCol} AS id, {$nombreCol} AS nombre, {$apellidosCol} AS apellidos, {$perfilCol} AS perfil
            FROM {$profesorTable}
            WHERE {$correoCol} = ?
            LIMIT 1";
        $row = $this->db->fetchOne($sql, [$correo]);
        if ($row === null) {
            throw new ApiException(401, 'UNAUTHORIZED', 'No existe profesor asociado a la sesión.');
        }

        $perfil = strtoupper(trim((string) ($row['perfil'] ?? '')));
        if ($perfil !== 'TUTOR') {
            throw new ApiException(403, 'FORBIDDEN', 'El usuario autenticado no tiene perfil de tutor.');
        }

        return new TutorContext(
            (int) ($row['id'] ?? 0),
            $correo,
            trim((string) ($row['nombre'] ?? '') . ' ' . (string) ($row['apellidos'] ?? ''))
        );
    }
}

