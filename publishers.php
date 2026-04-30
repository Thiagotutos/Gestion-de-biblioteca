<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// LÃ³gica para agregar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO publishers (name) VALUES (?)");
        try { $stmt->execute([$name]); $_SESSION['success_msg'] = "CategorÃ­a agregada."; } 
        catch (Exception $e) { $error = "La categorÃ­a ya existe."; }
        header('Location: publishers.php');
        exit;
    }
}

// LÃ³gica para borrar
if (isset($_GET['delete'])) {
    if ($_SESSION['user_role'] === 'Lector') { header('Location: publishers.php'); exit; }
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM publishers WHERE id = ?")->execute([$id]);
    $_SESSION['success_msg'] = "CategorÃ­a eliminada.";
    header('Location: publishers.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM publishers ORDER BY name ASC");
$publishers = $stmt->fetchAll();
?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Gestionar CategorÃ­as</h2>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display: flex; gap: 20px;">
    <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
    <!-- Formulario para agregar -->
    <div class="form-container" style="flex: 1; height: max-content;">
        <h3>Agregar Nueva</h3><br>
        <form method="POST">
            <div class="form-group">
                <label>Nombre de la CategorÃ­a</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <button type="submit" name="add_category" class="btn btn-primary">Guardar</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tabla -->
    <div style="flex: 2;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>AcciÃ³n</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($publishers as $cat): ?>
                <tr>
                    <td><?php echo $cat['id']; ?></td>
                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                    <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
                    <td>
                        <a href="publishers.php?delete=<?php echo $cat['id']; ?>" class="btn btn-delete" onclick="return confirm('Â¿Borrar categorÃ­a?');">Borrar</a>
                    </td>
                    <?php else: ?>
                    <td>-</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($publishers)): ?>
                <tr><td colspan="3" style="text-align: center;">No hay categorÃ­as.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

