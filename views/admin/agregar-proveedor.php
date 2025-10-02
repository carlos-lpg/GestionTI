<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
            // Conectar a la base de datos
            $database = new Database();
            $conn = $database->getConnection();
            
            // Preparar la consulta SQL
            $query = "INSERT INTO PROVEEDOR (Nombre, RFC, Email, Telefono, Direccion) 
                      VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            
            // Ejecutar la consulta
            $stmt->execute([$nombre, $rfc, $email, $telefono, $direccion]);
            
            // Redireccionar a la lista de proveedores con mensaje de éxito
            header("Location: proveedores.php?success=1");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error general: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Agregar Nuevo Proveedor</h1>

<!-- Formulario para agregar proveedor -->
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
                        <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="20" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="rfc" class="form-label">RFC *</label>
                        <input type="text" class="form-control" id="rfc" name="rfc" required maxlength="13" value="<?php echo isset($_POST['rfc']) ? htmlspecialchars($_POST['rfc']) : ''; ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="20" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label">Teléfono *</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" required maxlength="12" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="direccion" class="form-label">Dirección *</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" required maxlength="40" value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-success">Guardar</button>
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