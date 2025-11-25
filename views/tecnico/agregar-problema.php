<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de problemas (Simplificado)
if (!in_array($_SESSION['role_name'], [ 'Coordinador', 'Administrador'])) {
    header('Location: ../../access_denied.php');
    exit;
}

// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../models/Problema.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

$problema = new Problema($conn);

// Inicializar valores por defecto
$titulo = '';
$descripcion = '';
$id_categoria = '';
$id_impacto = '';
$id_stat = 1; // Estado por defecto: 1 = Identificado
$id_responsable = null;
$fecha_identificacion = date('Y-m-d H:i:s'); // Fecha de inicio actual
$id_ci = null; // <-- NUEVO CAMPO: ID del CI seleccionado

// Obtener listas para los selectores
$categorias = $problema->getCategorias()->fetchAll(PDO::FETCH_ASSOC);
$impactos = $problema->getImpactos()->fetchAll(PDO::FETCH_ASSOC);
$estados = $problema->getEstados()->fetchAll(PDO::FETCH_ASSOC);
$responsables = $problema->getResponsablesPotenciales()->fetchAll(PDO::FETCH_ASSOC);

// OBTENER TODOS LOS CIs DISPONIBLES (Elementos de Configuración)
$query_ci = "SELECT ID, Nombre, NumSerie FROM CI ORDER BY Nombre";
$stmt_ci = $conn->prepare($query_ci);
$stmt_ci->execute();
$elementos_ci = $stmt_ci->fetchAll(PDO::FETCH_ASSOC);

$error = null;

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recoger datos del formulario
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $id_categoria = $_POST['categoria'] ?? '';
        $id_impacto = $_POST['impacto'] ?? '';
        $id_stat = $_POST['estado'] ?? 1;
        $id_responsable = $_POST['responsable'] ?? null;
        $id_ci = $_POST['ci_afectado'] ?? null; // <-- CAPTURAR CI
        
        // Validaciones básicas
        if (empty($titulo) || empty($descripcion) || empty($id_categoria) || empty($id_impacto) || empty($id_stat)) {
            $error = "Por favor complete todos los campos obligatorios.";
        } else {
            // Asignar valores al objeto problema para la CREACIÓN
            $problema->titulo = $titulo;
            $problema->descripcion = $descripcion;
            $problema->id_prioridad = 3; // Asumimos una prioridad por defecto (ej. Media ID=3)
            $problema->id_categoria = $id_categoria;
            $problema->id_impacto = $id_impacto;
            $problema->id_stat = $id_stat; 
            $problema->id_responsable = $id_responsable;
            $problema->created_by = $_SESSION['user_id'];
            
            // Lógica de CREACIÓN
            if ($problema->create()) {
                
                // Si se seleccionó un CI, se puede asociar como un comentario/nota inicial
                // NOTA: La tabla PROBLEMA no tiene campo ID_CI, lo registramos como NOTA inicial.
                if (!empty($id_ci)) {
                    $ci_nombre = array_column($elementos_ci, 'Nombre', 'ID')[$id_ci] ?? 'CI Desconocido';
                    $nota_ci = "CI principal asociado al problema en la creación: " . htmlspecialchars($ci_nombre) . " (ID: $id_ci)";
                    $problema->agregarComentario($problema->id, $_SESSION['user_id'], $nota_ci, 'ANALISIS_INICIAL');
                }
                
                // Redireccionar a la página de detalle del nuevo problema con mensaje de éxito
                header("Location: ver-problema.php?id=" . $problema->id . "&success=created");
                exit;
            } else {
                $error = "Error al registrar el problema. Por favor intente nuevamente.";
            }
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<h1 class="h2">Registrar Nuevo Problema</h1>

<div class="row mb-4">
    <div class="col-12">
        <a href="problemas.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a la lista
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información del Problema (Causa Raíz)</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="100" 
                                   value="<?php echo htmlspecialchars($titulo); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select class="form-select" id="categoria" name="categoria" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['ID']; ?>" 
                                            <?php echo ($id_categoria == $categoria['ID']) ? 'selected' : ''; ?>>
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
                                            <?php echo ($id_impacto == $impacto['ID']) ? 'selected' : ''; ?>>
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
                                            <?php echo ($id_stat == $estado['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado['Descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">El estado inicial usualmente es 'Identificado'.</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="responsable" class="form-label">Responsable</label>
                            <select class="form-select" id="responsable" name="responsable">
                                <option value="">Sin asignar</option>
                                <?php foreach ($responsables as $responsable): ?>
                                    <option value="<?php echo $responsable['ID']; ?>" 
                                            <?php echo ($id_responsable == $responsable['ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($responsable['Nombre']); ?> 
                                        (<?php echo htmlspecialchars($responsable['Rol']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="ci_afectado" class="form-label">Elemento de Configuración (CI) Afectado (Opcional)</label>
                            <select class="form-select" id="ci_afectado" name="ci_afectado">
                                <option value="">Seleccionar CI...</option>
                                <?php foreach ($elementos_ci as $ci): ?>
                                    <option value="<?php echo $ci['ID']; ?>">
                                        <?php echo htmlspecialchars($ci['Nombre']); ?> (SN: <?php echo htmlspecialchars($ci['NumSerie']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Identifique el CI central que presenta la falla de raíz.</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="5" required><?php echo htmlspecialchars($descripcion); ?></textarea>
                            <small class="form-text text-muted">Describa el problema, incluyendo síntomas e impacto potencial.</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus-circle me-2"></i>Registrar Problema
                            </button>
                            <a href="problemas.php" class="btn btn-secondary">Cancelar</a>
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