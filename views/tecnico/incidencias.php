<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de incidencias
check_permission('gestionar_incidencias');

// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../models/Incidencia.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Crear instancia del modelo Incidencia
$incidencia = new Incidencia($conn);

// Obtener parámetros de filtrado
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_prioridad = isset($_GET['prioridad']) ? $_GET['prioridad'] : '';
$filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Preparar filtros
$filtros = [
    'tecnico' => $_SESSION['empleado_id'] // Filtrar solo por incidencias asignadas al técnico actual
];

if (!empty($filtro_estado)) {
    $filtros['estado'] = $filtro_estado;
}
if (!empty($filtro_prioridad)) {
    $filtros['prioridad'] = $filtro_prioridad;
}
if (!empty($filtro_busqueda)) {
    $filtros['busqueda'] = $filtro_busqueda;
}

// Obtener incidencias asignadas al técnico según los filtros
$stmt = $incidencia->getAll($filtros);

// Obtener listas para filtros
$prioridades = $incidencia->getPrioridades()->fetchAll(PDO::FETCH_ASSOC);
$estados = $incidencia->getEstados()->fetchAll(PDO::FETCH_ASSOC);

// Filtrar estados relevantes para técnicos (p.ej. no mostrar "Nueva" ya que esas no están asignadas)
$estados_filtrados = array_filter($estados, function($estado) {
    return in_array($estado['ID'], [2, 3, 4, 5, 6]); // Asignada, En proceso, En espera, Resuelta, Cerrada
});

// Obtener estadísticas de incidencias del técnico
$estadisticas = $incidencia->getEstadisticas($_SESSION['empleado_id']);
?>

<!-- Título de la página -->
<h1 class="h2">Mis Incidencias Asignadas</h1>

<!-- Mensajes de éxito o error -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        $success_type = $_GET['success'];
        switch ($success_type) {
            case 'updated':
                echo 'La incidencia ha sido actualizada exitosamente.';
                break;
            default:
                echo 'Operación realizada con éxito.';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <?php
        $error_type = $_GET['error'];
        switch ($error_type) {
            case 'not_found':
                echo 'La incidencia solicitada no fue encontrada.';
                break;
            case 'permission_denied':
                echo 'No tiene permisos para realizar esta acción.';
                break;
            default:
                echo 'Ha ocurrido un error al procesar su solicitud.';
        }
        ?>
    </div>
<?php endif; ?>

<!-- Estadísticas rápidas -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Asignadas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['total']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Incidencias Pendientes</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['abiertas']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Incidencias Críticas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['criticas']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Incidencias Resueltas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['resueltas']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Filtros</h5>
            </div>
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <?php foreach ($estados_filtrados as $estado): ?>
                                <option value="<?php echo $estado['ID']; ?>" <?php echo ($filtro_estado == $estado['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($estado['Descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="prioridad" class="form-label">Prioridad</label>
                        <select class="form-select" id="prioridad" name="prioridad">
                            <option value="">Todas</option>
                            <?php foreach ($prioridades as $prioridad): ?>
                                <option value="<?php echo $prioridad['ID']; ?>" <?php echo ($filtro_prioridad == $prioridad['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prioridad['Descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="busqueda" class="form-label">Búsqueda</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Descripción, CI..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <a href="mis-incidencias.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-undo me-2"></i>Limpiar Filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Lista de incidencias -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Incidencias Asignadas</h5>
            </div>
            <div class="card-body">
                <?php 
                $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($incidencias) > 0): 
                ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descripción</th>
                                <th>Elemento</th>
                                <th>Reportado Por</th>
                                <th>Fecha</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidencias as $inc): ?>
                                <tr>
                                    <td><?php echo $inc['ID']; ?></td>
                                    <td><?php echo substr(htmlspecialchars($inc['Descripcion']), 0, 50) . (strlen($inc['Descripcion']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($inc['CI_Nombre']); ?></td>
                                    <td>
                                    <?php
                                  // Obtener el nombre del usuario que reportó
                                  if (isset($inc['CreatedBy'])) {
                                 $query_usuario = "SELECT e.Nombre 
                                FROM USUARIO u 
                               JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
                               WHERE u.ID = ?";
                             $stmt_usuario = $conn->prepare($query_usuario);
                           $stmt_usuario->execute([$inc['CreatedBy']]);
                            $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
                           echo htmlspecialchars($usuario['Nombre'] ?? 'Desconocido');
                         } else {
                       echo 'Desconocido';
                           }
                           ?>
                            </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($inc['FechaInicio'])); ?></td>
                                    <td>
                                        <?php 
                                        $prioridad = htmlspecialchars($inc['Prioridad']);
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
                                    <td>
                                        <?php 
                                        $estado = htmlspecialchars($inc['Estado']);
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
                                    <td>
                                        <a href="ver-incidencia.php?id=<?php echo $inc['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($inc['ID_Estado'] != 6): // No mostrar si está Cerrada ?>
                                        <a href="actualizar-incidencia.php?id=<?php echo $inc['ID']; ?>" class="btn btn-sm btn-warning" title="Actualizar estado">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No se encontraron incidencias asignadas con los filtros seleccionados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Formulario rápido de actualización -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Actualización Rápida de Incidencia</h5>
            </div>
            <div class="card-body">
                <form action="procesar-actualizacion.php" method="POST">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="id_incidencia" class="form-label">ID Incidencia</label>
                            <input type="text" class="form-control" id="id_incidencia" name="id_incidencia" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="estado" class="form-label">Nuevo Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="">Seleccionar...</option>
                                <option value="3">En proceso</option>
                                <option value="4">En espera</option>
                                <option value="5">Resuelta</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="comentario" class="form-label">Comentario</label>
                            <input type="text" class="form-control" id="comentario" name="comentario" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">Actualizar Estado</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.card.border-left-primary {
    border-left: .25rem solid #4e73df!important;
}
.card.border-left-success {
    border-left: .25rem solid #1cc88a!important;
}
.card.border-left-warning {
    border-left: .25rem solid #f6c23e!important;
}
.card.border-left-danger {
    border-left: .25rem solid #e74a3b!important;
}
.text-xs {
    font-size: .7rem;
}
</style>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>