<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de Usuarios
// Asume que solo roles con el permiso 'gestionar_usuarios' pueden acceder
check_permission('gestionar_usuarios');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// --- Lógica de Obtención de Datos y Filtrado ---

// Obtener parámetros de filtrado
$estado = isset($_GET['estado']) ? $_GET['estado'] : ''; // 1 para Activo, 0 para Inactivo, '' para Todos
$rol = isset($_GET['rol']) ? $_GET['rol'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

// Construir la consulta base
$query = "SELECT u.ID, u.Username, u.UltimoAcceso, u.Estado,
          e.Nombre AS NombreEmpleado, r.Nombre AS NombreRol
          FROM USUARIO u
          LEFT JOIN EMPLEADO e ON u.ID_Empleado = e.ID
          LEFT JOIN ROL r ON u.ID_Rol = r.ID
          WHERE 1=1";

$params = array();

// Filtrar por Estado
if ($estado !== '') {
    $query .= " AND u.Estado = ?";
    $params[] = $estado;
}

// Filtrar por Rol
if (!empty($rol)) {
    $query .= " AND u.ID_Rol = ?";
    $params[] = $rol;
}

// Filtrar por Búsqueda (Username o Nombre de Empleado)
if (!empty($busqueda)) {
    $query .= " AND (u.Username LIKE ? OR e.Nombre LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

// Ordenar por ID
$query .= " ORDER BY u.ID ASC";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);
$stmt->execute($params);

// Obtener lista de Roles para el filtro
$rolStmt = $conn->prepare("SELECT ID, Nombre FROM ROL ORDER BY Nombre");
$rolStmt->execute();
$roles_filtro = $rolStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Estructura HTML y Presentación ---
?>

<h1 class="h2">Gestión de Usuarios</h1>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <?php
        switch ($_GET['error']) {
            case 'permiso_denegado':
                echo htmlspecialchars($_GET['mensaje'] ?? 'No tiene permiso para realizar esta acción.');
                break;
            case 'missing_id':
                echo 'Error: ID del usuario no proporcionado.';
                break;
            case 'not_found':
                echo 'Error: El usuario solicitado no existe.';
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

<div class="row mb-4">
    <div class="col-12">
        <a href="agregar-usuario.php" class="btn btn-success">
            <i class="fas fa-user-plus me-2"></i>Agregar Nuevo Usuario
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Filtros</h5>
            </div>
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="rol" class="form-label">Rol</label>
                        <select class="form-select" id="rol" name="rol">
                            <option value="">Todos los Roles</option>
                            <?php foreach ($roles_filtro as $r): ?>
                                <option value="<?php echo $r['ID']; ?>" <?php echo ($rol == $r['ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="1" <?php echo ($estado === '1') ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($estado === '0') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="busqueda" class="form-label">Búsqueda</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Username o Empleado..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>
                    <div class="col-md-12 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                        <a href="usuarios.php" class="btn btn-secondary">
                            <i class="fas fa-undo me-2"></i>Limpiar filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Usuarios</h5>
            </div>
            <div class="card-body">
                <?php
                $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($usuarios) > 0):
                ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Empleado Asignado</th>
                                <th>Rol</th>
                                <th>Último Acceso</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo $usuario['ID']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['Username']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['NombreEmpleado'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['NombreRol'] ?? 'N/A'); ?></td>
                                    <td><?php echo $usuario['UltimoAcceso'] ? date('d-m-Y H:i', strtotime($usuario['UltimoAcceso'])) : 'Nunca'; ?></td>
                                    <td>
                                        <?php if ($usuario['Estado'] == 1): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="ver-usuario.php?id=<?php echo $usuario['ID']; ?>" class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar-usuario.php?id=<?php echo $usuario['ID']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (isset($_SESSION['permisos']['admin']) && $_SESSION['permisos']['admin']): // O un permiso específico para eliminar usuarios ?>
                                        <a href="javascript:void(0);" onclick="confirmarEliminacion(<?php echo $usuario['ID']; ?>)" class="btn btn-sm btn-danger" title="Eliminar">
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
                        No se encontraron usuarios con los filtros seleccionados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarEliminacion(id) {
    if (confirm('¿Está seguro de que desea eliminar este usuario? Esta acción no se puede deshacer.')) {
        window.location.href = 'eliminar-usuario.php?id=' + id;
    }
}
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>