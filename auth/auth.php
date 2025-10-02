<?php
// auth/session.php

/**
 * Verificar si el usuario tiene permiso para realizar una acción
 * @param string|array $required_permission Permiso(s) requerido(s)
 * @return bool True si tiene permiso
 */
function check_permission($required_permission) {
    if (!isset($_SESSION['user_role'])) {
        header('Location: ../../login.php');
        exit;
    }
    
    // Si se pasa un array de permisos, verificar si tiene alguno de ellos
    if (is_array($required_permission)) {
        foreach ($required_permission as $permission) {
            if (has_permission($permission)) {
                return true;
            }
        }
        // Si no tiene ninguno de los permisos requeridos
        header('Location: ../../access_denied.php');
        exit;
    } else {
        // Comportamiento original para un solo permiso
        if (!has_permission($required_permission)) {
            header('Location: ../../access_denied.php');
            exit;
        }
    }
    
    return true;
}

/**
 * Verificar si el usuario actual tiene un permiso específico
 * @param string $permission Permiso a verificar
 * @return bool True si tiene el permiso
 */
function has_permission($permission) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    
    // Definir permisos por rol
    $permissions = [
        'admin' => ['all'],
        'Coordinador TI CEDIS' => ['gestionar_ci', 'gestionar_incidencias', 'gestionar_problemas', 'reportes'],
        'Coordinador TI Sucursales' => ['gestionar_ci', 'gestionar_incidencias', 'gestionar_problemas', 'reportes'],
        'Coordinador TI Corporativo' => ['gestionar_ci', 'gestionar_incidencias', 'gestionar_problemas', 'reportes'],
        'Supervisor Infraestructura' => ['gestionar_ci', 'gestionar_incidencias', 'gestionar_problemas', 'reportes'],
        'Supervisor Sistemas' => ['gestionar_ci', 'gestionar_incidencias', 'gestionar_problemas', 'reportes'],
        'Técnico TI' => ['tecnico', 'gestionar_incidencias', 'gestionar_problemas'] // AGREGAR gestionar_problemas aquí
    ];
    
    // Si es admin, tiene todos los permisos
    if ($user_role === 'admin' || (isset($permissions[$user_role]) && in_array('all', $permissions[$user_role]))) {
        return true;
    }
    
    // Verificar si el rol tiene el permiso específico
    return isset($permissions[$user_role]) && in_array($permission, $permissions[$user_role]);
}

// Resto de funciones existentes en tu session.php...