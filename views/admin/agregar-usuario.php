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

// ----------------------------------------------------------------------
// 1. Obtener listas para los dropdowns (Roles y Empleados)
// ----------------------------------------------------------------------

// Obtener lista de Roles
$rolStmt = $conn->prepare("SELECT ID, Nombre FROM ROL ORDER BY Nombre");
$rolStmt->execute();
$roles = $rolStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de Empleados disponibles para asignación (los que aún no tienen cuenta)
// NOTA: Esta lógica puede requerir ajuste según cómo manejes la asignación de empleados
$empleadoStmt = $conn->prepare("SELECT e.ID, e.Nombre 
                                FROM EMPLEADO e
                                LEFT JOIN USUARIO u ON e.ID = u.ID_Empleado
                                WHERE u.ID_Empleado IS NULL
                                ORDER BY e.Nombre");
$empleadoStmt->execute();
$empleados = $empleadoStmt->fetchAll(PDO::FETCH_ASSOC);

$error_message = '';

// ----------------------------------------------------------------------
// 2. Procesar el formulario POST
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir y sanear los datos
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // La contraseña no se sanea con trim() para preservar espacios intencionales
    $estado = $_POST['estado'] ?? 0;
    $id_empleado = $_POST['id_empleado'] ?? null;
    $id_rol = $_POST['id_rol'] ?? null;

    // Validación básica
    if (empty($username) || empty($password) || empty($id_rol)) {
        $error_message = 'Todos los campos obligatorios (Usuario, Contraseña, Rol) deben ser completados.';
    } else {
        try {
            // Hashing de la contraseña (¡CRUCIAL PARA LA SEGURIDAD!)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Preparar la consulta INSERT
            $query = "INSERT INTO USUARIO (Username, Password, Estado, ID_Empleado, ID_Rol)
                      VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            
            // Ejecutar la consulta
            if ($stmt->execute([$username, $hashed_password, $estado, $id_empleado, $id_rol])) {
                // Redirigir a la página principal con mensaje de éxito
                header("Location: usuarios.php?success=1&mensaje=" . urlencode("Usuario '$username' creado con éxito."));
                exit();
            } else {
                $error_message = 'Error al guardar el usuario en la base de datos.';
            }

        } catch (PDOException $e) {
            // En caso de error de base de datos (ej. duplicidad de Username)
            if ($e->getCode() == 23000) { // Código de error de integridad (puede variar según el driver)
                $error_message = 'Error: El nombre de usuario ya existe o hay un problema con las claves foráneas.';
            } else {
                $error_message = 'Error de base de datos: ' . $e->getMessage();
            }
        }
    }
}
?>

<h1 class="h2">Agregar Nuevo Usuario</h1>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="agregar-usuario.php" method="POST">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario (Username) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Mínimo 8 caracteres recomendado.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_rol" name="id_rol" required>
                            <option value="">Seleccione un Rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['ID']; ?>" <?php echo (($id_rol ?? '') == $rol['ID']) ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $empleado['ID']; ?>" <?php echo (($id_empleado ?? '') == $empleado['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empleado['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Solo se muestran empleados que no tienen un usuario asignado.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado" id="estado_activo" value="1" <?php echo (($estado ?? 1) == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="estado_activo">Activo</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado" id="estado_inactivo" value="0" <?php echo (($estado ?? 1) == 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="estado_inactivo">Inactivo</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success me-2">
                        <i class="fas fa-save me-2"></i>Guardar Usuario
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