# 📖 Manual de Usuario y Guía Operativa del Sistema
## Sistema Web de Gestión de Biblioteca Escolar / Institucional

> **Documento Oficial de Entrega Académica e Institucional**  
> **Versión:** 2.0  
> **Fecha de Emisión:** Noviembre 2026  
> **Acceso Web:** `https://sistemabiblioteca.dpdns.org`  
> **Destinatarios:** Equipo Directivo, Administradores de Red, Personal Bibliotecario, Docentes y Alumnos.

---

## 📑 Tabla de Contenidos
1. [Introducción y Objetivos del Sistema](#1-introducción-y-objetivos-del-sistema)
2. [Roles de Usuario y Matriz de Permisos](#2-roles-de-usuario-y-matriz-de-permisos)
3. [Acceso e Inicio de Sesión](#3-acceso-e-inicio-de-sesión)
4. [Módulo 1: Manual del Bibliotecario (Operación Diaria)](#4-módulo-1-manual-del-bibliotecario-operación-diaria)
   - [4.1 Navegación por el Dashboard Operativo](#41-navegación-por-el-dashboard-operativo)
   - [4.2 Búsqueda y Exploración del Catálogo](#42-búsqueda-y-exploración-del-catálogo)
   - [4.3 Registro de Nuevos Libros (Alta de Ejemplares)](#43-registro-de-nuevos-libros-alta-de-ejemplares)
   - [4.4 Modificación y Baja de Libros](#44-modificación-y-baja-de-libros)
   - [4.5 Gestión de Materias, Autores y Editoriales](#45-gestión-de-materias-autores-y-editoriales)
   - [4.6 Circulación: Préstamos a Alumnos y Docentes](#46-circulación-préstamos-a-alumnos-y-docentes)
   - [4.7 Circulación: Registro de Devoluciones](#47-circulación-registro-de-devoluciones)
5. [Módulo 2: Manual del Administrador (Gestión y Seguridad)](#5-módulo-2-manual-del-administrador-gestión-y-seguridad)
   - [5.1 Administración de Cuentas y Control de Acceso](#51-administración-de-cuentas-y-control-de-acceso)
   - [5.2 Copias de Seguridad (Backups) y Rotación Automática](#52-copias-de-seguridad-backups-y-rotación-automática)
   - [5.3 Restauración de la Base de Datos](#53-restauración-de-la-base-de-datos)
6. [Módulo 3: Manual del Lector (Consulta de Catálogo)](#6-módulo-3-manual-del-lector-consulta-de-catálogo)
7. [Preguntas Frecuentes y Solución de Problemas (FAQ)](#7-preguntas-frecuentes-y-solución-de-problemas-faq)

---

## 1. Introducción y Objetivos del Sistema

El **Sistema de Gestión de Biblioteca** es una solución integral desarrollada para modernizar, agilizar y transparentar los procesos bibliotecológicos de la institución. 

### Objetivos Principales:
* **Control de Inventario Preciso:** Seguimiento exacto de cada ejemplar físico mediante signatura topográfica, código de barras/inventario correlativo y estado de disponibilidad en tiempo real.
* **Agilidad en la Circulación:** Registro de préstamos y devoluciones en menos de 20 segundos, con alertas visuales inmediatas para ejemplares vencidos o próximos a vencer.
* **Seguridad y Respaldo Permanente:** Salvaguarda de la información histórica institucional mediante copias de seguridad automáticas mensuales y rotación programada.
* **Accesibilidad Universal:** Funciona tanto en la red local cableada/WiFi de la institución como de forma remota a través del dominio seguro con candado SSL: `https://sistemabiblioteca.dpdns.org`.

---

## 2. Roles de Usuario y Matriz de Permisos

El sistema implementa un modelo de **Control de Acceso Basado en Roles (RBAC)** para garantizar que cada integrante acceda únicamente a las funciones correspondientes a su tarea:

| Módulo / Funcionalidad | Lector (Estudiante/Docente) | Bibliotecario (Operador) | Administrador (Director/Soporte) |
| :--- | :---: | :---: | :---: |
| **Consultar Catálogo de Libros** | ✅ Sí | ✅ Sí | ✅ Sí |
| **Buscador en Tiempo Real (AJAX)** | ✅ Sí | ✅ Sí | ✅ Sí |
| **Ver Disponibilidad y Ficha Técnica** | ✅ Sí | ✅ Sí | ✅ Sí |
| **Registrar Préstamos y Devoluciones** | ❌ No | ✅ Sí | ✅ Sí |
| **Dar de Alta y Editar Libros** | ❌ No | ✅ Sí | ✅ Sí |
| **Eliminar Libros (Sin préstamos activos)**| ❌ No | ✅ Sí | ✅ Sí |
| **Administrar Materias, Autores y Editoriales** | ❌ No | ✅ Sí | ✅ Sí |
| **Gestionar Cuentas de Usuarios** | ❌ No | ❌ No | ✅ Sí |
| **Generar y Descargar Copias de Seguridad**| ❌ No | ❌ No | ✅ Sí |
| **Restaurar Base de Datos** | ❌ No | ❌ No | ✅ Sí |

---

## 3. Acceso e Inicio de Sesión

### Pasos para Ingresar:
1. Abra su navegador web preferido (Google Chrome, Firefox, Edge o Safari).
2. Ingrese a la dirección oficial: **`https://sistemabiblioteca.dpdns.org`** *(o `http://localhost/Gestion-de-biblioteca` si trabaja en el servidor local)*.
3. En la pantalla de bienvenida, complete:
   * **Correo electrónico:** Su dirección de correo registrada (ej: `bibliotecario@biblioteca.com`).
   * **Contraseña:** Su clave de acceso personal.
4. Presione el botón **"Iniciar Sesión"**.

> [!TIP]
> **Cuentas de Acceso Predeterminadas (Entorno de Evaluación):**
> * **Administrador:** `admin@biblioteca.com` | Clave: `admin123`
> * **Bibliotecario:** `bibliotecario@biblioteca.com` | Clave: `admin123`
> * **Lector:** `lector@biblioteca.com` | Clave: `admin123`

---

## 4. Módulo 1: Manual del Bibliotecario (Operación Diaria)

### 4.1 Navegación por el Dashboard Operativo
Al iniciar sesión, el Bibliotecario accede al panel de control central donde encontrará:
* **Banner de Estado del Establecimiento:** Indica si la biblioteca se encuentra actualmente **Abierta** o **Cerrada** según los horarios institucionales (Lunes a Viernes de 08:00 a 18:00 hs).
* **Tarjetas Métricas Clickeables:**
  * **Libros:** Total de títulos y copias registradas. Al hacer clic, abre el catálogo completo.
  * **Libros Disponibles:** Ejemplares listos en sala para ser prestados. Al hacer clic, filtra solo los disponibles.
  * **Préstamos Activos:** Total de libros actualmente en poder de lectores. Al hacer clic, abre el registro de préstamos.
* **Acciones Rápidas:** Botones directos para **"Crear Préstamo"** y **"Agregar Libro"**.
* **Tabla de Alertas de Vencimientos:** Muestra con indicadores luminosos parpadeantes aquellos préstamos que:
  * 🔴 **Vencido:** Superó el plazo de 7 días. Muestra cuántos días de retraso lleva.
  * 🟠 **Vence Hoy:** Debe devolverse durante la jornada actual.
  * 🟡 **Vence en 1 o 2 días:** Alerta preventiva para preparar la recepción.

---

### 4.2 Búsqueda y Exploración del Catálogo
Para localizar rápidamente un libro:
1. En el menú lateral, seleccione **Libros ➔ Gestionar Libros**.
2. Utilice la barra de búsqueda superior interactiva:
   * **Criterio de Búsqueda:** Puede buscar por *Todos los campos*, *Título*, *Autor*, *ISBN / Inventario*, *Signatura Topográfica*, *ISBN Editorial*, *Materia* o *Editorial*.
   * **Búsqueda Instantánea:** A medida que escribe, el sistema filtra los resultados en tiempo real sin recargar la página.
3. Cada libro muestra su título, autor, signatura de estante, año, editorial y una etiqueta de color con su estado:
   * 🟢 **Disponible**
   * 🔵 **Prestado**
   * 🔴 **Extraviado**

---

### 4.3 Registro de Nuevos Libros (Alta de Ejemplares)
Para incorporar nuevos libros al acervo bibliográfico:
1. Diríjase a **Libros ➔ Gestionar Libros** y presione el botón **"+ Agregar Libro"**.
2. Complete la ficha bibliográfica:
   * **Título:** Nombre completo de la obra (Obligatorio).
   * **Autor:** Seleccione un autor existente o escriba uno nuevo; el sistema lo creará automáticamente.
   * **Número de Inventario:** El sistema calcula y sugiere automáticamente el siguiente número correlativo institucional (ej: `000091`). Puede mantenerlo o modificarlo.
   * **Signatura Topográfica:** Ubicación física en estante según sistema Dewey/CDU (ej: `863 CER d`).
   * **ISBN de la Editorial:** Código de barras comercial del libro.
   * **Editorial y Lugar:** Nombre de la casa editora y ciudad/país.
   * **Año / Fecha de Publicación:** Solo permite fechas pasadas o presentes (bloquea fechas futuras).
   * **Materia / Categoría:** Clasificación temática (Literatura, Ciencias, Historia, etc.).
   * **Cantidad de Ejemplares:** Si la biblioteca recibió múltiples copias del mismo título (ej: 5 ejemplares de donación), ingrese el número y el sistema creará cada copia correlativa en un solo paso.
3. Presione **"Guardar Libro"**.

---

### 4.4 Modificación y Baja de Libros
* **Para Editar:** En la fila del libro correspondiente, presione el botón azul **"Editar"**. Podrá corregir cualquier dato y presionar **"Actualizar Libro"**.
* **Para Dar de Baja (Eliminar):** Presione el botón rojo **"Eliminar"**.

> [!IMPORTANT]
> **Regla de Integridad Institucional:**  
> El sistema **prohíbe estrictamente eliminar libros que estén actualmente prestados** a un alumno o docente. Si intenta hacerlo, el sistema le informará con un mensaje de bloqueo: *"No se puede eliminar el libro porque se encuentra prestado. Primero debes registrar su devolución en el módulo de Préstamos"*.

---

### 4.5 Gestión de Materias, Autores y Editoriales
Bajo el submenú de **Libros**, dispone de accesos directos para:
* **Materias (`categories.php`):** Permite unificar las áreas temáticas de la biblioteca.
* **Autores (`authors.php`):** Permite corregir nombres de autores mal tipeados.
* **Editoriales (`publishers.php`):** Control del padrón de editoriales.

> [!NOTE]
> **Protección de Catálogo:** Si elimina un autor o editorial que ya no se utiliza, los libros asociados **no se borran**. El campo del autor pasará a quedar vacío temporalmente para que pueda reasignarlo cuando lo desee.

---

### 4.6 Circulación: Préstamos a Alumnos y Docentes
El módulo de préstamos está optimizado para atención rápida en mostrador:
1. En el menú lateral, seleccione **"Préstamos Biblioteca"** (o haga clic en "Crear Préstamo" desde el Dashboard).
2. **Datos del Lector:**
   * **Nombre y Apellido:** Nombre del alumno o docente solicitante.
   * **DNI / Documento:** Identificación para control de legajo.
   * **Turno:** Seleccione **Mañana** o **Tarde**.
   * **Curso / División:** Ejemplo: *4to 1ra*, *6to Informática*.
   * **Docente o Adulto Responsable:** Nombre del profesor a cargo del curso o tutor.
3. **Selección del Libro:**
   * En el selector inteligente de libros, empiece a escribir el título o número de inventario.
   * Solo aparecerán libros con estado **Disponible**.
   * Puede agregar uno o varios libros al préstamo simultáneamente.
4. **Plazo de Devolución:** El sistema calcula automáticamente la fecha de vencimiento a **7 días corridos** (1 semana).
5. Presione **"Confirmar Préstamo"**. El libro pasará de inmediato al estado **Prestado**.

> [!WARNING]
> **Regla de Control para Múltiples Copias:**  
> Si se solicita el préstamo de **más de 1 libro o copia** en la misma transacción, el campo **Docente o Adulto Responsable es 100% obligatorio**.

---

### 4.7 Circulación: Registro de Devoluciones
Cuando el lector reintegra el ejemplar a la biblioteca:
1. Ingrese a **"Préstamos Biblioteca"**.
2. En la tabla inferior de préstamos activos, localice la fila del préstamo (puede filtrar por lector, libro o fecha).
3. Presione el botón verde **"Devolver"**.
4. El sistema asentará la fecha y hora exacta de retorno, liberará el libro y su estado volverá a figurar automáticamente como **Disponible** en el catálogo general.

---

## 5. Módulo 2: Manual del Administrador (Gestión y Seguridad)

*(Exclusivo para usuarios con rol de Administrador).*

### 5.1 Administración de Cuentas y Control de Acceso
Desde el menú **Usuarios**:
1. **Crear Nuevo Usuario:**
   * Ingrese Nombre completo (solo letras y espacios, 3 a 60 caracteres).
   * Ingrese Correo Electrónico: El sistema posee validación estricta de dominios (rechaza correos incompletos como `juan@gmail` sin `.com` y detecta errores tipográficos como `gma.com`).
   * Asigne el **Rol**: *Administrador*, *Bibliotecario* o *Lector*.
   * Defina una contraseña inicial (mínimo 6 caracteres).
2. **Modificación y Recuperación:**
   * El Administrador puede editar los roles y actualizar contraseñas en caso de olvido por parte del personal.

---

### 5.2 Copias de Seguridad (Backups) y Rotación Automática
Desde el menú **Importar Datos / Respaldo**:

El sistema incorpora un motor integral de resguardo de base de datos:
1. **Respaldo Mensual Automático:**  
   El sistema verifica automáticamente en cada inicio de mes si ya existe una copia del período actual. Si no existe, la crea de manera transparente sin interrumpir las operaciones.
2. **Generar Copia Manual Inmediata:**  
   Presione el botón azul **"Crear Copia de Seguridad Ahora"** antes de realizar cambios masivos o al finalizar el ciclo lectivo.
3. **Política de Rotación FIFO (Máximo 10 Copias):**  
   Para proteger el espacio en disco del servidor, el sistema mantiene archivadas las **10 copias más recientes**. Al generarse la número 11, el sistema borra automáticamente el archivo más antiguo.
4. **Descarga Segura a la PC:**  
   Cada archivo de la lista cuenta con un botón **"Descargar"** para almacenar una copia externa en un pendrive o disco externo de la escuela.

---

### 5.3 Restauración de la Base de Datos
En caso de requerir volver a un estado anterior:
1. **Opción A (Desde copias del servidor):** En la tabla de respaldos almacenados, seleccione la copia deseada, marque la casilla de confirmación *"Confirmo que deseo sobrescribir la base de datos actual"* y presione **"Restaurar"**.
2. **Opción B (Desde archivo externo):** En la sección inferior, seleccione un archivo `.sql` desde su computadora, marque la casilla de confirmación y presione **"Importar Base de Datos"**.

> [!TIP]
> **Tecnología de Streaming:**  
> El motor de restauración procesa el archivo SQL línea por línea en memoria dinámica. No sufre caídas por archivos grandes y soporta restauraciones masivas de catálogos sin congelar el servidor.

---

## 6. Módulo 3: Manual del Lector (Consulta de Catálogo)

Los alumnos, docentes e invitados que ingresen con rol de **Lector** (o terminales de autoconsulta en el salón de lectura):
* Tienen acceso de **solo lectura** al catálogo bibliográfico.
* Pueden buscar por temática, autor o palabra clave para saber si un libro existe en la biblioteca y si se encuentra **Disponible** en el estante antes de acercarse al mostrador.
* No tienen acceso a los datos personales de otros lectores, ni al registro de préstamos, ni a la configuración administrativa.

---

## 7. Preguntas Frecuentes y Solución de Problemas (FAQ)

### ¿Qué hago si olvidé la contraseña del Administrador?
El Administrador puede restablecer contraseñas desde la sección `Usuarios`. En caso de perder el acceso total, cualquier técnico con acceso a la consola de la base de datos puede restablecer la clave ejecutando:
```sql
UPDATE users SET password = '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u', plain_password = 'admin123' WHERE email = 'admin@biblioteca.com';
```

### ¿Por qué al borrar un libro el siguiente número de inventario no vuelve hacia atrás?
Por normas internacionales de auditoría y bibliotecología. Un número de inventario asignado en el pasado no se recicla jamás para evitar confusiones con libros dados de baja o fichas históricas de préstamos antiguos.

### ¿Se pueden imprimir reportes de los libros prestados?
Sí. El sistema cuenta con estilos de impresión optimizados. Desde el navegador presione `Ctrl + P` en la pantalla de Préstamos o Dashboard y la página se formateará automáticamente en blanco y negro, ocultando menús y barras laterales para una impresión limpia en hoja A4.

### ¿Cómo acceden los profesores desde sus computadoras personales?
Solo deben ingresar desde cualquier navegador a la dirección web pública segura:  
👉 **`https://sistemabiblioteca.dpdns.org`**  
No requieren instalar ningún programa adicional ni estar conectados a la misma red WiFi.
