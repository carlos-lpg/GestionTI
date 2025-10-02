<?php
// Incluir configuración básica
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../models/Problema.php';

// Verificar permiso de gestión de problemas
check_permission('gestionar_problemas');

// Verificar que se ha proporcionado un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: problemas.php?error=missing_id");
    exit;
}

$problema_id = intval($_GET['id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();
$problema = new Problema($conn);

// Verificar que el problema existe
if (!$problema->getById($problema_id)) {
    header("Location: problemas.php?error=not_found");
    exit;
}

// Verificar si hay confirmación para eliminar
if (isset($_GET['confirm']) && $_GET['confirm'] == '1') {
    // Intentar eliminar el problema
    if ($problema->delete($problema_id)) {
        // Redireccionar a la lista de problemas con mensaje de éxito
        header("Location: problemas.php?success=deleted");
        exit;
    } else {
        // Redireccionar con mensaje de error
        header("Location: problemas.php?error=delete_failed");
        exit;
    }
} else {
    // Mostrar página de confirmación
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Eliminación</title>
    <!-- Los estilos ya estarán incluidos mediante el header.php -->
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Confirmar Eliminación</h5>
                    </div>
                    <div class="card-body">
                        <p>¿Está seguro de que desea eliminar el problema <strong><?php echo htmlspecialchars($problema->titulo); ?></strong>?</p>
                        <p class="text-danger">Esta acción no se puede deshacer. Se eliminarán también todos los comentarios, soluciones propuestas y el historial asociado a este problema.</p>
                        
                        <div class="mt-4">
                            <a href="eliminar-problema.php?id=<?php echo $problema_id; ?>&confirm=1" class="btn btn-danger">Sí, eliminar problema</a>
                            <a href="problemas.php" class="btn btn-secondary ms-2">Cancelar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
}
// No incluir el footer.php porque ya tenemos la estructura HTML completa
?>