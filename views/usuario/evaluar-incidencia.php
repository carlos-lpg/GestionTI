<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de reportar incidencias
check_permission('reportar_incidencia');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar que se ha proporcionado un ID de incidencia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: mis-incidencias.php?error=missing_id");
    exit;
}

$incidencia_id = intval($_GET['id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener detalles de la incidencia
$query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                 i.ID_Prioridad, i.ID_CI, i.ID_Tecnico, i.ID_Stat, i.CreatedBy,
                 p.Descripcion as Prioridad, s.Descripcion as Estado,
                 ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                 e.Nombre as Tecnico_Nombre, e.ID as Tecnico_ID
          FROM INCIDENCIA i
          LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
          LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
          LEFT JOIN CI ci ON i.ID_CI = ci.ID
          LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
          LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
          WHERE i.ID = ?";

$stmt = $conn->prepare($query);
$stmt->execute([$incidencia_id]);

// Verificar si la incidencia existe
if ($stmt->rowCount() == 0) {
    header("Location: mis-incidencias.php?error=not_found");
    exit;
}

$incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar si la incidencia fue reportada por el usuario actual
if ($incidencia['CreatedBy'] != $_SESSION['user_id']) {
    header("Location: mis-incidencias.php?error=permission_denied");
    exit;
}

// Verificar que la incidencia esté resuelta o cerrada
if ($incidencia['ID_Stat'] != 5 && $incidencia['ID_Stat'] != 6) { // 5 = Resuelta, 6 = Cerrada
    header("Location: ver-incidencia.php?id=$incidencia_id&error=not_resolved");
    exit;
}

// Verificar si ya existe una evaluación
$query_eval = "SELECT ID FROM INCIDENCIA_EVALUACION WHERE ID_Incidencia = ?";
$stmt_eval = $conn->prepare($query_eval);
$stmt_eval->execute([$incidencia_id]);

if ($stmt_eval->rowCount() > 0) {
    header("Location: ver-incidencia.php?id=$incidencia_id&error=already_evaluated");
    exit;
}

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos del formulario
        $calificacion = $_POST['calificacion'] ?? '';
        $comentario = $_POST['comentario'] ?? '';
        
        // Validaciones básicas
        if (empty($calificacion)) {
            $error = "Por favor seleccione una calificación.";
        } else {
            // Iniciar transacción
            $conn->beginTransaction();
            
            // Insertar la evaluación
            $query = "INSERT INTO INCIDENCIA_EVALUACION (ID_Incidencia, ID_Usuario, ID_Tecnico, Calificacion, Comentario, FechaRegistro) 
                      VALUES (?, ?, ?, ?, ?, GETDATE())";
            $stmt = $conn->prepare($query);
            
            // Ejecutar la consulta
            $stmt->execute([
                $incidencia_id,
                $_SESSION['user_id'],
                $incidencia['Tecnico_ID'],
                $calificacion,
                $comentario
            ]);
            
            // Si la incidencia está resuelta, actualizar a cerrada
            if ($incidencia['ID_Stat'] == 5) {
                $query_update = "UPDATE INCIDENCIA SET ID_Stat = 6, ModifiedBy = ?, ModifiedDate = GETDATE() WHERE ID = ?";
                $stmt_update = $conn->prepare($query_update);
                $stmt_update->execute([$_SESSION['user_id'], $incidencia_id]);
                
                // Registrar el cambio de estado en el historial
                $query_hist = "INSERT INTO INCIDENCIA_HISTORIAL (ID_Incidencia, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                              VALUES (?, 5, 6, ?, GETDATE())";
                $stmt_hist = $conn->prepare($query_hist);
                $stmt_hist->execute([$incidencia_id, $_SESSION['user_id']]);
            }
            
            // Confirmar transacción
            $conn->commit();
            
            // Mostrar mensaje de éxito y redirigir
            $success = "Evaluación guardada correctamente. Gracias por su retroalimentación.";
            header("Refresh: 3; URL=mis-incidencias.php");
        }
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $conn->rollBack();
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Evaluar Resolución de Incidencia #<?php echo $incidencia_id; ?></h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="mis-incidencias.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a mis incidencias
        </a>
        <a href="ver-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-info ms-2">
            <i class="fas fa-eye me-2"></i>Ver detalles de la incidencia
        </a>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <p class="mt-2 mb-0">Redirigiendo a la lista de incidencias...</p>
    </div>
<?php elseif (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!isset($success)): ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Evaluar la Atención</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Su retroalimentación es importante para mejorar nuestro servicio. Por favor, evalúe la resolución de su incidencia.
                    </div>
                    
                    <form action="" method="POST">
                        <div class="mb-4">
                            <label class="form-label">Calificación de la atención: *</label>
                            <div class="rating-input">
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="calificacion" id="rating-<?php echo $i; ?>" value="<?php echo $i; ?>" required>
                                            <label class="form-check-label" for="rating-<?php echo $i; ?>">
                                                <?php echo $i; ?> <i class="fas fa-star text-warning"></i>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <div class="mt-2">
                                    <small class="form-text text-muted">1 estrella = Deficiente, 5 estrellas = Excelente</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="comentario" class="form-label">Comentarios (opcional):</label>
                            <textarea class="form-control" id="comentario" name="comentario" rows="3" placeholder="Comparta su opinión sobre la atención recibida..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <hr>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Evaluación
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Resumen de la Incidencia</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th>Estado:</th>
                            <td>
                                <?php 
                                $estado = htmlspecialchars($incidencia['Estado']);
                                $badgeClass = $estado === 'Resuelta' ? 'bg-success' : 'bg-dark';
                                echo "<span class='badge $badgeClass'>$estado</span>";
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Técnico:</th>
                            <td><?php echo htmlspecialchars($incidencia['Tecnico_Nombre'] ?? 'No asignado'); ?></td>
                        </tr>
                        <tr>
                            <th>Elemento:</th>
                            <td>
                                <?php if ($incidencia['CI_Tipo']): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($incidencia['CI_Tipo']); ?></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($incidencia['CI_Nombre']); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Fecha reporte:</th>
                            <td><?php echo date('d/m/Y', strtotime($incidencia['FechaInicio'])); ?></td>
                        </tr>
                        <tr>
                            <th>Fecha resolución:</th>
                            <td>
                                <?php if ($incidencia['FechaTerminacion']): ?>
                                    <?php echo date('d/m/Y', strtotime($incidencia['FechaTerminacion'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">No disponible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <hr>
                    
                    <h6>Descripción breve:</h6>
                    <p><?php 
                        // Mostrar solo las primeras líneas de la descripción
                        $desc = explode("\n", $incidencia['Descripcion']);
                        echo htmlspecialchars(substr($desc[0], 0, 150));
                        if (strlen($desc[0]) > 150) echo '...';
                    ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Script para mejorar la experiencia de selección de calificación -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ratingInputs = document.querySelectorAll('input[name="calificacion"]');
    const ratingLabels = document.querySelectorAll('.rating-stars label');
    
    // Función para actualizar estrellas
    function updateStars() {
        let selectedRating = 0;
        
        // Encontrar la calificación seleccionada
        ratingInputs.forEach(input => {
            if (input.checked) {
                selectedRating = parseInt(input.value);
            }
        });
        
        // Actualizar apariencia de las estrellas
        ratingLabels.forEach((label, index) => {
            const rating = index + 1;
            if (rating <= selectedRating) {
                label.classList.add('text-warning');
            } else {
                label.classList.remove('text-warning');
            }
        });
    }
    
    // Asignar eventos
    ratingInputs.forEach(input => {
        input.addEventListener('change', updateStars);
    });
});
</script>

<style>
.rating-stars {
    display: flex;
    justify-content: space-between;
    max-width: 400px;
}

.rating-stars label {
    cursor: pointer;
    transition: color 0.2s;
}

.rating-stars label:hover {
    color: #ffc107;
}
</style>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>