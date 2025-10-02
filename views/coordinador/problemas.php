<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de problemas
check_permission('gestionar_problemas');

// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../models/Problema.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

$problema = new Problema($conn);

// Obtener parámetros de filtrado
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_responsable = isset($_GET['responsable']) ? $_GET['responsable'] : '';
$filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Preparar filtros
$filtros = [];
if (!empty($filtro_estado)) {
    $filtros['estado'] = $filtro_estado;
}
if (!empty($filtro_categoria)) {
    $filtros['categoria'] = $filtro_categoria;
}
if (!empty($filtro_responsable)) {
    $filtros['responsable'] = $filtro_responsable;
}
if (!empty($filtro_busqueda)) {
    $filtros['busqueda'] = $filtro_busqueda;
}

// Obtener problemas según los filtros
$stmt = $problema->getAll($filtros);

// Obtener listas para filtros
$categorias = $problema->getCategorias()->fetchAll(PDO::FETCH_ASSOC);
$estados = $problema->getEstados()->fetchAll(PDO::FETCH_ASSOC);
$responsables = $problema->getResponsablesPotenciales()->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas generales
$estadisticas = $problema->getEstadisticas();
?>

<!-- Título de la página -->
<h1 class="h2">Gestión de Problemas</h1>

<!-- Mensajes de éxito o error -->
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        $success_type = $_GET['success'];
        switch ($success_type) {
            case 'created':
                echo 'El problema ha sido registrado exitosamente.';
                break;
            case 'updated':
                echo 'El problema ha sido actualizado exitosamente.';
                break;
            case 'deleted':
                echo 'El problema ha sido eliminado exitosamente.';
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
                echo 'El problema solicitado no fue encontrado.';
                break;
            case 'permission_denied':
                echo 'No tiene permisos para realizar esta acción.';
                break;
            case 'delete_failed':
                echo 'No se pudo eliminar el problema. Verifique si hay elementos relacionados.';
                break;
            default:
                echo 'Ha ocurrido un error al procesar su solicitud.';
        }
        ?>
    </div>
<?php endif; ?>

<!-- Botón para agregar nuevo problema -->
<div class="row mb-4">
    <div class="col-12">
        <a href="agregar-problema.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Registrar Nuevo Problema
        </a>
    </div>
</div>

<!-- Estadísticas rápidas -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Problemas</div>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Problemas Abiertos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['abiertos']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Alto Impacto</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['alto_impacto']; ?></div>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Problemas Resueltos</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['resueltos']; ?></div>
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
                    <div class="col-md-3">
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
                    <div class="col-md-3">
                        <label for="categoria" class="form-label">Categoría</label>
                        <select class="form-select" id="categoria" name="categoria">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['ID']; ?>" <?php echo ($filtro_categoria == $categoria['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="responsable" class="form-label">Responsable</label>
                        <select class="form-select" id="responsable" name="responsable">
                            <option value="">Todos</option>
                            <option value="null" <?php echo ($filtro_responsable === 'null') ? 'selected' : ''; ?>>Sin asignar</option>
                            <?php foreach ($responsables as $responsable): ?>
                                <option value="<?php echo $responsable['ID']; ?>" <?php echo ($filtro_responsable == $responsable['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($responsable['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="busqueda" class="form-label">Búsqueda</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Título, descripción..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <a href="problemas.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-undo me-2"></i>Limpiar Filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Lista de problemas -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Problemas</h5>
            </div>
            <div class="card-body">
                <?php 
                $problemas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($problemas) > 0): 
                ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Categoría</th>
                                <th>Impacto</th>
                                <th>Fecha Identificación</th>
                                <th>Responsable</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($problemas as $prob): ?>
                                <tr>
                                    <td><?php echo $prob['ID']; ?></td>
                                    <td><?php echo substr(htmlspecialchars($prob['Titulo']), 0, 50) . (strlen($prob['Titulo']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($prob['Categoria']); ?></td>
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
                                    <td><?php echo htmlspecialchars($prob['ResponsableNombre'] ?? 'Sin asignar'); ?></td>
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
                                        <a href="ver-problema.php?id=<?php echo $prob['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="editar-problema.php?id=<?php echo $prob['ID']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if (has_permission('admin')): ?>
                                        <a href="eliminar-problema.php?id=<?php echo $prob['ID']; ?>" class="btn btn-sm btn-danger" title="Eliminar">
                                            <i class="fas fa-trash"></i>
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
                        No se encontraron problemas con los filtros seleccionados.
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