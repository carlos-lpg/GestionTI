<?php
// Incluir archivo de sesión
require_once __DIR__ . '/../auth/session.php';

// Verificar sesión activa
check_session();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de CIs - Tecnm</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Estilos personalizados -->
    <link href="../../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar / Menú lateral -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5>Sistema de CIs</h5>
                        <p>Tecnm</p>
                    </div>
                    <hr>
                    <p class="text-center">
                        Bienvenido, <?php echo get_user_name(); ?><br>
                        <small><?php echo get_role_name(); ?></small>
                    </p>
                    <hr>
                    <ul class="nav flex-column">
                        <!-- El menú se incluirá desde nav.php -->
                        <?php include_once __DIR__ . '/nav.php'; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Contenido principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="dashboard-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><!-- El título se establecerá en cada página --></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../../auth/logout.php" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                        </a>
                    </div>
                </div>

        