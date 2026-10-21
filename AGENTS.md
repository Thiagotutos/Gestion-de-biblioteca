# AGENTS.md - Directrices para Modelos y Asistentes de IA

Este archivo define las instrucciones operativas, arquitectura y restricciones para que cualquier agente de Inteligencia Artificial (AI Coding Assistants, LLMs) trabaje eficientemente en este repositorio sin introducir regresiones.

---

## 🏗️ 1. Descripción y Stack Tecnológico
- **Dominio**: Sistema Web de Gestión de Biblioteca Escolar / Institucional.
- **Backend**: PHP 8.0+ nativo utilizando PDO (`pdo_mysql`).
- **Base de Datos**: MySQL 8.0+ / MariaDB 10.4+ (`library_system`).
- **Frontend**: HTML5, CSS3 Vainilla y JavaScript Nativo (Vanilla JS / `XMLHttpRequest`).
- **Filosofía**: Cero dependencias en tiempo de ejecución (sin Composer, sin Node, sin npm, sin frameworks pesados).

---

## 📂 2. Mapa Rápido de Archivos Críticos
- [`config.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/config.php): Conexión PDO centralizada (`PDO::ATTR_EMULATE_PREPARES => false`).
- [`database/database.sql`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/database/database.sql): Esquema DDL e inserción de datos iniciales.
- [`docs/DOCUMENTACION.md`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/docs/DOCUMENTACION.md): Documentación canónica bajo metodología **Diátaxis**.
- [`docs/der_sistema.png`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/docs/der_sistema.png): Diagrama Entidad-Relación visual.
- [`api/search_ajax.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/api/search_ajax.php): Motor de renderizado parcial para búsquedas en tiempo real.
- [`api/search_books_json.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/api/search_books_json.php): Endpoint JSON para selector de libros en préstamos.
- [`pages/issue_books.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/pages/issue_books.php): Módulo transaccional de préstamos y devoluciones.
- [`pages/db_tools.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/pages/db_tools.php): Herramientas de respaldo y restauración de base de datos.
- [`scripts_migracion/migrate_aguapay.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/scripts_migracion/migrate_aguapay.php): Parser e importador de registros MARC / ISO 2709.

---

## ⚠️ 3. Restricciones y Reglas de Desarrollo
1. **Seguridad SQL**: Nunca concatenar variables de entrada en consultas SQL. Utilizar siempre sentencias preparadas con `$pdo->prepare()` y marcadores posicionales o por nombre (`:param`).
2. **Transacciones Atómicas**: Cualquier operación que modifique estados cruzados (como `books.status` y `transactions`) debe ejecutarse dentro de un bloque `$pdo->beginTransaction()` y confirmar con `$pdo->commit()`. Al capturar excepciones, siempre verificar `$pdo->inTransaction()` antes de llamar a `$pdo->rollBack()`.
3. **Persistencia de Integridad**: Respetar la regla de desvinculación suave (`ON DELETE SET NULL`) para autores, categorías y editoriales.
4. **Docs-as-Code**: Si agregas nuevas funcionalidades, actualiza [`DOCUMENTACION.md`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/DOCUMENTACION.md) en su cuadrante correspondiente (Tutorial, Guía, Referencia o Explicación).

---

## 💻 4. Comandos de Verificación
```powershell
# Levantar servidor de desarrollo
php -S localhost:8000 -t "c:\xampp\htdocs\x\Gestion-de-biblioteca-main\Gestion-de-biblioteca-main"

# Ejecutar migración de prueba
php "c:\xampp\htdocs\x\Gestion-de-biblioteca-main\Gestion-de-biblioteca-main\scripts_migracion\migrate_aguapay.php"
```
