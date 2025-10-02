<?php
// Incluir el encabezado (esto podría ser problemático si necesitamos redirigir antes)
// Por lo tanto, lo incluimos al final, después de posibles redirecciones
// require_once '../../includes/header.php';

// Incluir configuración de base de datos
require_once '../../includes/header.php';
require_once '../../config/database.php';

// Verificar si el usuario tiene sesión iniciada
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Verificar permiso de gestión de CIs
require_once '../../includes/permissions.php';
if (!check_permission('gestionar_ci', false)) {
    header("Location: ../../index.php?error=permiso_denegado&mensaje=No tiene permiso para eliminar elementos de configuración.");
    exit;
}

// Verificar que el usuario tiene permisos de administrador
if (!isset($_SESSION['permisos']['admin']) || !$_SESSION['permisos']['admin']) {
    header("Location: gestion-ci.php?error=permiso_denegado&mensaje=Solo los administradores pueden eliminar elementos de configuración.");
    exit;
}

// Verificar que se ha proporcionado un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: gestion-ci.php?error=missing_id");
    exit;
}

$id_ci = intval($_GET['id']);

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Verificar que el CI existe y obtener sus datos
$query_ci = "SELECT ci.ID, ci.Nombre, ci.ID_Departamento, l.ID_Edificio 
             FROM CI ci
             LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
             WHERE ci.ID = ?";
$stmt_ci = $conn->prepare($query_ci);
$stmt_ci->execute([$id_ci]);

if ($stmt_ci->rowCount() == 0) {
    header("Location: gestion-ci.php?error=not_found");
    exit;
}

$ci = $stmt_ci->fetch(PDO::FETCH_ASSOC);

// Determinar si el usuario tiene permisos para este CI según su ubicación
$permiso_edificio = false;

// Si es administrador, tiene permisos completos
if ($_SESSION['role_name'] == 'Administrador' || 
    $_SESSION['role_name'] == 'Gerente TI' || 
    $_SESSION['role_name'] == 'Encargado Inventario') {
    $permiso_edificio = true;
} else {
    // Verificar según el rol específico y la categoría del edificio
    $query_permiso = "SELECT 1 
                     FROM EDIFICIO e
                     JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                     WHERE e.ID = ?";
    
    // Añadir condición específica según el rol
    switch ($_SESSION['role_name']) {
        case 'Coordinador TI CEDIS':
            $query_permiso .= " AND cu.Nombre = 'CEDIS'";
            break;
        case 'Coordinador TI Sucursales':
            $query_permiso .= " AND cu.Nombre = 'Sucursal'";
            break;
        case 'Coordinador TI Corporativo':
            $query_permiso .= " AND cu.Nombre = 'Corporativo'";
            break;
        case 'Supervisor Infraestructura':
        case 'Supervisor Sistemas':
        case 'Técnico TI':
            // Estos roles pueden eliminar elementos de cualquier edificio
            $permiso_edificio = true;
            break;
        default:
            // Otros roles no pueden eliminar
            $permiso_edificio = false;
    }
    
    // Solo ejecutar la consulta si no se ha determinado el permiso
    if (!$permiso_edificio && $_SESSION['role_name'] != 'Usuario Final') {
        $stmt_permiso = $conn->prepare($query_permiso);
        $stmt_permiso->execute([$ci['ID_Edificio']]);
        $permiso_edificio = ($stmt_permiso->rowCount() > 0);
    }
}

// Si no tiene permisos para este edificio, redirigir con error
if (!$permiso_edificio) {
    header("Location: gestion-ci.php?error=permiso_denegado&mensaje=No tiene permiso para eliminar elementos de configuración en esta ubicación.");
    exit;
}

// Verificar dependencias antes de eliminar
// Por ejemplo, si hay registros en tablas relacionadas

// 1. Verificar si hay relaciones en tabla de mantenimientos
$query_mant = "SELECT COUNT(*) as total FROM MANTENIMIENTO WHERE ID_CI = ?";
$stmt_mant = $conn->prepare($query_mant);
$stmt_mant->execute([$id_ci]);
$tiene_mantenimientos = ($stmt_mant->fetch(PDO::FETCH_ASSOC)['total'] > 0);

// 2. Verificar si hay relaciones en tabla de incidentes
$query_inc = "SELECT COUNT(*) as total FROM INCIDENTE WHERE ID_CI = ?";
$stmt_inc = $conn->prepare($query_inc);
$stmt_inc->execute([$id_ci]);
$tiene_incidentes = ($stmt_inc->fetch(PDO::FETCH_ASSOC)['total'] > 0);

// Si tiene dependencias, mostrar error
if ($tiene_mantenimientos || $tiene_incidentes) {
    header("Location: gestion-ci.php?error=dependencias&mensaje=No se puede eliminar este elemento porque tiene mantenimientos o incidentes asociados.");
    exit;
}

// Intentar eliminar el CI si no hay problemas
try {
    // Iniciar transacción
    $conn->beginTransaction();
    
    // 1. Primero eliminar registros de tablas relacionadas que no sean restricciones
    // (por ejemplo, atributos personalizados, archivos adjuntos, etc.)
    $query_atributos = "DELETE FROM CI_ATRIBUTO WHERE ID_CI = ?";
    $stmt_attr = $conn->prepare($query_atributos);
    $stmt_attr->execute([$id_ci]);
    
    // 2. Eliminar el CI
    $query_eliminar = "DELETE FROM CI WHERE ID = ?";
    $stmt_eliminar = $conn->prepare($query_eliminar);
    $stmt_eliminar->execute([$id_ci]);
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir con mensaje de éxito
    header("Location: gestion-ci.php?success=1&mensaje=El elemento de configuración ha sido eliminado correctamente.");
    exit;
} catch (PDOException $e) {
    // Revertir la transacción en caso de error
    $conn->rollBack();
    
    // Redirigir con mensaje de error
    header("Location: gestion-ci.php?error=db_error&mensaje=Error al eliminar el elemento: " . $e->getMessage());
    exit;
}

// Nota: Este punto no debería alcanzarse nunca debido a las redirecciones anteriores
?>