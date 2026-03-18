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
     - $id_profesor ==> Integer(10) se guarda el id del profesor
     - $nombre ==> Varchar(100) se guarda el nombre del profesor
     - $apellidos ==> Varchar(200) se guarda los apellidos del profesor
     - $dni ==> Varchar(9) se guarda el DNI del profesor
     - $doi ==> Varchar(15) se guarda el DOI del profesor
     - $telefono ==> Integer(9) se guarda el telefono del profesor
     - $facultad ==> Varchar(100) se guarda la facultad del profesor
     - $departamento ==> Varchar(100) se guarda el departamento del profesor
     - $numero_personal ==> Varchar(10) se guarda el numero personal del profesor
     - $correo ==> Varchar(10) se guarda el correo del profesor
       
   - Tabla Usuario (Login)
     - $id_usuario ==> Integer(10) se guarda el id del usuario
     - $correo ==> Varchar(100) se guarda el correo del usuario
     - $password ==> Varchar(200) se guarda la contraseña del profesor encriptada
