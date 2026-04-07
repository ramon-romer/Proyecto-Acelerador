- Normas para los commits
     Todos los commits seguirán esta estructura:
          ACC-RRR-MMM-NN
     Donde:
          ACC = Proyecto Acelerador
          RRR = rama o área principal:
               BAC = Backend
               FRO = Frontend
               SQL = Base de datos
               DES = Desarrollo general
          MMM = módulo o funcionalidad
               ejemplos: LOG, REG, FOR, PUB, MER, GRU
          NN = número correlativo de dos cifras
     Ejemplos
     ACC-BAC-LOG-01
     ACC-FRO-REG-02
     ACC-SQL-PUB-03
     ACC-DES-GRU-04
- Convenciones de datos y nomenclatura
     Las variables de acceso a datos se documentarán con prefijo $ en PHP, pero en la base de datos las columnas se almacenan sin $.
     La base actual mezcla nombres en mayúsculas, minúsculas y algunas columnas con acentos, por lo que se recomienda normalizar en futuras versiones para evitar      errores de desarrollo. En especial, la tabla de publicaciones usa actualmente id_publicación, DOI y ORCID_autor, y la tabla de profesor usa DNI y ORCID.

- Modelo de base de datos
     1. tbl_profesor
          Almacena la información del profesorado y sus datos de acceso principales.
          Campos principales:
               id_profesor
               nombre
               apellidos
               password
               DNI
               ORCID
               telefono
               perfil
               facultad
               departamento
               correo
               rama
          Observación técnica: en la estructura actual, la clave primaria real es ORCID y id_profesor queda como campo único autoincremental.
     2. tbl_usuario
          Tabla auxiliar de usuarios con credenciales básicas:
               id_usuario
               correo
               password
          Observación técnica: esta tabla debe documentarse mejor en el README para explicar cuándo se usa tbl_usuario y cuándo tbl_profesor, porque ambas                   almacenan credenciales.
     3. tbl_convocatoria
          Gestiona las convocatorias asociadas a méritos:
               id_convocatoria
               nombre
               fecha_convocatoria
               tipo
               organismo
     4. tbl_grupo
          Representa grupos de trabajo:
               id_grupo
               nombre
               id_tutor
     5. tbl_grupo_profesor
          Tabla intermedia entre grupos y profesores:
               id
               id_grupo
               id_profesor
     6. tbl_publicacion
          Almacena publicaciones asociadas a un autor:
               id_publicación
               DOI
               ORCID_autor
               autor
               titulo
               fecha_publicacion
               nombre_revista
               numero_revista
               documento
     7. tbl_merito
          Tabla que conecta profesor, publicación y convocatoria:
               id_merito
               ORCID_autor
               id_publicacion
               id_convocatoria
               aportacion_personal
               valoracion
               observaciones

     Relaciones lógicas del sistema que deben entenderse y documentarse en el proyecto:
          tbl_grupo.id_tutor → profesor tutor
          tbl_grupo_profesor.id_grupo → grupo
          tbl_grupo_profesor.id_profesor → profesor
          tbl_publicacion.ORCID_autor → profesor
          tbl_merito.id_publicacion → publicación
          tbl_merito.id_convocatoria → convocatoria
          tbl_merito.ORCID_autor → profesor

Observación técnica: en el dump actual se crean índices para varias de estas relaciones, pero no aparecen restricciones FOREIGN KEY explícitas. Eso debería aclararse en README y, si procede, corregirse en SQL.

Roles del sistema

La tabla tbl_profesor define estos perfiles:

ADMIN
PROFESOR
TUTOR
