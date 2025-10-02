<?php
// Incluir configuración de base de datos
require_once '../../config/database.php';
require_once '../../auth/session.php';

// Verificar sesión y permisos
check_session();
check_permission('admin');

// Verificar si se proporcionó el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: proveedores.php?error=missing_id");
    exit;
}

$id = $_GET['id'];

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar primero si hay CIs relacionados
    $check_query = "SELECT COUNT(*) as total FROM CI WHERE ID_Proveedor = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute([$id]);
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        // No se puede eliminar porque hay CIs relacionados
        header("Location: proveedores.php?error=has_related_cis");
        exit;
    }
    
    // Eliminar el proveedor
    $query = "DELETE FROM PROVEEDOR WHERE ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id]);
    
    // Redireccionar con mensaje de éxito
    header("Location: proveedores.php?success=2");
    exit;
    
} catch (PDOException $e) {
    // Error de base de datos
    header("Location: proveedores.php?error=database&message=" . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    // Error general
    header("Location: proveedores.php?error=general&message=" . urlencode($e->getMessage()));
    exit;
}
?>