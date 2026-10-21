# Script para crear un acceso directo en el escritorio
# que abre la aplicación de Gestión de Biblioteca directamente

$DesktopPath = [Environment]::GetFolderPath("Desktop")
$ShortcutPath = Join-Path $DesktopPath "Gestión de Biblioteca.lnk"

$WshShell = New-Object -ComObject WScript.Shell
$Shortcut = $WshShell.CreateShortcut($ShortcutPath)

# URL de la aplicación
$Shortcut.TargetPath = "http://localhost/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/books.php"

# Icono (usa el icono de Internet Explorer / navegador web genérico de Windows)
$Shortcut.IconLocation = "shell32.dll,14"

$Shortcut.Description = "Abrir Gestión de Biblioteca"
$Shortcut.Save()

Write-Host ""
Write-Host "✅ Acceso directo creado exitosamente en el escritorio!" -ForegroundColor Green
Write-Host "   Nombre: 'Gestión de Biblioteca'" -ForegroundColor Cyan
Write-Host "   URL: http://localhost/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/books.php" -ForegroundColor Cyan
Write-Host ""
Write-Host "Haz doble clic en el acceso directo del escritorio para abrir la app." -ForegroundColor Yellow
