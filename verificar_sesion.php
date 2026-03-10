<?php
// verificar_sesion.php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Opcional: Verificar rol para páginas específicas
function requiere_rol($rol_requerido) {
    if ($_SESSION['rol'] != $rol_requerido && $_SESSION['rol'] != 'admin') {
        header("Location: index.php?error=acceso_denegado");
        exit();
    }
}
?>