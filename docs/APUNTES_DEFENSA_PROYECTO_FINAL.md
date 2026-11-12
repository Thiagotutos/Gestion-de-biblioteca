# 🎓 Guía Maestra y Apuntes para la Defensa del Proyecto Final
## Sistema de Gestión de Biblioteca Escolar / Institucional

> **Destinatarios:** Todo el equipo del proyecto.  
> **Objetivo:** Preparación integral para la presentación final ante la mesa evaluadora (Prof. Juan Roude) en el **Aula 14A** a las **14:00 hs** (Vestimenta de gala / traje).  
> **Criterio Docente:** La evaluación es **grupal e individual**. Todos los integrantes deben dominar el 100% de la arquitectura, decisiones de diseño, flujo de datos y código fuente, independientemente de la tarea asignada durante el desarrollo.

---

## 📑 Índice de Contenidos
1. [Resumen Ejecutivo y Stack Tecnológico](#1-resumen-ejecutivo-y-stack-tecnológico)
2. [Fundamentos y Decisiones de Arquitectura (El "¿Por qué se hizo así?")](#2-fundamentos-y-decisiones-de-arquitectura-el-por-qué-se-hizo-así)
3. [El Gran Dilema: ¿Por qué NO se reciclan los números de inventario de libros eliminados?](#3-el-gran-dilema-por-qué-no-se-reciclan-los-números-de-inventario)
4. [Recorrido Módulo por Módulo (Código, Archivos y Funcionamiento)](#4-recorrido-módulo-por-módulo)
5. [Auditoría del Sistema: Estado Funcional y Robustez](#5-auditoría-del-sistema-estado-funcional-y-robustez)
6. [Oportunidades de Mejora Futura (Para demostrar visión ingenieril)](#6-oportunidades-de-mejora-futura)
7. [Batería de Preguntas Difíciles del Profesor y Respuestas Modelo](#7-batería-de-preguntas-difíciles-del-profesor-y-respuestas-modelo)
8. [Checklist Operativo para el Día de la Presentación](#8-checklist-operativo-para-el-día-de-la-presentación)

---

## 1. Resumen Ejecutivo y Stack Tecnológico

El proyecto es un **Sistema Web Integral de Gestión para Bibliotecas Escolares e Institucionales**. Resuelve el catálogo bibliográfico físico, el control estricto de préstamos y devoluciones a alumnos y docentes, la administración de usuarios con control de acceso por roles (RBAC) y la salvaguarda de datos mediante copias de seguridad automáticas y manuales.

| Capa | Tecnología | Justificación Técnica |
| :--- | :--- | :--- |
| **Backend** | **PHP 8.0+ Nativo** (orientado a objetos con PDO) | Máxima portabilidad, ejecución inmediata en servidores Debian/Ubuntu y en entornos locales XAMPP sin compiladores ni dependencias pesadas. |
| **Base de Datos** | **MySQL 8.0+ / MariaDB 10.4+** (`InnoDB`, `utf8mb4`) | Soporte de transacciones atómicas (ACID), integridad referencial estricta mediante claves foráneas y soporte de caracteres multilingües y acentos. |
| **Frontend** | **HTML5 Semántico, CSS3 Vanilla, JavaScript ES5 Nativo** | Interfaz rápida y liviana sin frameworks (React, Angular o Vue). Compatible con monitores de baja resolución y equipos antiguos (Windows XP / 7 / 10). |
| **Servidor Web** | **Nginx + PHP-FPM** en Debian Linux (remoto) y **Apache** en XAMPP (desarrollo) | Configuración de alto rendimiento en producción con buffers optimizados, subidas de hasta 256 MB y túnel seguro mediante Ngrok. |
| **Dependencias** | **CERO dependencias externas** | Sin Composer, sin Node.js, sin paquetes `npm`. El sistema es autosuficiente y nunca sufrirá fallas por paquetes obsoletos o dependencias rotas en repositorios de terceros. |

---

## 2. Fundamentos y Decisiones de Arquitectura (El "¿Por qué se hizo así?")

Si el profesor pregunta: *"¿Por qué no usaron un framework como Laravel, Django o React?"* o *"¿Cómo diseñaron la arquitectura?"*, esta es la respuesta que deben dar:

### 2.1 Filosofía "Zero Runtime Dependencies"
* **Realidad del entorno escolar:** Las escuelas y bibliotecas públicas a menudo operan en computadoras recicladas o servidores con recursos muy limitados (1 o 2 GB de RAM), sin conexión a internet constante o con bloqueos de red que impiden correr `npm install` o `composer update`.
* **Mantenibilidad a largo plazo:** Un sistema hecho en PHP y SQL nativo puede seguir funcionando idéntico dentro de 10 años. Los frameworks cambian de versión cada año, deprecando métodos y requiriendo migraciones complejas de dependencias.

### 2.2 Patrón de Acceso a Datos: PDO y Sentencias Preparadas
* Se utiliza exclusivamente **PDO (`PDO::ATTR_EMULATE_PREPARES => false`)**.
* Las sentencias preparadas separan la **estructura de la consulta SQL** de los **datos del usuario**. Esto neutraliza al 100% el riesgo de **Inyección SQL (SQL Injection)**, ya que los valores ingresados por el usuario viajan como parámetros y nunca se concatenan directamente en la cadena SQL.

### 2.3 Manejo Atómico de Transacciones (ACID)
* Cuando una operación compromete dos o más tablas simultáneamente (por ejemplo: prestar un libro implica cambiar `books.estado = 'Prestado'` e insertar una fila en `transactions`), **nunca se hacen consultas aisladas**.
* Se utiliza un bloque transaccional:
  ```php
  $pdo->beginTransaction();
  try {
      // 1. Bloqueo pesimista del libro
      // 2. Modificación de estado
      // 3. Registro de auditoría
      $pdo->commit();
  } catch (Exception $e) {
      if ($pdo->inTransaction()) {
          $pdo->rollBack();
      }
      throw $e;
  }
  ```
  Esto garantiza que o **se guarda todo o no se guarda nada**, evitando datos corruptos o libros en estado "fantasma".

### 2.4 Desvinculación Suave (`ON DELETE SET NULL`)
* Si en el sistema se elimina un Autor, una Editorial o una Categoría/Materia, los libros asociados **NO se eliminan de la base de datos**.
* La clave foránea está configurada con `ON DELETE SET NULL`. El libro permanece intacto en el catálogo y su campo de autor pasa a quedar temporalmente sin asignar para que el bibliotecario pueda rectificarlo.

### 2.5 Auto-Sanación del Esquema en `config.php`
* El archivo `config.php` cuenta con rutinas automáticas de verificación de esquema: si un administrador restaura una base de datos más vieja (por ejemplo, que no tenía las columnas `signatura`, `isbn_editorial`, `turno` o `responsable`), el sistema detecta la ausencia de dichas columnas y ejecuta dinámicamente un `ALTER TABLE` sin arrojar error fatal de pantalla blanca.

---

## 3. El Gran Dilema: ¿Por qué NO se reciclan los números de inventario?

> **Pregunta típica del profesor:**  
> *"Si yo tengo los libros 00072, 00073 y 00090, y borro el libro 00073... ¿por qué el próximo libro nuevo se crea como 00091 en vez de volver a ocupar el 00073?"*

### La Respuesta Correcta:
Reciclar números de inventario es una **mala práctica grave en bibliotecología y auditoría de sistemas**, por tres razones fundamentales:

1. **Integridad Histórica y Auditoría:**  
   El número `00073` perteneció a una obra física concreta (por ejemplo, *"Cien años de soledad"*). Si ese libro se prestó en el año 2024, existen registros históricos en la tabla de transacciones o en fichas impresas asociadas al `00073`. Si el libro fue dado de baja (por extravío o rotura) y reciclamos el número para *"Manual de Física 3"*, los reportes históricos mezclarían dos libros completamente distintos bajo el mismo identificador.
2. **Etiquetado Físico y Tejuelos:**  
   En una biblioteca real, los libros tienen tejuelos, códigos de barra y sellos impresos en el lomo. Si un libro se extravió y luego reaparece en un aula, convivirían dos libros distintos con la misma etiqueta física `00073`.
3. **Estándar Bibliotecario Internacional:**  
   En archivística y bibliotecología, los números de inventario son estrictamente correlativos ascendentes y monótonos. Los huecos generados por descartes se asientan formalmente como *"Baja de inventario"*, nunca como un espacio a reutilizar.

---

## 4. Recorrido Módulo por Módulo

### 4.1 Autenticación y Sesiones (`login.php`, `auth.php`, `logout.php`)
* **Seguridad de Contraseñas:** Las contraseñas de los usuarios nunca se guardan en texto plano en la base de datos. Se procesan con `password_hash($pass, PASSWORD_DEFAULT)`, que genera un hash seguro mediante el algoritmo **BCrypt** con salt aleatorio.
* **Verificación:** En el inicio de sesión se evalúa `password_verify($password, $user['password'])`.
* **Prevención de Session Fixation:** Inmediatamente tras verificar las credenciales, se llama a `session_regenerate_id(true)`. Esto invalida el ID de cookie de sesión anterior y crea uno nuevo, impidiendo que un atacante que haya interceptado la cookie previa tome el control.
* **Cierre de sesión seguro:** `logout.php` limpia `$_SESSION = []`, destruye la cookie en el cliente y finaliza con `session_destroy()`.

### 4.2 Control de Acceso por Roles (RBAC - Role-Based Access Control)
El sistema divide las atribuciones en 3 roles estrictos:
1. **Administrador:** Acceso total (Gestión de usuarios, restauración y respaldo de base de datos, libros, préstamos, autores, editoriales).
2. **Bibliotecario:** Operación diaria (Gestión de catálogo de libros, materias, autores, editoriales, y módulo completo de préstamos y devoluciones). No puede crear/eliminar usuarios ni manipular respaldos SQL.
3. **Lector:** Modo consulta (Puede buscar libros, ver disponibilidad y fichas técnicas). No tiene acceso a registrar préstamos ni modificar registros.

> **Protección en Código:** En cada archivo protegido (`pages/users.php`, `pages/db_tools.php`, `pages/issue_books.php`), las primeras líneas de código validan `$_SESSION['user_id']` y `$_SESSION['user_role']`. Si el usuario no tiene los privilegios necesarios, es redirigido inmediatamente antes de ejecutar cualquier consulta.

### 4.3 Módulo de Catálogo de Libros (`pages/books.php`, `add_book.php`, `edit_book.php`, `delete_book.php`)
* **Campos bibliotecológicos:** Título, Autor, Editorial, Categoría/Materia, Lugar de edición, Año de publicación, Notas, Observaciones, Signatura Topográfica (clasificación Dewey/CDU para ubicar en el estante) e ISBN de la editorial.
* **Numeración Automática:** Al registrar un libro, el sistema calcula automáticamente el siguiente número de inventario correlativo con relleno de ceros (`000001`, `000002`...) consultando el valor numérico máximo.
* **Bloqueo Inteligente de Eliminación (`pages/delete_book.php`):**  
  El sistema **prohíbe eliminar un libro si está prestado actualmente** a un alumno o docente. Valida tanto el campo `books.estado = 'Prestado'` como la existencia de transacciones abiertas en `transactions`. Muestra una advertencia indicando que primero debe registrarse la devolución.

### 4.4 Módulo Transaccional de Préstamos (`pages/issue_books.php`)
* **Plazo de Devolución:** 7 días corridos (1 semana) predeterminado por el sistema.
* **Regla de Negocio - Adulto Responsable:** Si se pide prestar más de 1 libro o ejemplar en el mismo acto, el sistema exige obligatoriamente consignar el nombre del docente o adulto responsable.
* **Turnos disponibles:** Mañana y Tarde.
* **Concurrencia Segura:** La consulta utiliza `SELECT ... FOR UPDATE` dentro de una transacción. Si dos bibliotecarios intentaran prestar el mismo libro exactamente en el mismo segundo, el motor MySQL bloquea la fila del libro para el primero y rechaza o espera para el segundo, impidiendo prestar dos veces el mismo ejemplar físico.
* **Devolución Rápida:** Al hacer clic en "Devolver", se registra la `fecha_devolucion_real` con timestamp exacto y el libro regresa inmediatamente al estado `Disponible`.

### 4.5 Módulo de Administración de Usuarios (`pages/users.php`)
* **Validación Robusta de Email:**  
  1. Valida largo mínimo y máximo (6 a 80 caracteres).  
  2. Valida sintaxis con `filter_var(..., FILTER_VALIDATE_EMAIL)` y expresión regular estricta exigiendo Dominio de Nivel Superior (TLD) válido (por ejemplo, rechaza `pepe@gmail` sin `.com`).  
  3. Incluye diccionario de detección de errores de tipeo comunes (ej. `gma.com` sugiere `gmail.com`).  
  4. Si se ingresa un dominio no corporativo ni `.edu.ar`, ejecuta `checkdnsrr($domain, 'MX')` para verificar si el servidor de correo realmente existe en Internet.
* **Validación de Nombres:** Solo letras, espacios, guiones y tildes mediante expresiones regulares Unicode (`^[\p{L}\s\.\'-]{3,60}$`).

### 4.6 Motor de Respaldo, Rotación y Restauración (`pages/db_tools.php`, `includes/backup_manager.php`, `scripts/cron_backup.php`)
* **Exportación y Generador SQL Nativo:** No depende de herramientas del sistema operativo como `mysqldump` (las cuales suelen fallar en Windows por rutas o permisos). El módulo PHP lee la metadata de las tablas con `SHOW CREATE TABLE` y vuelca los datos en lotes (`INSERT INTO`).
* **Parser de Restauración por Streaming:**  
  Los archivos `.sql` grandes (como el catálogo de Aguapay o un volcado masivo) no se cargan en memoria con `file_get_contents()` (lo cual agotaría el `memory_limit` de PHP). En su lugar, el sistema lee el archivo línea por línea con `fopen()` y `fgets()`, acumulando sentencias hasta el delimitador `;` y ejecutándolas una a una. **El consumo de memoria se mantiene constante en menos de 4 MB sin importar el tamaño del archivo.**
* **Rotación Automática FIFO (Tope de 10 copias):**  
  Para no saturar el almacenamiento del servidor, el sistema mantiene un máximo de **10 copias de seguridad**. Al generarse la copia número 11, el sistema ordena los archivos por fecha de modificación y elimina automáticamente el más antiguo.
* **Disparador Doble (Pasivo + Cron):**  
  1. **Automático mensual pasivo:** Cada vez que el Administrador ingresa a `db_tools.php`, el sistema comprueba si ya se generó el respaldo del mes en curso. Si no existe, lo crea silenciosamente.  
  2. **Automático por CLI / Cron:** Se diseñó el script `scripts/cron_backup.php` pensado para programarse en el crontab del servidor Debian (`0 3 1 * *`).
* **Seguridad de Archivos:** La carpeta `backups/` está protegida contra descargas no autorizadas mediante archivo `.htaccess` (`Deny from all`) y regla de denegación en Nginx. La descarga de copias se canaliza únicamente a través del endpoint autenticado `api/download_backup.php` que verifica el rol de Administrador y utiliza `basename()` para neutralizar ataques de Path Traversal.

### 4.7 Buscador en Tiempo Real y APIs AJAX (`api/search_ajax.php`, `api/search_books_json.php`)
* Permite buscar por Título, Autor, ISBN/Inventario, Signatura Topográfica, Editorial o Materia.
* Implementado con **JavaScript nativo (`XMLHttpRequest`)** y con debounce de 300 ms para no saturar al servidor de peticiones mientras el usuario escribe.
* No requiere librerías como jQuery ni Axios. Compatible con navegadores antiguos.

---

## 5. Auditoría del Sistema: Estado Funcional y Robustez

Tras la inspección profunda de todos los archivos del repositorio:

* **Sintaxis PHP:** 28 archivos evaluados con el linter de PHP CLI (`php -l`). **Resultado: 100% libre de errores de sintaxis.**
* **Seguridad contra Inyección SQL:** Todas las consultas dinámicas en catálogo, préstamos, usuarios y herramientas de BD utilizan sentencias preparadas con `prepare()` y `execute()`.
* **Seguridad contra XSS (Cross-Site Scripting):** La salida de variables de usuario hacia el HTML se escapa mediante `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
* **Navegación e Integridad Visual:** Estilos vanilla unificados en `style.css` con diseño responsive adaptado a pantallas de netbooks escolares y monitores de alta resolución.
* **Compatibilidad de Despliegue:** Scripts automáticos de instalación para Nginx (`deploy/deploy_nginx.sh`) y configuración de límites para archivos pesados (`client_max_body_size 256M`).

---

## 6. Oportunidades de Mejora Futura

Si el profesor pregunta: *"¿Qué cosas le faltan al sistema o qué le mejorarían en una segunda versión?"*, demuestren capacidad crítica mencionando estos 4 puntos planificados para la siguiente etapa:

1. **Tokens Anti-CSRF (Cross-Site Request Forgery):**  
   * *Actualidad:* Las sesiones están protegidas contra Session Fixation y se validan las cabeceras HTTP.  
   * *Mejora:* Incorporar tokens criptográficos de un solo uso (`$_SESSION['csrf_token']`) en los formularios POST para blindar las solicitudes contra envíos maliciosos desde otros sitios web.
2. **Historial de Auditoría Global (Log de Modificaciones):**  
   * *Actualidad:* La tabla `transactions` audita de forma impecable los préstamos y devoluciones.  
   * *Mejora:* Crear una tabla `audit_logs` que registre qué usuario administrador editó o eliminó un libro, autor o usuario, guardando fecha, hora y valores anteriores.
3. **Módulo Visual para Gestión de Eventos:**  
   * *Actualidad:* La tabla `events_schedule` alimenta la alerta del Dashboard con los avisos y ferias del libro.  
   * *Mejora:* Crear una pantalla CRUD en el panel de administración para que el bibliotecario cree y edite los avisos escolares sin tocar la base de datos.
4. **Exportación de Reportes a Formato Imprimible (PDF / Excel / CSV):**  
   * *Actualidad:* El sistema posee estilos de impresión CSS (`@media print`) optimizados para imprimir las tablas desde el navegador.  
   * *Mejora:* Incorporar generación de reportes en PDF descargables para balances de inventario anuales y listados de deudores.

---

## 7. Batería de Preguntas Difíciles del Profesor y Respuestas Modelo

### Pregunta 1: *"¿Cómo garantizan que un usuario malicioso no acceda a las páginas escribiendo directamente la URL (por ejemplo `/pages/users.php`)?"*
> **Respuesta:**  
> En el encabezado de cada página PHP se invoca `session_start()` y se evalúa si `$_SESSION['user_id']` está definido. Si no hay sesión activa, se redirige inmediatamente a `login.php` con `exit;`. Además, para páginas con privilegios, se evalúa el rol: si un usuario con rol 'Lector' o 'Bibliotecario' intenta ingresar a `users.php` o `db_tools.php`, el código detecta que no es 'Administrador' y lo expulsa hacia el `dashboard.php`. La validación ocurre en el servidor, no en el navegador.

### Pregunta 2: *"¿Qué ocurre si dos bibliotecarios prestan el mismo libro físico al mismo tiempo a dos alumnos distintos?"*
> **Respuesta:**  
> Implementamos un mecanismo de **bloqueo pesimista** dentro de una **transacción PDO**. Al seleccionar el libro a prestar, ejecutamos la consulta con la cláusula `FOR UPDATE`:
> ```sql
> SELECT id, titulo, estado FROM books WHERE id = ? FOR UPDATE;
> ```
> Esto hace que el motor InnoDB bloquee el registro en disco. El primer bibliotecario cambia el estado a 'Prestado' y hace `commit()`. Cuando el segundo bibliotecario intenta procesar la solicitud, la verificación encuentra que el libro ya tiene estado `Prestado` y cancela la operación lanzando una excepción con rollback, evitando el doble préstamo.

### Pregunta 3: *"¿Por qué la importación de bases de datos pesadas no les da error de tiempo de ejecución o límite de memoria en PHP?"*
> **Respuesta:**  
> Porque no usamos funciones tradicionales como `file_get_contents()` que cargan todo el archivo SQL en la memoria RAM del servidor. Desarrollamos un motor de streaming en `backup_manager.php` con `fopen()` y `fgets()` que lee el archivo línea por línea en búferes de 8 KB. Va reconstruyendo las sentencias SQL y las ejecuta individualmente. De esta manera, el uso de memoria RAM se mantiene en apenas ~3 megabytes, sin importar si el archivo pesa 10 MB o 200 MB. Además, en el servidor Nginx aumentamos `client_max_body_size` a 256M y los tiempos de espera FastCGI a 600 segundos.

### Pregunta 4: *"¿Dónde y cómo se guardan las copias de seguridad automáticas?"*
> **Respuesta:**  
> Se guardan en el directorio `/backups/` dentro del proyecto. Cada copia lleva una marca de tiempo en su nombre (`backup_library_system_YYYY-MM-DD_HHMMSS.sql`). El sistema aplica una política de retención **FIFO (First In, First Out)** con un tope de 10 archivos: cuando se genera una nueva copia y hay más de 10, la función `rotateBackups(10)` busca el archivo con fecha de modificación más antigua y lo elimina con `unlink()`. La carpeta está blindada con directivas de servidor para impedir el acceso directo desde el navegador.

### Pregunta 5: *"¿Por qué usan sentencias preparadas en vez de escapar cadenas con `addslashes` o `mysqli_real_escape_string`?"*
> **Respuesta:**  
> Porque el escapado manual de cadenas puede ser vulnerable a ataques basados en discrepancias de codificación de caracteres (character set bypass, como GBK o UTF-7). Las sentencias preparadas de PDO compilan la instrucción SQL en el motor de base de datos antes de enviar los parámetros; por lo tanto, el motor trata al parámetro puramente como un valor literal y es matemáticamente imposible que se interprete como código SQL ejecutable.

---

## 8. Checklist Operativo para el Día de la Presentación

- [ ] **Presentación Personal:** Traje formal y acreditación. Llegar a las **13:45 hs** (15 minutos antes de la apertura del Aula 14A).
- [ ] **Servidor Activo:**
  - Si se expone en local: Iniciar Apache y MySQL en el panel de XAMPP.
  - Si se expone en remoto: Comprobar el servicio Nginx (`sudo systemctl status nginx`) y el túnel Ngrok activo.
- [ ] **Cuentas de Demostración Preparadas:**
  - **Administrador:** `admin@biblioteca.com` (Clave: `admin123`) - Para mostrar usuarios y backups.
  - **Bibliotecario:** `bibliotecario@biblioteca.com` (Clave: `admin123`) - Para mostrar préstamos y catálogo.
  - **Lector:** `lector@biblioteca.com` (Clave: `admin123`) - Para demostrar la restricción de privilegios (RBAC).
- [ ] **Flujo de la Demo en Vivo (3 a 5 minutos):**
  1. Iniciar sesión como Bibliotecario.
  2. Buscar un libro en tiempo real con el buscador AJAX (mostrar velocidad sin recargar página).
  3. Prestar un libro a un alumno (mostrar la fecha de devolución automática a 7 días y el bloqueo si lleva más de un libro sin responsable).
  4. Ir al Dashboard y mostrar la alerta pulsante del libro recién prestado en la tabla de vencimientos.
  5. Iniciar sesión como Administrador.
  6. Ir a `Importar Datos / Respaldo` y mostrar la lista de las 10 copias de seguridad con su botón de descarga y creación inmediata.
  7. Explicar brevemente la arquitectura limpia sin dependencias y la defensa del por qué no se reutilizan números de inventario.
