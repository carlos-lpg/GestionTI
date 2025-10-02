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
                 e.Nombre as Tecnico_Nombre, e.Email as Tecnico_Email
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

// Comprobar si la tabla de comentarios existe antes de consultarla
$comentarios = [];
$historiales = [];
$solucion = null;
$evaluacion = null;

// Verificar si existe la tabla INCIDENCIA_COMENTARIO
$check_table_query = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'INCIDENCIA_COMENTARIO'";
$check_table_stmt = $conn->prepare($check_table_query);
$check_table_stmt->execute();
$table_exists = ($check_table_stmt->rowCount() > 0);

if ($table_exists) {
    // Obtener comentarios públicos de la incidencia
    $query_comentarios = "SELECT c.ID, c.Comentario, c.TipoComentario, c.FechaRegistro, 
                               u.Username, e.Nombre as NombreEmpleado
                        FROM INCIDENCIA_COMENTARIO c
                        LEFT JOIN USUARIO u ON c.ID_Usuario = u.ID
                        LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                        WHERE c.ID_Incidencia = ? AND c.Publico = 1
                        ORDER BY c.FechaRegistro ASC";
    $stmt_comentarios = $conn->prepare($query_comentarios);
    $stmt_comentarios->execute([$incidencia_id]);
    $comentarios = $stmt_comentarios->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener solución si existe
    $query_solucion = "SELECT s.ID, s.Descripcion, s.FechaRegistro, e.Nombre as Tecnico
                     FROM INCIDENCIA_SOLUCION s
                     LEFT JOIN USUARIO u ON s.ID_Usuario = u.ID
                     LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                     WHERE s.ID_Incidencia = ?
                     ORDER BY s.FechaRegistro DESC";
    $stmt_solucion = $conn->prepare($query_solucion);
    $stmt_solucion->execute([$incidencia_id]);
    if ($stmt_solucion->rowCount() > 0) {
        $solucion = $stmt_solucion->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener la evaluación si existe y el estado es 'Cerrada'
    if ($incidencia['ID_Stat'] == 6) { // 6 = Cerrada
        $query_evaluacion = "SELECT e.ID, e.Calificacion, e.Comentario, e.FechaRegistro
                           FROM INCIDENCIA_EVALUACION e
                           WHERE e.ID_Incidencia = ?";
        $stmt_evaluacion = $conn->prepare($query_evaluacion);
        $stmt_evaluacion->execute([$incidencia_id]);
        if ($stmt_evaluacion->rowCount() > 0) {
            $evaluacion = $stmt_evaluacion->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Verificar si existe la tabla INCIDENCIA_HISTORIAL
$check_historial_table_query = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'INCIDENCIA_HISTORIAL'";
$check_historial_table_stmt = $conn->prepare($check_historial_table_query);
$check_historial_table_stmt->execute();
$historial_table_exists = ($check_historial_table_stmt->rowCount() > 0);

if ($historial_table_exists) {
    // Obtener historial de estados
    $query_historial = "SELECT h.ID, h.ID_EstadoAnterior, h.ID_EstadoNuevo, h.FechaCambio,
                             s1.Descripcion as EstadoAnterior, s2.Descripcion as EstadoNuevo,
                             e.Nombre as NombreEmpleado
                      FROM INCIDENCIA_HISTORIAL h
                      LEFT JOIN ESTATUS_INCIDENCIA s1 ON h.ID_EstadoAnterior = s1.ID
                      LEFT JOIN ESTATUS_INCIDENCIA s2 ON h.ID_EstadoNuevo = s2.ID
                      LEFT JOIN USUARIO u ON h.ID_Usuario = u.ID
                      LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                      WHERE h.ID_Incidencia = ?
                      ORDER BY h.FechaCambio ASC";
    $stmt_historial = $conn->prepare($query_historial);
    $stmt_historial->execute([$incidencia_id]);
    $historiales = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar el formulario de comentario si se envía y la tabla de comentarios existe
if ($table_exists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'agregar_comentario') {
    try {
        $comentario = $_POST['comentario'];
        
        if (!empty($comentario)) {
            $query_insert = "INSERT INTO INCIDENCIA_COMENTARIO (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                            VALUES (?, ?, ?, 'COMENTARIO', GETDATE(), 1)";
            $stmt_insert = $conn->prepare($query_insert);
            
            if ($stmt_insert->execute([$incidencia_id, $_SESSION['user_id'], $comentario])) {
                header("Location: ver-incidencia.php?id=$incidencia_id&success=comment_added");
                exit;
            } else {
                $error = "Error al guardar el comentario.";
            }
        } else {
            $error = "El comentario no puede estar vacío.";
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}
?>

<!-- Título de la página -->
<h1 class="h2">Detalles de Incidencia #<?php echo $incidencia_id; ?></h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="mis-incidencias.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a mis incidencias
        </a>
        
        <?php if ($incidencia['ID_Stat'] == 5): // 5 = Resuelta ?>
        <a href="liberar-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-success ms-2">
            <i class="fas fa-check-circle me-2"></i>Liberar Incidencia
        </a>
        <?php endif; ?>
        
        <?php if ($incidencia['ID_Stat'] == 5 || ($incidencia['ID_Stat'] == 6 && !$evaluacion)): // 5 = Resuelta, 6 = Cerrada sin evaluación ?>
        <a href="evaluar-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-primary ms-2">
            <i class="fas fa-star me-2"></i>Evaluar Resolución
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 'comment_added'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>Comentario agregado correctamente.
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php 
        $error_type = $_GET['error'];
        switch ($error_type) {
            case 'not_resolved':
                echo 'La incidencia aún no está resuelta.';
                break;
            case 'already_evaluated':
                echo 'Esta incidencia ya ha sido evaluada.';
                break;
            default:
                echo isset($error) ? $error : 'Ha ocurrido un error.';
        }
        ?>
    </div>
<?php endif; ?>

<!-- Información general de la incidencia -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información General</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">ID:</th>
                                <td>#<?php echo $incidencia['ID']; ?></td>
                            </tr>
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
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">Fecha de reporte:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($incidencia['FechaInicio'])); ?></td>
                            </tr>
                            <tr>
                                <th>Técnico asignado:</th>
                                <td>
                                    <?php if ($incidencia['Tecnico_Nombre']): ?>
                                        <?php echo htmlspecialchars($incidencia['Tecnico_Nombre']); ?>
                                        <?php if (isset($incidencia['Tecnico_Email']) && $incidencia['Tecnico_Email']): ?>
                                            <br><small><a href="mailto:<?php echo htmlspecialchars($incidencia['Tecnico_Email']); ?>"><?php echo htmlspecialchars($incidencia['Tecnico_Email']); ?></a></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Tiempo transcurrido:</th>
                                <td>
                                    <?php
                                    $fecha_inicio = new DateTime($incidencia['FechaInicio']);
                                    $fecha_actual = new DateTime();
                                    
                                    if ($incidencia['FechaTerminacion']) {
                                        $fecha_fin = new DateTime($incidencia['FechaTerminacion']);
                                        $intervalo = $fecha_inicio->diff($fecha_fin);
                                        echo "Resuelto en: ";
                                    } else {
                                        $intervalo = $fecha_inicio->diff($fecha_actual);
                                        echo "En curso: ";
                                    }
                                    
                                    if ($intervalo->days > 0) {
                                        echo $intervalo->days . " día(s) ";
                                    }
                                    
                                    echo $intervalo->h . " hora(s) " . $intervalo->i . " min";
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Fecha de resolución:</th>
                                <td>
                                    <?php if ($incidencia['FechaTerminacion']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($incidencia['FechaTerminacion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Descripción del problema -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Descripción del Problema</h5>
            </div>
            <div class="card-body">
                <?php
                // Procesar la descripción para extraer la información adicional
                $descripcion_completa = $incidencia['Descripcion'];
                $partes = explode("Información adicional:", $descripcion_completa);
                
                $descripcion_principal = isset($partes[0]) ? trim($partes[0]) : $descripcion_completa;
                $info_adicional = isset($partes[1]) ? trim($partes[1]) : '';
                
                // Mostrar la descripción principal
                echo '<div class="mb-4">';
                echo '<h6>Descripción del problema:</h6>';
                echo '<p>' . nl2br(htmlspecialchars($descripcion_principal)) . '</p>';
                echo '</div>';
                
                // Si hay información adicional, mostrarla como una tabla
                if (!empty($info_adicional)) {
                    echo '<div class="mb-4">';
                    echo '<h6>Información adicional:</h6>';
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-striped">';
                    
                    // Procesar cada línea de información adicional
                    $lineas = explode("\n", $info_adicional);
                    foreach ($lineas as $linea) {
                        $linea = trim($linea);
                        if (empty($linea)) continue;
                        
                        if (strpos($linea, '- ') === 0) {
                            $linea = substr($linea, 2); // Quitar el guión inicial
                        }
                        
                        $datos = explode(':', $linea, 2);
                        if (count($datos) >= 2) {
                            $clave = trim($datos[0]);
                            $valor = trim($datos[1]);
                            
                            echo '<tr>';
                            echo '<th width="30%">' . htmlspecialchars($clave) . '</th>';
                            echo '<td>' . htmlspecialchars($valor) . '</td>';
                            echo '</tr>';
                        } else {
                            echo '<tr><td colspan="2">' . htmlspecialchars($linea) . '</td></tr>';
                        }
                    }
                    
                    echo '</table>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php if ($table_exists): ?>

<!-- Solución aplicada (si existe) -->
<?php if ($solucion): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Solución Aplicada</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <p class="mb-2"><strong>Aplicada por:</strong> <?php echo htmlspecialchars($solucion['Tecnico']); ?></p>
                    <p class="mb-2"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($solucion['FechaRegistro'])); ?></p>
                    <hr>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($solucion['Descripcion'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Evaluación del Servicio (si existe) -->
<?php if ($evaluacion): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Su Evaluación del Servicio</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Calificación:</h6>
                        <div class="rating">
                            <?php
                            $calificacion = intval($evaluacion['Calificacion']);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $calificacion) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                } else {
                                    echo '<i class="far fa-star text-warning"></i>';
                                }
                            }
                            ?>
                            <span class="badge bg-primary ms-2"><?php echo $calificacion; ?> de 5</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Fecha de evaluación:</h6>
                        <p><?php echo date('d/m/Y H:i', strtotime($evaluacion['FechaRegistro'])); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($evaluacion['Comentario'])): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Sus comentarios:</h6>
                        <p><?php echo nl2br(htmlspecialchars($evaluacion['Comentario'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Comentarios y Seguimiento -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Comentarios y Seguimiento</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($comentarios)): ?>
                    <div class="comentarios-container">
                        <?php foreach ($comentarios as $comentario): ?>
                            <div class="comentario <?php echo $comentario['Username'] == $_SESSION['username'] ? 'comentario-propio' : ''; ?> mb-3">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>
                                            <strong><?php echo htmlspecialchars($comentario['NombreEmpleado']); ?></strong>
                                            <?php if ($comentario['TipoComentario'] !== 'COMENTARIO'): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($comentario['TipoComentario']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($comentario['FechaRegistro'])); ?></small>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($comentario['Comentario'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if (!$table_exists): ?>
                            El sistema de comentarios aún no está disponible.
                        <?php else: ?>
                            No hay comentarios registrados para esta incidencia.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Formulario para agregar comentarios (solo si no está cerrada y la tabla existe) -->
                <?php if ($table_exists && $incidencia['ID_Stat'] != 6): // No permitir comentarios en incidencias cerradas ?>
                <div class="mt-4">
                    <h6>Agregar Comentario</h6>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="agregar_comentario">
                        <div class="mb-3">
                            <textarea class="form-control" name="comentario" id="comentario" rows="3" required placeholder="Escriba su comentario aquí..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Enviar Comentario</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Historial de Estados -->
<?php if (!empty($historiales)): ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Historial de Estados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estado Anterior</th>
                                <th>Nuevo Estado</th>
                                <th>Actualizado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historiales as $historial): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($historial['FechaCambio'])); ?></td>
                                    <td>
                                        <?php if ($historial['EstadoAnterior']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($historial['EstadoAnterior']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inicio</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $estado = htmlspecialchars($historial['EstadoNuevo']);
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
                                    <td><?php echo htmlspecialchars($historial['NombreEmpleado']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; // fin del bloque if ($table_exists) ?>

<style>
.comentario-propio .card {
    background-color: #f8f9fa;
}
</style>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>