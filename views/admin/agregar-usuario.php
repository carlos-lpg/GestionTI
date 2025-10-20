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
// 1. Obtener listas para los dropdowns (Roles)
// ----------------------------------------------------------------------

// Obtener lista de Roles
$rolStmt = $conn->prepare("SELECT ID, Nombre FROM ROL ORDER BY Nombre");
$rolStmt->execute();
$roles = $rolStmt->fetchAll(PDO::FETCH_ASSOC);

$error_message = '';

// ----------------------------------------------------------------------
// 2. Procesar el formulario POST
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir y sanear los datos del USUARIO
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $estado = $_POST['estado'] ?? 0;
    $id_rol = $_POST['id_rol'] ?? null;

    // Recibir y sanear los datos del EMPLEADO
    $nombre_empleado = trim($_POST['nombre_empleado'] ?? '');
    $email_empleado = trim($_POST['email_empleado'] ?? '');
    $celular_empleado = trim($_POST['celular_empleado'] ?? '');
    $direccion_empleado = trim($_POST['direccion_empleado'] ?? '');

    // Validación básica
    if (empty($username) || empty($password) || empty($id_rol) || 
        empty($nombre_empleado) || empty($email_empleado)) {
        $error_message = 'Todos los campos obligatorios (*) deben ser completados.';
    } else {
        try {
            // Iniciar transacción para asegurar consistencia en ambas tablas
            $conn->beginTransaction();

            // ----------------------------------------------------------------------
            // 3. INSERT en tabla EMPLEADO
            // ----------------------------------------------------------------------
            $queryEmpleado = "INSERT INTO EMPLEADO (Nombre, Email, Celular, Direccion, ID_Rol)
                             VALUES (?, ?, ?, ?, ?)";
            
            $stmtEmpleado = $conn->prepare($queryEmpleado);
            $stmtEmpleado->execute([
                $nombre_empleado, 
                $email_empleado, 
                $celular_empleado, 
                $direccion_empleado, 
                $id_rol
            ]);

            // Obtener el ID del empleado recién insertado
            $id_empleado = $conn->lastInsertId();

            // ----------------------------------------------------------------------
            // 4. INSERT en tabla USUARIO
            // ----------------------------------------------------------------------
            
            // Hashing de la contraseña (¡CRUCIAL PARA LA SEGURIDAD!)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $queryUsuario = "INSERT INTO USUARIO (Username, Password, Estado, ID_Empleado, ID_Rol)
                            VALUES (?, ?, ?, ?, ?)";
            
            $stmtUsuario = $conn->prepare($queryUsuario);
            $stmtUsuario->execute([
                $username, 
                $hashed_password, 
                $estado, 
                $id_empleado, 
                $id_rol
            ]);

            // Confirmar transacción
            $conn->commit();

            // Redirigir a la página principal con mensaje de éxito
            header("Location: usuarios.php?success=1&mensaje=" . urlencode("Usuario '$username' y empleado '$nombre_empleado' creados con éxito."));
            exit();

        } catch (PDOException $e) {
            // En caso de error, revertir transacción
            $conn->rollBack();
            
            // Manejar errores específicos
            if ($e->getCode() == 23000) { // Código de error de integridad
                if (strpos($e->getMessage(), 'Username') !== false) {
                    $error_message = 'Error: El nombre de usuario ya existe.';
                } elseif (strpos($e->getMessage(), 'Email') !== false) {
                    $error_message = 'Error: El correo electrónico ya está registrado.';
                } else {
                    $error_message = 'Error: Violación de integridad de datos. Verifique que los datos sean únicos.';
                }
            } else {
                $error_message = 'Error de base de datos: ' . $e->getMessage();
            }
        }
    }
}
?>

<h1 class="h2">Agregar Nuevo Usuario y Empleado</h1>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body">
                <form action="agregar-usuario.php" method="POST">
                    
                    <h5 class="text-primary mb-4">
                        <i class="fas fa-user me-2"></i>Información del Empleado
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nombre_empleado" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre_empleado" name="nombre_empleado" 
                                       value="<?php echo htmlspecialchars($nombre_empleado ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email_empleado" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email_empleado" name="email_empleado" 
                                       value="<?php echo htmlspecialchars($email_empleado ?? ''); ?>" required>
                                <div class="form-text">Ej: usuario@culiacan.tecnm.mx</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="celular_empleado" class="form-label">Celular</label>
                                <input type="tel" class="form-control" id="celular_empleado" name="celular_empleado" 
                                       value="<?php echo htmlspecialchars($celular_empleado ?? ''); ?>" 
                                       pattern="[0-9]{10}" placeholder="10 dígitos">
                                <div class="form-text">Ej: 5512345678</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="direccion_empleado" class="form-label">Dirección</label>
                                <input type="text" class="form-control" id="direccion_empleado" name="direccion_empleado" 
                                       value="<?php echo htmlspecialchars($direccion_empleado ?? ''); ?>">
                                <div class="form-text">Ej: Oficina Central, Sucursal Reforma</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    
                    <h5 class="text-primary mb-4">
                        <i class="fas fa-key me-2"></i>Información de Cuenta de Usuario
                    </h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Usuario (Username) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                                <div class="form-text">Nombre de usuario para iniciar sesión</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Mínimo 8 caracteres recomendado</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_rol" class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_rol" name="id_rol" required>
                                    <option value="">Seleccione un Rol</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?php echo $rol['ID']; ?>" 
                                                <?php echo (($id_rol ?? '') == $rol['ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rol['Nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Estado de la Cuenta</label>
                                <div class="mt-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="estado" id="estado_activo" value="1" 
                                               <?php echo (($estado ?? 1) == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="estado_activo">Activo</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="estado" id="estado_inactivo" value="0" 
                                               <?php echo (($estado ?? 1) == 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="estado_inactivo">Inactivo</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> Al guardar, se crearán tanto el registro del empleado como la cuenta de usuario asociada.
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-success me-2">
                            <i class="fas fa-save me-2"></i>Guardar Usuario y Empleado
                        </button>
                        <a href="usuarios.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>