#!/bin/bash
set -e

# 1. Asegurar directorios de carga y logs con permisos correctos
mkdir -p /var/www/html/uploads
mkdir -p /var/www/html/mcp-server/resultados/jobs
chown -R www-data:www-data /var/www/html/uploads /var/www/html/mcp-server/resultados

# 2. Arrancar el Servidor MCP en segundo plano
echo "Arrancando Servidor MCP en puerto 5000..."
php /var/www/html/mcp-server/server.php > /var/log/mcp_server.log 2>&1 &

# 3. Arrancar el servidor Apache en primer plano (esto mantiene el contenedor vivo)
echo "Arrancando Apache..."
apache2-foreground
