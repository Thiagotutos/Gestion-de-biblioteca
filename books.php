<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$where = "";
if (isset($_GET['filter']) && $_GET['filter'] == 'missing') {
    $where = "WHERE b.estado = 'Extraviado'";
}

$query = "
    SELECT b.id, b.titulo, b.isbn, b.estado, b.created_at,
           c.name as categoria, a.name as autor, p.name as editorial, r.name as ubicacion
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN publishers p ON b.publisher_id = p.id
    LEFT JOIN racks r ON b.rack_id = r.id
    $where
    ORDER BY b.id DESC
";
$stmt = $pdo->query($query);
$libros = $stmt->fetchAll();
?>

<?php require_once 'includes/sidebar.php'; ?>

<div class="content-header">
    <h2>Gestionar Libros</h2>
    <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
    <a href="add_book.php" class="btn btn-primary">Agregar Libro</a>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success">
        <?php 
            echo htmlspecialchars($_SESSION['success_msg']); 
            unset($_SESSION['success_msg']);
        ?>
    </div>
<?php endif; ?>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Libro</th>
            <th>ISBN</th>
            <th>Autor</th>
            <th>Categoría</th>
            <th>Estante</th>
            <th>Estado</th>
            <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
            <th>Acción</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($libros as $libro): ?>
        <tr>
            <td><?php echo $libro['id']; ?></td>
            <td>
                <div style="display: flex; align-items: center;">
                    <div class="book-cover" style="background-image: url('https://via.placeholder.com/50x70?text=Libro'); background-size: cover;"></div>
                    <span><?php echo htmlspecialchars($libro['titulo']); ?></span>
                </div>
            </td>
            <td><?php echo htmlspecialchars($libro['isbn'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($libro['autor'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($libro['categoria'] ?? 'N/A'); ?></td>
            <td><?php echo htmlspecialchars($libro['ubicacion'] ?? 'N/A'); ?></td>
            <td>
                <?php 
                    $statusClass = 'status-available';
                    if ($libro['estado'] === 'Prestado') $statusClass = 'status-borrowed';
                    if ($libro['estado'] === 'Extraviado') $statusClass = 'status-missing';
                ?>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($libro['estado']); ?>
                </span>
            </td>
            <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
            <td>
                <a href="edit_book.php?id=<?php echo $libro['id']; ?>" class="btn btn-edit">Editar</a>
                <a href="delete_book.php?id=<?php echo $libro['id']; ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de eliminar este libro?');">Borrar</a>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($libros)): ?>
        <tr>
            <td colspan="8" style="text-align: center;">No hay libros registrados.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once 'includes/footer.php'; ?>
