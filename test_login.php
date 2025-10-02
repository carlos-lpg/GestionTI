<?php
// Incluir configuración de base de datos
require_once 'config/database.php';
require_once 'models/Usuario.php';

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Función de depuración para imprimir todos los usuarios
function listarUsuarios($conexion) {
    $query = "SELECT * FROM USUARIO";
    $stmt = $conexion->query($query);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Usuarios en la base de datos:\n";
    foreach ($usuarios as $usuario) {
        echo "ID: {$usuario['ID']}, ";
        echo "Username: {$usuario['Username']}, ";
        echo "Password: {$usuario['Password']}, ";
        echo "Estado: {$usuario['Estado']}\n";
    }
}

// Función de prueba de login
function probarLogin($conexion, $username, $password) {
    $query = "SELECT u.*, r.Nombre as rol_nombre, e.Nombre as empleado_nombre 
              FROM USUARIO u
              JOIN ROL r ON u.ID_Rol = r.ID
              JOIN EMPLEADO e ON u.ID_Empleado = e.ID
              WHERE u.Username = :username";
    
    $stmt = $conexion->prepare($query);
    $stmt->execute(['username' => $username]);
    
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo "Usuario no encontrado\n";
        return false;
    }
    
    echo "Usuario encontrado:\n";
    echo "ID: {$usuario['ID']}\n";
    echo "Username: {$usuario['Username']}\n";
    echo "Password en BD: {$usuario['Password']}\n";
    echo "Password ingresada: $password\n";
    echo "Estado: {$usuario['Estado']}\n";
    echo "Rol: {$usuario['rol_nombre']}\n";
    
    if ($usuario['Estado'] != 1) {
        echo "El usuario está inactivo\n";
        return false;
    }
    
    if ($password == $usuario['Password']) {
        echo "Contraseña correcta\n";
        return true;
    } else {
        echo "Contraseña incorrecta\n";
        return false;
    }
}

// Mostrar usuarios
listarUsuarios($conn);

// Probar login con usuario admin
echo "\nProbando login para 'admin':\n";
probarLogin($conn, 'admin', 'admin123');
?>