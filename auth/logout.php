<?php
// Inicia la sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Elimina todas las variables de sesión
$_SESSION = array();

// Si se está usando una cookie de sesión, eliminamos también la cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruimos la sesión
session_destroy();

// Redirige al usuario a la página de inicio de sesión
header("Location: ../index.php?logout=success");
exit();