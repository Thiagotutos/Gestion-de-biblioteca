# DOCUMENTACIÓN INTERNA DE ARQUITECTURA Y MANTENIMIENTO
## SISTEMA DE GESTIÓN DE BIBLIOTECA ESCOLAR

> [!IMPORTANT]
> Este documento técnico contiene la especificación de arquitectura interna, decisiones de diseño, estándares de código, seguridad y procedimientos de despliegue para desarrolladores, mantenedores y administradores de sistemas.

---

## 📑 ÍNDICE

1. [Stack Tecnológico y Filosofía de Diseño](#1-stack-tecnológico-y-filosofía-de-diseño)
2. [Estructura del Proyecto y Mapa de Archivos](#2-estructura-del-proyecto-y-mapa-de-archivos)
3. [Capa de Persistencia y Seguridad de Datos](#3-capa-de-persistencia-y-seguridad-de-datos)
   - 3.1 Conexión Centralizada PDO
   - 3.2 Transacciones Atómicas (ACID)
   - 3.3 Políticas de Integridad Referencial
   - 3.4 Cifrado y Prevención de Vulnerabilidades
4. [Motor de Búsqueda en Tiempo Real y API Interna](#4-motor-de-búsqueda-en-tiempo-real-y-api-interna)
   - 4.1 Endpoint `api/search_ajax.php` (Renderizado Parcial)
   - 4.2 Endpoint `api/search_books_json.php` (Selector de Libros)
   - 4.3 Control de Caché Dinámico en CSS
5. [Mecanismo de Migración desde Aguapey (MARC / ISO 2709)](#5-mecanismo-de-migración-desde-aguapey-marc--iso-2709)
   - 5.1 Anatomía del Registro Aguapey
   - 5.2 Mapeo de Subcampos a Entidades Relacionales
   - 5.3 Normalización y Resolución de Conflictos
6. [Módulo de Respaldo y Restauración de Base de Datos](#6-módulo-de-respaldo-y-restauración-de-base-de-datos)
   - 6.1 Algoritmo del Exportador SQL (`api/export_db.php`)
   - 6.2 Proceso Seguro de Restauración (`pages/db_tools.php`)
7. [Despliegue y Configuración de Infraestructura en Linux Debian](#7-despliegue-y-configuración-de-infraestructura-en-linux-debian)
   - 7.1 Permisos del Sistema de Archivos
   - 7.2 Configuración del Servidor Web (Nginx / Apache)
   - 7.3 Red Local Wi-Fi e IP Estática
8. [Directrices para Contribuidores y Docs-as-Code](#8-directrices-para-contribuidores-y-docs-as-code)

---

## 1. STACK TECNOLÓGICO Y FILOSOFÍA DE DISEÑO

* **Lenguaje Backend**: PHP 8.0+ (Sintaxis nativa estricta, sin frameworks como Laravel ni dependencias Composer en runtime).
* **Motor de Base de Datos**: MySQL 8.0+ / MariaDB 10.4+ (Motor de almacenamiento InnoDB, cotejamiento `utf8mb4_unicode_ci`).
* **Frontend**: HTML5 Semántico, CSS3 Vainilla y JavaScript Nativo (Vanilla JS, `XMLHttpRequest` y API `Fetch`).
* **Filosofía**: **Cero Dependencias en Tiempo de Ejecución (Zero Runtime Dependencies)**.
  - El sistema no depende de Node.js, npm, Webpack, Composer ni CDNs de internet.
  - Esto garantiza que el software pueda ejecutarse indefinidamente en servidores locales aislados sin romperse por actualizaciones de paquetes de terceros o por caída de la conexión a internet.

---

## 2. ESTRUCTURA DEL PROYECTO Y MAPA DE ARCHIVOS

```
Gestion-de-biblioteca/
├── api/                             # Endpoints asíncronos y servicios backend
│   ├── export_db.php                # Generador de respaldos SQL al vuelo
│   ├── search_ajax.php              # Búsqueda en vivo y paginación para el catálogo
│   ├── search_books_json.php        # Autocompletado JSON para préstamos
│   └── search_entities_json.php     # Autocompletado para autores/categorías
├── database/                        # Esquemas y datos de producción
│   ├── database.sql                 # Esquema base DDL
│   └── database_produccion.sql      # Dump con los 7.756 libros migrados
├── deploy/                          # Scripts de despliegue para servidores
│   ├── deploy.sh                    # Configuración de permisos y servicios en Linux
│   ├── deploy_nginx.sh              # Aprovisionamiento automatizado de Nginx + PHP-FPM
│   ├── nginx.conf                   # Archivo de configuración de host virtual Nginx
│   └── sincronizar.ps1              # Script de sincronización SCP desde Windows
├── docs/                            # Documentación técnica y canónica
│   ├── der_sistema.png              # Diagrama Entidad-Relación gráfico
│   ├── DOCUMENTACION.md             # Documentación metodológica Diátaxis
│   ├── DOCUMENTACION_PROYECTO.md    # Especificación formal del proyecto
│   └── DOCUMENTACION_INTERNA.md     # Este documento (Arquitectura técnica)
├── includes/                        # Componentes de interfaz compartidos
│   ├── footer.php                   # Pie de página y cierre de etiquetas
│   ├── header.php                   # Cabecera, metas, estilos y control de sesión
│   └── sidebar.php                  # Menú lateral de navegación con roles
├── pages/                           # Controladores de vista de la aplicación
│   ├── add_book.php                 # Formulario de alta de ejemplares
│   ├── authors.php                  # ABM de Autores (Split Layout)
│   ├── books.php                    # Catálogo general con búsqueda multicriterio
│   ├── categories.php               # ABM de Categorías (Split Layout)
│   ├── dashboard.php                # Métricas del sistema y estado de apertura
│   ├── db_tools.php                 # Interfaz de copias de seguridad y restauración
│   ├── delete_book.php              # Controlador de baja física o lógica de libro
│   ├── edit_book.php                # Edición de libros y gestión de portadas
│   ├── issue_books.php              # Emisión y recepción de préstamos
│   ├── publishers.php               # ABM de Editoriales (Split Layout)
│   ├── racks.php                    # ABM de Estantes y ubicaciones
│   └── users.php                    # Administración de usuarios y roles
├── scripts_y_herramientas/          # Utilidades auxiliares (fuera de producción)
│   └── scripts_migracion/           # Herramientas de migración MARC / Aguapey
│       └── migrate_aguapay.php      # Parser e importador de registros ISO 2709
├── uploads/                         # Almacenamiento dinámico de archivos
│   └── books/                       # Imágenes de portadas de libros subidas
├── AGENTS.md                        # Directrices estrictas para agentes de IA
├── auth.php                         # Middleware de verificación de roles y sesión
├── config.php                       # Configuración y conexión PDO central
├── index.php                        # Enrutador inicial (redirección a login/dashboard)
├── login.php                        # Pantalla de inicio de sesión con Bcrypt
├── logout.php                       # Destrucción segura de sesiones PHP
└── style.css                        # Hoja de estilos universal responsiva
```

---

## 3. CAPA DE PERSISTENCIA Y SEGURIDAD DE DATOS

### 3.1 Conexión Centralizada PDO
Todas las operaciones de base de datos se canalizan a través de [`config.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/config.php). La instancia PDO se inicializa con parámetros defensivos:

```php
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Excepciones ante cualquier fallo
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Arrays asociativos limpios
    PDO::ATTR_EMULATE_PREPARES   => false,                   // Preparación REAL en motor MySQL
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"     // Soporte completo caracteres UTF-8
];
```

> [!WARNING]
> La directiva `PDO::ATTR_EMULATE_PREPARES => false` es obligatoria. Al desactivar la emulación, PHP delega la preparación de sentencias directamente al motor MySQL, imposibilitando los ataques de inyección SQL basados en codificación o bypass de comillas.

### 3.2 Transacciones Atómicas (ACID)
El sistema exige atomicidad estricta en operaciones donde intervengan cambios cruzados de estado. En particular, en la emisión y devolución de libros ([`pages/issue_books.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/pages/issue_books.php)):

```php
try {
    $pdo->beginTransaction();

    // 1. Verificar disponibilidad y bloquear fila con SELECT ... FOR UPDATE si es necesario
    // 2. Insertar movimiento en transactions
    $stmt1 = $pdo->prepare("INSERT INTO transactions (...) VALUES (...)");
    $stmt1->execute([...]);

    // 3. Modificar estado del ejemplar en books
    $stmt2 = $pdo->prepare("UPDATE books SET estado = 'Prestado' WHERE id = ?");
    $stmt2->execute([$book_id]);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error transaccional en préstamos: " . $e->getMessage());
}
```

### 3.3 Políticas de Integridad Referencial
Para prevenir la pérdida accidental de catálogos cuando un operador elimina una entidad secundaria:
* Las claves foráneas `author_id`, `publisher_id`, `category_id` y `rack_id` en la tabla `books` poseen la regla **`ON DELETE SET NULL`**. Si un autor o categoría es eliminado, los libros simplemente quedan con autor o categoría sin asignar (`NULL`), preservando el registro bibliográfico intacto.
* Las transacciones históricas (`transactions`) mantienen `ON DELETE CASCADE` solo respecto al libro si este es purgado de forma definitiva.

### 3.4 Cifrado y Prevención de Vulnerabilidades
* **Contraseñas**: Se procesan exclusivamente con `password_hash($pass, PASSWORD_BCRYPT)` en [`pages/users.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/pages/users.php) y se verifican mediante `password_verify($pass, $hash)` en [`login.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/login.php).
* **Prevención de XSS**: Toda salida de variables hacia el DOM HTML utiliza `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`.

---

## 4. MOTOR DE BÚSQUEDA EN TIEMPO REAL Y API INTERNA

### 4.1 Endpoint `api/search_ajax.php` (Renderizado Parcial)
El catálogo principal implementa una arquitectura híbrida de renderizado:
1. **Petición Cliente**: El script cliente en `pages/books.php` intercepta el evento `input` del teclado, aplica un *debounce* de 280 milisegundos y envía una petición `GET` asíncrona hacia `api/search_ajax.php`.
2. **Construcción Segura del Query**:
   El endpoint evalúa el parámetro `search_by`:
   - `all`: Busca simultáneamente en Título, Autor, Inventario, Categoría y Editorial mediante cláusulas `OR` enlazadas a parámetros preparados (`:term1`, `:term2`, etc.).
   - `title`, `author`, `isbn`, `category`, `publisher`: Filtra estrictamente sobre el campo seleccionado.
3. **Paginación en Servidor**: Ejecuta dos consultas PDO:
   - Una consulta `COUNT(*)` para computar el total de páginas.
   - Una consulta con `LIMIT 25 OFFSET :offset` para retornar únicamente los 25 registros visibles.
4. **Respuesta HTML**: Devuelve directamente las filas `<tr>` renderizadas junto con la barra de paginación `<div class="pagination">`, evitando el costo computacional de construir el DOM en navegadores de computadoras antiguas.

### 4.2 Endpoint `api/search_books_json.php` (Selector de Libros)
Utilizado por el módulo de Préstamos para autocompletar el libro mientras el bibliotecario escribe el título o número de inventario.
* Devuelve un JSON estructurado con `id`, `titulo`, `inventario`, `autor` y `estado`.
* Filtra exclusivamente libros con `estado = 'Disponible'`.

### 4.3 Control de Caché Dinámico en CSS
En estaciones de trabajo con navegadores antiguos, el almacenamiento en caché de archivos CSS solía impedir que los usuarios vieran de inmediato correcciones visuales.
Para solucionar esto sin intervención manual, [`includes/header.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/includes/header.php) implementa un parámetro de versionado automático (*Cache Busting*):
```php
<link rel="stylesheet" href="../style.css?v=<?php echo file_exists(__DIR__ . '/../style.css') ? filemtime(__DIR__ . '/../style.css') : time(); ?>">
```
Cada vez que se modifica y guarda `style.css`, su marca de modificación (`filemtime`) cambia, forzando a todos los navegadores de la red local a descargar la versión actualizada inmediatamente.

---

## 5. MECANISMO DE MIGRACIÓN DESDE AGUAPEY (MARC / ISO 2709)

El script [`scripts_y_herramientas/scripts_migracion/migrate_aguapay.php`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/scripts_y_herramientas/scripts_migracion/migrate_aguapay.php) implementa un parser binario nativo capaz de leer archivos de exportación de Aguapey en formato MARC 21 / ISO 2709.

### 5.1 Anatomía del Registro Aguapey
Cada registro bibliográfico en el archivo exportado consta de:
* **Líder (Leader)**: Primeros 24 bytes con metadatos de longitud del registro y posición inicial de los datos.
* **Directorio (Directory)**: Secuencia de entradas de 12 bytes que especifican la etiqueta de campo (Tag de 3 dígitos), la longitud del campo y su posición relativa.
* **Campos de Datos**: Delimitados por el carácter separador de campos `\x1E` y subcampos identificados por el delimitador `^` o `\x1F`.

### 5.2 Mapeo de Subcampos a Entidades Relacionales

| Campo MARC Aguapey | Subcampo | Significado en Aguapey | Destino en `library_system` |
| :---: | :---: | :--- | :--- |
| **`245`** | `^a` | Título principal de la obra | `books.titulo` |
| **`100` / `700`** | `^a` | Nombre del autor personal | Tabla normalizada `authors.name` ➔ `books.author_id` |
| **`260`** | `^a` | Lugar / Ciudad de publicación | `books.lugar_editorial` |
| **`260`** | `^b` | Nombre de la Editorial | Tabla normalizada `publishers.name` ➔ `books.publisher_id` |
| **`260`** | `^c` | Año de edición | Extraído y guardado en `books.fecha_publicacion` |
| **`859`** | `^a` | **Número de Inventario Escolar** | **`books.isbn`** (Identificador primario de búsqueda) |
| **`020`** | `^a` | Código ISBN comercial | `books.isbn` (usado como alternativa si `859^a` está vacío) |
| **`659` / `653` / `650`**| `^a` | Materia / Clasificación Temática | Tabla normalizada `categories.name` ➔ `books.category_id` |
| **`859`** | `^m` | Código de clasificación física | Mapeado a notas u observaciones internas |

### 5.3 Normalización y Resolución de Conflictos
1. **Extracción y Deduplicación**: El script compila en memoria conjuntos únicos (`Set`) de nombres de autores, editoriales y categorías.
2. **Inserción de Diccionarios**: Inserta las listas únicas en `authors`, `categories` y `publishers`, generando mapas asociativos en memoria `[Nombre => ID]`.
3. **Inserción de Libros**: Itera los más de 7.700 libros mapeando las claves foráneas correspondientes. Se implementaron bloques `try/catch` para capturar registros malformados o con títulos truncados.
4. **Archivo Generado**: El resultado limpio se exportó a [`database/database_produccion.sql`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/database/database_produccion.sql), libre de claves duplicadas y listo para importación directa en servidores nuevos.

---

## 6. MÓDULO DE RESPALDO Y RESTAURACIÓN DE BASE DE DATOS

### 6.1 Algoritmo del Exportador SQL (`api/export_db.php`)
1. **Control de Acceso**: Restringido exclusivamente al rol `Administrador`.
2. **Inspección de Tablas**: Ejecuta `SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'` para obtener la lista de tablas activas.
3. **Volcado DDL**: Para cada tabla, ejecuta `SHOW CREATE TABLE` e inyecta directivas `DROP TABLE IF EXISTS`.
4. **Volcado DML**: Itera los registros con cursor buffered, escapa los valores con `$pdo->quote()` y emite bloques `INSERT INTO` en grupos de hasta 100 filas para optimizar la velocidad de restauración.
5. **Streaming HTTP**: Envía las cabeceras `Content-Type: application/sql` y `Content-Disposition: attachment; filename="respaldo_..."` escribiendo directamente al buffer de salida `php://output`, lo que previene el desbordamiento de memoria RAM en el servidor.

### 6.2 Proceso Seguro de Restauración (`pages/db_tools.php`)
Cuando un administrador restaura un archivo `.sql`:
1. El script lee el contenido SQL subido.
2. Envía la instrucción preliminar **`SET FOREIGN_KEY_CHECKS = 0;`** para evitar fallos por dependencias de claves foráneas durante la reconstrucción de tablas.
3. Descompone y ejecuta las sentencias SQL en bloques delimitados.
4. Restablece la integridad referencial con **`SET FOREIGN_KEY_CHECKS = 1;`**.

---

## 7. DESPLIEGUE Y CONFIGURACIÓN DE INFRAESTRUCTURA EN LINUX DEBIAN

### 7.1 Permisos del Sistema de Archivos
En el servidor de producción (Debian 12 / Ubuntu Server), los archivos deben residir en `/var/www/Gestion-de-biblioteca`. Los permisos requeridos son:

```bash
# Otorgar propiedad al usuario del servidor web
sudo chown -R www-data:www-data /var/www/Gestion-de-biblioteca

# Permisos generales de directorios y archivos de código
sudo chmod -R 755 /var/www/Gestion-de-biblioteca

# Permiso de escritura exclusivo para la carpeta de portadas subidas
sudo chmod -R 775 /var/www/Gestion-de-biblioteca/uploads
```

### 7.2 Configuración del Servidor Web (Nginx / Apache)
Si se utiliza Nginx con PHP-FPM, el bloque de servidor configurado en [`deploy/nginx.conf`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/deploy/nginx.conf) es:

```nginx
server {
    listen 80;
    server_name _; # Responde a cualquier IP local o hostname
    root /var/www/Gestion-de-biblioteca;
    index index.php index.html;

    client_max_body_size 256M; # Permite subida de respaldos SQL grandes
    client_body_buffer_size 256k;
    client_body_timeout 600s;
    client_header_timeout 600s;
    large_client_header_buffers 4 32k;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # Procesamiento de scripts PHP mediante socket FastCGI
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        fastcgi_read_timeout 600;
        fastcgi_send_timeout 600;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }

    # Bloqueo de acceso directo a archivos sensibles
    location ~ /\.(git|env|sql) {
        deny all;
    }
}
```

### 7.3 Red Local Wi-Fi e IP Estática
Para que los clientes (computadoras de administración, terminales de consulta y teléfonos) accedan al sistema:
1. Se asigna una dirección IP estática al servidor en la interfaz de red (ejemplo: `192.168.1.100` o `192.168.1.50`).
2. Los clientes abren el navegador e ingresan `http://<IP_SERVIDOR>/`.
3. No se requiere configurar servidores DNS externos ni salida a internet.

---

## 8. DIRECTRICES PARA CONTRIBUIDORES Y DOCS-AS-CODE

Cualquier mantenimiento o modificación futura en el código fuente debe respetar las siguientes reglas obligatorias:

1. **Seguridad SQL Inviolable**: Nunca concatenar variables directamente en consultas SQL. Utilizar siempre sentencias preparadas con `$pdo->prepare()` y marcadores nombrados o posicionales.
2. **Manejo de Transacciones**: Toda operación que actualice estados compartidos entre tablas debe utilizar `$pdo->beginTransaction()`, `$pdo->commit()` y en el bloque `catch` evaluar `if ($pdo->inTransaction()) $pdo->rollBack()`.
3. **Integridad de Documentación (Docs-as-Code)**: Si se agrega una nueva funcionalidad o se altera la base de datos:
   - Actualizar el esquema en [`database/database.sql`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/database/database.sql).
   - Registrar los cambios en los documentos correspondientes de la carpeta [`docs/`](file:///c:/xampp/htdocs/x/Gestion-de-biblioteca-main/Gestion-de-biblioteca-main/docs/).
4. **Cero Dependencias**: No introducir dependencias que requieran gestores de paquetes externos en tiempo de ejecución. Mantener la ligereza y portabilidad del sistema en PHP nativo, CSS y Vanilla JS.

