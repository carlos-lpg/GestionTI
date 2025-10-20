<?php
// Incluir archivos necesarios
require_once '../config/database.php';
require_once '../models/Usuario.php';

// Mostrar errores durante el desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar logging de errores
ini_set('log_errors', 1);
ini_set('error_log', '../logs/login_errors.log');

// Verificar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    error_log("Acceso no autorizado: Método no POST");
    header("Location: ../index.php");
    exit();
}

// Obtener datos del formulario
$username = isset($_POST['username']) ? trim($_POST['username']) : "";
$password = isset($_POST['password']) ? $_POST['password'] : "";

// Validación básica
if (empty($username) || empty($password)) {
    error_log("Login fallido: Campos vacíos");
    header("Location: ../index.php?error=empty_fields");
    exit();
}

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        error_log("Error: No se pudo establecer la conexión a la base de datos");
        header("Location: ../index.php?error=database_connection");
        exit();
    }
    
    // Crear instancia del modelo Usuario
    $usuario = new Usuario($conn);
    
    // Intentar login con registro de errores
    if ($usuario->login($username, $password)) {
        // Login exitoso
        session_start();
        
        // Guardar datos básicos
        $_SESSION['user_id'] = $usuario->id;
        $_SESSION['username'] = $usuario->username;
        $_SESSION['role_id'] = $usuario->id_rol;
        $_SESSION['role_name'] = $usuario->nombre_rol;
        $_SESSION['full_name'] = $usuario->nombre_empleado;
        $_SESSION['empleado_id'] = $usuario->id_empleado;
        
        // Obtener permisos basados en el rol
        $permisos = $usuario->obtenerPermisos();
        $_SESSION['permisos'] = $permisos;
        
        // Log de login exitoso
        error_log("Login exitoso para usuario: $username, Rol: " . $usuario->nombre_rol);
        
        // Determinar la página de dashboard según el rol
        switch ($usuario->nombre_rol) {
            case 'Administrador':
                header("Location: ../views/admin/dashboard.php");
                break;
            case 'Coordinador TI CEDIS':
            case 'Coordinador TI Sucursales':
            case 'Coordinador TI Corporativo':
                header("Location: ../views/coordinador/dashboard.php");
                break;
            case 'Técnico TI':
                header("Location: ../views/tecnico/dashboard.php");
                break;
            case 'Supervisor Infraestructura':
            case 'Supervisor Sistemas':
                header("Location: ../views/supervisor/dashboard.php");
                break;
            case 'Encargado Inventario':
                header("Location: ../views/inventario/dashboard.php");
                break;
            case 'Gerente TI':
                header("Location: ../views/gerente/dashboard.php");
                break;
            default:
                header("Location: ../views/usuario/dashboard.php");
        }
        exit();
    } else {
        // Login fallido
        error_log("Login fallido para usuario: $username");
        header("Location: ../index.php?error=wrong_password");
        exit();
    }
} catch (PDOException $e) {
    // Error de base de datos
    error_log("Error PDO en login.php: " . $e->getMessage());
    header("Location: ../index.php?error=database_error");
    exit();
} catch (Exception $e) {
    // Error general
    error_log("Error general en login.php: " . $e->getMessage());
    header("Location: ../index.php?error=general_error");
    exit();
}
?>