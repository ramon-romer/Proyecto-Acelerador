<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Repositories;

use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;
use Acelerador\PanelBackend\Domain\Repositories\ProfesorRepositoryInterface;
use Acelerador\PanelBackend\Infrastructure\Persistence\MysqliDatabase;
use Acelerador\PanelBackend\Infrastructure\Persistence\SchemaMap;
use Acelerador\PanelBackend\Infrastructure\Persistence\SqlIdentifier;
use Acelerador\PanelBackend\Infrastructure\SqlMappers\ProfesorSqlMapper;

final class MySqlProfesorRepository implements ProfesorRepositoryInterface
{
    private MysqliDatabase $db;
    private SchemaMap $schema;
    private ProfesorSqlMapper $mapper;

    public function __construct(MysqliDatabase $db, SchemaMap $schema, ProfesorSqlMapper $mapper)
    {
        $this->db = $db;
        $this->schema = $schema;
        $this->mapper = $mapper;
    }

    public function existsById(int $profesorId): bool
    {
        $table = SqlIdentifier::quote($this->schema->table('profesor'));
        $idColumn = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'id'));
        $sql = "SELECT 1 FROM {$table} WHERE {$idColumn} = ? LIMIT 1";
        return $this->db->fetchOne($sql, [$profesorId]) !== null;
    }

    public function findById(int $profesorId): ?ProfesorAsignado
    {
        $table = SqlIdentifier::quote($this->schema->table('profesor'));
        $idColumn = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'id'));
        $nombreColumn = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'nombre'));
        $apellidosColumn = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'apellidos'));

        $selectParts = [
            "{$idColumn} AS id",
            "{$nombreColumn} AS nombre",
            "{$apellidosColumn} AS apellidos",
        ];

        if ($this->schema->hasPhysicalColumn('profesor', 'orcid')) {
            $orcidColumn = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'orcid'));
            $selectParts[] = "{$orcidColumn} AS orcid";
        }
        if ($this->schema->hasPhysicalColumn('profesor', 'departamento')) {
            $depColumn = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'departamento'));
            $selectParts[] = "{$depColumn} AS departamento";
        }
        if ($this->schema->hasPhysicalColumn('profesor', 'correo')) {
            $correoColumn = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'correo'));
            $selectParts[] = "{$correoColumn} AS correo";
        }

        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = ? LIMIT 1',
            implode(', ', $selectParts),
            $table,
            $idColumn
        );
        $row = $this->db->fetchOne($sql, [$profesorId]);
        if ($row === null) {
            return null;
        }

        return $this->mapper->mapRowToEntity($row);
    }
}

