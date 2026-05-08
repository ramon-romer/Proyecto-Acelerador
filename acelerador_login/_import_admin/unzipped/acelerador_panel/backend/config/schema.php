<?php
declare(strict_types=1);

return [
    'tables' => [
        'tutoria' => 'tbl_grupo',
        'asignacion' => 'tbl_grupo_profesor',
        'profesor' => 'tbl_profesor',
    ],
    'columns' => [
        'tutoria' => [
            'id' => 'id_grupo',
            'nombre' => 'nombre',
            'descripcion' => 'descripcion',
            'tutorId' => 'id_tutor',
        ],
        'asignacion' => [
            'tutoriaId' => 'id_grupo',
            'profesorId' => 'id_profesor',
        ],
        'profesor' => [
            'id' => 'id_profesor',
            'nombre' => 'nombre',
            'apellidos' => 'apellidos',
            'orcid' => 'ORCID',
            'departamento' => 'departamento',
            'correo' => 'correo',
            'perfil' => 'perfil',
        ],
    ],
];

