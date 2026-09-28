<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// Procesar Préstamo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    $book_id = $_POST['book_id'] ?? null;
    $lector_nombre = trim($_POST['lector_nombre'] ?? '');
    $lector_documento = trim($_POST['lector_documento'] ?? '');
    $librarian_id = $_SESSION['user_id'];
    
    if ($book_id && $lector_nombre) {
        $pdo->beginTransaction();
        try {
            // Insertar transacción
            $stmt = $pdo->prepare("INSERT INTO transactions (book_id, user_id, lector_nombre, lector_documento, librarian_id, accion) VALUES (?, NULL, ?, ?, ?, 'Prestamo')");
            $stmt->execute([$book_id, $lector_nombre, $lector_documento, $librarian_id]);
            
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
        $stmt_user = $pdo->prepare("SELECT user_id, lector_nombre, lector_documento FROM transactions WHERE book_id = ? AND accion = 'Prestamo' ORDER BY id DESC LIMIT 1");
        $stmt_user->execute([$book_id]);
        $last_trans = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("INSERT INTO transactions (book_id, user_id, lector_nombre, lector_documento, librarian_id, accion) VALUES (?, ?, ?, ?, ?, 'Devolucion')");
        $stmt->execute([$book_id, $last_trans['user_id'], $last_trans['lector_nombre'], $last_trans['lector_documento'], $librarian_id]);
        
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
// Los libros ahora se buscan por AJAX para evitar colgar la página
$readers = $pdo->query("SELECT id, nombre, email FROM users ORDER BY nombre ASC")->fetchAll();

// Obtener historial de préstamos activos
$query = "
    SELECT t.id, b.titulo, b.id as book_id, 
           COALESCE(t.lector_nombre, u.nombre) as lector, 
           t.lector_documento, t.fecha_hora 
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    LEFT JOIN users u ON t.user_id = u.id
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
                <label>Nombre del Lector / Alumno</label>
                <input type="text" name="lector_nombre" class="form-control" required placeholder="Ej: Juan Pérez">
            </div>
            <div class="form-group">
                <label>Documento / DNI (Opcional)</label>
                <input type="text" name="lector_documento" class="form-control" placeholder="Ej: 40123456">
            </div>
            <div class="form-group" style="position: relative;">
                <label>Libro Disponible (Buscar por Título o Inventario)</label>
                <input type="text" id="bookSearchInput" class="form-control" placeholder="Escribe para buscar un libro..." autocomplete="off">
                <input type="hidden" name="book_id" id="selectedBookId" required>
                <div id="bookSearchResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #ccc; max-height:200px; overflow-y:auto; z-index:100; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px;"></div>
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
                    <th>Documento</th>
                    <th>Fecha Préstamo</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prestamos as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['titulo']); ?></td>
                    <td><?php echo htmlspecialchars($p['lector']); ?></td>
                    <td><?php echo htmlspecialchars($p['lector_documento'] ?? '-'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($p['fecha_hora'])); ?></td>
                    <td>
                        <a href="issue_books.php?return_book=<?php echo $p['book_id']; ?>" class="btn btn-edit" style="background-color: var(--color-success);" onclick="return confirm('¿Confirmar devolución del libro?');">Marcar Devuelto</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($prestamos)): ?>
                <tr><td colspan="5" style="text-align: center;">No hay préstamos activos en este momento.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookSearchInput = document.getElementById('bookSearchInput');
    const bookSearchResults = document.getElementById('bookSearchResults');
    const selectedBookId = document.getElementById('selectedBookId');
    let searchTimeout;

    if (bookSearchInput) {
        bookSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                bookSearchResults.style.display = 'none';
                selectedBookId.value = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`search_books_json.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        bookSearchResults.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(book => {
                                const div = document.createElement('div');
                                div.style.padding = '10px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                div.textContent = `${book.titulo} (Inv: ${book.isbn || 'N/A'})`;
                                
                                div.addEventListener('mouseover', () => div.style.background = '#f5f5f5');
                                div.addEventListener('mouseout', () => div.style.background = 'white');
                                
                                div.addEventListener('click', () => {
                                    bookSearchInput.value = book.titulo;
                                    selectedBookId.value = book.id;
                                    bookSearchResults.style.display = 'none';
                                });
                                
                                bookSearchResults.appendChild(div);
                            });
                            bookSearchResults.style.display = 'block';
                        } else {
                            bookSearchResults.innerHTML = '<div style="padding:10px; color:#666;">No se encontraron libros disponibles.</div>';
                            bookSearchResults.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error searching books:', error);
                    });
            }, 300);
        });

        // Ocultar resultados al hacer click fuera
        document.addEventListener('click', function(e) {
            if (e.target !== bookSearchInput && e.target !== bookSearchResults) {
                bookSearchResults.style.display = 'none';
            }
        });
        
        // Limpiar ID si el usuario borra el texto
        bookSearchInput.addEventListener('change', function() {
            if (this.value.trim() === '') {
                selectedBookId.value = '';
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
