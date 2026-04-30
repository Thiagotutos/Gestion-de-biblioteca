<?php
require_once 'config.php';
require_once 'includes/header.php';

// Redirigir si no está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lógica del Estado de la Biblioteca (Abierto/Cerrado)
$hora_actual = date('H:i');
$estado_biblioteca = "Cerrado";
$clase_alerta_horario = "alert-warning";
if ($hora_actual >= "08:00" && $hora_actual <= "18:00") {
    $estado_biblioteca = "Abierto";
    $clase_alerta_horario = "alert-success";
}

// Consultar próximos eventos
$stmt_eventos = $pdo->query("SELECT * FROM events_schedule WHERE fecha_inicio >= NOW() ORDER BY fecha_inicio ASC LIMIT 1");
$proximo_evento = $stmt_eventos->fetch();

// Verificar si hay libros extraviados
$stmt_extraviados = $pdo->query("SELECT COUNT(*) FROM books WHERE estado = 'Extraviado'");
$cant_extraviados = $stmt_extraviados->fetchColumn();
?>

<?php require_once 'includes/sidebar.php'; ?>

<!-- Banners Informativos -->
<div class="alert <?php echo $clase_alerta_horario; ?>">
    <strong>Estado de la Biblioteca:</strong> Actualmente nos encontramos <?php echo $estado_biblioteca; ?>. (Horario: 08:00 a 18:00)
</div>

<?php if ($cant_extraviados > 0): ?>
<div class="alert alert-warning" style="background-color: var(--color-warning);">
    <strong>¡Atención!</strong> Hay <?php echo $cant_extraviados; ?> libro(s) marcado(s) como extraviado(s) o con retraso severo. <a href="books.php?filter=missing" style="color:white; text-decoration:underline;">Ver cuáles son</a>
</div>
<?php endif; ?>

<?php if ($proximo_evento): ?>
<div class="alert alert-info">
    <strong>Próximo Evento:</strong> <?php echo htmlspecialchars($proximo_evento['titulo']); ?> - <?php echo htmlspecialchars($proximo_evento['descripcion']); ?> (Desde el <?php echo date('d/m/Y', strtotime($proximo_evento['fecha_inicio'])); ?>)
</div>
<?php endif; ?>

<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; margin-top: 20px;">
    <h2 style="color: var(--color-primary); margin-bottom: 10px;">¡Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
    <p>Utiliza el menú lateral para gestionar los recursos de la biblioteca.</p>
    <br>
    <div style="display: flex; justify-content: center; gap: 20px;">
        <a href="books.php" class="btn btn-primary" style="padding: 15px 30px; font-size: 1.1rem;">Ver Inventario de Libros</a>
        <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
        <a href="add_book.php" class="btn btn-edit" style="padding: 15px 30px; font-size: 1.1rem; background-color: var(--color-success);">Registrar Nuevo Libro</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
