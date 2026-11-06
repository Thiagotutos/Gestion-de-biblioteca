<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['user_role'] === 'Lector') {
    header('Location: books.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT titulo, imagen, estado FROM books WHERE id = ?");
        $stmt->execute([$id]);
        $book = $stmt->fetch();

        if ($book) {
            // Regla de negocio: No permitir eliminar libros en préstamo activo
            $isBorrowed = ($book['estado'] === 'Prestado');
            if (!$isBorrowed) {
                // Verificar si hay préstamos activos en la tabla de transacciones
                $stmt_loan = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE book_id = ? AND accion = 'Prestamo' AND fecha_devolucion_real IS NULL");
                $stmt_loan->execute([$id]);
                if ((int)$stmt_loan->fetchColumn() > 0) {
                    $isBorrowed = true;
                }
            }

            if ($isBorrowed) {
                $_SESSION['error_msg'] = "No se puede eliminar el libro '{$book['titulo']}' porque actualmente se encuentra prestado a un alumno o lector. Primero debes registrar su devolución en el módulo de Préstamos.";
            } else {
                // Eliminar imagen del disco solo si ningún otro libro la está utilizando (ej: copias/donaciones compartidas)
                if (!empty($book['imagen'])) {
                    $stmt_img = $pdo->prepare("SELECT COUNT(*) FROM books WHERE imagen = ? AND id != ?");
                    $stmt_img->execute([$book['imagen'], $id]);
                    if ($stmt_img->fetchColumn() == 0) {
                        $imgPath = __DIR__ . '/../uploads/books/' . $book['imagen'];
                        if (file_exists($imgPath)) {
                            unlink($imgPath);
                        }
                    }
                }

                $stmt_del = $pdo->prepare("DELETE FROM books WHERE id = ?");
                $stmt_del->execute([$id]);
                $_SESSION['success_msg'] = "Libro '{$book['titulo']}' eliminado exitosamente.";
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error al eliminar el libro: " . $e->getMessage();
    }
}

if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: books.php');
}
exit;
