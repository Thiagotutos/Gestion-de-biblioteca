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
$racks = $pdo->query("SELECT * FROM racks ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $author_id = $_POST['author_id'] ? (int)$_POST['author_id'] : null;
    $isbn = trim($_POST['isbn'] ?? '');
    $publisher_id = $_POST['publisher_id'] ? (int)$_POST['publisher_id'] : null;
    $category_id = $_POST['category_id'] ? (int)$_POST['category_id'] : null;
    $rack_id = $_POST['rack_id'] ? (int)$_POST['rack_id'] : null;
    $estado = $_POST['estado'] ?? 'Disponible';

    if ($titulo && $author_id) {
        $stmt = $pdo->prepare("UPDATE books SET titulo=?, author_id=?, isbn=?, publisher_id=?, category_id=?, rack_id=?, estado=? WHERE id=?");
        $stmt->execute([$titulo, $author_id, $isbn, $publisher_id, $category_id, $rack_id, $estado, $id]);
        $_SESSION['success_msg'] = "Libro actualizado exitosamente.";
        header('Location: books.php');
        exit;
    } else {
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

    <form method="POST">
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
            <label>ISBN</label>
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
            <label>Ubicación (Estante)</label>
            <select name="rack_id" class="form-control">
                <option value="">-- Seleccionar Estante --</option>
                <?php foreach ($racks as $r): ?>
                    <option value="<?php echo $r['id']; ?>" <?php if ($libro['rack_id'] == $r['id']) echo 'selected'; ?>><?php echo htmlspecialchars($r['name']); ?></option>
                <?php endforeach; ?>
            </select>
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

<?php require_once 'includes/footer.php'; ?>
