<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de incidencias
has_permission('gestionar_incidencias');

// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../models/Problema.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener el ID del empleado del técnico actual
$tecnico_id = $_SESSION['empleado_id'];

// Obtener incidencias asignadas al técnico
$query_asignadas = "SELECT TOP 5 i.ID, i.Descripcion, i.FechaInicio, 
                           p.Descripcion as Prioridad, p.ID as ID_Prioridad,
                           s.Descripcion as Estado, s.ID as ID_Estado,
                           ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                           u.ID as ID_Usuario, e.Nombre as Reportado_Por
                    FROM INCIDENCIA i
                    LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                    LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                    LEFT JOIN CI ci ON i.ID_CI = ci.ID
                    LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                    LEFT JOIN USUARIO u ON i.CreatedBy = u.ID
                    LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                    WHERE i.ID_Tecnico = ? AND i.ID_Stat IN (2, 3, 4)
                    ORDER BY i.ID_Prioridad ASC, i.FechaInicio DESC";
$stmt_asignadas = $conn->prepare($query_asignadas);
$stmt_asignadas->execute([$tecnico_id]);

// Obtener incidencias resueltas recientes
$query_resueltas = "SELECT TOP 3 i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion,
                          p.Descripcion as Prioridad, 
                          s.Descripcion as Estado,
                          ci.Nombre as CI_Nombre, 
                          u.ID as ID_Usuario, e.Nombre as Reportado_Por
                    FROM INCIDENCIA i
                    LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                    LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                    LEFT JOIN CI ci ON i.ID_CI = ci.ID
                    LEFT JOIN USUARIO u ON i.CreatedBy = u.ID
                    LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                    WHERE i.ID_Tecnico = ? AND i.ID_Stat IN (5, 6)
                    ORDER BY i.FechaTerminacion DESC";
$stmt_resueltas = $conn->prepare($query_resueltas);
$stmt_resueltas->execute([$tecnico_id]);

// Contar incidencias por estado
$query_conteo = "SELECT s.Descripcion as Estado, COUNT(i.ID) as Total
                FROM ESTATUS_INCIDENCIA s
                LEFT JOIN INCIDENCIA i ON s.ID = i.ID_Stat AND i.ID_Tecnico = ?
                GROUP BY s.Descripcion
                ORDER BY s.Descripcion";
$stmt_conteo = $conn->prepare($query_conteo);
$stmt_conteo->execute([$tecnico_id]);
$conteo_estados = $stmt_conteo->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas de incidencias
$query_estadisticas = "SELECT 
                        COUNT(CASE WHEN i.ID_Stat IN (2, 3, 4) THEN 1 END) as pendientes,
                        COUNT(CASE WHEN i.ID_Stat = 5 THEN 1 END) as resueltas,
                        COUNT(CASE WHEN i.ID_Stat = 6 THEN 1 END) as cerradas,
                        COUNT(CASE WHEN i.ID_Prioridad = 1 THEN 1 END) as criticas
                      FROM INCIDENCIA i
                      WHERE i.ID_Tecnico = ?";
$stmt_estadisticas = $conn->prepare($query_estadisticas);
$stmt_estadisticas->execute([$tecnico_id]);
$estadisticas = $stmt_estadisticas->fetch(PDO::FETCH_ASSOC);

// AGREGAR: Obtener estadísticas de problemas relacionadas con las incidencias del técnico
$problema = new Problema($conn);

// Problemas relacionados con incidencias del técnico
$query_problemas_relacionados = "SELECT DISTINCT p.ID, p.Titulo, p.FechaIdentificacion,
                                        s.Descripcion as Estado, imp.Descripcion as Impacto
                                 FROM PROBLEMA p
                                 JOIN PROBLEMA_INCIDENCIA pi ON p.ID = pi.ID_Problema
                                 JOIN INCIDENCIA i ON pi.ID_Incidencia = i.ID
                                 LEFT JOIN ESTATUS_PROBLEMA s ON p.ID_Stat = s.ID
                                 LEFT JOIN IMPACTO imp ON p.ID_Impacto = imp.ID
                                 WHERE i.ID_Tecnico = ?
                                 ORDER BY p.FechaIdentificacion DESC";
$stmt_problemas_relacionados = $conn->prepare($query_problemas_relacionados);
$stmt_problemas_relacionados->execute([$tecnico_id]);

// Contar problemas por estado relacionados con el técnico
$query_problemas_estadisticas = "SELECT 
                                  COUNT(DISTINCT p.ID) as total_problemas,
                                  COUNT(DISTINCT CASE WHEN p.ID_Stat IN (1, 2, 3) THEN p.ID END) as problemas_abiertos,
                                  COUNT(DISTINCT CASE WHEN p.ID_Stat = 4 THEN p.ID END) as problemas_resueltos,
                                  COUNT(DISTINCT CASE WHEN p.ID_Impacto = 1 THEN p.ID END) as problemas_alto_impacto
                                 FROM PROBLEMA p
                                 JOIN PROBLEMA_INCIDENCIA pi ON p.ID = pi.ID_Problema
                                 JOIN INCIDENCIA i ON pi.ID_Incidencia = i.ID
                                 WHERE i.ID_Tecnico = ?";
$stmt_problemas_estadisticas = $conn->prepare($query_problemas_estadisticas);
$stmt_problemas_estadisticas->execute([$tecnico_id]);
$estadisticas_problemas = $stmt_problemas_estadisticas->fetch(PDO::FETCH_ASSOC);
?>

<!-- Título de la página -->
<h1 class="h2">Panel de Técnico</h1>

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

<!-- Tarjetas de resumen de incidencias -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Incidencias Pendientes</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['pendientes'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Incidencias Resueltas</div>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Incidencias Cerradas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['cerradas'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-lock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Críticas</div>
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

<!-- NUEVA SECCIÓN: Tarjetas de resumen de problemas -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Problemas Relacionados</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas_problemas['total_problemas'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-secondary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Problemas Abiertos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas_problemas['problemas_abiertos'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-folder-open fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Problemas Resueltos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas_problemas['problemas_resueltos'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-double fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Alto Impacto</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas_problemas['problemas_alto_impacto'] ?? 0; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-fire fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Accesos directos -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Gestión de Incidencias</h5>
                <p class="card-text">Acceda a todas las incidencias asignadas a usted.</p>
                <a href="mis-incidencias.php" class="btn btn-primary">
                    <i class="fas fa-list me-2"></i>Ver Mis Incidencias
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Información de Contacto</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-phone me-2"></i> Teléfono de Soporte</h6>
                        <p>Ext. 1234</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-envelope me-2"></i> Email de Soporte</h6>
                        <p>soporte.ti@culiacan.tecnm.mx</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-clock me-2"></i> Horario de Atención</h6>
                        <p>Lunes a Viernes: 8:00 - 18:00<br>Sábado: 9:00 - 14:00</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-question-circle me-2"></i> Soporte</h6>
                        <p>Para asistencia técnica, contacte al supervisor de sistemas al ext. 4321</p>
                    </div>
                </div>
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
.card.border-left-secondary {
    border-left: .25rem solid #858796!important;
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