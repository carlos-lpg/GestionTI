<?php
// Incluir archivos necesarios
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../models/Incidencia.php';

// Verificar permiso de gestión de incidencias
check_permission('gestionar_incidencias');

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: mis-incidencias.php?error=invalid_method");
    exit;
}

// Validar datos del formulario
$id_incidencia = isset($_POST['id_incidencia']) ? intval($_POST['id_incidencia']) : 0;
$nuevo_estado = isset($_POST['estado']) ? intval($_POST['estado']) : 0;
$comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';

// Validar datos básicos
if ($id_incidencia <= 0 || $nuevo_estado <= 0 || empty($comentario)) {
    header("Location: mis-incidencias.php?error=incomplete_data");
    exit;
}

// Verificar que el nuevo estado sea válido (3=En proceso, 4=En espera, 5=Resuelta)
if (!in_array($nuevo_estado, [3, 4, 5])) {
    header("Location: mis-incidencias.php?error=invalid_state");
    exit;
}

try {
    // Conexión a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    $incidencia = new Incidencia($conn);
    
    // Obtener información actual de la incidencia
    if (!$incidencia->getById($id_incidencia)) {
        throw new Exception("La incidencia no existe.");
    }
    
    // Verificar si la incidencia está asignada al técnico actual
    if ($incidencia->id_tecnico != $_SESSION['empleado_id'] && !has_permission('admin')) {
        throw new Exception("No tiene permiso para actualizar esta incidencia.");
    }
    
    // Verificar que la incidencia no esté cerrada
    if ($incidencia->id_stat == 6) { // 6 = Cerrada
        throw new Exception("No se puede actualizar una incidencia cerrada.");
    }
    
    // Guardar el estado anterior para el historial
    $estado_anterior = $incidencia->id_stat;
    
    // Determinar el tipo de comentario
    $tipo_comentario = 'ACTUALIZACION';
    if ($nuevo_estado == 5) { // 5 = Resuelta
        $tipo_comentario = 'SOLUCION';
        
        // Para estado "Resuelta", registrar la solución
        $query_solucion = "INSERT INTO INCIDENCIA_SOLUCION (ID_Incidencia, Descripcion, FechaRegistro, ID_Usuario) 
                         VALUES (?, ?, GETDATE(), ?)";
        $stmt_solucion = $conn->prepare($query_solucion);
        $stmt_solucion->execute([$id_incidencia, $comentario, $_SESSION['user_id']]);
    }
    
    // Actualizar el estado de la incidencia
    if (!$incidencia->cambiarEstado($id_incidencia, $nuevo_estado, $_SESSION['user_id'])) {
        throw new Exception("Error al actualizar el estado de la incidencia.");
    }
    
    // Registrar cambio en el historial
    if (!$incidencia->registrarCambioEstado($id_incidencia, $estado_anterior, $nuevo_estado, $_SESSION['user_id'])) {
        throw new Exception("Error al registrar el cambio de estado en el historial.");
    }
    
    // Agregar comentario
    if (!$incidencia->agregarComentario($id_incidencia, $_SESSION['user_id'], $comentario, $tipo_comentario, true)) {
        throw new Exception("Error al agregar el comentario.");
    }
    
    // Redireccionar con mensaje de éxito
    header("Location: mis-incidencias.php?success=updated");
    exit;
    
} catch (Exception $e) {
    // Redireccionar con mensaje de error
    header("Location: mis-incidencias.php?error=update_failed&message=" . urlencode($e->getMessage()));
    exit;
}
?>
