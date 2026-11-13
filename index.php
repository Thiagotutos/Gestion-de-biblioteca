<?php
session_start();
// Redirige directamente al Dashboard en modo catálogo / Lector público
header('Location: pages/dashboard.php');
exit;
?>
