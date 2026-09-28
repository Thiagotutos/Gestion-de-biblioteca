<?php
require_once 'config.php';
require_once 'includes/header.php';

// Redirigir si no está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lógica del Estado de la Biblioteca (Abierto/Cerrado) - Zona horaria Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hora_actual = (int) date('H');
$minuto_actual = (int) date('i');
$dia_semana = (int) date('w'); // 0=Domingo, 6=Sábado

$estado_biblioteca = "Cerrado";
$clase_alerta_horario = "alert-warning";
$horario_texto = "";

// Lunes a Viernes: 08:00 a 18:00
if ($dia_semana >= 1 && $dia_semana <= 5 && $hora_actual >= 8 && $hora_actual < 18) {
    $estado_biblioteca = "Abierto";
    $clase_alerta_horario = "alert-success";
    $horario_texto = "Lunes a Viernes de 08:00 a 18:00";
} else {
    $horario_texto = "Lunes a Viernes de 08:00 a 18:00";
}

// Estadísticas
$total_libros = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$total_disponibles = $pdo->query("SELECT COUNT(*) FROM books WHERE estado = 'Disponible'")->fetchColumn();
$total_prestados = $pdo->query("SELECT COUNT(*) FROM books WHERE estado = 'Prestado'")->fetchColumn();
$total_autores = $pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn();
$total_categorias = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// Consultar próximos eventos
$stmt_eventos = $pdo->query("SELECT * FROM events_schedule WHERE fecha_inicio >= NOW() ORDER BY fecha_inicio ASC LIMIT 1");
$proximo_evento = $stmt_eventos->fetch();

// Verificar si hay libros extraviados
$stmt_extraviados = $pdo->query("SELECT COUNT(*) FROM books WHERE estado = 'Extraviado'");
$cant_extraviados = $stmt_extraviados->fetchColumn();
?>

<?php require_once 'includes/sidebar.php'; ?>

<!-- Banners Informativos -->
<div class="alert <?php echo $clase_alerta_horario; ?>" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <strong>Estado de la Biblioteca:</strong> Actualmente nos encontramos <strong><?php echo $estado_biblioteca; ?></strong>
    </div>
    <div style="font-size: 0.9rem; opacity: 0.9;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <?php echo $horario_texto; ?> | Hora actual: <?php echo date('H:i'); ?>
    </div>
</div>

<?php if ($cant_extraviados > 0): ?>
<div class="alert alert-warning" style="background-color: var(--color-warning);">
    <strong>¡Atención!</strong> Hay <?php echo $cant_extraviados; ?> libro(s) marcado(s) como extraviado(s). <a href="books.php?filter=missing" style="color:white; text-decoration:underline;">Ver cuáles son</a>
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
    
    <!-- Estadísticas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); color: white; padding: 20px; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold;"><?php echo number_format($total_libros); ?></div>
            <div style="font-size: 0.9rem; opacity: 0.9;">Total Libros</div>
        </div>
        <div style="background: linear-gradient(135deg, var(--color-success), #43a047); color: white; padding: 20px; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold;"><?php echo number_format($total_disponibles); ?></div>
            <div style="font-size: 0.9rem; opacity: 0.9;">Disponibles</div>
        </div>
        <div style="background: linear-gradient(135deg, var(--color-info), #0277bd); color: white; padding: 20px; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold;"><?php echo number_format($total_prestados); ?></div>
            <div style="font-size: 0.9rem; opacity: 0.9;">Prestados</div>
        </div>
        <div style="background: linear-gradient(135deg, #6a1b9a, #8e24aa); color: white; padding: 20px; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold;"><?php echo number_format($total_autores); ?></div>
            <div style="font-size: 0.9rem; opacity: 0.9;">Autores</div>
        </div>
        <div style="background: linear-gradient(135deg, #e65100, #f57c00); color: white; padding: 20px; border-radius: 8px;">
            <div style="font-size: 2rem; font-weight: bold;"><?php echo number_format($total_categorias); ?></div>
            <div style="font-size: 0.9rem; opacity: 0.9;">Categorías</div>
        </div>
    </div>

    <div style="display: flex; justify-content: center; gap: 20px;">
        <a href="books.php" class="btn btn-primary" style="padding: 15px 30px; font-size: 1.1rem;">Ver Inventario de Libros</a>
        <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
        <a href="add_book.php" class="btn btn-edit" style="padding: 15px 30px; font-size: 1.1rem; background-color: var(--color-success);">Registrar Nuevo Libro</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
