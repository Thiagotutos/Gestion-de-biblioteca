# =============================================================================
# Script para SUBIR archivos al servidor VPS desde tu PC Windows
# =============================================================================
# Este script usa SCP (viene con Windows 10/11) para copiar los archivos
# al servidor y luego ejecuta el script de despliegue automáticamente.
#
# USO: Haz doble clic o ejecuta en PowerShell:
#      .\subir_al_servidor.ps1
# =============================================================================

# ===================== CONFIGURACIÓN =====================
# ⚠️ CAMBIA ESTOS VALORES con los datos de tu servidor VPS
$SERVIDOR_IP   = "TU_IP_AQUI"          # ← Pon la IP de tu servidor (ej: 123.45.67.89)
$SERVIDOR_USER = "root"                 # ← Usuario SSH (generalmente root)
$SERVIDOR_PORT = 22                     # ← Puerto SSH (generalmente 22)
# =========================================================

# Ruta local del proyecto
$PROYECTO_LOCAL = Split-Path -Parent $PSScriptRoot
$ARCHIVOS_DEPLOY = $PSScriptRoot

Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   🏛️  Subir Gestión de Biblioteca al Servidor VPS       ║" -ForegroundColor Cyan
Write-Host "╚══════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Verificar que se configuró la IP
if ($SERVIDOR_IP -eq "TU_IP_AQUI") {
    Write-Host "❌ ERROR: Debes configurar la IP del servidor." -ForegroundColor Red
    Write-Host "   Abre este archivo y cambia 'TU_IP_AQUI' por la IP real de tu VPS." -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Presiona Enter para salir"
    exit 1
}

Write-Host "📌 Servidor: $SERVIDOR_USER@$SERVIDOR_IP`:$SERVIDOR_PORT" -ForegroundColor Yellow
Write-Host "📂 Proyecto: $PROYECTO_LOCAL" -ForegroundColor Yellow
Write-Host ""

# ─────────────────────────────────────────────────────────
# Paso 1: Crear directorio temporal en el servidor
# ─────────────────────────────────────────────────────────
Write-Host "📁 [1/4] Preparando directorio en el servidor..." -ForegroundColor Green

ssh -p $SERVIDOR_PORT "$SERVIDOR_USER@$SERVIDOR_IP" "mkdir -p /tmp/biblioteca-deploy"

# ─────────────────────────────────────────────────────────
# Paso 2: Subir archivos PHP y recursos
# ─────────────────────────────────────────────────────────
Write-Host "📤 [2/4] Subiendo archivos al servidor..." -ForegroundColor Green

# Subir archivos PHP principales
$archivos_php = Get-ChildItem -Path $PROYECTO_LOCAL -Filter "*.php" -File
foreach ($archivo in $archivos_php) {
    Write-Host "   → $($archivo.Name)" -ForegroundColor DarkGray
    scp -P $SERVIDOR_PORT "$($archivo.FullName)" "$SERVIDOR_USER@$SERVIDOR_IP`:/tmp/biblioteca-deploy/"
}

# Subir CSS
Write-Host "   → style.css" -ForegroundColor DarkGray
scp -P $SERVIDOR_PORT "$PROYECTO_LOCAL\style.css" "$SERVIDOR_USER@$SERVIDOR_IP`:/tmp/biblioteca-deploy/"

# Subir database.sql
Write-Host "   → database.sql" -ForegroundColor DarkGray
scp -P $SERVIDOR_PORT "$PROYECTO_LOCAL\database.sql" "$SERVIDOR_USER@$SERVIDOR_IP`:/tmp/biblioteca-deploy/"

# Subir directorio includes
Write-Host "   → includes/" -ForegroundColor DarkGray
scp -P $SERVIDOR_PORT -r "$PROYECTO_LOCAL\includes" "$SERVIDOR_USER@$SERVIDOR_IP`:/tmp/biblioteca-deploy/"

# Subir directorio uploads
Write-Host "   → uploads/" -ForegroundColor DarkGray
scp -P $SERVIDOR_PORT -r "$PROYECTO_LOCAL\uploads" "$SERVIDOR_USER@$SERVIDOR_IP`:/tmp/biblioteca-deploy/"

# Subir script de despliegue
Write-Host "   → deploy/deploy.sh" -ForegroundColor DarkGray
scp -P $SERVIDOR_PORT "$ARCHIVOS_DEPLOY\deploy.sh" "$SERVIDOR_USER@$SERVIDOR_IP`:/tmp/biblioteca-deploy/"

Write-Host "✅ Archivos subidos correctamente" -ForegroundColor Green

# ─────────────────────────────────────────────────────────
# Paso 3: Ejecutar script de despliegue en el servidor
# ─────────────────────────────────────────────────────────
Write-Host ""
Write-Host "🚀 [3/4] Ejecutando despliegue en el servidor..." -ForegroundColor Green
Write-Host "   (Esto puede tardar unos minutos...)" -ForegroundColor DarkGray
Write-Host ""

ssh -p $SERVIDOR_PORT "$SERVIDOR_USER@$SERVIDOR_IP" "cd /tmp/biblioteca-deploy && chmod +x deploy.sh && sudo bash deploy.sh"

# ─────────────────────────────────────────────────────────
# Paso 4: Limpieza
# ─────────────────────────────────────────────────────────
Write-Host ""
Write-Host "🧹 [4/4] Limpiando archivos temporales..." -ForegroundColor Green

ssh -p $SERVIDOR_PORT "$SERVIDOR_USER@$SERVIDOR_IP" "rm -rf /tmp/biblioteca-deploy"

Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║           ✅ ¡DESPLIEGUE COMPLETADO!                    ║" -ForegroundColor Green
Write-Host "╠══════════════════════════════════════════════════════════╣" -ForegroundColor Green
Write-Host "║                                                          ║" -ForegroundColor Green
Write-Host "║  🌐 Tu aplicación está disponible en:                    ║" -ForegroundColor Green
Write-Host "║     http://$SERVIDOR_IP/                                 ║" -ForegroundColor Green
Write-Host "║                                                          ║" -ForegroundColor Green
Write-Host "║  🔑 Credenciales:                                        ║" -ForegroundColor Green
Write-Host "║     admin@biblioteca.com / admin123                      ║" -ForegroundColor Green
Write-Host "║                                                          ║" -ForegroundColor Green
Write-Host "║  Cualquier persona puede acceder desde cualquier         ║" -ForegroundColor Green
Write-Host "║  computadora usando esa URL.                             ║" -ForegroundColor Green
Write-Host "║                                                          ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Read-Host "Presiona Enter para salir"
