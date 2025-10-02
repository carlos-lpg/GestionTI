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
                 e.Nombre as Tecnico_Nombre
          FROM INCIDENCIA i
          LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
          LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
          LEFT JOIN CI ci ON i.ID_CI = ci.ID
          LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
          LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
          WHERE i.ID = ?";

$stmt = $conn->prepare($query);
$stmt->execute([$incidencia_id]);

// Verificar si la incidencia existe y pertenece al usuario actual
if ($stmt->rowCount() == 0) {
    header("Location: mis-incidencias.php?error=not_found");
    exit;
}

$incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar si el usuario tiene permiso para liberar esta incidencia
if ($incidencia['CreatedBy'] != $_SESSION['user_id']) {
    header("Location: mis-incidencias.php?error=permission_denied");
    exit;
}

// Verificar que la incidencia está en estado "Resuelta" (ID_Stat = 5)
if ($incidencia['ID_Stat'] != 5) {
    header("Location: ver-incidencia.php?id=$incidencia_id&error=wrong_state");
    exit;
}

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        $accion = $_POST['accion'];
        $comentario = $_POST['comentario'] ?? '';
        
        if ($accion === 'cerrar') {
            // Cambiar el estado de la incidencia a "Cerrada" (ID_Stat = 6)
            $query_update = "UPDATE INCIDENCIA SET ID_Stat = 6, ModifiedBy = ?, ModifiedDate = GETDATE() WHERE ID = ?";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->execute([$_SESSION['user_id'], $incidencia_id]);
            
            // Registrar el cambio de estado en el historial
            $query_hist = "INSERT INTO INCIDENCIA_HISTORIAL (ID_Incidencia, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                          VALUES (?, 5, 6, ?, GETDATE())";
            $stmt_hist = $conn->prepare($query_hist);
            $stmt_hist->execute([$incidencia_id, $_SESSION['user_id']]);
            
            // Agregar comentario si se proporcionó
            if (!empty($comentario)) {
                $query_comment = "INSERT INTO INCIDENCIA_COMENTARIO (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                                 VALUES (?, ?, ?, 'LIBERACION', GETDATE(), 1)";
                $stmt_comment = $conn->prepare($query_comment);
                $stmt_comment->execute([$incidencia_id, $_SESSION['user_id'], $comentario]);
            }
            
            // Confirmar la transacción
            $conn->commit();
            
            // Redirigir a la página de evaluación
            header("Location: evaluar-incidencia.php?id=$incidencia_id&success=liberada");
            exit;
        } elseif ($accion === 'rechazar') {
            // Cambiar el estado de la incidencia a "En proceso" (ID_Stat = 3)
            $query_update = "UPDATE INCIDENCIA SET ID_Stat = 3, ModifiedBy = ?, ModifiedDate = GETDATE() WHERE ID = ?";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->execute([$_SESSION['user_id'], $incidencia_id]);
            
            // Registrar el cambio de estado en el historial
            $query_hist = "INSERT INTO INCIDENCIA_HISTORIAL (ID_Incidencia, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                          VALUES (?, 5, 3, ?, GETDATE())";
            $stmt_hist = $conn->prepare($query_hist);
            $stmt_hist->execute([$incidencia_id, $_SESSION['user_id']]);
            
            // Agregar comentario (obligatorio para rechazar)
            if (!empty($comentario)) {
                $query_comment = "INSERT INTO INCIDENCIA_COMENTARIO (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                                 VALUES (?, ?, ?, 'RECHAZO', GETDATE(), 1)";
                $stmt_comment = $conn->prepare($query_comment);
                $stmt_comment->execute([$incidencia_id, $_SESSION['user_id'], $comentario]);
            }
            
            // Confirmar la transacción
            $conn->commit();
            
            // Redirigir a la vista de la incidencia
            header("Location: ver-incidencia.php?id=$incidencia_id&success=rechazada");
            exit;
        } else {
            $error = "Acción no válida.";
            $conn->rollBack();
        }
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $conn->rollBack();
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Liberación de Incidencia #<?php echo $incidencia_id; ?></h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="ver-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a la incidencia
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Información de liberación -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Liberación de Incidencia</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    El técnico ha marcado esta incidencia como <strong>resuelta</strong>. Por favor, confirme si el problema ha sido solucionado satisfactoriamente.
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Detalles de la Incidencia:</h6>
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">Descripción:</th>
                                <td><?php echo htmlspecialchars($incidencia['Descripcion']); ?></td>
                            </tr>
                            <tr>
                                <th>Elemento afectado:</th>
                                <td>
                                    <?php if ($incidencia['CI_Tipo']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($incidencia['CI_Tipo']); ?></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($incidencia['CI_Nombre']); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Técnico asignado:</th>
                                <td><?php echo htmlspecialchars($incidencia['Tecnico_Nombre']); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha de reporte:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($incidencia['FechaInicio'])); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha de resolución:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($incidencia['FechaTerminacion'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Últimos comentarios:</h6>
                        <?php
                        // Obtener los últimos comentarios
                        $query_comentarios = "SELECT TOP 3 c.Comentario, c.FechaRegistro, c.TipoComentario, e.Nombre as NombreEmpleado
                                             FROM INCIDENCIA_COMENTARIO c
                                             LEFT JOIN USUARIO u ON c.ID_Usuario = u.ID
                                             LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                                             WHERE c.ID_Incidencia = ? AND c.Publico = 1
                                             ORDER BY c.FechaRegistro DESC";
                        $stmt_comentarios = $conn->prepare($query_comentarios);
                        $stmt_comentarios->execute([$incidencia_id]);
                        
                        if ($stmt_comentarios->rowCount() > 0) {
                            while ($comentario = $stmt_comentarios->fetch(PDO::FETCH_ASSOC)) {
                                echo '<div class="card mb-2">';
                                echo '<div class="card-body p-2">';
                                echo '<p class="mb-1"><strong>' . htmlspecialchars($comentario['NombreEmpleado']) . '</strong> ';
                                echo '<small class="text-muted">(' . date('d/m/Y H:i', strtotime($comentario['FechaRegistro'])) . ')</small></p>';
                                echo '<p class="mb-0">' . nl2br(htmlspecialchars($comentario['Comentario'])) . '</p>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p class="text-muted">No hay comentarios recientes.</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <form action="" method="POST">
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="comentario" class="form-label">Comentarios (opcional para liberación, obligatorio para rechazo):</label>
                                <textarea class="form-control" id="comentario" name="comentario" rows="3" placeholder="Escriba sus comentarios o razones para rechazar la solución..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 text-center">
                            <p class="mb-3">¿Confirma que la incidencia ha sido resuelta satisfactoriamente?</p>
                            <button type="submit" name="accion" value="cerrar" class="btn btn-success btn-lg me-2">
                                <i class="fas fa-check-circle me-2"></i>Sí, el problema está resuelto
                            </button>
                            <button type="submit" name="accion" value="rechazar" class="btn btn-danger btn-lg" onclick="return validarRechazo()">
                                <i class="fas fa-times-circle me-2"></i>No, el problema persiste
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function validarRechazo() {
    var comentario = document.getElementById('comentario').value.trim();
    if (comentario === '') {
        alert('Por favor, proporcione una razón para rechazar la solución.');
        return false;
    }
    return true;
}
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>