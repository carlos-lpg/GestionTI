<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar si se proporcionó el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: proveedores.php?error=missing_id");
    exit;
}

$id = $_GET['id'];

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener datos del proveedor
try {
    $query = "SELECT * FROM PROVEEDOR WHERE ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() == 0) {
        header("Location: proveedores.php?error=not_found");
        exit;
    }
    
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si se envió el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Recoger datos del formulario
        $nombre = $_POST['nombre'];
        $rfc = $_POST['rfc'];
        $email = $_POST['email'];
        $telefono = $_POST['telefono'];
        $direccion = $_POST['direccion'];
        
        // Validaciones básicas
        if (empty($nombre) || empty($rfc) || empty($email)) {
            $error = "Por favor complete todos los campos obligatorios.";
        } else {
            // Preparar la consulta SQL
            $update_query = "UPDATE PROVEEDOR 
                           SET Nombre = ?, RFC = ?, Email = ?, Telefono = ?, Direccion = ? 
                           WHERE ID = ?";
            
            $update_stmt = $conn->prepare($update_query);
            
            // Ejecutar la consulta
            $update_stmt->execute([$nombre, $rfc, $email, $telefono, $direccion, $id]);
            
            // Redireccionar a la lista de proveedores con mensaje de éxito
            header("Location: proveedores.php?success=3");
            exit;
        }
    }
} catch (PDOException $e) {
    $error = "Error en la base de datos: " . $e->getMessage();
}
?>

<!-- Título de la página -->
<h1 class="h2">Editar Proveedor</h1>

<!-- Formulario para editar proveedor -->
<div class="row">
    <div class="col-12">
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="20" value="<?php echo htmlspecialchars($proveedor['Nombre']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="rfc" class="form-label">RFC *</label>
                        <input type="text" class="form-control" id="rfc" name="rfc" required maxlength="13" value="<?php echo htmlspecialchars($proveedor['RFC']); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="20" value="<?php echo htmlspecialchars($proveedor['Email']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label">Teléfono *</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" required maxlength="12" value="<?php echo htmlspecialchars($proveedor['Telefono']); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="direccion" class="form-label">Dirección *</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" required maxlength="40" value="<?php echo htmlspecialchars($proveedor['Direccion']); ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-success">Guardar Cambios</button>
                        <a href="proveedores.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>