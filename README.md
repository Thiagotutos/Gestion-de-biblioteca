# Sistema de Gestión de Biblioteca

Un sistema web completo y liviano desarrollado en **PHP** y **MySQL** para la administración integral de bibliotecas escolares, públicas o comunitarias.

Incluye panel de control (Dashboard), gestión de libros, autores, categorías, editoriales, estantes, control de préstamos y devoluciones, control de usuarios con roles y búsqueda instantánea (AJAX).

---

## 🚀 Inicio Rápido para Pruebas

Este repositorio incluye un esquema de base de datos preparado (`database.sql`) con datos de demostración para que cualquiera pueda probar el sistema inmediatamente tras descargarlo, **sin requerir archivos privados ni bases de datos de la escuela**.

### 1. Requisitos Previos
* Servidor local como **XAMPP**, **Laragon**, **WampServer** o servidor LAMP/LEMP.
* **PHP** 7.4 o superior (compatible con PHP 8.x).
* **MySQL** 5.7+ o **MariaDB** 10.3+.

### 2. Instalación
1. Descarga o clona este repositorio en tu directorio de servidor web (por ejemplo, en `htdocs` para XAMPP o `www` para Laragon):
   ```bash
   git clone https://github.com/tu-usuario/Gestion-de-biblioteca.git
   ```
2. Inicia los servicios de **Apache** y **MySQL**.
3. Importa la base de datos:
   * Abre **phpMyAdmin** (`http://localhost/phpmyadmin/`) o tu cliente MySQL favorito.
   * Importa el archivo [`database.sql`](file:///c:/Users/thiag/OneDrive/Desktop/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/database.sql).
   * *(El script creará automáticamente la base de datos `library_system` y todas las tablas con datos de prueba)*.
4. Revisa [`config.php`](file:///c:/Users/thiag/OneDrive/Desktop/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/config.php) si tu servidor MySQL utiliza un usuario o contraseña diferente (por defecto: `root` sin contraseña).
5. Abre el sistema en tu navegador:
   ```
   http://localhost/Gestion-de-biblioteca-main/
   ```

---

## 🔐 Credenciales de Prueba

Todos los usuarios de prueba tienen la contraseña predeterminada: **`admin123`**

| Rol | Correo Electrónico | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@biblioteca.com` | `admin123` | Acceso total: gestión de libros, usuarios, préstamos y catálogos |
| **Bibliotecario** | `bibliotecario@biblioteca.com` | `admin123` | Gestión de libros, préstamos, autores y catálogos |
| **Lector** | `lector@biblioteca.com` | `admin123` | Consulta de inventario, catálogo y disponibilidad de libros |

---

## 📚 Datos de Demostración Incluidos

El esquema [`database.sql`](file:///c:/Users/thiag/OneDrive/Desktop/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/database.sql) viene preconfigurado con:
* **25 Libros de muestra** clásicos y universales (García Márquez, Borges, Cortázar, Orwell, Asimov, Sagan, etc.).
* **Estados variados**:
  * 21 libros **Disponibles** (listos para solicitar o prestar).
  * 3 libros **Prestados** con transacciones activas visibles en el módulo de Préstamos para probar la devolución con 1 clic.
  * 1 libro **Extraviado** para probar las alertas automáticas del Dashboard.
* **16 Autores**, **10 Categorías**, **9 Editoriales** y **6 Estanterías/Ubicaciones**.
* **Préstamos activos e historial** con lectores de prueba asociados.
* **Eventos próximos** con fechas dinámicas que se muestran automáticamente en el Dashboard.

---

## 🔒 Privacidad y Seguridad

* **Sin datos privados**: No se incluyen archivos `.iso`, `.mst`, `.xrf` ni registros confidenciales de alumnos o inventario real de la institución educativa.
* Las carpetas y archivos privados de migración están excluidos en [`.gitignore`](file:///c:/Users/thiag/OneDrive/Desktop/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/.gitignore).

---

## 🛠️ Estructura Principal del Proyecto

* [`index.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/index.php): Redirección inicial según estado de sesión.
* [`login.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/login.php) / [`auth.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/auth.php): Autenticación y verificación segura de credenciales (`password_verify`).
* [`pages/`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/pages): Vistas y módulos principales (`dashboard.php`, `books.php`, `add_book.php`, `edit_book.php`, `issue_books.php`, `db_tools.php`, etc.).
* [`api/`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/api): Endpoints AJAX/JSON (`search_ajax.php`, `search_books_json.php`, `export_db.php`).
* [`database/`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/database): Esquema SQL (`database.sql`) y migraciones versionadas.
* [`deploy/`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/deploy): Configuración Nginx (`nginx.conf`) y scripts de despliegue automatizado.
* [`docs/`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/docs): Documentación canónica (`DOCUMENTACION.md`) y diagrama entidad-relación.
* [`includes/`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/includes): Plantillas comunes de interfaz (`header.php`, `sidebar.php`, `footer.php`).
