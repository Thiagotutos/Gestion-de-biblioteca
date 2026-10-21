# =============================================================================
# Script para SINCRONIZAR cambios al servidor automáticamente
# =============================================================================
# Cada vez que hagas un cambio en tu código local, ejecuta este script
# y se subirán SOLO los archivos modificados al servidor.
#
# USO: .\sincronizar.ps1
# =============================================================================

# ===================== CONFIGURACIÓN =====================
$SERVIDOR_IP   = "TU_IP_AQUI"          # ← Misma IP que en subir_al_servidor.ps1
$SERVIDOR_USER = "root"
$SERVIDOR_PORT = 22
$APP_DIR       = "/var/www/biblioteca"   # ← Ruta en el servidor
# =========================================================

$PROYECTO_LOCAL = Split-Path -Parent $PSScriptRoot

# Verificar que se configuró la IP
if ($SERVIDOR_IP -eq "TU_IP_AQUI") {
    Write-Host "❌ ERROR: Configura la IP del servidor en este archivo." -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
    exit 1
}

Write-Host ""
Write-Host "🔄 Sincronizando cambios con el servidor..." -ForegroundColor Cyan
Write-Host "   Servidor: $SERVIDOR_USER@$SERVIDOR_IP" -ForegroundColor DarkGray
Write-Host ""

# Subir archivos PHP
Write-Host "📤 Subiendo archivos PHP..." -ForegroundColor Green
$archivos_php = Get-ChildItem -Path $PROYECTO_LOCAL -Filter "*.php" -File
foreach ($archivo in $archivos_php) {
    Write-Host "   → $($archivo.Name)" -ForegroundColor DarkGray
    scp -P $SERVIDOR_PORT "$($archivo.FullName)" "$SERVIDOR_USER@$SERVIDOR_IP`:$APP_DIR/"
}

# Subir CSS
Write-Host "📤 Subiendo style.css..." -ForegroundColor Green
scp -P $SERVIDOR_PORT "$PROYECTO_LOCAL\style.css" "$SERVIDOR_USER@$SERVIDOR_IP`:$APP_DIR/"

# Subir includes
Write-Host "📤 Subiendo includes/..." -ForegroundColor Green
scp -P $SERVIDOR_PORT -r "$PROYECTO_LOCAL\includes" "$SERVIDOR_USER@$SERVIDOR_IP`:$APP_DIR/"

# Restaurar config.php del servidor (para no sobreescribir las credenciales de producción)
Write-Host "🔒 Restaurando config.php de producción..." -ForegroundColor Yellow
ssh -p $SERVIDOR_PORT "$SERVIDOR_USER@$SERVIDOR_IP" "cd $APP_DIR && chown -R www-data:www-data . && chmod -R 755 . && chmod -R 775 uploads/"

Write-Host ""
Write-Host "✅ ¡Sincronización completada!" -ForegroundColor Green
Write-Host "   Los cambios ya están disponibles en: http://$SERVIDOR_IP/" -ForegroundColor Cyan
Write-Host ""
Read-Host "Presiona Enter para salir"
