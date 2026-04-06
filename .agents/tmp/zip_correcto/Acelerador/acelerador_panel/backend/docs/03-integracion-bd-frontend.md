# Integración con BD Existente y Frontend Existente

Fecha: 25-03-2026

## 1. Estrategia de integración con BD existente

## Principio base

- No inventar tablas ni columnas como punto de partida.
- Adaptar backend al esquema real entregado por el equipo.

## Implementación aplicada

- Mapeo centralizado en:
  - `config/schema.php`
- Dominios mapeados:
  - `tutoria` -> tabla de tutorías/grupos
  - `asignacion` -> tabla puente tutoría-profesor
  - `profesor` -> tabla de profesores

## Validación obligatoria al recibir BD final

1. Ejecutar:
   - `php acelerador_panel/backend/tools/inspect_schema.php`
2. Verificar:
   - tablas reales
   - columnas clave (`id`, `id_tutor`, `id_grupo`, `id_profesor`, etc.)
   - restricciones/índices únicos para duplicados
3. Ajustar `config/schema.php` sin tocar casos de uso.

## 2. Estrategia de integración con frontend existente

## Contexto actual observado

- El frontend del panel (`acelerador_panel/fronten`) consulta BD directamente con `mysqli`.
- La integración objetivo es migrar esas consultas a llamadas REST.

## Mapeo inicial vista -> endpoint

- `panel_tutor.php`
  - perfil de tutor (fuera de alcance de asignaciones puras)
  - navegación a gestión de grupos/profesores
- `grupos_profesor.php`
  - listado de profesores del grupo/tutoría
  - endpoint objetivo: `GET /api/tutorias/{tutoriaId}/profesores`
- futuras vistas de gestión:
  - añadir profesor: `POST /api/tutorias/{tutoriaId}/profesores`
  - eliminar profesor: `DELETE /api/tutorias/{tutoriaId}/profesores/{profesorId}`
  - sincronizar lista: `PUT /api/tutorias/{tutoriaId}/profesores`

## 3. Checklist de integración operativa

1. Validar esquema real y ajustar `config/schema.php`.
2. Levantar backend API local.
3. Probar endpoints con sesión real de tutor.
4. Sustituir en frontend una vista piloto (`grupos_profesor.php`) por consumo API.
5. Migrar operaciones de modificación (add/remove/sync).
6. Mantener fallback temporal si alguna vista depende aún de SQL directo.

