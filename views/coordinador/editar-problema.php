<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de problemas
check_permission('gestionar_problemas');

// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../models/Problema.php';

// Verificar si se proporcionó el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redireccionar a la lista
    header("Location: problemas.php?error=missing_id");
    exit;
}

$problema_id = $_GET['id'];

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

$problema = new Problema($conn);


// Obtener listas para los selectores
$categorias = $problema->getCategorias()->fetchAll(PDO::FETCH_ASSOC);
$impactos = $problema->getImpactos()->fetchAll(PDO::FETCH_ASSOC);
$estados = $problema->getEstados()->fetchAll(PDO::FETCH_ASSOC);
$responsables = $problema->getResponsablesPotenciales()->fetchAll(PDO::FETCH_ASSOC);

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recoger datos del formulario
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $id_categoria = $_POST['categoria'] ?? '';
        $id_impacto = $_POST['impacto'] ?? '';
        $id_stat = $_POST['estado'] ?? '';
        $id_responsable = $_POST['responsable'] ?? null;
        
        // Validaciones básicas
        if (empty($titulo) || empty($descripcion) || empty($id_categoria) || empty($id_impacto) || empty($id_stat)) {
            $error = "Por favor complete todos los campos obligatorios.";
        } else {
            // Asignar valores al objeto problema
            $problema->titulo = $titulo;
            $problema->descripcion = $descripcion;
            $problema->id_categoria = $id_categoria;
            $problema->id_impacto = $id_impacto;
            $problema->id_stat = $id_stat;
            $problema->id_responsable = $id_responsable;
            $problema->modified_by = $_SESSION['user_id'];
            
            // Si el estado cambió, registrar el cambio en el historial
            if ($problema->id_stat != $id_stat) {
                $estado_anterior = $problema->id_stat;
                $problema->registrarCambioEstado($problema_id, $estado_anterior, $id_stat, $_SESSION['user_id']);
            }
            
            // Actualizar el problema
            if ($problema->update()) {
                // Redireccionar a la página de detalle del problema con mensaje de éxito
                header("Location: ver-problema.php?id=" . $problema_id . "&success=updated");
                exit;
            } else {
                $error = "Error al actualizar el problema. Por favor intente nuevamente.";
            }
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Editar Problema</h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="problemas.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a la lista
        </a>
        <a href="ver-problema.php?id=<?php echo $problema_id; ?>" class="btn btn-info ms-2">
            <i class="fas fa-eye me-2"></i>Ver detalles
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Formulario para editar problema -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información del Problema</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="100" 
                                   value="<?php echo htmlspecialchars($problema->titulo); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select class="form-select" id="categoria" name="categoria" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['ID']; ?>" 
                                            <?php echo ($problema->id_categoria == $categoria['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['Nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="impacto" class="form-label">Impacto *</label>
                            <select class="form-select" id="impacto" name="impacto" required>
                                <option value="">Seleccionar impacto...</option>
                                <?php foreach ($impactos as $impacto): ?>
                                    <option value="<?php echo $impacto['ID']; ?>" 
                                            <?php echo ($problema->id_impacto == $impacto['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($impacto['Descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="">Seleccionar estado...</option>
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado['ID']; ?>" 
                                            <?php echo ($problema->id_stat == $estado['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado['Descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="responsable" class="form-label">Responsable</label>
                            <select class="form-select" id="responsable" name="responsable">
                                <option value="">Sin asignar</option>
                                <?php foreach ($responsables as $responsable): ?>
                                    <option value="<?php echo $responsable['ID']; ?>" 
                                            <?php echo ($problema->id_responsable == $responsable['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($responsable['Nombre']); ?> 
                                        (<?php echo htmlspecialchars($responsable['Rol']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_identificacion" class="form-label">Fecha de Identificación</label>
                            <input type="text" class="form-control" id="fecha_identificacion" readonly 
                                   value="<?php echo date('d/m/Y', strtotime($problema->fecha_identificacion)); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="5" required><?php echo htmlspecialchars($problema->descripcion); ?></textarea>
                            <small class="form-text text-muted">Describa detalladamente el problema, incluyendo síntomas, condiciones en que ocurre, e impacto en el negocio.</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                            <a href="ver-problema.php?id=<?php echo $problema_id; ?>" class="btn btn-secondary">Cancelar</a>
                        </div>
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