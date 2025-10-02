<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de incidencias
check_permission('gestionar_incidencias');

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
    
    <!-- NUEVA TARJETA: Gestión de Problemas -->
    <div class="col-md-4">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Gestión de Problemas</h5>
                <p class="card-text">Consulte problemas conocidos, proponga soluciones y comparta su experiencia.</p>
                <a href="problemas.php" class="btn btn-success">
                    <i class="fas fa-exclamation-triangle me-2"></i>Ver Problemas
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Soluciones Rápidas</h5>
                <p class="card-text">Registre soluciones aplicadas y actualice estados.</p>
                <a href="#form-actualizacion" class="btn btn-primary">
                    <i class="fas fa-tools me-2"></i>Actualización Rápida
                </a>
            </div>
        </div>
    </div>
</div>

<!-- NUEVA SECCIÓN: Problemas relacionados con incidencias del técnico -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Problemas Relacionados con Mis Incidencias</h5>
                <a href="problemas.php" class="btn btn-sm btn-outline-success">Ver todos los problemas</a>
            </div>
            <div class="card-body">
                <?php if ($stmt_problemas_relacionados->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título</th>
                                    <th>Estado</th>
                                    <th>Impacto</th>
                                    <th>Fecha Identificación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($prob = $stmt_problemas_relacionados->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $prob['ID']; ?></td>
                                        <td>
                                            <strong><?php echo substr(htmlspecialchars($prob['Titulo']), 0, 50) . (strlen($prob['Titulo']) > 50 ? '...' : ''); ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $estado = htmlspecialchars($prob['Estado']);
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
                                        <td>
                                            <?php 
                                            $impacto = htmlspecialchars($prob['Impacto']);
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
                                        <td><?php echo date('d/m/Y', strtotime($prob['FechaIdentificacion'])); ?></td>
                                        <td>
                                            <a href="ver-problema.php?id=<?php echo $prob['ID']; ?>" class="btn btn-sm btn-info" title="Ver problema">
                                                <i class="fas fa-eye"></i> Ver
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
                        No hay problemas relacionados con sus incidencias. Los problemas aparecerán aquí cuando se asocien incidencias que usted maneje.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Resto del contenido original del dashboard... -->
<!-- Incidencias asignadas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Incidencias Pendientes Asignadas</h5>
                <a href="mis-incidencias.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body">
                <?php if ($stmt_asignadas->rowCount() > 0): ?>
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
                                <?php while ($inc = $stmt_asignadas->fetch(PDO::FETCH_ASSOC)): ?>
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
                                            <?php 
                                            $estado = htmlspecialchars($inc['Estado']);
                                            $badgeClass = 'bg-info';
                                            
                                            if ($estado === 'Asignada') {
                                                $badgeClass = 'bg-primary';
                                            } elseif ($estado === 'En proceso') {
                                                $badgeClass = 'bg-warning text-dark';
                                            } elseif ($estado === 'En espera') {
                                                $badgeClass = 'bg-secondary';
                                            }
                                            
                                            echo "<span class='badge $badgeClass'>$estado</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <a href="ver-incidencia.php?id=<?php echo $inc['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="actualizar-incidencia.php?id=<?php echo $inc['ID']; ?>" class="btn btn-sm btn-warning" title="Actualizar estado">
                                                <i class="fas fa-edit"></i>
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
                        No hay incidencias pendientes asignadas a usted en este momento.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Incidencias resueltas recientes -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Últimas Incidencias Resueltas</h5>
            </div>
            <div class="card-body">
                <?php if ($stmt_resueltas->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Descripción</th>
                                    <th>Elemento</th>
                                    <th>Fecha Reporte</th>
                                    <th>Fecha Resolución</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($resuelta = $stmt_resueltas->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $resuelta['ID']; ?></td>
                                        <td><?php echo substr(htmlspecialchars($resuelta['Descripcion']), 0, 50) . (strlen($resuelta['Descripcion']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars($resuelta['CI_Nombre']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($resuelta['FechaInicio'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($resuelta['FechaTerminacion'])); ?></td>
                                        <td>
                                            <?php 
                                            $estado = htmlspecialchars($resuelta['Estado']);
                                            $badgeClass = $estado === 'Resuelta' ? 'bg-success' : 'bg-dark';
                                            echo "<span class='badge $badgeClass'>$estado</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay incidencias resueltas para mostrar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Formulario rápido de actualización -->
<div id="form-actualizacion" class="row mb-4">
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
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Actualizar Estado
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Distribución de incidencias -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Distribución por Estado</h5>
            </div>
            <div class="card-body">
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
                            foreach ($conteo_estados as $conteo) {
                                $total_incidencias += $conteo['Total'];
                            }
                            
                            foreach ($conteo_estados as $conteo): 
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
                        <p>soporte.ti@dportenis.com.mx</p>
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