<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de Usuarios
check_permission('gestionar_usuarios');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

$id_usuario = $_GET['id'] ?? null;

if (!$id_usuario) {
    // Si no se proporciona ID, redirigir con error
    header("Location: usuarios.php?error=missing_id");
    exit();
}

try {
    // Consulta para obtener los datos del Usuario, el Empleado y el Rol
    $query = "SELECT u.ID, u.Username, u.UltimoAcceso, u.Estado,
              e.Nombre AS NombreEmpleado, r.Nombre AS NombreRol
              FROM USUARIO u
              LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
              LEFT JOIN ROL r ON u.ID_Rol = r.ID
              WHERE u.ID = ?";

    $stmt = $conn->prepare($query);
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Si no se encuentra el usuario, redirigir con error
        header("Location: usuarios.php?error=not_found&mensaje=" . urlencode("El usuario con ID $id_usuario no existe."));
        exit();
    }

} catch (PDOException $e) {
    // Error de conexión o consulta
    header("Location: usuarios.php?error=1&mensaje=" . urlencode("Error de base de datos al cargar el usuario."));
    exit();
}
?>

<h1 class="h2">Detalles del Usuario: <?php echo htmlspecialchars($usuario['Username']); ?></h1>

<div class="row mb-4">
    <div class="col-12">
        <a href="usuarios.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left me-2"></i>Volver al Listado
        </a>
        <a href="editar-usuario.php?id=<?php echo $usuario['ID']; ?>" class="btn btn-warning">
            <i class="fas fa-edit me-2"></i>Editar Usuario
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información General del Usuario</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">ID de Usuario:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($usuario['ID']); ?></dd>

                    <dt class="col-sm-4">Username:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($usuario['Username']); ?></dd>

                    <dt class="col-sm-4">Rol Asignado:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($usuario['NombreRol'] ?? 'N/A'); ?></dd>

                    <dt class="col-sm-4">Empleado Asignado:</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($usuario['NombreEmpleado'] ?? 'No Asignado'); ?></dd>

                    <dt class="col-sm-4">Estado:</dt>
                    <dd class="col-sm-8">
                        <?php if ($usuario['Estado'] == 1): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactivo</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4">Último Acceso:</dt>
                    <dd class="col-sm-8">
                        <?php 
                        if ($usuario['UltimoAcceso']) {
                            echo date('d-m-Y H:i:s', strtotime($usuario['UltimoAcceso']));
                        } else {
                            echo 'Nunca ha accedido';
                        }
                        ?>
                    </dd>

                    <dt class="col-sm-4">Contraseña:</dt>
                    <dd class="col-sm-8">
                        <span class="text-muted">No visible por seguridad. Use "Editar Usuario" para cambiarla.</span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>