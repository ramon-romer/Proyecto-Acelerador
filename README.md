# Proyecto-Acelerador
Proyecto Acelerador CEU
1. Primera decisión Booststarp
2. Normas para los commit
     Todos los commit empezará con "ACC" que significa Acelerador.
     A continuación iría a que rama pertenece: "BAC" ==> Backend, "FRO" ==> Frontend, "SQL" ==> Base de datos, "DES" ==> Desarrollo
     El siguiente campo sería "XXX" donde XXX puede ser "FRO", "LOG", "REG", etc según sea frontend, loging, registro
     El siguente campo es el numero de orden que será 01 en adelante
     Ejemplo: ACC-BAC-FOR-01 ==> acelerador-backend-formulario-01
3. Variables para las tablas (todas las variables tiene que empezar con un $ y debe de seguir este orden).
   - Tabla Profesor (Login)
     - $id_profesor ==> Integer(10) se guarda el id del profesor, not null
     - $nombre ==> Varchar(100) se guarda el nombre del profesor, not null
     - $apellidos ==> Varchar(200) se guarda los apellidos del profesor, not null
     - $password ==> Varchar(255) se guarda la contraseña del profesor encriptada, not null
     - $dni ==> Varchar(9) se guarda el DNI del profesor, not null
     - $orcid ==> Varchar(19) se guarda el DOI del profesor, not null
     - $telefono ==> Integer(9) se guarda el telefono del profesor, not null
     - $perfil ==> Enum('ADMIN', 'PROFESOR', 'TUTOR') se guarda el perfil del profesor, not null
     - $facultad ==> Varchar(100) se guarda la facultad del profesor, not null
     - $departamento ==> Varchar(100) se guarda el departamento del profesor, not null
     - $correo ==> Varchar(10) se guarda el correo del profesor, not null
     - $rama ==> Enum('SALUD','TECNICA','S Y J','HUMANIDADES','EXPERIMENTALES') se guarda la rama académica del profesor, not null
       
   - Tabla Usuario (Login)
     - $id_usuario ==> Integer(10) se guarda el id del usuario, not null
     - $correo ==> Varchar(100) se guarda el correo del usuario, not null
     - $password ==> Varchar(255) se guarda la contraseña del usuario, not null
       
   - Tabla Convocatoria
     - $id_convocatoria ==> Integer(10) se guarda el id de la convocatoria, not null, autoincrement
     - $nombre ==> Varchar(100) se guarda el nombre de la convocatoria, not null
     - $fecha_convocatoria ==> Varchar(50) se guarda la fecha de la convocatoria, not null
     - $tipo ==> Enum('CONCURSO DE MERITOS','OPOSICION') se guarda el tipo de convocatoria, not null
     - $organismo ==> Enum('PUBLICO','PRIVADO') se guarda el tipo de organismo de la convocatoria, not null
       
   - Tabla Grupo
     - $id_grupo ==> Integer(10) se guarda el id del grupo, not null, autoincrement
     - $nombre ==> Varchar(100) se guarda el nombre del grupo, not null
     - $id_tutor ==> Integer(10) se guarda el id del tutor, not null, FK de la tabla profesor
     
   - Tabla Grupo_Profesor
     - $id ==> Integer(10) se guarda el id de la tabla intermedia, not null, autoincrement
     - $id_grupo ==> Integer(10) se guarda el id del grupo, not null, FK de la tabla grupo
     - $id_profesor ==> Integer(10) se guarda el id del profesor, not null, FK de la tabla profesor
       
   - Tabla Mérito
     - $id_merito ==> Integer(10) se guarda el id del mérito, not null
     - $orcid_autor ==> Varchar(19) se guarda el ORCID del autor al que pertenece el mérito, not null
     - $id_publicacion ==> Integer(10) se guarda el id de la publicación asociada al mérito, not null, FK de la tabla publicación
     - $id_convocatoria ==> Integer(10) se guarda el id de la convocatoria asociada al mérito, not null, FK de la tabla convocatoria
     - $aportacion_personal ==> Text se guarda la aportación personal del profesor en el mérito, not null
     - $valoracion ==> Integer(2) se guarda la valoración numérica del mérito, not null, el numero máximo es 10
     - $observaciones ==> Text se guardan las observaciones del mérito, not null

   - Tabla Publicación
     - $id_publicacion ==> Integer(10) se guarda el id de la publicación, not null, autoincrement
     - $doi ==> Varchar(255) se guarda el DOI de la publicación, not null
     - $orcid_autor ==> Varchar(19) se guarda el ORCID del autor de la publicación, not null, FK de la tabla profesor
     - $autor ==> Varchar(100) se guarda el nombre del autor principal de la publicación, not null
     - $autor2 ==> Varchar(100) se guarda el nombre del segundo autor de la publicación, null permitido
     - $autor3 ==> Varchar(100) se guarda el nombre del tercer autor de la publicación, null permitido
     - $autor4 ==> Varchar(100) se guarda el nombre del cuarto autor de la publicación, null permitido
     - $titulo ==> Varchar(100) se guarda el título de la publicación, not null
     - $fecha_publicacion ==> Varchar(50) se guarda la fecha de publicación, not null
     - $nombre_revista ==> Varchar(100) se guarda el nombre de la revista, not null
     - $numero_revista ==> Integer(100) se guarda el número de la revista, not null
     - $documento ==> Varchar(255) se guarda la ruta o nombre del documento asociado a la publicación, not null
     
