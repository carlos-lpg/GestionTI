<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de reportar incidencias
check_permission('reportar_incidencia');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener estado para filtrado (si se proporciona)
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Construir la consulta base para incidencias del usuario actual
$query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                 p.Descripcion as Prioridad, p.ID as ID_Prioridad,
                 s.Descripcion as Estado, s.ID as ID_Estado,
                 ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                 e.Nombre as Tecnico_Nombre, e.ID as Tecnico_ID
          FROM INCIDENCIA i
          LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
          LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
          LEFT JOIN CI ci ON i.ID_CI = ci.ID
          LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
          LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
          WHERE i.CreatedBy = ?";

$params = [$_SESSION['user_id']];

// Aplicar filtro por estado
if (!empty($filtro_estado)) {
    $query .= " AND i.ID_Stat = ?";
    $params[] = $filtro_estado;
}

// Ordenar las incidencias por fechas (más recientes primero)
$query .= " ORDER BY i.FechaInicio DESC";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);
$stmt->execute($params);

// Obtener lista de estados para filtrado
$query_estados = "SELECT ID, Descripcion FROM ESTATUS_INCIDENCIA ORDER BY ID";
$stmt_estados = $conn->prepare($query_estados);
$stmt_estados->execute();
$estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Título de la página -->
<h1 class="h2">Mis Incidencias Reportadas</h1>

<!-- Botón para reportar nueva incidencia -->
<div class="row mb-4">
    <div class="col-12">
        <a href="reportar-incidencia.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Reportar Nueva Incidencia
        </a>
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
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo $estado['ID']; ?>" <?php echo ($filtro_estado == $estado['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($estado['Descripcion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Filtrar
                            </button>
                            <a href="mis-incidencias.php" class="btn btn-secondary">
                                <i class="fas fa-undo me-2"></i>Limpiar filtros
                            </a>
                        </div>
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
            <div class="card-header">
                <h5 class="mb-0">Incidencias Reportadas</h5>
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
                                <th>Fecha Reporte</th>
                                <th>Prioridad</th>
                                <th>Técnico</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidencias as $incidencia): ?>
                                <tr>
                                    <td><?php echo $incidencia['ID']; ?></td>
                                    <td><?php echo substr(htmlspecialchars($incidencia['Descripcion']), 0, 50) . (strlen($incidencia['Descripcion']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <?php if ($incidencia['CI_Tipo']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($incidencia['CI_Tipo']); ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($incidencia['CI_Nombre']); ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($incidencia['FechaInicio'])); ?></td>
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
                                    <td><?php echo $incidencia['Tecnico_Nombre'] ? htmlspecialchars($incidencia['Tecnico_Nombre']) : 'Sin asignar'; ?></td>
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
                                    <td>
                                        <a href="ver-incidencia.php?id=<?php echo $incidencia['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($incidencia['ID_Estado'] == 5): // 5 = Resuelta, pendiente de liberación ?>
                                        <a href="liberar-incidencia.php?id=<?php echo $incidencia['ID']; ?>" class="btn btn-sm btn-success" title="Liberar incidencia">
                                            <i class="fas fa-check-circle"></i>
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
                        No se encontraron incidencias reportadas. Puedes crear una nueva incidencia haciendo clic en "Reportar Nueva Incidencia".
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>