<?php
// Incluir configuración de base de datos
require_once 'config/database.php';

// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Prueba simple de consulta</h2>";

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Error: No se pudo conectar a la base de datos.");
}

// Prueba de consulta simple
$username = "admin";
$query = "SELECT * FROM USUARIO WHERE Username = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$username]);

// Intentar usar fetchAll en lugar de rowCount
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Usuarios encontrados: " . count($users) . "<br>";

if (count($users) > 0) {
    echo "Usuario encontrado: " . $users[0]['Username'] . "<br>";
    echo "Contraseña: " . $users[0]['Password'] . "<br>";
} else {
    echo "Usuario no encontrado<br>";
}

// Vamos a hacer una versión simplificada de login
function loginSimple($username, $password) {
    global $conn;
    
    $query = "SELECT * FROM USUARIO WHERE Username = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$username]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if ($password == $user['Password']) {
            return [
                'success' => true,
                'message' => 'Login exitoso',
                'user' => $user
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Contraseña incorrecta'
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'Usuario no encontrado'
        ];
    }
}

echo "<h2>Prueba de función de login simplificada</h2>";
$test_user = "admin";
$test_password = "admin123";
$result = loginSimple($test_user, $test_password);

echo "Resultado: " . ($result['success'] ? "ÉXITO" : "FALLO") . "<br>";
echo "Mensaje: " . $result['message'] . "<br>";
if (isset($result['user'])) {
    echo "Usuario ID: " . $result['user']['ID'] . "<br>";
    echo "Username: " . $result['user']['Username'] . "<br>";
}
?>