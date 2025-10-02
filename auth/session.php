<?php
/**
 * Archivo para gestionar las sesiones y la verificación de permisos
 */

// Verifica si hay una sesión activa, de lo contrario redirige al login
function check_session() {
    // Inicia la sesión si no está iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verifica si existe la variable de sesión user_id
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php?error=session_expired");
        exit();
    }
}

// Verifica si el usuario tiene un permiso específico
function check_permission($permission) {
    // Verifica primero que haya sesión activa
    check_session();
    
    // Verifica si el usuario tiene el permiso necesario
    if (!isset($_SESSION['permisos'][$permission]) || $_SESSION['permisos'][$permission] !== true) {
        header("Location: ../../views/error.php?error=no_permission");
        exit();
    }
}

// Determina si mostrar un elemento de menú basado en permisos
function has_permission($permission) {
    // Inicia la sesión si no está iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['permisos'][$permission])) {
        return false;
    }
    return $_SESSION['permisos'][$permission] === true;
}

// Obtiene el nombre del rol actual
function get_role_name() {
    // Inicia la sesión si no está iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['role_name'] ?? 'Desconocido';
}

// Obtiene el nombre completo del usuario
function get_user_name() {
    // Inicia la sesión si no está iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Usuario';
}