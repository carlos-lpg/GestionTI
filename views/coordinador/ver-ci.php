<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de CIs
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Incluir modelo de componentes
require_once '../../models/CIComponente.php';

// Verificar si se proporcionó el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redireccionar a la lista
    header("Location: gestion-ci.php?error=missing_id&mensaje=No se proporcionó un ID válido");
    exit;
}

$ci_id = $_GET['id'];

// Incluir modelo de componentes - DESPUÉS de definir $conn y $ci_id
require_once '../../models/CIComponente.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();


// Obtener componentes asociados al CI
$ciComponente = new CIComponente($conn);
$componentesHWStmt = $ciComponente->getComponentesByCiYTipo($ci_id, 'HW');
$componentesSWStmt = $ciComponente->getComponentesByCiYTipo($ci_id, 'SW');

// Obtener todos los componentes en arrays
$componentesHW = $componentesHWStmt->fetchAll(PDO::FETCH_ASSOC);
$componentesSW = $componentesSWStmt->fetchAll(PDO::FETCH_ASSOC);


// Obtener datos completos del CI
try {
    $query = "SELECT ci.ID, ci.Nombre, ci.Descripcion, ci.NumSerie, ci.FechaAdquisicion, 
              ci.ID_TipoCI, ci.ID_Localizacion, ci.ID_Encargado, ci.ID_Proveedor,
              ci.ID_Departamento, ci.CreatedBy, ci.CreatedDate, ci.ModifiedBy, ci.ModifiedDate,
              t.Nombre as TipoCI, p.Nombre as Proveedor, e.Nombre as Encargado,
              l.Nombre as Localizacion, l.NumPlanta, ed.Nombre as Edificio, 
              d.Nombre as Departamento,
              cat.Nombre as CategoriaUbicacion,
              cu.Username as CreadoPor, mu.Username as ModificadoPor
              FROM CI ci
              LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
              LEFT JOIN PROVEEDOR p ON ci.ID_Proveedor = p.ID
              LEFT JOIN EMPLEADO e ON ci.ID_Encargado = e.ID
              LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
              LEFT JOIN EDIFICIO ed ON l.ID_Edificio = ed.ID
              LEFT JOIN DEPARTAMENTO d ON ci.ID_Departamento = d.ID
              LEFT JOIN CATEGORIA_UBICACION cat ON ed.ID_CategoriaUbicacion = cat.ID
              LEFT JOIN USUARIO cu ON ci.CreatedBy = cu.ID
              LEFT JOIN USUARIO mu ON ci.ModifiedBy = mu.ID
              WHERE ci.ID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$ci_id]);
    
    if ($stmt->rowCount() == 0) {
        // CI no encontrado
        header("Location: gestion-ci.php?error=not_found&mensaje=El elemento de configuración no fue encontrado");
        exit;
    }
    
    $ci = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si el usuario tiene permiso para ver este CI
    $tiene_permiso = false;

    if ($_SESSION['role_name'] == 'Administrador') {
        $tiene_permiso = true;
    } else {
        // Definir lógica según el rol
        switch ($_SESSION['role_name']) {
            case 'Gerente TI':
            case 'Técnico TI':
            case 'Supervisor Infraestructura':
            case 'Supervisor Sistemas':
            case 'Encargado Inventario':
            case 'Coordinador TI CEDIS':
                // Estos roles pueden ver todos los CIs
                $tiene_permiso = true;
                break;
                
            case 'Coordinador TI CEDIS':
            case 'Usuario CEDIS':
                // Solo ve CIs ubicados en el edificio CEDIS
                $check_query = "SELECT 1 
                               FROM LOCALIZACION l
                               JOIN EDIFICIO e ON l.ID_Edificio = e.ID
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE l.ID = ? AND cu.Nombre = 'CEDIS'";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$ci['ID_Localizacion']]);
                $tiene_permiso = ($check_stmt->rowCount() > 0);
                
                // Si no encontramos coincidencia por localización, verificamos si el nombre del CI tiene CEDIS
                if (!$tiene_permiso) {
                    $nombre_check_query = "SELECT 1 FROM CI WHERE ID = ? AND Nombre LIKE '%CEDIS%'";
                    $nombre_check_stmt = $conn->prepare($nombre_check_query);
                    $nombre_check_stmt->execute([$ci_id]);
                    $tiene_permiso = ($nombre_check_stmt->rowCount() > 0);
                }
                break;
                
            case 'Coordinador TI Sucursales':
                // Solo ve CIs ubicados en edificios de Sucursales
                $check_query = "SELECT 1 
                               FROM LOCALIZACION l
                               JOIN EDIFICIO e ON l.ID_Edificio = e.ID
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE l.ID = ? AND cu.Nombre = 'Sucursal'";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$ci['ID_Localizacion']]);
                $tiene_permiso = ($check_stmt->rowCount() > 0);
                
                // Si no encontramos coincidencia por localización, verificamos si el nombre del CI tiene Sucursal
                if (!$tiene_permiso) {
                    $nombre_check_query = "SELECT 1 FROM CI WHERE ID = ? AND Nombre LIKE '%Sucursal%'";
                    $nombre_check_stmt = $conn->prepare($nombre_check_query);
                    $nombre_check_stmt->execute([$ci_id]);
                    $tiene_permiso = ($nombre_check_stmt->rowCount() > 0);
                }
                break;
                
            case 'Coordinador TI Corporativo':
                // Solo ve CIs ubicados en edificio Corporativo
                $check_query = "SELECT 1 
                               FROM LOCALIZACION l
                               JOIN EDIFICIO e ON l.ID_Edificio = e.ID
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE l.ID = ? AND cu.Nombre = 'Corporativo'";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$ci['ID_Localizacion']]);
                $tiene_permiso = ($check_stmt->rowCount() > 0);
                
                // Si no encontramos coincidencia por localización, verificamos si el nombre del CI tiene Corporativo
                if (!$tiene_permiso) {
                    $nombre_check_query = "SELECT 1 FROM CI WHERE ID = ? AND Nombre LIKE '%Corporativo%'";
                    $nombre_check_stmt = $conn->prepare($nombre_check_query);
                    $nombre_check_stmt->execute([$ci_id]);
                    $tiene_permiso = ($nombre_check_stmt->rowCount() > 0);
                }
                break;
                
            case 'Usuario Final':
                // Verificar si el CI pertenece al departamento del usuario
                $deptos_query = "SELECT 1 
                                FROM EMPLEADO_DEPTO ed 
                                WHERE ed.ID_Empleado = ? AND ed.ID_Depto = ?";
                $deptos_stmt = $conn->prepare($deptos_query);
                $deptos_stmt->execute([$_SESSION['empleado_id'], $ci['ID_Departamento']]);
                
                if ($deptos_stmt->rowCount() > 0) {
                    $tiene_permiso = true;
                } else {
                    // Si no tiene departamentos asignados, verificar si es su departamento principal
                    $depto_query = "SELECT 1 FROM EMPLEADO WHERE ID = ? AND ID_Departamento = ?";
                    $depto_stmt = $conn->prepare($depto_query);
                    $depto_stmt->execute([$_SESSION['empleado_id'], $ci['ID_Departamento']]);
                    $tiene_permiso = ($depto_stmt->rowCount() > 0);
                }
                break;
                
            default:
                // Para roles no especificados, permitir acceso a todos los CIs
                $tiene_permiso = true;
        }
    }

    if (!$tiene_permiso) {
        // Registrar intento de acceso no autorizado (opcional)
        $log_query = "INSERT INTO LOG_ACCESO (ID_Usuario, FechaAcceso, TipoAcceso, RecursoAccedido, Resultado) 
                      VALUES (?, GETDATE(), 'Visualización CI', ?, 'Denegado')";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([$_SESSION['user_id'], 'CI ID: ' . $ci_id]);
        
        // Redirigir con mensaje adecuado
        header("Location: gestion-ci.php?error=permiso_denegado&mensaje=No tiene permiso para ver este elemento de configuración");
        exit;
    }
    
    // Consultar incidencias relacionadas con este CI
    $incidenciasQuery = "SELECT i.ID, i.Descripcion, i.FechaInicio, e.Nombre as Tecnico, 
                          s.Descripcion as Estado, p.Descripcion as Prioridad
                          FROM INCIDENCIA i
                          LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                          LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                          LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                          WHERE i.ID_CI = ?
                          ORDER BY i.FechaInicio DESC";
    
    $incidenciasStmt = $conn->prepare($incidenciasQuery);
    $incidenciasStmt->execute([$ci_id]);
    
    // Consultar CIs relacionados
    $relacionesQuery = "SELECT r.ID, r.TipoRelacion, 
                         ci_hijo.ID as ID_Hijo, ci_hijo.Nombre as Nombre_Hijo, t_hijo.Nombre as Tipo_Hijo,
                         ci_padre.ID as ID_Padre, ci_padre.Nombre as Nombre_Padre, t_padre.Nombre as Tipo_Padre
                         FROM RELACION_CI r
                         LEFT JOIN CI ci_hijo ON r.ID_CI_Hijo = ci_hijo.ID
                         LEFT JOIN CI ci_padre ON r.ID_CI_Padre = ci_padre.ID
                         LEFT JOIN TIPO_CI t_hijo ON ci_hijo.ID_TipoCI = t_hijo.ID
                         LEFT JOIN TIPO_CI t_padre ON ci_padre.ID_TipoCI = t_padre.ID
                         WHERE r.ID_CI_Hijo = ? OR r.ID_CI_Padre = ?";
    
    $relacionesStmt = $conn->prepare($relacionesQuery);
    $relacionesStmt->execute([$ci_id, $ci_id]);
    
} catch (PDOException $e) {
    $error = "Error en la base de datos: " . $e->getMessage();
    
    // Registrar el error (opcional)
    error_log("Error en ver-ci.php: " . $e->getMessage());
}
?>

<!-- Título de la página -->
<h1 class="h2">Detalles del Elemento de Configuración</h1>

<!-- Botones de acción -->
<div class="row mb-4">
    <div class="col-12">
        <a href="gestion-ci.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a la lista
        </a>
        <a href="editar-ci.php?id=<?php echo $ci_id; ?>" class="btn btn-warning ms-2">
            <i class="fas fa-edit me-2"></i>Editar
        </a>
        <?php if ($_SESSION['permisos']['admin']): ?>
        <a href="javascript:void(0);" onclick="confirmarEliminacion(<?php echo $ci_id; ?>)" class="btn btn-danger ms-2">
            <i class="fas fa-trash me-2"></i>Eliminar
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error; ?>
    </div>
<?php else: ?>

<!-- Información general del CI -->
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
                                <td><?php echo $ci['ID']; ?></td>
                            </tr>
                            <tr>
                                <th>Nombre:</th>
                                <td><?php echo htmlspecialchars($ci['Nombre']); ?></td>
                            </tr>
                            <tr>
                                <th>Tipo:</th>
                                <td><?php echo htmlspecialchars($ci['TipoCI']); ?></td>
                            </tr>
                            <tr>
                                <th>Número de Serie:</th>
                                <td><?php echo htmlspecialchars($ci['NumSerie']); ?></td>
                            </tr>
                            <tr>
                                <th>Descripción:</th>
                                <td><?php echo htmlspecialchars($ci['Descripcion']); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha de Adquisición:</th>
                                <td><?php echo date('d/m/Y', strtotime($ci['FechaAdquisicion'])); ?></td>
                            </tr>
                            <tr>
                                <th>Departamento:</th>
                                <td><?php echo htmlspecialchars($ci['Departamento']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">Categoría:</th>
                                <td><?php echo htmlspecialchars($ci['CategoriaUbicacion'] ?? 'No especificada'); ?></td>
                            </tr>
                            <tr>
                                <th>Edificio:</th>
                                <td><?php echo htmlspecialchars($ci['Edificio']); ?></td>
                            </tr>
                            <tr>
                                <th>Localización:</th>
                                <td><?php echo htmlspecialchars($ci['Localizacion'] . ' (Planta ' . $ci['NumPlanta'] . ')'); ?></td>
                            </tr>
                            <tr>
                                <th>Encargado:</th>
                                <td><?php echo htmlspecialchars($ci['Encargado']); ?></td>
                            </tr>
                            <tr>
                                <th>Proveedor:</th>
                                <td><?php echo htmlspecialchars($ci['Proveedor']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Información de Auditoría -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información de Auditoría</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">Creado por:</th>
                                <td><?php echo htmlspecialchars($ci['CreadoPor'] ?? 'No registrado'); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha de creación:</th>
                                <td>
                                    <?php 
                                    echo $ci['CreatedDate'] 
                                        ? date('d/m/Y H:i', strtotime($ci['CreatedDate'])) 
                                        : 'No registrada'; 
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th class="w-25">Modificado por:</th>
                                <td><?php echo htmlspecialchars($ci['ModificadoPor'] ?? 'No modificado'); ?></td>
                            </tr>
                            <tr>
                                <th>Última modificación:</th>
                                <td>
                                    <?php 
                                    echo $ci['ModifiedDate'] 
                                        ? date('d/m/Y H:i', strtotime($ci['ModifiedDate'])) 
                                        : 'No modificado'; 
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Relaciones con otros CIs -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Relaciones con otros Elementos</h5>
                <a href="agregar-relacion.php?ci_id=<?php echo $ci_id; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Agregar Relación
                </a>
            </div>
            <div class="card-body">
                <?php if ($relacionesStmt->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tipo de Relación</th>
                                    <th>Elemento Relacionado</th>
                                    <th>Tipo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($relacion = $relacionesStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($relacion['TipoRelacion']); ?></td>
                                        <td>
                                            <?php 
                                            if ($relacion['ID_Padre'] == $ci_id) {
                                                echo htmlspecialchars($relacion['Nombre_Hijo']);
                                                $relacionId = $relacion['ID_Hijo'];
                                            } else {
                                                echo htmlspecialchars($relacion['Nombre_Padre']);
                                                $relacionId = $relacion['ID_Padre'];
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($relacion['ID_Padre'] == $ci_id) {
                                                echo htmlspecialchars($relacion['Tipo_Hijo']);
                                            } else {
                                                echo htmlspecialchars($relacion['Tipo_Padre']);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="ver-ci.php?id=<?php echo $relacionId; ?>" class="btn btn-sm btn-info" title="Ver elemento">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="eliminar-relacion.php?id=<?php echo $relacion['ID']; ?>&ci_id=<?php echo $ci_id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de eliminar esta relación?')" title="Eliminar relación">
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
                        No hay relaciones registradas para este elemento.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Historial de Incidencias -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Historial de Incidencias</h5>
            </div>
            <div class="card-body">
                <?php if ($incidenciasStmt->rowCount() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Descripción</th>
                                    <th>Fecha</th>
                                    <th>Técnico</th>
                                    <th>Prioridad</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($incidencia = $incidenciasStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $incidencia['ID']; ?></td>
                                        <td><?php echo htmlspecialchars($incidencia['Descripcion']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($incidencia['FechaInicio'])); ?></td>
                                        <td><?php echo htmlspecialchars($incidencia['Tecnico'] ?? 'No asignado'); ?></td>
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
                                                $badgeClass = 'bg-secondary';
                                            }
                                            
                                            echo "<span class='badge $badgeClass'>$estado</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <a href="../incidencias/ver-incidencia.php?id=<?php echo $incidencia['ID']; ?>" class="btn btn-sm btn-info" title="Ver incidencia">
                                                <i class="fas fa-eye"></i>
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
                        No hay incidencias registradas para este elemento.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php

// Reiniciar los punteros de las consultas para poder usarlos nuevamente
$componentesHWStmt->execute([$ci_id, 'HW']);
$componentesSWStmt->execute([$ci_id, 'SW']);
?>
<!-- Componentes de Hardware -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Componentes de Hardware</h5>
            </div>
            <div class="card-body">
                <?php
                $hayComponentes = false;
                if (isset($componentesHWStmt) && $componentesHWStmt->rowCount() > 0) {
                    $hayComponentes = true;
                } elseif (isset($componentesHW) && count($componentesHW) > 0) {
                    $hayComponentes = true;
                }
                ?>

                <?php if ($hayComponentes): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Componente</th>
                                    <th>Categoría</th>
                                    <th>Fabricante</th>
                                    <th>Modelo</th>
                                    <th>Cantidad</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($componentesHWStmt) && $componentesHWStmt->rowCount() > 0): ?>
                                    <?php while ($componente = $componentesHWStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($componente['Nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Categoria']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Fabricante']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Modelo']); ?></td>
                                            <td><?php echo $componente['Cantidad']; ?></td>
                                            <td><?php echo htmlspecialchars($componente['Notas']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php elseif (isset($componentesHW) && count($componentesHW) > 0): ?>
                                    <?php foreach ($componentesHW as $componente): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($componente['Nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Categoria']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Fabricante']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Modelo']); ?></td>
                                            <td><?php echo $componente['Cantidad']; ?></td>
                                            <td><?php echo htmlspecialchars($componente['Notas']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay componentes de hardware registrados para este elemento.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Software Instalado -->
<!-- Software Instalado -->
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Software Instalado</h5>
            </div>
            <div class="card-body">
                <?php
                $haySoftware = false;
                if (isset($componentesSWStmt) && $componentesSWStmt->rowCount() > 0) {
                    $haySoftware = true;
                } elseif (isset($componentesSW) && count($componentesSW) > 0) {
                    $haySoftware = true;
                }
                ?>

                <?php if ($haySoftware): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Software</th>
                                    <th>Categoría</th>
                                    <th>Fabricante</th>
                                    <th>Versión</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($componentesSWStmt) && $componentesSWStmt->rowCount() > 0): ?>
                                    <?php while ($componente = $componentesSWStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($componente['Nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Categoria']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Fabricante']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Modelo']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Notas']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php elseif (isset($componentesSW) && count($componentesSW) > 0): ?>
                                    <?php foreach ($componentesSW as $componente): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($componente['Nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Categoria']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Fabricante']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Modelo']); ?></td>
                                            <td><?php echo htmlspecialchars($componente['Notas']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay software registrado para este elemento.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

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