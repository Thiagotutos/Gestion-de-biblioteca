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

// Fetch all options for dropdowns
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
    $imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['imagen']['type'];
        if (in_array($fileType, $allowed)) {
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
        $stmt = $pdo->prepare("INSERT INTO books (titulo, author_id, isbn, publisher_id, category_id, rack_id, imagen, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $author_id, $isbn, $publisher_id, $category_id, $rack_id, $imagen, $estado]);
        $_SESSION['success_msg'] = "Libro agregado exitosamente.";
        header('Location: books.php');
        exit;
    } elseif (!isset($error)) {
        $error = "Título y Autor son obligatorios.";
    }
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Agregar Nuevo Libro</h2>
    <a href="books.php" class="btn btn-primary" style="background-color: #666;">Volver</a>
</div>

<div class="form-container">
    <?php if (isset($error)): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Título *</label>
            <input type="text" name="titulo" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Autor *</label>
            <select name="author_id" class="form-control" required>
                <option value="">-- Seleccionar Autor --</option>
                <?php foreach ($authors as $a): ?>
                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Nº Inventario</label>
            <input type="text" name="isbn" class="form-control" placeholder="Ej: 000123">
        </div>
        <div class="form-group">
            <label>Editorial</label>
            <select name="publisher_id" class="form-control">
                <option value="">-- Seleccionar Editorial --</option>
                <?php foreach ($publishers as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Categoría</label>
            <select name="category_id" class="form-control">
                <option value="">-- Seleccionar Categoría --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Portada del Libro</label>
            <div class="image-upload-area" id="uploadArea">
                <input type="file" name="imagen" id="imagenInput" class="image-input" accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="upload-placeholder" id="uploadPlaceholder">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <path d="M21 15l-5-5L5 21"/>
                    </svg>
                    <p>Haz clic o arrastra una imagen aquí</p>
                    <span>JPG, PNG, GIF o WebP (máx. 5MB)</span>
                </div>
                <img id="imagePreview" class="image-preview" style="display:none;" alt="Vista previa">
            </div>
        </div>
        <div class="form-group">
            <label>Estado Inicial</label>
            <select name="estado" class="form-control">
                <option value="Disponible">Disponible</option>
                <option value="Prestado">Prestado</option>
                <option value="Extraviado">Extraviado</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Libro</button>
    </form>
</div>

<script>
const uploadArea = document.getElementById('uploadArea');
const imageInput = document.getElementById('imagenInput');
const imagePreview = document.getElementById('imagePreview');
const placeholder = document.getElementById('uploadPlaceholder');

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
    if (e.target.files.length) showPreview(e.target.files[0]);
});

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
