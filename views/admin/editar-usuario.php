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

$error_message = '';
$usuario_data = null;
$id_usuario = $_GET['id'] ?? null;

// ----------------------------------------------------------------------
// 1. Obtener datos del usuario a editar y listas para dropdowns
// ----------------------------------------------------------------------

if (!$id_usuario) {
    // Si no hay ID, redirigir con error
    header("Location: usuarios.php?error=missing_id");
    exit();
}

try {
    // Obtener datos del usuario existente
    $query = "SELECT ID, Username, Estado, ID_Empleado, ID_Rol FROM USUARIO WHERE ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id_usuario]);
    $usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_data) {
        // Si no se encuentra el usuario, redirigir con error
        header("Location: usuarios.php?error=not_found&mensaje=" . urlencode("El usuario con ID $id_usuario no existe."));
        exit();
    }
    
    // Obtener lista de Roles
    $rolStmt = $conn->prepare("SELECT ID, Nombre FROM ROL ORDER BY Nombre");
    $rolStmt->execute();
    $roles = $rolStmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de Empleados disponibles + el asignado actualmente
    // Esto asegura que el empleado actualmente asignado al usuario se muestre en el dropdown
    $empleadoStmt = $conn->prepare("SELECT e.ID, e.Nombre 
                                    FROM EMPLEADO e
                                    LEFT JOIN USUARIO u ON e.ID = u.ID_Empleado
                                    WHERE u.ID_Empleado IS NULL OR u.ID = ?
                                    ORDER BY e.Nombre");
    $empleadoStmt->execute([$id_usuario]);
    $empleados = $empleadoStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = 'Error al cargar datos: ' . $e->getMessage();
}


// ----------------------------------------------------------------------
// 2. Procesar el formulario POST (Actualización)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir y sanear los datos
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $estado = $_POST['estado'] ?? 0;
    $id_empleado = $_POST['id_empleado'] ?? null;
    $id_rol = $_POST['id_rol'] ?? null;

    // Validación básica
    if (empty($username) || empty($id_rol)) {
        $error_message = 'Los campos obligatorios (Usuario, Rol) deben ser completados.';
    } else {
        try {
            // Consulta base de actualización
            $query = "UPDATE USUARIO SET Username = ?, Estado = ?, ID_Empleado = ?, ID_Rol = ?";
            $params = [$username, $estado, $id_empleado, $id_rol];

            // ------------------------------------
            // Manejo de la contraseña
            // ------------------------------------
            if (!empty($password)) {
                // Solo si se ingresa una nueva contraseña, la hasheamos y la incluimos en el UPDATE
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query .= ", Password = ?";
                $params[] = $hashed_password;
            }

            // Cláusula WHERE
            $query .= " WHERE ID = ?";
            $params[] = $id_usuario;
            
            $stmt = $conn->prepare($query);
            
            // Ejecutar la consulta
            if ($stmt->execute($params)) {
                // Redirigir a la página principal con mensaje de éxito
                header("Location: usuarios.php?success=1&mensaje=" . urlencode("Usuario '$username' actualizado con éxito."));
                exit();
            } else {
                $error_message = 'Error al actualizar el usuario en la base de datos.';
            }

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error_message = 'Error: El nombre de usuario ya existe o hay un problema con las claves foráneas.';
            } else {
                $error_message = 'Error de base de datos: ' . $e->getMessage();
            }
        }
    }
    
    // Si hay un error, actualizar $usuario_data para que el formulario mantenga los datos del POST
    if (!empty($error_message)) {
        $usuario_data['Username'] = $username;
        $usuario_data['Estado'] = $estado;
        $usuario_data['ID_Empleado'] = $id_empleado;
        $usuario_data['ID_Rol'] = $id_rol;
    }
}
?>

<h1 class="h2">Editar Usuario: <?php echo htmlspecialchars($usuario_data['Username'] ?? 'ID ' . $id_usuario); ?></h1>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="editar-usuario.php?id=<?php echo htmlspecialchars($id_usuario); ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario (Username) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($usuario_data['Username'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <div class="form-text">Deje en blanco para mantener la contraseña actual.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_rol" name="id_rol" required>
                            <option value="">Seleccione un Rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['ID']; ?>" <?php echo (($usuario_data['ID_Rol'] ?? '') == $rol['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rol['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_empleado" class="form-label">Empleado Asignado</label>
                        <select class="form-select" id="id_empleado" name="id_empleado">
                            <option value="">(Sin Asignar)</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?php echo $empleado['ID']; ?>" <?php echo (($usuario_data['ID_Empleado'] ?? '') == $empleado['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empleado['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Solo se muestran empleados sin cuenta, además del empleado actualmente asignado.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado" id="estado_activo" value="1" <?php echo (($usuario_data['Estado'] ?? 1) == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="estado_activo">Activo</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado" id="estado_inactivo" value="0" <?php echo (($usuario_data['Estado'] ?? 1) == 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="estado_inactivo">Inactivo</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning me-2">
                        <i class="fas fa-edit me-2"></i>Actualizar Usuario
                    </button>
                    <a href="usuarios.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Cancelar
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>