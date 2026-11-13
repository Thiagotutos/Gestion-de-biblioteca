<?php
session_start();
session_unset();
session_destroy();
// Redirige al dashboard en modo lector público
header("Location: pages/dashboard.php");
exit;
?>
