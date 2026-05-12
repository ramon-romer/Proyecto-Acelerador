# Cierre técnico: despliegue homelab y estabilización de registro en Docker/Linux

## 1. Resumen ejecutivo

Este documento recoge el cierre técnico del despliegue temporal de Proyecto Acelerador en un entorno homelab basado en CachyOS y Docker. El objetivo del entorno ha sido simular de forma razonable el futuro despliegue en CPD Linux + Docker, validar el comportamiento real de la aplicación fuera de Windows/XAMPP y estabilizar el flujo de registro bajo condiciones más próximas a producción.

El despliegue temporal ha permitido identificar errores que en Windows/XAMPP no quedaban suficientemente claros: rutas relativas frágiles, diferencias de comportamiento entre Windows y Linux, carga incorrecta de dependencias Composer, disponibilidad incompleta de PHPMailer y fallos del registro antes de insertar usuario. La estabilización se ha realizado en la rama separada `fix/registro-docker-linux`, con el commit local validado `0070590`.

El estado validado al cierre es funcional para demo temporal: la aplicación responde en Docker, el registro real inserta correctamente usuario y perfil, la contraseña se almacena con hash, el login posterior funciona y el panel del tutor permite acceder al evaluador.

## 2. Estado del entorno de despliegue

### Rutas principales

- Deploy temporal homelab: `~/deploys/acelerador-demo`
- Repositorio fuente real: `~/Documentos/GitHub/Proyecto-Acelerador`
- Montaje del repositorio dentro del contenedor: `/var/www/html`

### Servicios Docker validados

Los servicios validados en el entorno temporal son:

- `acelerador_app`
- `acelerador_db`
- `acelerador_proxy`

La comprobación operativa del entorno incluye `docker compose ps` en el deploy temporal para verificar que los servicios esperados están levantados. Esta documentación no modifica la configuración Docker ni los ficheros `docker-compose`.

### Comprobación local

Se ha validado acceso local contra el proxy:

```bash
curl -I http://127.0.0.1:8088
```

Resultado esperado documentado:

```http
HTTP/1.1 302 Found
Location: acelerador_login/fronten/index.php
```

Este comportamiento confirma que el proxy responde y redirige al login de la aplicación.

### Publicación temporal con Cloudflare Quick Tunnel

También se ha validado publicación temporal mediante Cloudflare Quick Tunnel. Esta publicación debe considerarse una solución provisional e inestable para demo o validación puntual, porque depende de URLs `trycloudflare.com` temporales y no representa una arquitectura final de despliegue.

Para un despliegue estable será necesario disponer de dominio o subdominio controlado y un túnel fijo o mecanismo equivalente de publicación persistente.

## 3. Problemas detectados

### Diferencias Windows/XAMPP frente a Linux/Docker

El paso de Windows/XAMPP a Linux/Docker ha expuesto problemas de portabilidad que no se manifestaban de forma evidente en el entorno local anterior. Entre ellos destacan diferencias en resolución de rutas, sensibilidad a mayúsculas/minúsculas, rutas relativas dependientes del directorio de ejecución y supuestos implícitos sobre la ubicación de dependencias.

### Rutas y autoload

Se detectó fragilidad en rutas relativas y en el uso de `vendor/autoload.php`. En Linux/Docker estos problemas provocaban fallos más claros porque el contenedor ejecuta la aplicación desde una estructura estricta y con rutas reales bajo `/var/www/html`.

### Composer y vendor

El estado de Composer y `vendor` no estaba suficientemente normalizado. Parte del árbol de dependencias era incompleto o no estaba alineado con lo que la aplicación necesitaba realmente para ejecutar registro, correo y evaluador.

Este punto queda documentado como riesgo técnico posterior: `composer.json` y `composer.lock` deben ser la fuente de verdad, y `vendor` debe revisarse tras la demo para evitar depender de un estado parcial o accidental.

### PHPMailer

PHPMailer no estaba disponible correctamente en el entorno Linux/Docker. Esto afectaba al flujo de registro cuando se alcanzaba la lógica de correo, especialmente si la carga de dependencias o clases fallaba antes de completar la inserción de datos.

### Registro antes de inserción

El flujo de registro fallaba antes de insertar el usuario cuando se producía un problema de correo, autoload o dependencia. Este comportamiento hacía que una incidencia periférica, como el envío de email, bloquease la creación efectiva del usuario y del perfil asociado.

### Artefactos runtime versionados

Se detectaron artefactos de ejecución versionados en:

- `evaluador/output`
- `evaluador/storage/pdfs`

Estos contenidos deben tratarse como salida runtime y no como código fuente. Queda pendiente revisar `.gitignore` y la política de versionado para impedir que salidas generadas, PDFs temporales u otros artefactos operativos entren en Git.

## 4. Cambios aplicados

La estabilización del registro se realizó en una rama separada:

- Rama: `fix/registro-docker-linux`
- Commit local validado: `0070590`
- Asunto verificado en Git: `ACC-BAC-REGISTRO: estabiliza registro en Docker Linux-Pseudo CPD`

Archivos y áreas principales modificadas en el commit:

- `acelerador_registro/correo/sendmail.php`
- `acelerador_registro/fronten/index.php`
- `composer.json`
- `composer.lock`
- `vendor/composer/*`
- `vendor/phpmailer/`
- `vendor/setasign/`
- `vendor/smalot/`
- `vendor/symfony/`

Los cambios se centraron en estabilizar la carga de dependencias y el flujo de registro bajo Docker/Linux. Este documento no introduce cambios funcionales adicionales.

## 5. Validaciones realizadas

Validaciones registradas en el cierre técnico del despliegue y la estabilización:

- `git status --short` limpio tras el commit funcional `0070590`, antes de crear este documento de cierre.
- `docker compose ps` con los servicios esperados levantados en el entorno temporal.
- `curl -I http://127.0.0.1:8088` con respuesta `HTTP/1.1 302 Found` y redirección a `acelerador_login/fronten/index.php`.
- Carga correcta de Composer autoload.
- Carga correcta de PHPMailer.
- Carga correcta de `sendmail.php`.
- Registro real ejecutado correctamente.
- Inserción de usuario en `tbl_usuario`.
- Inserción de perfil en `tbl_profesor`.
- Contraseña almacenada con hash.
- Login posterior con el usuario registrado.
- Acceso al panel tutor.
- Acceso al evaluador desde el panel.

En esta generación documental no se han ejecutado cambios de código, cambios Docker, cambios de base de datos, commits ni push.

## 6. Estado actual

El despliegue homelab queda funcional como entorno temporal de validación y demo. La rama `fix/registro-docker-linux` contiene la estabilización del flujo de registro para Docker/Linux y el commit `0070590` queda como punto local validado.

El estado funcional documentado es:

- Aplicación accesible mediante Docker en entorno homelab.
- Proxy local respondiendo y redirigiendo al login.
- Registro real funcional.
- Usuario y perfil persistidos correctamente.
- Login posterior funcional.
- Panel tutor accesible.
- Evaluador accesible desde el panel.
- Demo temporal viable.

## 7. Pendientes y riesgos

- Cloudflare Quick Tunnel no debe considerarse solución final de publicación.
- Conviene disponer de dominio o subdominio y túnel fijo para un despliegue estable.
- `vendor` parcialmente versionado debe revisarse después de la demo.
- Composer debe normalizarse para que `composer.json` y `composer.lock` sean la fuente de verdad.
- Los outputs y PDFs generados no deberían versionarse.
- El equipo debería validar en Docker/Linux antes de cerrar tareas funcionales.
- Si parte del equipo trabaja en Windows, se recomienda validar con Windows + WSL2 + Docker para aproximarse al entorno Linux final.

## 8. Recomendaciones para próximos proyectos

- Adoptar un enfoque Docker-first desde el inicio del proyecto.
- Definir un entorno de validación común para todo el equipo.
- Mantener `composer.json` y `composer.lock` como fuente de verdad de dependencias.
- Evitar XAMPP como entorno oficial de validación.
- Evitar intercambio de ZIPs como mecanismo de entrega técnica.
- Mantener Git limpio, con cambios revisables y separados por objetivo.
- Incluir `.env.example` para documentar variables de entorno sin exponer secretos.
- Mantener un `.gitignore` robusto para excluir salidas runtime, PDFs generados, caches y ficheros temporales.
- Validar dentro del contenedor antes de dar una tarea por terminada.
- Separar incidencias de infraestructura, dependencias y lógica funcional para facilitar diagnóstico y revisión.

## 9. Conclusión

El homelab ha servido como pseudo-CPD para detectar y corregir problemas reales antes del despliegue final. La validación en CachyOS + Docker ha aportado una señal técnica más fiable que Windows/XAMPP para anticipar el comportamiento esperado en un CPD Linux + Docker.

El flujo de registro queda estabilizado en una rama separada y con un commit local identificado. La demo temporal es viable, siempre que se mantenga explícito que Cloudflare Quick Tunnel es una solución provisional y que quedan pendientes tareas de normalización de Composer, revisión de `vendor` y limpieza de artefactos runtime versionados.
