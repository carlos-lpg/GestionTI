<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de CIs
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener incidencias pendientes (sin asignar)
$query_pendientes = "SELECT TOP 5 i.ID, i.Descripcion, i.FechaInicio, 
                           p.Descripcion as Prioridad, p.ID as ID_Prioridad,
                           s.Descripcion as Estado,
                           ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                           e.Nombre as Reportado_Por
                    FROM INCIDENCIA i
                    LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                    LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                    LEFT JOIN CI ci ON i.ID_CI = ci.ID
                    LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                    LEFT JOIN USUARIO u ON i.CreatedBy = u.ID
                    LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                    WHERE i.ID_Stat = 1 -- Estado 'Nueva'
                    ORDER BY i.ID_Prioridad ASC, i.FechaInicio ASC";

$stmt_pendientes = $conn->prepare($query_pendientes);
$stmt_pendientes->execute();

// Obtener técnicos con su carga de trabajo
$query_tecnicos = "SELECT e.ID, e.Nombre, e.Email, r.Nombre as Rol,
                       (SELECT COUNT(*) FROM INCIDENCIA WHERE ID_Tecnico = e.ID AND ID_Stat IN (2, 3, 4)) as IncidenciasActivas
                FROM EMPLEADO e
                JOIN ROL r ON e.ID_Rol = r.ID
                WHERE r.Nombre = 'Técnico TI'
                ORDER BY IncidenciasActivas ASC, e.Nombre ASC";
$stmt_tecnicos = $conn->prepare($query_tecnicos);
$stmt_tecnicos->execute();

// Obtener últimas incidencias asignadas
$query_asignadas = "SELECT TOP 5 i.ID, i.Descripcion, i.FechaInicio, 
                          p.Descripcion as Prioridad,
                          s.Descripcion as Estado,
                          ci.Nombre as CI_Nombre,
                          e.Nombre as Tecnico_Nombre
                   FROM INCIDENCIA i
                   LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                   LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                   LEFT JOIN CI ci ON i.ID_CI = ci.ID
                   LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                   WHERE i.ID_Stat IN (2, 3, 4, 5) -- Estados asignados
                   ORDER BY i.ModifiedDate DESC";
$stmt_asignadas = $conn->prepare($query_asignadas);
$stmt_asignadas->execute();

// Obtener resumen por estado
$query_estados = "SELECT s.Descripcion as Estado, COUNT(i.ID) as Total
                 FROM ESTATUS_INCIDENCIA s
                 LEFT JOIN INCIDENCIA i ON s.ID = i.ID_Stat
                 GROUP BY s.Descripcion
                 ORDER BY s.Descripcion";
$stmt_estados = $conn->prepare($query_estados);
$stmt_estados->execute();

// Obtener estadísticas generales
$query_estadisticas = "SELECT 
                        COUNT(CASE WHEN i.ID_Stat = 1 THEN 1 END) as nuevas,
                        COUNT(CASE WHEN i.ID_Stat IN (2, 3, 4) THEN 1 END) as en_proceso,
                        COUNT(CASE WHEN i.ID_Stat = 5 THEN 1 END) as resueltas,
                        COUNT(CASE WHEN i.ID_Stat = 6 THEN 1 END) as cerradas,
                        COUNT(CASE WHEN i.ID_Prioridad = 1 THEN 1 END) as criticas
                      FROM INCIDENCIA i";
$stmt_estadisticas = $conn->prepare($query_estadisticas);
$stmt_estadisticas->execute();
$estadisticas = $stmt_estadisticas->fetch(PDO::FETCH_ASSOC);

// Obtener resumen por tipo de CI
$query_tipos_ci = "SELECT t.Nombre as TipoCI, COUNT(i.ID) as Total
                  FROM TIPO_CI t
                  LEFT JOIN CI ci ON t.ID = ci.ID_TipoCI
                  LEFT JOIN INCIDENCIA i ON ci.ID = i.ID_CI
                  GROUP BY t.Nombre
                  ORDER BY COUNT(i.ID) DESC";
$stmt_tipos_ci = $conn->prepare($query_tipos_ci);
$stmt_tipos_ci->execute();
?>

<!-- Título de la página -->
<h1 class="h2">Panel de Coordinador</h1>

<!-- Mensajes de éxito o error -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        $success_type = $_GET['success'];
        switch ($success_type) {
            case 'assigned':
                echo 'La incidencia ha sido asignada exitosamente.';
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

<!-- Tarjetas de resumen -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Nuevas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['nuevas'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">En Proceso</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['en_proceso'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resueltas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['resueltas'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Críticas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['criticas'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Accesos directos -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Elementos de Configuración</h5>
                <p class="card-text">Gestión de elementos de configuración de su área.</p>
                <a href="gestion-ci.php" class="btn btn-primary">
                    <i class="fas fa-desktop me-2"></i>Gestionar CIs
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Incidencias</h5>
                <p class="card-text">Gestión y asignación de incidencias reportadas.</p>
                <a href="incidencias.php" class="btn btn-primary">
                    <i class="fas fa-tasks me-2"></i>Gestionar Incidencias
                </a>
            </div>
        </div>
    </div>
</div>
<div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Gestión de Problemas</h5>
                <p class="card-text">Administre problemas conocidos y sus soluciones.</p>
                <a href="problemas.php" class="btn btn-primary">
                    <i class="fas fa-exclamation-triangle me-2"></i>Gestionar Problemas
                </a>
            </div>
        </div>
    </div>


<!-- Incidencias pendientes por asignar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Incidencias Pendientes por Asignar</h5>
                <a href="incidencias.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body">
                <?php if ($stmt_pendientes->rowCount() > 0): ?>
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
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($inc = $stmt_pendientes->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $inc['ID']; ?></td>
                                        <td><?php echo substr(htmlspecialchars($inc['Descripcion']), 0, 50) . (strlen($inc['Descripcion']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <?php if (isset($inc['CI_Tipo']) && !empty($inc['CI_Tipo'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($inc['CI_Tipo']); ?></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($inc['CI_Nombre']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($inc['Reportado_Por'] ?? 'Desconocido'); ?>
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
                                            <a href="ver-incidencia.php?id=<?php echo $inc['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="asignar-incidencia.php?id=<?php echo $inc['ID']; ?>" class="btn btn-sm btn-primary" title="Asignar">
                                                <i class="fas fa-user-plus"></i>
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
                        No hay incidencias pendientes por asignar en este momento.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Técnicos disponibles -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Técnicos Disponibles</h5>
            </div>
            <div class="card-body">
                <?php if ($stmt_tecnicos->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Carga Actual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tecnico = $stmt_tecnicos->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tecnico['Nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($tecnico['Email']); ?></td>
                                        <td>
                                            <?php 
                                            $carga = $tecnico['IncidenciasActivas'];
                                            $badgeClass = 'bg-success';
                                            
                                            if ($carga > 5) {
                                                $badgeClass = 'bg-danger';
                                            } elseif ($carga > 3) {
                                                $badgeClass = 'bg-warning text-dark';
                                            }
                                            
                                            echo "<span class='badge $badgeClass'>$carga incidencias</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        No hay técnicos disponibles en el sistema.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Distribución por Estado</h5>
            </div>
            <div class="card-body">
                <?php if ($stmt_estados->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th>Cantidad</th>
                                    <th>Proporción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_incidencias = 0;
                                $estados_data = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($estados_data as $conteo) {
                                    $total_incidencias += $conteo['Total'];
                                }
                                
                                foreach ($estados_data as $conteo): 
                                    $porcentaje = $total_incidencias > 0 ? round(($conteo['Total'] / $total_incidencias) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($conteo['Estado']); ?></td>
                                        <td><?php echo $conteo['Total']; ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $porcentaje; ?>%;" 
                                                     aria-valuenow="<?php echo $porcentaje; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo $porcentaje; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay datos disponibles sobre estados de incidencias.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Últimas incidencias asignadas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Últimas Incidencias Asignadas</h5>
            </div>
            <div class="card-body">
                <?php if ($stmt_asignadas->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Descripción</th>
                                    <th>Elemento</th>
                                    <th>Técnico</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($asignada = $stmt_asignadas->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $asignada['ID']; ?></td>
                                        <td><?php echo substr(htmlspecialchars($asignada['Descripcion']), 0, 50) . (strlen($asignada['Descripcion']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars($asignada['CI_Nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($asignada['Tecnico_Nombre'] ?? 'No asignado'); ?></td>
                                        <td>
                                            <?php 
                                            $estado = htmlspecialchars($asignada['Estado']);
                                            $badgeClass = 'bg-info';
                                            
                                            if ($estado === 'Asignada') {
                                                $badgeClass = 'bg-primary';
                                            } elseif ($estado === 'En proceso') {
                                                $badgeClass = 'bg-warning text-dark';
                                            } elseif ($estado === 'En espera') {
                                                $badgeClass = 'bg-secondary';
                                            } elseif ($estado === 'Resuelta') {
                                                $badgeClass = 'bg-success';
                                            }
                                            
                                            echo "<span class='badge $badgeClass'>$estado</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <a href="ver-incidencia.php?id=<?php echo $asignada['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="asignar-incidencia.php?id=<?php echo $asignada['ID']; ?>" class="btn btn-sm btn-warning" title="Reasignar">
                                                <i class="fas fa-exchange-alt"></i>
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
                        No hay incidencias asignadas para mostrar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Resumen por Tipo de CI -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Incidencias por Tipo de CI</h5>
            </div>
            <div class="card-body">
                <?php if ($stmt_tipos_ci->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tipo de CI</th>
                                    <th>Total Incidencias</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tipo = $stmt_tipos_ci->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tipo['TipoCI']); ?></td>
                                        <td><?php echo $tipo['Total']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay datos disponibles sobre tipos de CI.
                    </div>
                <?php endif; ?>
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
.card.border-left-info {
    border-left: .25rem solid #36b9cc!important;
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
.progress {
    height: 20px;
}
.progress-bar {
    background-color: #4e73df;
}
</style>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>