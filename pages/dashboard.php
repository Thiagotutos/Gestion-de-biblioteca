<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/header.php';

// Redirigir si no está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Lógica del Estado de la Biblioteca (Abierto/Cerrado) - Zona horaria Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hora_actual = (int) date('H');
$minuto_actual = (int) date('i');
$dia_semana = (int) date('w'); // 0=Domingo, 6=Sábado

$estado_biblioteca = "Cerrado";
$clase_alerta_horario = "alert-warning";
$horario_texto = "Lunes a Viernes de 08:00 a 18:00";

// Lunes a Viernes: 08:00 a 18:00
if ($dia_semana >= 1 && $dia_semana <= 5 && $hora_actual >= 8 && $hora_actual < 18) {
    $estado_biblioteca = "Abierto";
    $clase_alerta_horario = "alert-success";
}

// Estadísticas Clave
$total_libros = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$total_disponibles = (int)$pdo->query("SELECT COUNT(*) FROM books WHERE estado = 'Disponible'")->fetchColumn();
$total_prestados = (int)$pdo->query("SELECT COUNT(*) FROM books WHERE estado = 'Prestado'")->fetchColumn();

// Consultar préstamos activos que están vencidos o próximos a vencer (en los siguientes 2 días)
$query_alertas = "
    SELECT t.id, t.book_id, t.fecha_hora, t.fecha_devolucion_esperada,
           COALESCE(t.lector_nombre, u.nombre, 'No especificado') as lector,
           t.lector_documento, t.turno, t.curso,
           b.titulo, b.isbn, a.name as autor
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    LEFT JOIN authors a ON b.author_id = a.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id IN (
        SELECT MAX(id) FROM transactions GROUP BY book_id
    ) AND t.accion = 'Prestamo' AND b.estado = 'Prestado'
    ORDER BY t.fecha_devolucion_esperada ASC, t.fecha_hora ASC
";
$prestamos_activos = $pdo->query($query_alertas)->fetchAll();

$prestamos_alerta = [];
$hoy_ts = strtotime(date('Y-m-d'));
foreach ($prestamos_activos as $p) {
    $fecha_prestamo = !empty($p['fecha_hora']) ? $p['fecha_hora'] : date('Y-m-d');
    $fecha_limite = !empty($p['fecha_devolucion_esperada']) 
        ? $p['fecha_devolucion_esperada'] 
        : date('Y-m-d', strtotime('+7 days', strtotime($fecha_prestamo)));
    
    $limite_ts = strtotime($fecha_limite);
    $dias_restantes = (int) round(($limite_ts - $hoy_ts) / 86400);
    
    if ($dias_restantes <= 2) {
        $p['dias_restantes'] = $dias_restantes;
        $p['fecha_limite_ts'] = $limite_ts;
        $prestamos_alerta[] = $p;
    }
}

// Consultar próximos eventos
$stmt_eventos = $pdo->query("SELECT * FROM events_schedule WHERE fecha_inicio >= NOW() ORDER BY fecha_inicio ASC LIMIT 1");
$proximo_evento = $stmt_eventos->fetch();

// Verificar si hay libros extraviados
$stmt_extraviados = $pdo->query("SELECT COUNT(*) FROM books WHERE estado = 'Extraviado'");
$cant_extraviados = (int)$stmt_extraviados->fetchColumn();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Banners Informativos -->
<div class="alert <?php echo $clase_alerta_horario; ?>" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
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

<!-- Tarjeta Principal del Dashboard -->
<div style="background: white; padding: 28px 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 15px;">
    <div style="text-align: center; margin-bottom: 25px;">
        <h2 style="color: var(--color-primary); margin-bottom: 6px;">¡Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        <p style="color: #64748b; font-size: 0.95rem; margin: 0;">Panel de control y resumen operativo del Sistema de Biblioteca Escolar.</p>
    </div>
    
    <!-- 3 Métricas Principales (Clickeables para ir a su sección) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 25px;">
        <!-- Tarjeta 1: Total Libros -->
        <a href="books.php" style="text-decoration: none; display: block; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); color: white; padding: 22px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 12px -1px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1)';" title="Ver catálogo completo de libros">
            <div style="font-size: 2.3rem; font-weight: bold; line-height: 1.1;"><?php echo number_format($total_libros); ?></div>
            <div style="font-size: 1.05rem; opacity: 0.95; font-weight: 600; margin-top: 4px;">Libros</div>
            <div style="font-size: 0.8rem; opacity: 0.85; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
                <span>Ver inventario completo</span> &rarr;
            </div>
        </a>

        <!-- Tarjeta 2: Libros Disponibles -->
        <a href="books.php?filter=disponible" style="text-decoration: none; display: block; background: linear-gradient(135deg, var(--color-success), #388e3c); color: white; padding: 22px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 12px -1px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1)';" title="Ver libros disponibles para préstamo">
            <div style="font-size: 2.3rem; font-weight: bold; line-height: 1.1;"><?php echo number_format($total_disponibles); ?></div>
            <div style="font-size: 1.05rem; opacity: 0.95; font-weight: 600; margin-top: 4px;">Libros Disponibles</div>
            <div style="font-size: 0.8rem; opacity: 0.85; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
                <span>Ver ejemplares en sala</span> &rarr;
            </div>
        </a>

        <!-- Tarjeta 3: Préstamos Activos -->
        <a href="issue_books.php" style="text-decoration: none; display: block; background: linear-gradient(135deg, var(--color-info), #0277bd); color: white; padding: 22px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 12px -1px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1)';" title="Ver préstamos y registrar devoluciones">
            <div style="font-size: 2.3rem; font-weight: bold; line-height: 1.1;"><?php echo number_format($total_prestados); ?></div>
            <div style="font-size: 1.05rem; opacity: 0.95; font-weight: 600; margin-top: 4px;">Préstamos</div>
            <div style="font-size: 0.8rem; opacity: 0.85; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
                <span>Ver préstamos y devoluciones</span> &rarr;
            </div>
        </a>
    </div>

    <!-- Botones de Acción Rápida -->
    <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; padding-top: 10px; border-top: 1px solid #e2e8f0;">
        <?php if ($_SESSION['user_role'] !== 'Lector'): ?>
        <a href="issue_books.php" class="btn" style="background-color: var(--color-primary); color: white; padding: 12px 22px; font-size: 0.95rem; font-weight: 600; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7.5" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            Crear Préstamo
        </a>
        <a href="add_book.php" class="btn" style="background-color: var(--color-success); color: white; padding: 12px 22px; font-size: 0.95rem; font-weight: 600; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Agregar Libro
        </a>
        <?php endif; ?>
        <a href="books.php" class="btn" style="background-color: #0288d1; color: white; padding: 12px 22px; font-size: 0.95rem; font-weight: 600; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            Ver Libros
        </a>
    </div>
</div>

<!-- Sección de Alerta: Préstamos Vencidos o por Vencer -->
<?php if (!empty($prestamos_alerta)): ?>
<div style="background: white; border-radius: 8px; padding: 22px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); margin-top: 20px; border-left: 5px solid #dc2626;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="pulse-dot-danger"></span>
            <h3 style="margin: 0; color: #991b1b; font-size: 1.15rem;">
                Préstamos Vencidos o por Vencer (<?php echo count($prestamos_alerta); ?>)
            </h3>
        </div>
        <a href="issue_books.php" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.85rem; text-decoration: none;">
            Gestionar en Préstamos &rarr;
        </a>
    </div>
    
    <div class="table-responsive">
        <table class="table" style="width: 100%; font-size: 0.88rem; margin: 0;">
            <thead>
                <tr style="background: #fef2f2; color: #991b1b;">
                    <th style="width: 35%;">Libro</th>
                    <th style="width: 25%;">Alumno / Lector</th>
                    <th style="width: 15%;">Curso / Turno</th>
                    <th style="width: 10%;">Fecha Límite</th>
                    <th style="width: 15%; text-align: center;">Situación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prestamos_alerta as $pa): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($pa['titulo']); ?></strong>
                        <small style="display: block; color: #666;">Inv: <?php echo htmlspecialchars($pa['isbn'] ?? 'S/N'); ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($pa['lector']); ?></strong>
                        <?php if (!empty($pa['lector_documento'])): ?>
                            <small style="display: block; color: #666;">DNI: <?php echo htmlspecialchars($pa['lector_documento']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span><?php echo htmlspecialchars($pa['curso'] ?? '-'); ?></span><br>
                        <?php if (!empty($pa['turno'])): ?>
                            <span class="status-badge" style="background: #e2e8f0; color: #334155; font-size: 0.72rem; padding: 1px 5px;"><?php echo htmlspecialchars($pa['turno']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space: nowrap; font-weight: 600;">
                        <?php echo date('d/m/Y', $pa['fecha_limite_ts']); ?>
                    </td>
                    <td style="text-align: center; white-space: nowrap;">
                        <?php if ($pa['dias_restantes'] < 0): ?>
                            <span class="status-badge" style="background-color: #dc2626; color: white; font-weight: bold;">
                                <span class="pulse-dot-danger"></span>Vencido (hace <?php echo abs($pa['dias_restantes']); ?>d)
                            </span>
                        <?php elseif ($pa['dias_restantes'] === 0): ?>
                            <span class="status-badge" style="background-color: #ea580c; color: white; font-weight: bold;">
                                <span class="pulse-dot-warning"></span>Vence Hoy
                            </span>
                        <?php else: ?>
                            <span class="status-badge" style="background-color: #f59e0b; color: white;">
                                <span class="pulse-dot-warning"></span>Vence en <?php echo $pa['dias_restantes']; ?> días
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
