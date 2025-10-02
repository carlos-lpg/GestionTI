<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de problemas
check_permission('gestionar_problemas');

// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../models/Problema.php';
require_once '../../models/Incidencia.php';

// Verificar que se ha proporcionado un ID de problema
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: problemas.php?error=missing_id");
    exit;
}

$problema_id = intval($_GET['id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();
$problema = new Problema($conn);
$incidencia = new Incidencia($conn);

$problema = new Problema($conn);
$incidencia = new Incidencia($conn);

// Obtener detalles del problema
if (!$problema->getById($problema_id)) {
    header("Location: problemas.php?error=not_found");
    exit;
}

// Obtener comentarios del problema
$stmt_comentarios = $problema->getComentarios($problema_id);

// Obtener historial de estados
$stmt_historial = $problema->getHistorialEstados($problema_id);

// Obtener soluciones propuestas
$stmt_soluciones = $problema->getSolucionesPropuestas($problema_id);

// Obtener incidencias asociadas
$stmt_incidencias = $problema->getIncidenciasAsociadas($problema_id);


// Procesar el formulario de comentario si se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'agregar_comentario') {
    $comentario = $_POST['comentario'] ?? '';
    $tipo_comentario = $_POST['tipo_comentario'] ?? 'COMENTARIO';
    
    if (!empty($comentario)) {
        if ($problema->agregarComentario($problema_id, $_SESSION['user_id'], $comentario, $tipo_comentario)) {
            header("Location: ver-problema.php?id=$problema_id&success=comment_added");
            exit;
        } else {
            $error = "Error al guardar el comentario.";
        }
    } else {
        $error = "El comentario no puede estar vacío.";
    }
}

// Procesar el formulario de cambio de estado si se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'cambiar_estado') {
    $nuevo_estado = $_POST['nuevo_estado'] ?? '';
    $comentario = $_POST['comentario_estado'] ?? '';
    
    if (!empty($nuevo_estado)) {
        // Guardar el estado anterior para el historial
        $estado_anterior = $problema->id_stat;
        
        // Actualizar el estado
        if ($problema->cambiarEstado($problema_id, $nuevo_estado, $_SESSION['user_id'])) {
            // Registrar en el historial
            $problema->registrarCambioEstado($problema_id, $estado_anterior, $nuevo_estado, $_SESSION['user_id']);
            
            // Agregar comentario si se proporcionó
            if (!empty($comentario)) {
                $problema->agregarComentario($problema_id, $_SESSION['user_id'], $comentario, 'CAMBIO_ESTADO');
            }
            
            header("Location: ver-problema.php?id=$problema_id&success=status_changed");
            exit;
        } else {
            $error = "Error al cambiar el estado del problema.";
        }
    } else {
        $error = "Debe seleccionar un estado.";
    }
}

// Procesar el formulario para agregar solución propuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'agregar_solucion') {
    $titulo = $_POST['titulo_solucion'] ?? '';
    $descripcion = $_POST['descripcion_solucion'] ?? '';
    $tipo_solucion = $_POST['tipo_solucion'] ?? '';
    
    if (!empty($titulo) && !empty($descripcion) && !empty($tipo_solucion)) {
        if ($problema->agregarSolucionPropuesta($problema_id, $titulo, $descripcion, $tipo_solucion, $_SESSION['user_id'])) {
            header("Location: ver-problema.php?id=$problema_id&success=solution_added");
            exit;
        } else {
            $error = "Error al guardar la solución propuesta.";
        }
    } else {
        $error = "Todos los campos son obligatorios para agregar una solución.";
    }
}

// Procesar el formulario para asociar incidencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'asociar_incidencia') {
    $incidencia_id = $_POST['incidencia_id'] ?? '';
    
    if (!empty($incidencia_id)) {
        if ($problema->asignarIncidencia($problema_id, $incidencia_id, $_SESSION['user_id'])) {
            header("Location: ver-problema.php?id=$problema_id&success=incidencia_asociada");
            exit;
        } else {
            $error = "Error al asociar la incidencia al problema.";
        }
    } else {
        $error = "Debe proporcionar un ID de incidencia válido.";
    }
}

// Obtener estados para el selector de cambio de estado
$estados = $problema->getEstados()->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Título de la página -->
<h1 class="h2">Detalles del Problema #<?php echo $problema_id; ?></h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="problemas.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a la lista
        </a>
        
        <a href="editar-problema.php?id=<?php echo $problema_id; ?>" class="btn btn-warning ms-2">
            <i class="fas fa-edit me-2"></i>Editar Problema
        </a>
        
        <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#modalCambiarEstado">
            <i class="fas fa-exchange-alt me-2"></i>Cambiar Estado
        </button>
        
        <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#modalAgregarSolucion">
            <i class="fas fa-plus-circle me-2"></i>Agregar Solución
        </button>
        
        <button type="button" class="btn btn-info ms-2" data-bs-toggle="modal" data-bs-target="#modalAsociarIncidencia">
            <i class="fas fa-link me-2"></i>Asociar Incidencia
        </button>
    </div>
</div>

<!-- Mensajes de éxito o error -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        $success_type = $_GET['success'];
        switch ($success_type) {
            case 'comment_added':
                echo 'El comentario se ha agregado correctamente.';
                break;
            case 'status_changed':
                echo 'El estado del problema se ha actualizado correctamente.';
                break;
            case 'solution_added':
                echo 'La solución propuesta se ha agregado correctamente.';
                break;
            case 'incidencia_asociada':
                echo 'La incidencia se ha asociado correctamente al problema.';
                break;
            default:
                echo 'Operación realizada con éxito.';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Información general del Problema -->
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
                                <td>#<?php echo $problema->id; ?></td>
                            </tr>
                            <tr>
                                <th>Título:</th>
                                <td><?php echo htmlspecialchars($problema->titulo); ?></td>
                            </tr>
                            <tr>
                                <th>Categoría:</th>
                                <td><?php echo htmlspecialchars($problema->categoria); ?></td>
                            </tr>
                            <tr>
                                <th>Estado:</th>
                                <td>
                                    <?php 
                                    $estado = htmlspecialchars($problema->estado);
                                    $badgeClass = 'bg-info';
                                    
                                    if ($estado === 'Identificado') {
                                        $badgeClass = 'bg-danger';
                                    } elseif ($estado === 'En análisis') {
                                        $badgeClass = 'bg-primary';
                                    } elseif ($estado === 'En implementación') {
                                        $badgeClass = 'bg-warning text-dark';
                                    } elseif ($estado === 'Resuelto') {
                                        $badgeClass = 'bg-success';
                                    } elseif ($estado === 'Cerrado') {
                                        $badgeClass = 'bg-secondary';
                                    }
                                    
                                    echo "<span class='badge $badgeClass'>$estado</span>";
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Impacto:</th>
                                <td>
                                    <?php 
                                    $impacto = htmlspecialchars($problema->impacto);
                                    $badgeClass = 'bg-info';
                                    
                                    if ($impacto === 'Alto') {
                                        $badgeClass = 'bg-danger';
                                    } elseif ($impacto === 'Medio') {
                                        $badgeClass = 'bg-warning text-dark';
                                    } elseif ($impacto === 'Bajo') {
                                        $badgeClass = 'bg-success';
                                    }
                                    
                                    echo "<span class='badge $badgeClass'>$impacto</span>";
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">Fecha Identificación:</th>
                                <td><?php echo date('d/m/Y', strtotime($problema->fecha_identificacion)); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha Resolución:</th>
                                <td>
                                    <?php if ($problema->fecha_resolucion): ?>
                                        <?php echo date('d/m/Y', strtotime($problema->fecha_resolucion)); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Responsable:</th>
                                <td>
                                    <?php if ($problema->responsable_nombre): ?>
                                        <?php echo htmlspecialchars($problema->responsable_nombre); ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Creado por:</th>
                                <td>
                                    <?php 
                                    // Obtener nombre del usuario que creó el problema
                                    $query_usuario = "SELECT e.Nombre FROM USUARIO u JOIN EMPLEADO e ON u.ID_Empleado = e.ID WHERE u.ID = ?";
                                    $stmt_usuario = $conn->prepare($query_usuario);
                                    $stmt_usuario->execute([$problema->created_by]);
                                    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
                                    echo htmlspecialchars($usuario['Nombre'] ?? 'Desconocido');
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Última modificación:</th>
                                <td>
                                    <?php if ($problema->modified_date): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($problema->modified_date)); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin modificaciones</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Descripción del problema:</h6>
                        <p><?php echo nl2br(htmlspecialchars($problema->descripcion)); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incidencias Asociadas -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Incidencias Asociadas</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAsociarIncidencia">
                    <i class="fas fa-link me-1"></i>Asociar Incidencia
                </button>
            </div>
            <div class="card-body">
                <?php if ($stmt_incidencias && $stmt_incidencias->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Descripción</th>
                                    <th>CI</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Técnico</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($incidencia = $stmt_incidencias->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $incidencia['ID']; ?></td>
                                        <td><?php echo substr(htmlspecialchars($incidencia['Descripcion']), 0, 50) . (strlen($incidencia['Descripcion']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <?php if ($incidencia['CI_Tipo']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($incidencia['CI_Tipo']); ?></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($incidencia['CI_Nombre']); ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($incidencia['FechaInicio'])); ?></td>
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
                                            } elseif ($estado === 'Resuelta') {
                                                $badgeClass = 'bg-success';
                                            } elseif ($estado === 'Cerrada') {
                                                $badgeClass = 'bg-dark';
                                            }
                                            
                                            echo "<span class='badge $badgeClass'>$estado</span>";
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($incidencia['Tecnico'] ?? 'Sin asignar'); ?></td>
                                        <td>
                                            <a href="../coordinador/ver-incidencia.php?id=<?php echo $incidencia['ID']; ?>" class="btn btn-sm btn-info" title="Ver Incidencia">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="javascript:void(0);" onclick="desasociarIncidencia(<?php echo $problema_id; ?>, <?php echo $incidencia['ID']; ?>)" class="btn btn-sm btn-danger" title="Desasociar">
                                                <i class="fas fa-unlink"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay incidencias asociadas a este problema.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Soluciones Propuestas -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Soluciones Propuestas</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarSolucion">
                    <i class="fas fa-plus-circle me-1"></i>Agregar Solución
                </button>
            </div>
            <div class="card-body">
                <?php if ($stmt_soluciones && $stmt_soluciones->rowCount() > 0): ?>
                    <div class="accordion" id="accordionSoluciones">
                        <?php $count = 0; while ($solucion = $stmt_soluciones->fetch(PDO::FETCH_ASSOC)): $count++; ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $count; ?>">
                                    <button class="accordion-button <?php echo ($count > 1) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $count; ?>" aria-expanded="<?php echo ($count == 1) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $count; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                            <span>
                                                <strong><?php echo htmlspecialchars($solucion['Titulo']); ?></strong>
                                                <?php 
                                                $tipo = htmlspecialchars($solucion['TipoSolucion']);
                                                $badgeClass = ($tipo === 'WORKAROUND') ? 'bg-warning text-dark' : 'bg-success';
                                                $tipoTexto = ($tipo === 'WORKAROUND') ? 'Solución Temporal' : 'Solución Permanente';
                                                echo "<span class='badge $badgeClass ms-2'>$tipoTexto</span>";
                                                ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($solucion['FechaRegistro'])); ?></small>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $count; ?>" class="accordion-collapse collapse <?php echo ($count == 1) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $count; ?>" data-bs-parent="#accordionSoluciones">
                                    <div class="accordion-body">
                                        <p><strong>Propuesta por:</strong> <?php echo htmlspecialchars($solucion['NombreUsuario']); ?></p>
                                        <p><?php echo nl2br(htmlspecialchars($solucion['Descripcion'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay soluciones propuestas para este problema.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Comentarios y Seguimiento -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Comentarios y Seguimiento</h5>
            </div>
            <div class="card-body">
                <?php if ($stmt_comentarios && $stmt_comentarios->rowCount() > 0): ?>
                    <div class="comentarios-container">
                        <?php while ($comentario = $stmt_comentarios->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="comentario mb-3">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>
                                            <strong><?php echo htmlspecialchars($comentario['NombreEmpleado'] ?? 'Usuario'); ?></strong>
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
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay comentarios registrados para este problema.
                    </div>
                <?php endif; ?>
                
                <!-- Formulario para agregar comentarios -->
                <div class="mt-4">
                    <h6>Agregar Comentario</h6>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="agregar_comentario">
                        <div class="mb-3">
                            <label for="tipo_comentario" class="form-label">Tipo de comentario</label>
                            <select class="form-select" id="tipo_comentario" name="tipo_comentario">
                                <option value="COMENTARIO">Comentario general</option>
                                <option value="ANALISIS">Análisis</option>
                                <option value="INVESTIGACION">Investigación</option>
                                <option value="ACTUALIZACION">Actualización</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control" name="comentario" id="comentario" rows="3" required placeholder="Escriba su comentario aquí..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Enviar Comentario</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historial de Estados -->
<?php if ($stmt_historial && $stmt_historial->rowCount() > 0): ?>
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
                            <?php while ($historial = $stmt_historial->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($historial['FechaCambio'])); ?></td>
                                    <td>
                                        <?php if ($historial['EstadoAnterior']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($historial['EstadoAnterior']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Creación</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $estado = htmlspecialchars($historial['EstadoNuevo']);
                                        $badgeClass = 'bg-info';
                                        
                                        if ($estado === 'Identificado') {
                                            $badgeClass = 'bg-danger';
                                        } elseif ($estado === 'En análisis') {
                                            $badgeClass = 'bg-primary';
                                        } elseif ($estado === 'En implementación') {
                                            $badgeClass = 'bg-warning text-dark';
                                        } elseif ($estado === 'Resuelto') {
                                            $badgeClass = 'bg-success';
                                        } elseif ($estado === 'Cerrado') {
                                            $badgeClass = 'bg-secondary';
                                        }
                                        
                                        echo "<span class='badge $badgeClass'>$estado</span>";
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($historial['NombreEmpleado'] ?? 'Usuario'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal para cambiar estado -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-labelledby="modalCambiarEstadoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCambiarEstadoLabel">Cambiar Estado del Problema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="cambiar_estado">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nuevo_estado" class="form-label">Nuevo Estado *</label>
                        <select class="form-select" id="nuevo_estado" name="nuevo_estado" required>
                            <option value="">Seleccionar nuevo estado...</option>
                            <?php foreach ($estados as $estado): ?>
                                <?php if ($estado['ID'] != $problema->id_stat): // No mostrar el estado actual ?>
                                <option value="<?php echo $estado['ID']; ?>"><?php echo htmlspecialchars($estado['Descripcion']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="comentario_estado" class="form-label">Comentario (opcional)</label>
                        <textarea class="form-control" id="comentario_estado" name="comentario_estado" rows="3" placeholder="Justificación del cambio de estado..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cambiar Estado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para agregar solución -->
<div class="modal fade" id="modalAgregarSolucion" tabindex="-1" aria-labelledby="modalAgregarSolucionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgregarSolucionLabel">Agregar Solución Propuesta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="agregar_solucion">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="titulo_solucion" class="form-label">Título de la Solución *</label>
                        <input type="text" class="form-control" id="titulo_solucion" name="titulo_solucion" required>
                    </div>
                    <div class="mb-3">
                        <label for="tipo_solucion" class="form-label">Tipo de Solución *</label>
                        <select class="form-select" id="tipo_solucion" name="tipo_solucion" required>
                            <option value="">Seleccionar tipo...</option>
                            <option value="WORKAROUND">Solución Temporal (Workaround)</option>
                            <option value="SOLUCION_PERMANENTE">Solución Permanente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion_solucion" class="form-label">Descripción de la Solución *</label>
                        <textarea class="form-control" id="descripcion_solucion" name="descripcion_solucion" rows="5" required placeholder="Describa detalladamente la solución propuesta..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar Solución</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para asociar incidencia -->
<div class="modal fade" id="modalAsociarIncidencia" tabindex="-1" aria-labelledby="modalAsociarIncidenciaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAsociarIncidenciaLabel">Asociar Incidencia al Problema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="asociar_incidencia">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="incidencia_id" class="form-label">ID de la Incidencia *</label>
                        <input type="number" class="form-control" id="incidencia_id" name="incidencia_id" required min="1">
                        <small class="form-text text-muted">Ingrese el número de la incidencia que desea asociar a este problema.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Asociar Incidencia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Función para desasociar una incidencia
function desasociarIncidencia(problemaId, incidenciaId) {
    if (confirm('¿Está seguro de que desea desasociar esta incidencia del problema?')) {
        window.location.href = 'desasociar-incidencia.php?problema_id=' + problemaId + '&incidencia_id=' + incidenciaId;
    }
}
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>