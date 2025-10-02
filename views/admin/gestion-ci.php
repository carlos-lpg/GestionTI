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

// Determinar los departamentos que el usuario puede ver según su rol
$departamentos_permitidos = array();

// Determinar las ubicaciones permitidas según el rol
$edificios_permitidos = array();
$filtrar_por_edificio = false;
if ($_SESSION['role_name'] == 'Administrador' || 
    $_SESSION['role_name'] == 'Gerente TI' || 
    $_SESSION['role_name'] == 'Técnico TI' || 
    $_SESSION['role_name'] == 'Encargado Inventario' || 
    $_SESSION['role_name'] == 'Supervisor Infraestructura' || 
    $_SESSION['role_name'] == 'Supervisor Sistemas') {
    // Estos roles pueden ver elementos de todas las ubicaciones
    $edificios_query = "SELECT ID FROM EDIFICIO";
    $edificios_stmt = $conn->prepare($edificios_query);
    $edificios_stmt->execute();
    $edificios_permitidos = $edificios_stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Para roles específicos de ubicación, filtrar por categoría de ubicación
    $filtrar_por_edificio = true;
    
    switch ($_SESSION['role_name']) {
        case 'Coordinador TI CEDIS':
        case 'Usuario CEDIS':
            // Solo ve elementos en edificios CEDIS
            $edificios_query = "SELECT e.ID 
                               FROM EDIFICIO e
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE cu.Nombre = 'CEDIS'";
            break;
            
        case 'Coordinador TI Sucursales':
            // Solo ve elementos en edificios de Sucursales
            $edificios_query = "SELECT e.ID 
                               FROM EDIFICIO e
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE cu.Nombre = 'Sucursal'";
            break;
            
        case 'Coordinador TI Corporativo':
            // Solo ve elementos en edificios Corporativos
            $edificios_query = "SELECT e.ID 
                               FROM EDIFICIO e
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE cu.Nombre = 'Corporativo'";
            break;
            
        default:
            // Usuario Final y otros roles no especificados - mostrar todos
            $filtrar_por_edificio = false;
            $edificios_query = "SELECT ID FROM EDIFICIO";
    }
    
    $edificios_stmt = $conn->prepare($edificios_query);
    $edificios_stmt->execute();
    $edificios_permitidos = $edificios_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Mantener también la lógica de departamentos para casos específicos
if ($_SESSION['role_name'] == 'Administrador') {
    // El administrador puede ver todos los departamentos
    $deptos_query = "SELECT ID FROM DEPARTAMENTO";
    $deptos_stmt = $conn->prepare($deptos_query);
    $deptos_stmt->execute();
    $departamentos_permitidos = $deptos_stmt->fetchAll(PDO::FETCH_COLUMN);
} else if ($_SESSION['role_name'] == 'Usuario Final') {
    // Los usuarios finales ven solo sus departamentos asignados
    $deptos_query = "SELECT d.ID 
                     FROM EMPLEADO_DEPTO ed 
                     JOIN DEPARTAMENTO d ON ed.ID_Depto = d.ID 
                     WHERE ed.ID_Empleado = ?";
    $deptos_stmt = $conn->prepare($deptos_query);
    $deptos_stmt->execute([$_SESSION['empleado_id']]);
    $departamentos_permitidos = $deptos_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Si el usuario final no tiene departamentos asignados,
    // permitirle ver al menos el departamento al que pertenece
    if (empty($departamentos_permitidos)) {
        $deptos_query = "SELECT ID_Departamento FROM EMPLEADO WHERE ID = ?";
        $deptos_stmt = $conn->prepare($deptos_query);
        $deptos_stmt->execute([$_SESSION['empleado_id']]);
        $departamentos_permitidos = $deptos_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} else {
    // Para otros roles, obtener todos los departamentos
    $deptos_query = "SELECT ID FROM DEPARTAMENTO";
    $deptos_stmt = $conn->prepare($deptos_query);
    $deptos_stmt->execute();
    $departamentos_permitidos = $deptos_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Obtener parámetros de filtrado
$tipo_ci = isset($_GET['tipo_ci']) ? $_GET['tipo_ci'] : '';
$ubicacion = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$departamento = isset($_GET['departamento']) ? $_GET['departamento'] : '';

// Construir la consulta base
$query = "SELECT ci.ID, ci.Nombre, ci.Descripcion, ci.NumSerie, ci.FechaAdquisicion, 
          t.Nombre as TipoCI, p.Nombre as Proveedor, e.Nombre as Encargado,
          l.Nombre as Localizacion, ed.Nombre as Edificio, d.Nombre as Departamento
          FROM CI ci
          LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
          LEFT JOIN PROVEEDOR p ON ci.ID_Proveedor = p.ID
          LEFT JOIN EMPLEADO e ON ci.ID_Encargado = e.ID
          LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
          LEFT JOIN EDIFICIO ed ON l.ID_Edificio = ed.ID
          LEFT JOIN DEPARTAMENTO d ON ci.ID_Departamento = d.ID
          WHERE 1=1";

$params = array();

// Aplicar filtro por edificio según el rol
if ($filtrar_por_edificio && !empty($edificios_permitidos)) {
    $placeholders = str_repeat('?,', count($edificios_permitidos) - 1) . '?';
    $query .= " AND l.ID_Edificio IN ($placeholders)";
    foreach ($edificios_permitidos as $edificio_id) {
        $params[] = $edificio_id;
    }
}

// Filtrar por departamentos permitidos
if ($_SESSION['role_name'] != 'Administrador') {
    if (!empty($departamentos_permitidos)) {
        $placeholders = str_repeat('?,', count($departamentos_permitidos) - 1) . '?';
        $query .= " AND ci.ID_Departamento IN ($placeholders)";
        foreach ($departamentos_permitidos as $depto_id) {
            $params[] = $depto_id;
        }
    } else {
        // Si no hay departamentos permitidos, no mostrar nada
        $query .= " AND 1=0";
    }
}

// Filtro de departamento adicional si el usuario seleccionó uno específico
if (!empty($departamento)) {
    if ($_SESSION['role_name'] == 'Administrador' || in_array($departamento, $departamentos_permitidos)) {
        $query .= " AND ci.ID_Departamento = ?";
        $params[] = $departamento;
    }
}

// Añadir condiciones de filtrado adicionales
if (!empty($tipo_ci)) {
    $query .= " AND ci.ID_TipoCI = ?";
    $params[] = $tipo_ci;
}

if (!empty($ubicacion)) {
    $query .= " AND l.ID_Edificio = ?";
    $params[] = $ubicacion;
}

if (!empty($busqueda)) {
    $query .= " AND (ci.Nombre LIKE ? OR ci.NumSerie LIKE ? OR ci.Descripcion LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

// Ordenar por ID
$query .= " ORDER BY ci.ID DESC";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);
$stmt->execute($params);

// Obtener listas para los filtros
$tipoStmt = $conn->prepare("SELECT ID, Nombre FROM TIPO_CI ORDER BY Nombre");
$tipoStmt->execute();

$ubicacionStmt = $conn->prepare("SELECT ID, Nombre FROM EDIFICIO ORDER BY Nombre");
$ubicacionStmt->execute();

// Obtener departamentos para el filtro (según los departamentos permitidos)
if (!empty($departamentos_permitidos)) {
    $placeholders = str_repeat('?,', count($departamentos_permitidos) - 1) . '?';
    $deptoStmt = $conn->prepare("SELECT ID, Nombre FROM DEPARTAMENTO WHERE ID IN ($placeholders) ORDER BY Nombre");
    $deptoStmt->execute($departamentos_permitidos);
    $departamentos_filtro = $deptoStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $departamentos_filtro = array();
}
?>

<!-- Título de la página -->
<h1 class="h2">Gestión de Elementos de Configuración</h1>

<!-- Mensajes de error o alertas -->
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <?php 
        switch ($_GET['error']) {
            case 'permiso_denegado':
                echo htmlspecialchars($_GET['mensaje'] ?? 'No tiene permiso para realizar esta acción.');
                break;
            case 'missing_id':
                echo 'Error: ID del elemento de configuración no proporcionado.';
                break;
            case 'not_found':
                echo 'Error: El elemento de configuración solicitado no existe.';
                break;
            default:
                echo htmlspecialchars($_GET['mensaje'] ?? 'Ha ocurrido un error.');
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_GET['mensaje'] ?? 'Operación realizada con éxito.'); ?>
    </div>
<?php endif; ?>

<!-- Alerta cuando no hay departamentos asignados -->
<?php if (empty($departamentos_permitidos) && $_SESSION['role_name'] != 'Administrador'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        No tiene departamentos asignados. Contacte al administrador para que le asigne acceso a los departamentos correspondientes.
    </div>
<?php endif; ?>

<!-- Botón para agregar nuevo CI -->
<div class="row mb-4">
    <div class="col-12">
        <a href="agregar-ci.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Elemento
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
                    <div class="col-md-3">
                        <label for="departamento" class="form-label">Departamento</label>
                        <select class="form-select" id="departamento" name="departamento">
                            <option value="">Todos</option>
                            <?php foreach ($departamentos_filtro as $depto): ?>
                                <option value="<?php echo $depto['ID']; ?>" <?php echo ($departamento == $depto['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($depto['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tipo_ci" class="form-label">Tipo de CI</label>
                        <select class="form-select" id="tipo_ci" name="tipo_ci">
                            <option value="">Todos</option>
                            <?php while ($tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $tipo['ID']; ?>" <?php echo ($tipo_ci == $tipo['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['Nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="ubicacion" class="form-label">Ubicación</label>
                        <select class="form-select" id="ubicacion" name="ubicacion">
                            <option value="">Todas</option>
                            <?php while ($ubicacion_item = $ubicacionStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $ubicacion_item['ID']; ?>" <?php echo ($ubicacion == $ubicacion_item['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ubicacion_item['Nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="busqueda" class="form-label">Búsqueda</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Nombre, número de serie..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <a href="gestion-ci.php" class="btn btn-secondary">
                            <i class="fas fa-undo me-2"></i>Limpiar filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de CIs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Elementos de Configuración</h5>
            </div>
            <div class="card-body">
                <?php 
                $elementos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($elementos) > 0): 
                ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Número de Serie</th>
                                <th>Ubicación</th>
                                <th>Encargado</th>
                                <th>Departamento</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($elementos as $ci): ?>
                                <tr>
                                    <td><?php echo $ci['ID']; ?></td>
                                    <td><?php echo htmlspecialchars($ci['Nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($ci['TipoCI']); ?></td>
                                    <td><?php echo htmlspecialchars($ci['NumSerie']); ?></td>
                                    <td><?php echo htmlspecialchars($ci['Edificio'] . ' - ' . $ci['Localizacion']); ?></td>
                                    <td><?php echo htmlspecialchars($ci['Encargado']); ?></td>
                                    <td><?php echo htmlspecialchars($ci['Departamento']); ?></td>
                                    <td>
                                        <a href="ver-ci.php?id=<?php echo $ci['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar-ci.php?id=<?php echo $ci['ID']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($_SESSION['permisos']['admin']): ?>
                                        <a href="javascript:void(0);" onclick="confirmarEliminacion(<?php echo $ci['ID']; ?>)" class="btn btn-sm btn-danger" title="Eliminar">
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
                        No se encontraron elementos de configuración con los filtros seleccionados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Script para confirmación de eliminación -->
<script>
function confirmarEliminacion(id) {
    if (confirm('¿Está seguro de que desea eliminar este elemento de configuración? Esta acción no se puede deshacer.')) {
        window.location.href = 'eliminar-ci.php?id=' + id;
    }
}
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>