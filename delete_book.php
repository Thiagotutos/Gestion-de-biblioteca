<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user_role'] === 'Lector') {
    header('Location: books.php');
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    // Eliminar imagen asociada si existe
    $stmt = $pdo->prepare("SELECT imagen FROM books WHERE id = ?");
    $stmt->execute([$id]);
    $book = $stmt->fetch();
    if ($book && $book['imagen']) {
        $imgPath = __DIR__ . '/uploads/books/' . $book['imagen'];
        if (file_exists($imgPath)) unlink($imgPath);
    }

    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success_msg'] = "Libro eliminado exitosamente.";
}

if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: books.php');
}
exit;
?>
