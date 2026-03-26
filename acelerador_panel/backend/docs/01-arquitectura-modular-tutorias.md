# Arquitectura Modular - Backend Tutor/Tutoría

Fecha: 25-03-2026

## 1. Resumen ejecutivo

- Objetivo del módulo:
  - gestionar tutorías y asignaciones de profesores
  - evitar mezcla con edición general de profesores
- Rol del backend:
  - capa de adaptación entre frontend existente y BD existente
  - exposición de contratos JSON estables
- Condición de diseño:
  - funcionamiento autónomo sin MCP
  - punto de extensión preparado para MCP al final del proyecto

## 2. Terminología de dominio

- Tutor:
  - profesor autenticado con perfil `TUTOR`
  - solo puede operar sobre tutorías propias
- Tutoría:
  - unidad de gestión del tutor
  - en la BD actual suele corresponder a estructura de grupo
- Profesor asignado:
  - profesor vinculado a una tutoría
- Asignación:
  - relación Tutoría-Profesor mediante tabla puente

## 3. Arquitectura por capas

### Presentation

- Ubicación: `src/Presentation`
- Responsabilidad:
  - entrada HTTP
  - validación de payload/query/params
  - serialización de respuesta
- Componentes:
  - `Controllers/TutoriaController.php`
  - `Routes/TutoriaRoutes.php`
  - `Validators/*`

### Application

- Ubicación: `src/Application`
- Responsabilidad:
  - casos de uso de negocio
  - coordinación transaccional
  - mapeo de salida a contrato
- Casos de uso implementados:
  - `CreateTutoriaUseCase`
  - `GetTutoriaUseCase`
  - `ListAssignedProfesoresUseCase`
  - `GetAssignedProfesorDetailUseCase`
  - `AddProfesoresToTutoriaUseCase`
  - `RemoveProfesorFromTutoriaUseCase`
  - `SyncTutoriaProfesoresUseCase`

### Domain

- Ubicación: `src/Domain`
- Responsabilidad:
  - entidades puras
  - interfaces de repositorios y servicios de dominio
- Entidades:
  - `TutorContext`
  - `Tutoria`
  - `ProfesorAsignado`

### Infrastructure

- Ubicación: `src/Infrastructure`
- Responsabilidad:
  - acceso a BD MySQL/MariaDB (`mysqli`)
  - adaptación SQL -> dominio
  - auth por sesión existente
- Componentes clave:
  - `Persistence/MysqliDatabase.php`
  - `Persistence/SchemaMap.php`
  - `Repositories/MySql*Repository.php`
  - `Auth/SessionTutorContextProvider.php`

## 4. Decisiones técnicas aplicadas

- Prefix API: `/api/tutorias`
- Stack: PHP modular sin framework pesado
- Auth: sesión existente (`$_SESSION['nombredelusuario']`)
- Permisos:
  - valida perfil `TUTOR`
  - limita operaciones a tutorías del tutor autenticado
- Contrato de respuesta homogéneo:
  - `data`, `meta`, `error`
- Transacciones:
  - operaciones masivas de asignación bajo `TransactionManagerInterface`

## 5. Principios de diseño y desacoplamiento

- No acoplar nombres de tablas/columnas al contrato de API.
- Centralizar mapeo SQL en `config/schema.php`.
- Mantener separación estricta entre:
  - validación de entrada
  - lógica de negocio
  - persistencia
- Preparar extensión para MCP sin modificar casos de uso existentes.

