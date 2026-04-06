<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Repositories;

use Acelerador\PanelBackend\Domain\Entities\Tutoria;
use Acelerador\PanelBackend\Domain\Repositories\TutoriaRepositoryInterface;
use Acelerador\PanelBackend\Infrastructure\Persistence\MysqliDatabase;
use Acelerador\PanelBackend\Infrastructure\Persistence\SchemaMap;
use Acelerador\PanelBackend\Infrastructure\Persistence\SqlIdentifier;
use Acelerador\PanelBackend\Infrastructure\SqlMappers\TutoriaSqlMapper;

final class MySqlTutoriaRepository implements TutoriaRepositoryInterface
{
    private MysqliDatabase $db;
    private SchemaMap $schema;
    private TutoriaSqlMapper $mapper;

    public function __construct(MysqliDatabase $db, SchemaMap $schema, TutoriaSqlMapper $mapper)
    {
        $this->db = $db;
        $this->schema = $schema;
        $this->mapper = $mapper;
    }

    public function createForTutor(int $tutorId, string $nombre, ?string $descripcion): Tutoria
    {
        $table = SqlIdentifier::quote($this->schema->table('tutoria'));
        $tutorColumn = SqlIdentifier::quote($this->schema->requiredColumn('tutoria', 'tutorId'));
        $nombreColumn = SqlIdentifier::quote($this->schema->requiredColumn('tutoria', 'nombre'));

        $columns = [$tutorColumn, $nombreColumn];
        $params = [$tutorId, $nombre];

        $hasDescripcionColumn = $this->schema->hasPhysicalColumn('tutoria', 'descripcion');
        if ($hasDescripcionColumn) {
            $descripcionColumn = SqlIdentifier::quote($this->schema->requiredColumn('tutoria', 'descripcion'));
            $columns[] = $descripcionColumn;
            $params[] = $descripcion;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', $columns),
            $placeholders
        );
        $this->db->execute($sql, $params);
        $newId = $this->db->lastInsertId();

        return $this->findByIdForTutor($newId, $tutorId)
            ?? new Tutoria($newId, $nombre, $hasDescripcionColumn ? $descripcion : null, $tutorId);
    }

    public function findByIdForTutor(int $tutoriaId, int $tutorId): ?Tutoria
    {
        $table = SqlIdentifier::quote($this->schema->table('tutoria'));
        $idColumn = SqlIdentifier::quote($this->schema->requiredColumn('tutoria', 'id'));
        $nombreColumn = SqlIdentifier::quote($this->schema->requiredColumn('tutoria', 'nombre'));
        $tutorColumn = SqlIdentifier::quote($this->schema->requiredColumn('tutoria', 'tutorId'));

        $selectParts = [
            "{$idColumn} AS id",
            "{$nombreColumn} AS nombre",
            "{$tutorColumn} AS tutor_id",
        ];

        if ($this->schema->hasPhysicalColumn('tutoria', 'descripcion')) {
            $descColumn = SqlIdentifier::quote($this->schema->requiredColumn('tutoria', 'descripcion'));
            $selectParts[] = "{$descColumn} AS descripcion";
        }

        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = ? AND %s = ? LIMIT 1',
            implode(', ', $selectParts),
            $table,
            $idColumn,
            $tutorColumn
        );
        $row = $this->db->fetchOne($sql, [$tutoriaId, $tutorId]);
        if ($row === null) {
            return null;
        }

        return $this->mapper->mapRowToEntity($row);
    }
}

