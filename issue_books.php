<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] === 'Lector') {
    header('Location: dashboard.php');
    exit;
}

// Procesar Préstamo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $book_id = $_POST['book_id'];
    $user_id = $_POST['user_id'];
    $librarian_id = $_SESSION['user_id'];
    
    if ($book_id && $user_id) {
        $pdo->beginTransaction();
        try {
            // Insertar transacción
            $stmt = $pdo->prepare("INSERT INTO transactions (book_id, user_id, librarian_id, accion) VALUES (?, ?, ?, 'Prestamo')");
            $stmt->execute([$book_id, $user_id, $librarian_id]);
            
            // Actualizar estado del libro
            $stmt2 = $pdo->prepare("UPDATE books SET estado = 'Prestado' WHERE id = ?");
            $stmt2->execute([$book_id]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "Libro prestado exitosamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al procesar el préstamo.";
        }
        header('Location: issue_books.php');
        exit;
    }
}

// Procesar Devolución
if (isset($_GET['return_book'])) {
    $book_id = $_GET['return_book'];
    $librarian_id = $_SESSION['user_id'];
    
    $pdo->beginTransaction();
    try {
        // Encontrar quién tenía el libro y crear registro de devolución
        $stmt_user = $pdo->prepare("SELECT user_id FROM transactions WHERE book_id = ? AND accion = 'Prestamo' ORDER BY id DESC LIMIT 1");
        $stmt_user->execute([$book_id]);
        $last_user = $stmt_user->fetchColumn();
        
        $stmt = $pdo->prepare("INSERT INTO transactions (book_id, user_id, librarian_id, accion) VALUES (?, ?, ?, 'Devolucion')");
        $stmt->execute([$book_id, $last_user ?: 1, $librarian_id]);
        
        // Actualizar estado del libro a Disponible
        $stmt2 = $pdo->prepare("UPDATE books SET estado = 'Disponible' WHERE id = ?");
        $stmt2->execute([$book_id]);
        
        $pdo->commit();
        $_SESSION['success_msg'] = "Libro devuelto exitosamente.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error al devolver el libro.";
    }
    header('Location: issue_books.php');
    exit;
}

// Obtener datos para los select
$available_books = $pdo->query("SELECT id, titulo FROM books WHERE estado = 'Disponible' ORDER BY titulo ASC")->fetchAll();
$readers = $pdo->query("SELECT id, nombre, email FROM users ORDER BY nombre ASC")->fetchAll();

// Obtener historial de préstamos activos
$query = "
    SELECT t.id, b.titulo, b.id as book_id, u.nombre as lector, t.fecha_hora 
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN users u ON t.user_id = u.id
    WHERE t.id IN (
        SELECT MAX(id) FROM transactions GROUP BY book_id
    ) AND t.accion = 'Prestamo' AND b.estado = 'Prestado'
    ORDER BY t.fecha_hora DESC
";
$prestamos = $pdo->query($query)->fetchAll();
?>

<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Préstamos y Devoluciones</h2>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <!-- Formulario Prestar -->
    <div class="form-container" style="flex: 1; min-width: 300px; height: max-content;">
        <h3>Nuevo Préstamo</h3><br>
        <form method="POST">
            <div class="form-group">
                <label>Seleccionar Lector</label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Buscar Lector --</option>
                    <?php foreach ($readers as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['nombre']); ?> (<?php echo htmlspecialchars($r['email']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Libro Disponible</label>
                <select name="book_id" class="form-control" required>
                    <option value="">-- Buscar Libro --</option>
                    <?php foreach ($available_books as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['titulo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="issue_book" class="btn btn-primary" style="width:100%">Registrar Préstamo</button>
        </form>
    </div>

    <!-- Préstamos Activos -->
    <div style="flex: 2; min-width: 400px;">
        <h3>Libros Actualmente Prestados</h3><br>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Libro</th>
                    <th>Lector</th>
                    <th>Fecha Préstamo</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prestamos as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['titulo']); ?></td>
                    <td><?php echo htmlspecialchars($p['lector']); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($p['fecha_hora'])); ?></td>
                    <td>
                        <a href="issue_books.php?return_book=<?php echo $p['book_id']; ?>" class="btn btn-edit" style="background-color: var(--color-success);" onclick="return confirm('¿Confirmar devolución del libro?');">Marcar Devuelto</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($prestamos)): ?>
                <tr><td colspan="4" style="text-align: center;">No hay préstamos activos en este momento.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
