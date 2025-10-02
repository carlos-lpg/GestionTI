<?php
// Incluir el encabezado (necesario para la conexión y permisos)
require_once '../../includes/header.php';

// Verificar permiso de gestión de Usuarios
// Nota: Puedes considerar un permiso más estricto solo para eliminación, ej. 'eliminar_usuarios'
check_permission('gestionar_usuarios');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

$id_usuario = $_GET['id'] ?? null;
$redirect_url = 'usuarios.php';

if (!$id_usuario) {
    // Si no hay ID, redirigir con error
    header("Location: $redirect_url?error=missing_id");
    exit();
}

try {
    // 1. Verificar si el usuario existe antes de intentar eliminar
    $checkStmt = $conn->prepare("SELECT Username FROM USUARIO WHERE ID = ?");
    $checkStmt->execute([$id_usuario]);
    $usuario_existente = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_existente) {
        header("Location: $redirect_url?error=not_found&mensaje=" . urlencode("El usuario con ID $id_usuario no existe."));
        exit();
    }
    
    $username = $usuario_existente['Username'];

    // 2. Preparar y ejecutar la consulta DELETE
    $query = "DELETE FROM USUARIO WHERE ID = ?";
    $stmt = $conn->prepare($query);

    if ($stmt->execute([$id_usuario])) {
        // Éxito: Redirigir con mensaje de éxito
        header("Location: $redirect_url?success=1&mensaje=" . urlencode("Usuario '$username' eliminado correctamente."));
    } else {
        // Error en la ejecución
        header("Location: $redirect_url?error=1&mensaje=" . urlencode("Error al intentar eliminar el usuario '$username'."));
    }
    
} catch (PDOException $e) {
    // Error de clave foránea u otro error de BD
    if ($e->getCode() == 23000) {
        $mensaje = "No se puede eliminar el usuario '$username' porque está asociado a otros registros (ej. historial de acciones o registros de la BD).";
    } else {
        $mensaje = "Error de base de datos: " . $e->getMessage();
    }
    header("Location: $redirect_url?error=1&mensaje=" . urlencode($mensaje));

} finally {
    exit();
}
// El pie de página no se incluye ya que el script finaliza con la redirección
?>