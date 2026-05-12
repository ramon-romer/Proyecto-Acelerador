# Registro diario de trabajo

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Registro diario
FECHA: 2026-05-12
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: Docker validado / pendiente decision seed demo

## 1. Resumen del dia
- Se consolido la evidencia de que el Proyecto Acelerador levanta correctamente en Docker sobre Windows con Docker Desktop.
- Se documento que el entorno base para demo ya no esta bloqueado por Docker, PHP, MariaDB, Tesseract ni Poppler.
- Se confirmo que el fallo del login demo actual es coherente con el modelo de datos: el usuario existe en `tbl_usuario`, pero `tbl_profesor` esta vacia.

## 2. Trabajo realizado
- Se reviso el estado operativo reportado de Docker Compose.
- Se recogieron los resultados validados del entorno `web_aneca` y `bd_aneca`.
- Se reviso el flujo de login y su dependencia entre `tbl_usuario` y `tbl_profesor`.
- Se revisaron riesgos previos al test funcional de paneles y evaluador ANECA.
- Se clasificaron los principales problemas antes de iniciar pruebas funcionales en navegador.

## 3. Decisiones tecnicas
- Docker queda considerado validado para el MVP actual.
- El fallo de `admin@admin.com / 1234` no se interpreta como fallo de Docker ni de MariaDB.
- El bloqueo del login se clasifica como problema de datos demo incompletos o de criterio de autenticacion/perfil.
- El siguiente paso recomendado es elegir entre:
  - seed demo reproducible en `init.sql`;
  - ajuste controlado del login/datos demo.
- No se modifica codigo ni datos hasta tomar esa decision.

## 4. Problemas encontrados
- `tbl_profesor` esta vacia, por lo que el login consolidado no puede resolver perfil para `admin@admin.com`.
- Los evaluadores ANECA pueden abrirse directamente si se conoce la ruta, sin control de sesion uniforme.
- `evaluador/output` y `evaluador/storage/pdfs` pueden quedar expuestos por Apache.
- Hay credenciales hardcodeadas y uso de `root` en partes legacy.
- Hay consultas SQL interpoladas en paneles legacy.
- La subida de PDFs requiere validacion server-side mas estricta.
- Persisten artefactos/copia como `evaluador - copia` y `scratch`.

## 5. Soluciones aplicadas
- No se han aplicado cambios tecnicos sobre codigo, Docker ni BD.
- Se ha documentado el estado real para evitar diagnosticar como fallo de infraestructura lo que corresponde a datos demo.
- Se ha dejado explicito que el comportamiento observado coincide con la validacion previa del companero.

## 6. Pendientes
- Decidir y aplicar posteriormente una estrategia de datos demo:
  - crear seed reproducible en `init.sql` para `admin@admin.com`;
  - o ajustar de forma controlada el flujo de login/datos demo.
- Validar login en navegador tras resolver el perfil demo.
- Revisar control de acceso de evaluadores ANECA.
- Revisar exposicion de `output`, `storage/pdfs`, `evaluador - copia` y `scratch`.
- Revisar credenciales hardcodeadas y uso de root en rutas legacy.
- Revisar SQL interpolado en paneles legacy.
- Endurecer validacion de subida PDF.

## 7. Siguiente paso
- Tomar decision de producto/entrega sobre el desbloqueo del login demo: seed reproducible en `init.sql` o ajuste controlado del login/datos demo.
- Una vez tomada la decision, ejecutar prueba funcional guiada del flujo:
  - login;
  - panel correspondiente;
  - acceso a evaluador ANECA;
  - subida PDF;
  - extraccion;
  - evaluacion;
  - resultado.

## 8. Validacion realizada
- Validaciones Docker ya comprobadas y documentadas:
  - `docker compose config`.
  - `docker compose build`.
  - `docker compose up -d`.
  - PHP 8.2.31 dentro de `web_aneca`.
  - Extensiones `mysqli` y `pdo_mysql`.
  - Tesseract 5.5.0.
  - Poppler/pdftotext.
  - Conexion PHP con MariaDB usando `base-de-datos`.
  - Importacion de `init.sql`.
  - Existencia de BDs y tablas clave.
- No se han ejecutado tests adicionales en esta ejecucion documental.

## Firma
Registro elaborado por Basilio Lagares como constancia del trabajo tecnico realizado durante la fecha indicada.
