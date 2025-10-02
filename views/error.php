<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Sistema de Gestión de CIs</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos personalizados -->
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white text-center">
                        <h4>Error</h4>
                    </div>
                    <div class="card-body text-center">
                        <?php
                        $error_message = "Ha ocurrido un error.";
                        
                        if (isset($_GET['error'])) {
                            switch ($_GET['error']) {
                                case 'no_permission':
                                    $error_message = "No tiene permisos para acceder a esta sección.";
                                    break;
                                case 'not_found':
                                    $error_message = "El recurso solicitado no fue encontrado.";
                                    break;
                                case 'database_error':
                                    $error_message = "Error en la base de datos. Contacte al administrador.";
                                    break;
                                default:
                                    $error_message = "Ha ocurrido un error inesperado.";
                            }
                        }
                        ?>
                        
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        </div>
                        
                        <h5 class="mb-4"><?php echo $error_message; ?></h5>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="javascript:history.back()" class="btn btn-secondary me-2">Volver atrás</a>
                            
                            <?php 
                            // Determinar la página de dashboard según el rol
                            $dashboard_url = "../index.php";
                            if (isset($_SESSION['role_name'])) {
                                switch ($_SESSION['role_name']) {
                                    case 'Administrador':
                                        $dashboard_url = "admin/dashboard.php";
                                        break;
                                    case 'Coordinador TI CEDIS':
                                    case 'Coordinador TI Sucursales':
                                    case 'Coordinador TI Corporativo':
                                        $dashboard_url = "coordinador/dashboard.php";
                                        break;
                                    case 'Técnico TI':
                                        $dashboard_url = "tecnico/dashboard.php";
                                        break;
                                    case 'Supervisor Infraestructura':
                                    case 'Supervisor Sistemas':
                                        $dashboard_url = "supervisor/dashboard.php";
                                        break;
                                    case 'Encargado Inventario':
                                        $dashboard_url = "inventario/dashboard.php";
                                        break;
                                    case 'Gerente TI':
                                        $dashboard_url = "gerente/dashboard.php";
                                        break;
                                    default:
                                        $dashboard_url = "usuario/dashboard.php";
                                }
                            }
                            ?>
                            <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary">Ir al Dashboard</a>
                        <?php else: ?>
                            <a href="../index.php" class="btn btn-primary">Ir al Inicio</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>