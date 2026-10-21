#!/bin/bash
# =============================================================================
# Script de Despliegue Automático con NGINX + PHP-FPM + MySQL
# Para Servidor Local (WiFi) o VPS Linux (Debian / Ubuntu)
# =============================================================================
# USO: sudo bash deploy/deploy_nginx.sh
# =============================================================================

set -e

# ===================== CONFIGURACIÓN =====================
DB_NAME="library_system"
DB_USER="biblioteca_user"
DB_PASS="BibliotecaSegura2026!" # Contraseña para el usuario de la app
MYSQL_ROOT_PASS="123ferchu127"   # Contraseña de root de MySQL (dejar vacía si no tiene)
APP_DIR="/var/www/biblioteca"
# =========================================================

# Comando base de MySQL (con o sin contraseña de root)
if [ -n "$MYSQL_ROOT_PASS" ]; then
    MYSQL_CMD="mysql -u root -p${MYSQL_ROOT_PASS}"
else
    MYSQL_CMD="mysql -u root"
fi

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║   🚀 Despliegue con NGINX - Sistema de Biblioteca        ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

if [ "$EUID" -ne 0 ]; then
    echo "❌ Este script debe ejecutarse como root (usa: sudo bash deploy/deploy_nginx.sh)"
    exit 1
fi

echo "📦 [1/6] Instalando Nginx, MySQL y PHP-FPM..."
apt-get update -y
apt-get install -y nginx mysql-server php-fpm php-mysql php-pdo php-mbstring php-xml php-curl php-gd php-zip ufw

PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | head -n 1)
if [ -z "$PHP_SOCK" ]; then
    PHP_SOCK="/run/php/php-fpm.sock"
fi
echo "ℹ️ Socket PHP detectado: $PHP_SOCK"

echo ""
echo "🗄️ [2/6] Configurando base de datos MySQL..."
$MYSQL_CMD <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SOURCE="$(dirname "$SCRIPT_DIR")"

# Importar esquema si la base de datos está vacía
TABLE_COUNT=$($MYSQL_CMD -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" -s -N)
if [ "$TABLE_COUNT" -eq 0 ]; then
    if [ -f "$APP_SOURCE/database/database.sql" ]; then
        echo "📥 Importando esquema base desde database/database.sql..."
        $MYSQL_CMD ${DB_NAME} < "$APP_SOURCE/database/database.sql"
    fi
fi

echo ""
echo "📂 [3/6] Copiando archivos a ${APP_DIR}..."
mkdir -p ${APP_DIR}
mkdir -p ${APP_DIR}/uploads/books

# Copiar estructura completa
cp -r ${APP_SOURCE}/pages ${APP_DIR}/
cp -r ${APP_SOURCE}/api ${APP_DIR}/
cp -r ${APP_SOURCE}/includes ${APP_DIR}/
cp -r ${APP_SOURCE}/database ${APP_DIR}/
cp -r ${APP_SOURCE}/uploads ${APP_DIR}/
cp -r ${APP_SOURCE}/style.css ${APP_DIR}/
cp -r ${APP_SOURCE}/index.php ${APP_DIR}/
cp -r ${APP_SOURCE}/login.php ${APP_DIR}/
cp -r ${APP_SOURCE}/logout.php ${APP_DIR}/
cp -r ${APP_SOURCE}/auth.php ${APP_DIR}/

# Configurar config.php en el servidor
cat > ${APP_DIR}/config.php <<EOF
<?php
// Configuración de conexión generada automáticamente para servidor de producción
\$host = 'localhost';
\$dbname = '${DB_NAME}';
\$username = '${DB_USER}';
\$password = '${DB_PASS}';

try {
    \$pdo = new PDO("mysql:host=\$host;dbname=\$dbname;charset=utf8mb4", \$username, \$password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException \$e) {
    die("Error de conexión a la base de datos: " . \$e->getMessage());
}
?>
EOF

echo ""
echo "🔒 [4/6] Configurando permisos de archivos..."
chown -R www-data:www-data ${APP_DIR}
find ${APP_DIR} -type d -exec chmod 755 {} \;
find ${APP_DIR} -type f -exec chmod 644 {} \;
chmod -R 775 ${APP_DIR}/uploads

echo ""
echo "🌐 [5/6] Configurando Virtual Host de NGINX..."
sed -i "s|fastcgi_pass unix:.*|fastcgi_pass unix:${PHP_SOCK};|g" "${SCRIPT_DIR}/nginx.conf"
cp "${SCRIPT_DIR}/nginx.conf" /etc/nginx/sites-available/biblioteca

ln -sf /etc/nginx/sites-available/biblioteca /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t
systemctl restart nginx
systemctl restart $(basename "$PHP_SOCK" .sock | sed 's/-fpm//')-fpm 2>/dev/null || systemctl restart php*-fpm 2>/dev/null || true

echo ""
echo "🛡️ [6/6] Abriendo puerto 80 en Firewall (UFW)..."
ufw allow 80/tcp 2>/dev/null || true

SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo "=========================================================="
echo "🎉 ¡DESPLIEGUE COMPLETADO CON ÉXITO!"
echo "=========================================================="
echo ""
echo "Para acceder al sistema desde cualquier dispositivo en la misma red WiFi:"
echo "👉 Abre el navegador y visita: http://${SERVER_IP}/"
echo ""
echo "Usuario administrador inicial:"
echo "📧 Email:      admin@biblioteca.com"
echo "🔑 Contraseña: admin123"
echo "=========================================================="
