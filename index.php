<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de CIs - Tecnm</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos personalizados -->
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container login-container">
        <div class="card">
            <div class="card-header">
                <h4>Sistema de Gestión de CIs</h4>
                <h6>Tecnm</h6>
            </div>
            <div class="card-body p-4">
                <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                        $error = $_GET['error'];
                        switch($error) {
                            case 'empty_fields':
                                echo "Por favor complete todos los campos.";
                                break;
                            case 'wrong_password':
                                echo "Contraseña incorrecta. Intente nuevamente.";
                                break;
                            case 'user_not_found':
                                echo "Usuario no encontrado en el sistema.";
                                break;
                            case 'session_expired':
                                echo "Su sesión ha expirado. Por favor inicie sesión nuevamente.";
                                break;
                            default:
                                echo "Ha ocurrido un error. Por favor intente nuevamente.";
                        }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if(isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Ha cerrado sesión correctamente.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form action="auth/login.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-login">Iniciar Sesión</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>