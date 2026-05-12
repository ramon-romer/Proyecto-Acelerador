# Estado tecnico del dia

## Cabecera
PROYECTO: Acelerador
DOCUMENTO: Estado tecnico del dia
FECHA: 2026-05-12
AUTOR: Basilio Lagares
ROL: Desarrollo backend
ESTADO: Docker validado / auditoria pre-test funcional

## 1. Resumen tecnico de la jornada
- Se ha validado el entorno Docker del Proyecto Acelerador en Windows con Docker Desktop.
- Docker Compose queda operativo: `docker compose config`, `docker compose build` y `docker compose up -d` funcionan.
- Los contenedores `web_aneca` y `bd_aneca` levantan correctamente.
- PHP 8.2.31 funciona dentro de `web_aneca`.
- Las extensiones `mysqli` y `pdo_mysql` estan disponibles.
- Tesseract 5.5.0 y Poppler/pdftotext estan instalados en el contenedor web.
- El codigo queda montado en `/var/www/html`.
- PHP conecta correctamente con MariaDB usando el host Docker `base-de-datos`.
- MariaDB importa `init.sql` y deja disponibles las bases necesarias del MVP.
- El fallo de login con `admin@admin.com / 1234` queda explicado y clasificado como coherente: existe en `tbl_usuario`, pero falta perfil asociado en `tbl_profesor`.

## 2. Modulos o areas afectadas
- Docker: `Dockerfile`, `docker-compose.yml`.
- Base de datos: `init.sql`.
- Login: `acelerador_login/fronten/index.php`, `acelerador_login/fronten/login.php`, `acelerador_login/fronten/lib/auth_password.php`.
- Paneles: `acelerador_panel/fronten/panel_profesor.php`, `acelerador_panel/fronten/panel_tutor.php`, `acelerador_panel/fronten/panel_admin.php`.
- Evaluador ANECA: `evaluador/evaluador_aneca_*`, `evaluador/src/Pipeline.php`, `evaluador/src/OcrProcessor.php`.
- Artefactos operativos detectados: `evaluador/output`, `evaluador/storage/pdfs`, `evaluador - copia`, `scratch`.

## 3. Cambios realizados
- No se han modificado archivos de codigo.
- No se ha tocado Docker.
- No se ha tocado la base de datos.
- No se han borrado archivos.
- No se ha realizado commit ni push.
- Se documenta el estado real validado del entorno Docker y la auditoria previa al test funcional.

## 4. Impacto en arquitectura o integracion
- Docker deja de ser bloqueante para la demo tecnica: el stack PHP/MariaDB/OCR queda validado como entorno reproducible.
- La conexion entre PHP y MariaDB mediante `base-de-datos` queda confirmada como ruta correcta dentro de Docker Compose.
- La importacion de `init.sql` crea las bases esperadas:
  - `acelerador`
  - `evaluador_aneca_csyj`
  - `evaluador_aneca_experimentales`
  - `evaluador_aneca_humanidades`
  - `evaluador_aneca_salud`
  - `evaluador_aneca_tecnicas`
- Las bases de evaluador contienen tabla `evaluaciones`.
- La BD `acelerador` contiene tablas principales, pero `tbl_profesor` esta vacia.
- El login consolidado depende de la relacion entre `tbl_usuario` y `tbl_profesor`: el usuario existe, pero no tiene perfil enrutable.

## 5. Dependencias relevantes
- Docker Desktop en Windows.
- Imagen PHP 8.2 con Apache.
- MariaDB 10.11.
- Extensiones PHP `mysqli`, `pdo`, `pdo_mysql`.
- Poppler/pdftotext.
- Tesseract 5.5.0 con idioma `spa`.
- Host interno Docker `base-de-datos`.
- Credenciales demo actuales en `tbl_usuario`: `admin@admin.com / 1234`.

## 6. Riesgos y pendientes
- Login demo bloqueado por falta de registro correspondiente en `tbl_profesor`.
- Evaluadores ANECA accesibles directamente sin control de sesion consistente.
- `evaluador/output` y `evaluador/storage/pdfs` pueden quedar servidos por Apache si el repositorio completo se expone como document root.
- Persisten credenciales hardcodeadas y uso de `root` en partes legacy.
- Persisten consultas SQL interpoladas en paneles legacy.
- La subida de PDF necesita validacion server-side mas robusta: MIME, extension real, tamano maximo y contenido.
- Existen artefactos o copias versionadas como `evaluador - copia` y `scratch`, que no deben confundirse con flujo MVP vivo.

## 7. Proximos pasos
- Decidir el camino seguro para desbloquear demo:
  - Opcion A: seed demo reproducible en `init.sql` que cree un perfil valido en `tbl_profesor` para `admin@admin.com`.
  - Opcion B: ajuste controlado del login/datos demo, sin relajar la dependencia entre usuario y perfil.
- Despues de decidir, ejecutar test funcional del login y navegacion por paneles.
- Antes de entrega externa, aislar o excluir outputs, PDFs, copias y scratch del paquete demo.
- Mantener el alcance MVP: no abrir integraciones externas, no reabrir MCP-first y no introducir lineas nuevas fuera de estabilizacion.

## 8. Validacion y pruebas ejecutadas
- Validacion Docker reportada como realizada previamente:
  - `docker compose config`: correcto.
  - `docker compose build`: correcto.
  - `docker compose up -d`: correcto.
  - `web_aneca` y `bd_aneca`: UP.
  - PHP 8.2.31: disponible.
  - `mysqli` y `pdo_mysql`: disponibles.
  - Tesseract 5.5.0: disponible.
  - Poppler/pdftotext: disponible.
  - Conexion PHP -> MariaDB por `base-de-datos`: correcta.
  - Importacion de `init.sql`: correcta.
- No se han ejecutado tests adicionales en esta ejecucion documental.
- El comportamiento del login demo fallando por ausencia de perfil en `tbl_profesor` coincide con la validacion previa del companero y se considera esperado.

## Firma
Documento elaborado por Basilio Lagares como reflejo del estado tecnico real del trabajo realizado en la fecha indicada.
