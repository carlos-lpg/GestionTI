<?php
// views/admin/asignar-edificios.php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de administrador
check_permission('admin');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ID del usuario seleccionado
        $usuario_id = $_POST['usuario_id'];
        
        // Edificios seleccionados (array)
        $edificios_seleccionados = isset($_POST['edificios']) ? $_POST['edificios'] : [];
        
        // Eliminar asignaciones existentes
        $delete_query = "DELETE FROM USUARIO_EDIFICIO WHERE ID_Usuario = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->execute([$usuario_id]);
        
        // Insertar nuevas asignaciones
        if (!empty($edificios_seleccionados)) {
            $insert_query = "INSERT INTO USUARIO_EDIFICIO (ID_Usuario, ID_Edificio) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            
            foreach ($edificios_seleccionados as $edificio_id) {
                $insert_stmt->execute([$usuario_id, $edificio_id]);
            }
        }
        
        $success = "Asignaciones de edificios actualizadas correctamente.";
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// Obtener lista de usuarios
$usuarios_query = "SELECT u.ID, u.Username, e.Nombre as NombreEmpleado, r.Nombre as Rol
                  FROM USUARIO u
                  JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  JOIN ROL r ON u.ID_Rol = r.ID
                  ORDER BY u.Username";
$usuarios_stmt = $conn->query($usuarios_query);
$usuarios = $usuarios_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de edificios
$edificios_query = "SELECT ID, Nombre FROM EDIFICIO ORDER BY Nombre";
$edificios_stmt = $conn->query($edificios_query);
$edificios = $edificios_stmt->fetchAll(PDO::FETCH_ASSOC);

// Si se seleccionó un usuario, obtener sus edificios asignados
$edificios_usuario = [];
if (isset($_GET['usuario_id'])) {
    $usuario_id = $_GET['usuario_id'];
    $asignados_query = "SELECT ID_Edificio FROM USUARIO_EDIFICIO WHERE ID_Usuario = ?";
    $asignados_stmt = $conn->prepare($asignados_query);
    $asignados_stmt->execute([$usuario_id]);
    $edificios_usuario = $asignados_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!-- Título de la página -->
<h1 class="h2">Asignación de Edificios a Usuarios</h1>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Formulario de selección de usuario -->
<div class="row mb-4">
    <div class="col-12">
        <div class="form-container">
            <h5>Seleccionar Usuario</h5>
            <form action="" method="GET">
                <div class="row">
                    <div class="col-md-6">
                        <select class="form-select" name="usuario_id" required>
                            <option value="">Seleccionar usuario...</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['ID']; ?>" <?php echo (isset($_GET['usuario_id']) && $_GET['usuario_id'] == $usuario['ID']) ? 'selected' : ''; ?>>
                                    <?php echo $usuario['Username'] . ' (' . $usuario['NombreEmpleado'] . ' - ' . $usuario['Rol'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary">Seleccionar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulario de asignación de edificios -->
<?php if (isset($_GET['usuario_id'])): ?>
    <div class="row">
        <div class="col-12">
            <div class="form-container">
                <h5>Asignar Edificios</h5>
                <form action="" method="POST">
                    <input type="hidden" name="usuario_id" value="<?php echo $_GET['usuario_id']; ?>">
                    
                    <div class="mb-3">
                        <p>Seleccione los edificios a los que este usuario tendrá acceso:</p>
                        <div class="row">
                            <?php foreach ($edificios as $edificio): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="edificios[]" 
                                               value="<?php echo $edificio['ID']; ?>" 
                                               id="edificio<?php echo $edificio['ID']; ?>"
                                               <?php echo in_array($edificio['ID'], $edificios_usuario) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="edificio<?php echo $edificio['ID']; ?>">
                                            <?php echo $edificio['Nombre']; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">Guardar Asignaciones</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>