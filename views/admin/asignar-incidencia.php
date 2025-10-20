<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de incidencias
check_permission('gestionar_incidencias');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar que se ha proporcionado un ID de incidencia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: incidencias.php?error=missing_id");
    exit;
}

$incidencia_id = intval($_GET['id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener detalles de la incidencia
$query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.ID_Prioridad, i.ID_CI, 
                 i.ID_Tecnico, i.ID_Stat, i.CreatedBy,
                 p.Descripcion as Prioridad, s.Descripcion as Estado,
                 ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                 emp.Nombre as Reportado_Por_Nombre
          FROM INCIDENCIA i
          LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
          LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
          LEFT JOIN CI ci ON i.ID_CI = ci.ID
          LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
          LEFT JOIN USUARIO u ON i.CreatedBy = u.ID
          LEFT JOIN EMPLEADO emp ON u.ID_Empleado = emp.ID
          WHERE i.ID = ?";

$stmt = $conn->prepare($query);
$stmt->execute([$incidencia_id]);

// Verificar si la incidencia existe
if ($stmt->rowCount() == 0) {
    header("Location: incidencias.php?error=not_found");
    exit;
}

$incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar si la incidencia ya está asignada
$ya_asignada = ($incidencia['ID_Tecnico'] != null);

// Obtener técnicos disponibles
$query_tecnicos = "SELECT e.ID, e.Nombre, e.Email, r.Nombre as Rol,
                        (SELECT COUNT(*) FROM INCIDENCIA WHERE ID_Tecnico = e.ID AND ID_Stat IN (2, 3, 4)) as IncidenciasActivas
                  FROM EMPLEADO e
                  JOIN ROL r ON e.ID_Rol = r.ID
                  WHERE r.Nombre = 'Técnico TI'
                  ORDER BY IncidenciasActivas ASC, e.Nombre ASC";
$stmt_tecnicos = $conn->prepare($query_tecnicos);
$stmt_tecnicos->execute();
$tecnicos = $stmt_tecnicos->fetchAll(PDO::FETCH_ASSOC);

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos del formulario
        $tecnico_id = $_POST['tecnico_id'] ?? '';
        $comentario = $_POST['comentario'] ?? '';
        
        // Validaciones básicas
        if (empty($tecnico_id)) {
            $error = "Por favor seleccione un técnico.";
        } else {
            // Iniciar transacción
            $conn->beginTransaction();
            
            // Si la incidencia ya tenía un técnico asignado, registrar el cambio
            if ($ya_asignada) {
                // Actualizar el técnico asignado
                $query_update = "UPDATE INCIDENCIA SET ID_Tecnico = ?, ModifiedBy = ?, ModifiedDate = GETDATE() WHERE ID = ?";
                $stmt_update = $conn->prepare($query_update);
                $stmt_update->execute([$tecnico_id, $_SESSION['user_id'], $incidencia_id]);
                
                $mensaje_exito = "Se ha reasignado la incidencia correctamente.";
            } else {
                // Asignar técnico y cambiar el estado a "Asignada"
                $query_update = "UPDATE INCIDENCIA SET ID_Tecnico = ?, ID_Stat = 2, ModifiedBy = ?, ModifiedDate = GETDATE() WHERE ID = ?";
                $stmt_update = $conn->prepare($query_update);
                $stmt_update->execute([$tecnico_id, $_SESSION['user_id'], $incidencia_id]);
                
                // Registrar el cambio de estado en el historial
                $query_hist = "INSERT INTO INCIDENCIA_HISTORIAL (ID_Incidencia, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                              VALUES (?, ?, 2, ?, GETDATE())";
                $stmt_hist = $conn->prepare($query_hist);
                $stmt_hist->execute([$incidencia_id, $incidencia['ID_Stat'], $_SESSION['user_id']]);
                
                $mensaje_exito = "Se ha asignado la incidencia correctamente.";
            }
            
            // Agregar el comentario si se proporcionó
            if (!empty($comentario)) {
                $query_comment = "INSERT INTO INCIDENCIA_COMENTARIO (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                                 VALUES (?, ?, ?, 'ASIGNACION', GETDATE(), 1)";
                $stmt_comment = $conn->prepare($query_comment);
                $stmt_comment->execute([$incidencia_id, $_SESSION['user_id'], $comentario]);
            }
            
            // Confirmar la transacción
            $conn->commit();
            
            // Redirigir con mensaje de éxito
            $success = $mensaje_exito;
            header("Refresh: 3; URL=incidencias.php?success=assigned");
        }
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $conn->rollBack();
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Asignar Incidencia #<?php echo $incidencia_id; ?></h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="incidencias.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a incidencias
        </a>
        <a href="ver-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-info ms-2">
            <i class="fas fa-eye me-2"></i>Ver detalles completos
        </a>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <p class="mt-2 mb-0">Redirigiendo...</p>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!isset($success)): ?>
<!-- Formulario de asignación -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $ya_asignada ? 'Reasignar' : 'Asignar'; ?> Incidencia</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="mb-4">
                        <label for="tecnico_id" class="form-label">Seleccionar Técnico:</label>
                        <select class="form-select" id="tecnico_id" name="tecnico_id" required>
                            <option value="">Seleccionar técnico...</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                                <option value="<?php echo $tecnico['ID']; ?>" <?php echo ($incidencia['ID_Tecnico'] == $tecnico['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tecnico['Nombre']); ?> 
                                    (<?php echo htmlspecialchars($tecnico['Email']); ?>) - 
                                    <?php echo $tecnico['IncidenciasActivas']; ?> incidencias activas
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Se recomienda seleccionar técnicos con menor carga de trabajo.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Comentarios (opcional):</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3" placeholder="Incluya instrucciones o información adicional para el técnico..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i><?php echo $ya_asignada ? 'Reasignar' : 'Asignar'; ?> Incidencia
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
                <h5 class="mb-0">Información de la Incidencia</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <?php 
                            $estado = htmlspecialchars($incidencia['Estado']);
                            $badgeClass = 'bg-info';
                            
                            if ($estado === 'Nueva') {
                                $badgeClass = 'bg-danger';
                            } elseif ($estado === 'Asignada') {
                                $badgeClass = 'bg-primary';
                            } elseif ($estado === 'En proceso') {
                                $badgeClass = 'bg-warning text-dark';
                            } elseif ($estado === 'En espera') {
                                $badgeClass = 'bg-secondary';
                            } elseif ($estado === 'Resuelta') {
                                $badgeClass = 'bg-success';
                            } elseif ($estado === 'Cerrada') {
                                $badgeClass = 'bg-dark';
                            }
                            
                            echo "<span class='badge $badgeClass'>$estado</span>";
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Prioridad:</th>
                        <td>
                            <?php 
                            $prioridad = htmlspecialchars($incidencia['Prioridad']);
                            $badgeClass = 'bg-info';
                            
                            if ($prioridad === 'Crítica') {
                                $badgeClass = 'bg-danger';
                            } elseif ($prioridad === 'Alta') {
                                $badgeClass = 'bg-warning text-dark';
                            } elseif ($prioridad === 'Media') {
                                $badgeClass = 'bg-primary';
                            }
                            
                            echo "<span class='badge $badgeClass'>$prioridad</span>";
                            ?>
                        </td>
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
                        <th>Reportado por:</th>
                        <td><?php echo htmlspecialchars($incidencia['Reportado_Por_Nombre']); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha reporte:</th>
                        <td><?php echo date('d/m/Y H:i', strtotime($incidencia['FechaInicio'])); ?></td>
                    </tr>
                    <tr>
                        <th>Técnico actual:</th>
                        <td>
                            <?php 
                            if ($ya_asignada) {
                                // Obtener nombre del técnico actual
                                $query_tecnico = "SELECT e.Nombre FROM EMPLEADO e WHERE e.ID = ?";
                                $stmt_tecnico = $conn->prepare($query_tecnico);
                                $stmt_tecnico->execute([$incidencia['ID_Tecnico']]);
                                $tecnico_actual = $stmt_tecnico->fetch(PDO::FETCH_ASSOC);
                                echo htmlspecialchars($tecnico_actual['Nombre']);
                            } else {
                                echo '<span class="text-muted">Sin asignar</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                
                <hr>
                
                <h6>Descripción del problema:</h6>
                <p><?php echo nl2br(htmlspecialchars($incidencia['Descripcion'])); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>