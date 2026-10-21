#!/bin/bash
# =============================================================================
# Script de Despliegue Automático - Gestión de Biblioteca
# =============================================================================
# Este script configura TODO automáticamente en el servidor VPS:
# - Actualiza el sistema
# - Instala/verifica Apache, PHP, MySQL
# - Configura la base de datos
# - Despliega la aplicación
# - Configura Apache para servir la app
#
# USO: sudo bash deploy.sh
# =============================================================================

set -e

# ===================== CONFIGURACIÓN =====================
# Cambia estos valores según tu servidor
DB_NAME="library_system"
DB_USER="biblioteca_user"
DB_PASS="BibliotecaSegura2026!"  # ← CAMBIA ESTA CONTRASEÑA
DB_ROOT_PASS=""                   # ← Contraseña root de MySQL (déjalo vacío si no tiene)
APP_DIR="/var/www/biblioteca"
DOMAIN_OR_IP="_"                  # ← Pon tu IP o dominio aquí (ej: 123.45.67.89), o deja _ para aceptar todas
# =========================================================

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║   🏛️  Despliegue Automático - Gestión de Biblioteca     ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Verificar que se ejecuta como root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Este script debe ejecutarse como root (usa: sudo bash deploy.sh)"
    exit 1
fi

# ─────────────────────────────────────────────────────────
# Paso 1: Actualizar sistema e instalar dependencias
# ─────────────────────────────────────────────────────────
echo "📦 [1/6] Actualizando sistema e instalando dependencias..."
apt-get update -y
apt-get upgrade -y

# Instalar Apache, PHP, MySQL y extensiones necesarias
apt-get install -y \
    apache2 \
    mysql-server \
    php \
    php-mysql \
    php-pdo \
    php-mbstring \
    php-xml \
    php-curl \
    php-gd \
    php-zip \
    libapache2-mod-php \
    unzip \
    git

echo "✅ Dependencias instaladas correctamente"

# ─────────────────────────────────────────────────────────
# Paso 2: Configurar MySQL
# ─────────────────────────────────────────────────────────
echo ""
echo "🗄️  [2/6] Configurando base de datos MySQL..."

# Crear base de datos y usuario
mysql -u root ${DB_ROOT_PASS:+-p$DB_ROOT_PASS} <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "✅ Base de datos '${DB_NAME}' y usuario '${DB_USER}' creados"

# ─────────────────────────────────────────────────────────
# Paso 3: Desplegar archivos de la aplicación
# ─────────────────────────────────────────────────────────
echo ""
echo "📂 [3/6] Desplegando archivos de la aplicación..."

# Crear directorio de la app
mkdir -p ${APP_DIR}
mkdir -p ${APP_DIR}/uploads/books

# Copiar archivos (se asume que los archivos de la app ya están
# en el mismo directorio que este script, o en su directorio padre)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SOURCE="$(dirname "$SCRIPT_DIR")"

# Copiar todos los archivos PHP, CSS y directorios necesarios
cp -r ${APP_SOURCE}/*.php ${APP_DIR}/ 2>/dev/null || true
cp -r ${APP_SOURCE}/style.css ${APP_DIR}/ 2>/dev/null || true
cp -r ${APP_SOURCE}/includes ${APP_DIR}/ 2>/dev/null || true
cp -r ${APP_SOURCE}/uploads ${APP_DIR}/ 2>/dev/null || true
cp -r ${APP_SOURCE}/database.sql ${APP_DIR}/ 2>/dev/null || true

# Ajustar config.php con las credenciales del servidor
cat > ${APP_DIR}/config.php <<PHPEOF
<?php
// config.php - Configuración del servidor de producción
// Generado automáticamente por el script de despliegue

\$host = 'localhost';
\$db   = '${DB_NAME}';
\$user = '${DB_USER}';
\$pass = '${DB_PASS}';
\$charset = 'utf8mb4';

\$dsn = "mysql:host=\$host;dbname=\$db;charset=\$charset";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (\PDOException \$e) {
    try {
        \$pdo_test = new PDO("mysql:host=\$host;charset=\$charset", \$user, \$pass, \$options);
        die("Error: La base de datos '\$db' no existe. Por favor, ejecuta el archivo database.sql en tu gestor de base de datos.");
    } catch (\PDOException \$e2) {
         die("Error de conexión a la base de datos: " . \$e2->getMessage());
    }
}
?>
PHPEOF

echo "✅ Archivos de la aplicación desplegados en ${APP_DIR}"

# ─────────────────────────────────────────────────────────
# Paso 4: Importar la base de datos
# ─────────────────────────────────────────────────────────
echo ""
echo "📊 [4/6] Importando base de datos..."

if [ -f "${APP_DIR}/database.sql" ]; then
    mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} < ${APP_DIR}/database.sql
    echo "✅ Base de datos importada exitosamente"
else
    echo "⚠️  Archivo database.sql no encontrado. Impórtalo manualmente."
fi

# ─────────────────────────────────────────────────────────
# Paso 5: Configurar permisos y Apache
# ─────────────────────────────────────────────────────────
echo ""
echo "🔧 [5/6] Configurando Apache y permisos..."

# Permisos
chown -R www-data:www-data ${APP_DIR}
chmod -R 755 ${APP_DIR}
chmod -R 775 ${APP_DIR}/uploads

# Crear configuración de Apache
cat > /etc/apache2/sites-available/biblioteca.conf <<APACHEEOF
<VirtualHost *:80>
    ServerName ${DOMAIN_OR_IP}
    DocumentRoot ${APP_DIR}

    <Directory ${APP_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Configurar PHP
    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>

    # Logs
    ErrorLog \${APACHE_LOG_DIR}/biblioteca-error.log
    CustomLog \${APACHE_LOG_DIR}/biblioteca-access.log combined
</VirtualHost>
APACHEEOF

# Habilitar el sitio y módulos necesarios
a2enmod rewrite
a2dissite 000-default.conf 2>/dev/null || true
a2ensite biblioteca.conf

# Reiniciar Apache
systemctl restart apache2
systemctl enable apache2

echo "✅ Apache configurado y reiniciado"

# ─────────────────────────────────────────────────────────
# Paso 6: Verificación final
# ─────────────────────────────────────────────────────────
echo ""
echo "🔍 [6/6] Verificación final..."

# Verificar servicios
APACHE_STATUS=$(systemctl is-active apache2)
MYSQL_STATUS=$(systemctl is-active mysql)

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║              ✅ DESPLIEGUE COMPLETADO                   ║"
echo "╠══════════════════════════════════════════════════════════╣"
echo "║                                                          ║"
echo "║  Apache: ${APACHE_STATUS}                                ║"
echo "║  MySQL:  ${MYSQL_STATUS}                                 ║"
echo "║                                                          ║"
echo "║  📂 Archivos:  ${APP_DIR}                                ║"
echo "║  🗄️  Base datos: ${DB_NAME}                              ║"
echo "║  👤 DB Usuario: ${DB_USER}                               ║"
echo "║                                                          ║"
echo "╠══════════════════════════════════════════════════════════╣"
echo "║                                                          ║"
echo "║  🌐 Accede desde cualquier navegador:                    ║"
echo "║     http://TU_IP_DEL_SERVIDOR/                           ║"
echo "║                                                          ║"
echo "║  🔑 Credenciales de prueba:                              ║"
echo "║     admin@biblioteca.com / admin123                      ║"
echo "║                                                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
