<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de CIs
// NOTA IMPORTANTE: Asegúrate de que el rol de Coordinador tenga el permiso 'gestionar_ci' o el permiso que uses
// en el archivo gestionar-solicitudes.php (ej: 'gestionar_coordinacion').
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
$query_tecnicos = "SELECT e.ID, e.Nombre, e.Email, COUNT(i.ID) as TotalIncidencias 
                   FROM EMPLEADO e
                   LEFT JOIN INCIDENCIA i ON e.ID = i.ID_Tecnico AND i.ID_Stat IN (2, 3, 4) -- Asignada, En proceso, En espera
                   WHERE e.ID_Rol = 2 -- Asumiendo 2 es el rol de Técnico
                   GROUP BY e.ID, e.Nombre, e.Email
                   ORDER BY TotalIncidencias DESC";

$stmt_tecnicos = $conn->prepare($query_tecnicos);
$stmt_tecnicos->execute();

// Obtener métricas rápidas (Totales)
$query_metrics = "SELECT 
                    (SELECT COUNT(ID) FROM INCIDENCIA) as TotalIncidencias,
                    (SELECT COUNT(ID) FROM INCIDENCIA WHERE ID_Stat = 5) as IncidenciasResueltas,
                    (SELECT COUNT(ID) FROM CI) as TotalCI,
                    (SELECT COUNT(ID) FROM INCIDENCIA WHERE ID_Stat IN (2, 3, 4)) as IncidenciasActivas";

$stmt_metrics = $conn->prepare($query_metrics);
$stmt_metrics->execute();
$metrics = $stmt_metrics->fetch(PDO::FETCH_ASSOC);

// Obtener el conteo de CI por tipo
$query_tipos_ci = "SELECT t.Nombre as TipoCI, COUNT(c.ID) as Total
                   FROM CI c
                   JOIN TIPO_CI t ON c.ID_TipoCI = t.ID
                   GROUP BY t.Nombre
                   ORDER BY Total DESC";
$stmt_tipos_ci = $conn->prepare($query_tipos_ci);
$stmt_tipos_ci->execute();
?>

<h1 class="h2">Dashboard de Coordinación de TI</h1>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Incidencias
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $metrics['TotalIncidencias'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Incidencias Activas
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $metrics['IncidenciasActivas'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Resueltas (Pendientes de Cerrar)
                        </div>
                        <div class="row no-gutters align-items-center">
                            <div class="col-auto">
                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $metrics['IncidenciasResueltas'] ?? 0; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Elementos de Configuración (CI)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $metrics['TotalCI'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-desktop fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Top 5 Incidencias Nuevas</h6>
            </div>
            <div class="card-body">
                <?php if ($stmt_pendientes->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Descripción</th>
                                    <th>Prioridad</th>
                                    <th>Reportó</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($inc = $stmt_pendientes->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td>#<?php echo $inc['ID']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars(substr($inc['Descripcion'], 0, 40)) . (strlen($inc['Descripcion']) > 40 ? '...' : ''); ?>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars($inc['CI_Nombre']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $prioridad = htmlspecialchars($inc['Prioridad']);
                                            $badgeClass = 'bg-info';
                                            
                                            if ($prioridad === 'Crítica') $badgeClass = 'bg-danger';
                                            elseif ($prioridad === 'Alta') $badgeClass = 'bg-warning text-dark';
                                            elseif ($prioridad === 'Media') $badgeClass = 'bg-primary';
                                            
                                            echo "<span class='badge $badgeClass'>$prioridad</span>";
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($inc['Reportado_Por']); ?></td>
                                        <td>
                                            <a href="asignar-incidencia.php?id=<?php echo $inc['ID']; ?>" class="btn btn-sm btn-primary">Asignar</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle me-2"></i>
                        No hay incidencias pendientes de asignación.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Carga de Trabajo de Técnicos</h6>
            </div>
            <div class="card-body">
                <?php if ($stmt_tecnicos->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Técnico</th>
                                    <th>Incidencias Activas</th>
                                    <th>Carga</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $max_carga = 1;
                                $tecnicos_data = $stmt_tecnicos->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($tecnicos_data as $tec) {
                                    if ($tec['TotalIncidencias'] > $max_carga) {
                                        $max_carga = $tec['TotalIncidencias'];
                                    }
                                }
                                ?>
                                <?php foreach ($tecnicos_data as $tec): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tec['Nombre']); ?></td>
                                        <td><?php echo $tec['TotalIncidencias']; ?></td>
                                        <td>
                                            <div class="progress">
                                                <?php 
                                                $porcentaje = ($tec['TotalIncidencias'] / $max_carga) * 100;
                                                $color = 'bg-success';
                                                if ($porcentaje > 75) $color = 'bg-danger';
                                                elseif ($porcentaje > 50) $color = 'bg-warning';
                                                ?>
                                                <div class="progress-bar <?php echo $color; ?>" role="progressbar" style="width: <?php echo $porcentaje; ?>%" aria-valuenow="<?php echo $porcentaje; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay técnicos o datos de carga de trabajo disponibles.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h2 class="h4 mt-4">Accesos de Coordinación</h2>
<hr>
<div class="row mb-4">
    
    <div class="col-md-4">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <h5 class="card-title">Elementos de Configuración</h5>
                <p class="card-text">Gestión de elementos de configuración de su área.</p>
                <a href="gestion-ci.php" class="btn btn-primary">
                    <i class="fas fa-desktop me-2"></i>Gestionar CIs
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <h5 class="card-title">Incidencias</h5>
                <p class="card-text">Gestión y asignación de incidencias reportadas.</p>
                <a href="incidencias.php" class="btn btn-primary">
                    <i class="fas fa-tasks me-2"></i>Gestionar Incidencias
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-dashboard h-100 border-left-warning shadow">
            <div class="card-body">
                <h5 class="card-title text-warning">Solicitudes de Componente (Paso 8)</h5>
                <p class="card-text">Revisar y aprobar peticiones de componentes a técnicos.</p>
                <a href="gestionar-solicitudes.php" class="btn btn-warning text-dark">
                    <i class="fas fa-box me-2"></i>Gestionar Solicitudes
                </a>
            </div>
        </div>
    </div>

</div>

<div class="row mb-4">
    
    <div class="col-md-4"> 
        <div class="card card-dashboard h-100">
            <div class="card-body">
                <h5 class="card-title">Gestión de Problemas</h5>
                <p class="card-text">Administre problemas conocidos y sus soluciones de causa raíz.</p>
                <a href="problemas.php" class="btn btn-primary">
                    <i class="fas fa-exclamation-triangle me-2"></i>Gestionar Problemas
                </a>
            </div>
        </div>
    </div>
    
</div>
<div class="row">
    <div class="col-xl-6 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Conteo de Elementos de Configuración (CI) por Tipo</h6>
            </div>
            <div class="card-body">
                <?php if ($stmt_tipos_ci->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Tipo de CI</th>
                                    <th>Total</th>
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
    <div class="col-xl-6 col-lg-6">
        </div>
</div>

<style>
/* Estilos adicionales que tenías */
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