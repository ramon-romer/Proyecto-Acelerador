<?php
declare(strict_types=1);

namespace Acelerador\PanelBackend\Infrastructure\Repositories;

use Acelerador\PanelBackend\Domain\Entities\ProfesorAsignado;
use Acelerador\PanelBackend\Domain\Repositories\AsignacionRepositoryInterface;
use Acelerador\PanelBackend\Infrastructure\Persistence\MysqliDatabase;
use Acelerador\PanelBackend\Infrastructure\Persistence\SchemaMap;
use Acelerador\PanelBackend\Infrastructure\Persistence\SqlIdentifier;
use Acelerador\PanelBackend\Infrastructure\SqlMappers\ProfesorSqlMapper;

final class MySqlAsignacionRepository implements AsignacionRepositoryInterface
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

    public function listByTutoria(int $tutoriaId, int $page, int $pageSize, ?string $search): array
    {
        $assignmentTable = SqlIdentifier::quote($this->schema->table('asignacion'));
        $profesorTable = SqlIdentifier::quote($this->schema->table('profesor'));
        $assignmentTutoriaCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'tutoriaId'));
        $assignmentProfesorCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'profesorId'));
        $profesorIdCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'id'));
        $nombreCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'nombre'));
        $apellidosCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'apellidos'));

        $selectParts = [
            "p.{$profesorIdCol} AS id",
            "p.{$nombreCol} AS nombre",
            "p.{$apellidosCol} AS apellidos",
        ];
        if ($this->schema->hasPhysicalColumn('profesor', 'orcid')) {
            $orcidCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'orcid'));
            $selectParts[] = "p.{$orcidCol} AS orcid";
        }
        if ($this->schema->hasPhysicalColumn('profesor', 'departamento')) {
            $depCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'departamento'));
            $selectParts[] = "p.{$depCol} AS departamento";
        }
        if ($this->schema->hasPhysicalColumn('profesor', 'correo')) {
            $correoCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'correo'));
            $selectParts[] = "p.{$correoCol} AS correo";
        }

        $whereSql = "a.{$assignmentTutoriaCol} = ?";
        $params = [$tutoriaId];
        if ($search !== null && trim($search) !== '') {
            $whereParts = ["CONCAT_WS(' ', p.{$nombreCol}, p.{$apellidosCol}) LIKE ?"];
            $searchLike = '%' . trim($search) . '%';
            $params[] = $searchLike;

            if ($this->schema->hasPhysicalColumn('profesor', 'orcid')) {
                $orcidCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'orcid'));
                $whereParts[] = "p.{$orcidCol} LIKE ?";
                $params[] = $searchLike;
            }
            if ($this->schema->hasPhysicalColumn('profesor', 'correo')) {
                $correoCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'correo'));
                $whereParts[] = "p.{$correoCol} LIKE ?";
                $params[] = $searchLike;
            }

            $whereSql .= ' AND (' . implode(' OR ', $whereParts) . ')';
        }

        $countSql = "SELECT COUNT(*) AS total
            FROM {$assignmentTable} a
            INNER JOIN {$profesorTable} p ON p.{$profesorIdCol} = a.{$assignmentProfesorCol}
            WHERE {$whereSql}";
        $countRow = $this->db->fetchOne($countSql, $params) ?? ['total' => 0];
        $total = (int) ($countRow['total'] ?? 0);

        $offset = max(0, ($page - 1) * $pageSize);
        $listSql = "SELECT " . implode(', ', $selectParts) . "
            FROM {$assignmentTable} a
            INNER JOIN {$profesorTable} p ON p.{$profesorIdCol} = a.{$assignmentProfesorCol}
            WHERE {$whereSql}
            ORDER BY p.{$nombreCol} ASC, p.{$apellidosCol} ASC
            LIMIT ? OFFSET ?";

        $rows = $this->db->fetchAll($listSql, array_merge($params, [$pageSize, $offset]));
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapper->mapRowToEntity($row);
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function findAssignedProfesor(int $tutoriaId, int $profesorId): ?ProfesorAsignado
    {
        $assignmentTable = SqlIdentifier::quote($this->schema->table('asignacion'));
        $profesorTable = SqlIdentifier::quote($this->schema->table('profesor'));
        $assignmentTutoriaCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'tutoriaId'));
        $assignmentProfesorCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'profesorId'));
        $profesorIdCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'id'));
        $nombreCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'nombre'));
        $apellidosCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'apellidos'));

        $selectParts = [
            "p.{$profesorIdCol} AS id",
            "p.{$nombreCol} AS nombre",
            "p.{$apellidosCol} AS apellidos",
        ];
        if ($this->schema->hasPhysicalColumn('profesor', 'orcid')) {
            $orcidCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'orcid'));
            $selectParts[] = "p.{$orcidCol} AS orcid";
        }
        if ($this->schema->hasPhysicalColumn('profesor', 'departamento')) {
            $depCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'departamento'));
            $selectParts[] = "p.{$depCol} AS departamento";
        }
        if ($this->schema->hasPhysicalColumn('profesor', 'correo')) {
            $correoCol = SqlIdentifier::quote($this->schema->requiredColumn('profesor', 'correo'));
            $selectParts[] = "p.{$correoCol} AS correo";
        }

        $sql = "SELECT " . implode(', ', $selectParts) . "
            FROM {$assignmentTable} a
            INNER JOIN {$profesorTable} p ON p.{$profesorIdCol} = a.{$assignmentProfesorCol}
            WHERE a.{$assignmentTutoriaCol} = ? AND a.{$assignmentProfesorCol} = ?
            LIMIT 1";
        $row = $this->db->fetchOne($sql, [$tutoriaId, $profesorId]);
        if ($row === null) {
            return null;
        }

        return $this->mapper->mapRowToEntity($row);
    }

    public function getAssignedProfesorIds(int $tutoriaId): array
    {
        $assignmentTable = SqlIdentifier::quote($this->schema->table('asignacion'));
        $assignmentTutoriaCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'tutoriaId'));
        $assignmentProfesorCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'profesorId'));

        $sql = "SELECT {$assignmentProfesorCol} AS profesor_id
            FROM {$assignmentTable}
            WHERE {$assignmentTutoriaCol} = ?";
        $rows = $this->db->fetchAll($sql, [$tutoriaId]);

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) ($row['profesor_id'] ?? 0);
        }

        return array_values(array_unique($ids));
    }

    public function insertAssignments(int $tutoriaId, array $profesorIds): void
    {
        if ($profesorIds === []) {
            return;
        }

        $assignmentTable = SqlIdentifier::quote($this->schema->table('asignacion'));
        $assignmentTutoriaCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'tutoriaId'));
        $assignmentProfesorCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'profesorId'));

        $placeholders = [];
        $params = [];
        foreach ($profesorIds as $profesorId) {
            $placeholders[] = '(?, ?)';
            $params[] = $tutoriaId;
            $params[] = $profesorId;
        }

        $sql = "INSERT INTO {$assignmentTable} ({$assignmentTutoriaCol}, {$assignmentProfesorCol})
            VALUES " . implode(', ', $placeholders);
        $this->db->execute($sql, $params);
    }

    public function deleteAssignment(int $tutoriaId, int $profesorId): bool
    {
        $assignmentTable = SqlIdentifier::quote($this->schema->table('asignacion'));
        $assignmentTutoriaCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'tutoriaId'));
        $assignmentProfesorCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'profesorId'));

        $sql = "DELETE FROM {$assignmentTable}
            WHERE {$assignmentTutoriaCol} = ? AND {$assignmentProfesorCol} = ?";
        $affected = $this->db->execute($sql, [$tutoriaId, $profesorId]);
        return $affected > 0;
    }

    public function deleteAssignments(int $tutoriaId, array $profesorIds): int
    {
        if ($profesorIds === []) {
            return 0;
        }

        $assignmentTable = SqlIdentifier::quote($this->schema->table('asignacion'));
        $assignmentTutoriaCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'tutoriaId'));
        $assignmentProfesorCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'profesorId'));

        $in = implode(',', array_fill(0, count($profesorIds), '?'));
        $params = array_merge([$tutoriaId], $profesorIds);

        $sql = "DELETE FROM {$assignmentTable}
            WHERE {$assignmentTutoriaCol} = ? AND {$assignmentProfesorCol} IN ({$in})";
        return $this->db->execute($sql, $params);
    }

    public function countByTutoria(int $tutoriaId): int
    {
        $assignmentTable = SqlIdentifier::quote($this->schema->table('asignacion'));
        $assignmentTutoriaCol = SqlIdentifier::quote($this->schema->requiredColumn('asignacion', 'tutoriaId'));
        $sql = "SELECT COUNT(*) AS total FROM {$assignmentTable} WHERE {$assignmentTutoriaCol} = ?";
        $row = $this->db->fetchOne($sql, [$tutoriaId]) ?? ['total' => 0];
        return (int) ($row['total'] ?? 0);
    }
}

