<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] === 'Lector') {
    header('Location: books.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: books.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
$stmt->execute([$id]);
$libro = $stmt->fetch();

if (!$libro) {
    header('Location: books.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$authors = $pdo->query("SELECT * FROM authors ORDER BY name ASC")->fetchAll();
$publishers = $pdo->query("SELECT * FROM publishers ORDER BY name ASC")->fetchAll();
$racks = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $author_id = $_POST['author_id'] ? (int)$_POST['author_id'] : null;
    $isbn = trim($_POST['isbn'] ?? '');
    $publisher_id = $_POST['publisher_id'] ? (int)$_POST['publisher_id'] : null;
    $category_id = $_POST['category_id'] ? (int)$_POST['category_id'] : null;
    $rack_id = null;
    $estado = $_POST['estado'] ?? 'Disponible';

    // Manejo de imagen
    $imagen = $libro['imagen']; // Mantener la imagen actual por defecto

    // Si se marcó eliminar imagen
    if (isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] === '1') {
        if ($imagen) {
            $oldPath = __DIR__ . '/uploads/books/' . $imagen;
            if (file_exists($oldPath)) unlink($oldPath);
        }
        $imagen = null;
    }

    // Si se subió una nueva imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['imagen']['type'];
        if (in_array($fileType, $allowed)) {
            // Eliminar imagen anterior si existe
            if ($libro['imagen']) {
                $oldPath = __DIR__ . '/uploads/books/' . $libro['imagen'];
                if (file_exists($oldPath)) unlink($oldPath);
            }
            $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
            $imagen = uniqid('book_') . '.' . $ext;
            $uploadDir = __DIR__ . '/uploads/books/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $imagen);
        } else {
            $error = "Formato de imagen no permitido. Use JPG, PNG, GIF o WebP.";
        }
    }

    if (!isset($error) && $titulo && $author_id) {
        $stmt = $pdo->prepare("UPDATE books SET titulo=?, author_id=?, isbn=?, publisher_id=?, category_id=?, rack_id=?, imagen=?, estado=? WHERE id=?");
        $stmt->execute([$titulo, $author_id, $isbn, $publisher_id, $category_id, $rack_id, $imagen, $estado, $id]);
        $_SESSION['success_msg'] = "Libro actualizado exitosamente.";
        header('Location: books.php');
        exit;
    } elseif (!isset($error)) {
        $error = "Título y Autor son obligatorios.";
    }
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Editar Libro</h2>
    <a href="books.php" class="btn btn-primary" style="background-color: #666;">Volver</a>
</div>

<div class="form-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Título *</label>
            <input type="text" name="titulo" class="form-control" required value="<?php echo htmlspecialchars($libro['titulo']); ?>">
        </div>
        <div class="form-group">
            <label>Autor *</label>
            <select name="author_id" class="form-control" required>
                <option value="">-- Seleccionar Autor --</option>
                <?php foreach ($authors as $a): ?>
                    <option value="<?php echo $a['id']; ?>" <?php if ($libro['author_id'] == $a['id']) echo 'selected'; ?>><?php echo htmlspecialchars($a['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Nº Inventario</label>
            <input type="text" name="isbn" class="form-control" value="<?php echo htmlspecialchars($libro['isbn'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Editorial</label>
            <select name="publisher_id" class="form-control">
                <option value="">-- Seleccionar Editorial --</option>
                <?php foreach ($publishers as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php if ($libro['publisher_id'] == $p['id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Categoría</label>
            <select name="category_id" class="form-control">
                <option value="">-- Seleccionar Categoría --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php if ($libro['category_id'] == $c['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Portada del Libro</label>
            <div class="image-upload-area" id="uploadArea">
                <input type="file" name="imagen" id="imagenInput" class="image-input" accept="image/jpeg,image/png,image/gif,image/webp">
                <input type="hidden" name="eliminar_imagen" id="eliminarImagen" value="0">
                <div class="upload-placeholder" id="uploadPlaceholder" <?php if ($libro['imagen']): ?>style="display:none;"<?php endif; ?>>
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="M21 15l-5-5L5 21"/>
                    </svg>
                    <p>Haz clic o arrastra una imagen aquí</p>
                    <span>JPG, PNG, GIF o WebP (máx. 5MB)</span>
                </div>
                <img id="imagePreview" class="image-preview" 
                     <?php if ($libro['imagen']): ?>
                         src="uploads/books/<?php echo htmlspecialchars($libro['imagen']); ?>" 
                         style="display:block;"
                     <?php else: ?>
                         style="display:none;"
                     <?php endif; ?> 
                     alt="Vista previa">
            </div>
            <?php if ($libro['imagen']): ?>
            <button type="button" class="btn btn-delete" id="btnEliminarImg" style="margin-top: 8px; font-size: 0.85rem; padding: 5px 12px;">
                ✕ Eliminar imagen actual
            </button>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Estado</label>
            <select name="estado" class="form-control">
                <option value="Disponible" <?php if ($libro['estado'] == 'Disponible') echo 'selected'; ?>>Disponible</option>
                <option value="Prestado" <?php if ($libro['estado'] == 'Prestado') echo 'selected'; ?>>Prestado</option>
                <option value="Extraviado" <?php if ($libro['estado'] == 'Extraviado') echo 'selected'; ?>>Extraviado</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar Libro</button>
    </form>
</div>

<script>
const uploadArea = document.getElementById('uploadArea');
const imageInput = document.getElementById('imagenInput');
const imagePreview = document.getElementById('imagePreview');
const placeholder = document.getElementById('uploadPlaceholder');
const eliminarInput = document.getElementById('eliminarImagen');
const btnEliminar = document.getElementById('btnEliminarImg');

uploadArea.addEventListener('click', () => imageInput.click());
uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
        imageInput.files = e.dataTransfer.files;
        showPreview(e.dataTransfer.files[0]);
    }
});

imageInput.addEventListener('change', (e) => {
    if (e.target.files.length) {
        showPreview(e.target.files[0]);
        eliminarInput.value = '0';
    }
});

if (btnEliminar) {
    btnEliminar.addEventListener('click', () => {
        imagePreview.style.display = 'none';
        imagePreview.src = '';
        placeholder.style.display = '';
        eliminarInput.value = '1';
        imageInput.value = '';
        btnEliminar.style.display = 'none';
    });
}

function showPreview(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        imagePreview.src = e.target.result;
        imagePreview.style.display = 'block';
        placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
}
</script>

<?php require_once 'includes/footer.php'; ?>
