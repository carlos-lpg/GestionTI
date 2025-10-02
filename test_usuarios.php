<?php
// Incluir configuración de base de datos
require_once 'config/database.php';

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Error: No se pudo conectar a la base de datos.");
}

echo "<h2>Diagnóstico de Usuarios en la Base de Datos</h2>";

// Consulta para verificar si existen usuarios
$query_usuarios = "SELECT * FROM USUARIO";
$stmt_usuarios = $conn->query($query_usuarios);
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Usuarios encontrados: " . count($usuarios) . "</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Password</th><th>Estado</th><th>ID_Empleado</th><th>ID_Rol</th></tr>";

foreach ($usuarios as $user) {
    echo "<tr>";
    echo "<td>" . $user['ID'] . "</td>";
    echo "<td>" . $user['Username'] . "</td>";
    echo "<td>" . $user['Password'] . "</td>";
    echo "<td>" . $user['Estado'] . "</td>";
    echo "<td>" . $user['ID_Empleado'] . "</td>";
    echo "<td>" . $user['ID_Rol'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Consulta para verificar los roles
echo "<h3>Roles encontrados:</h3>";
$query_roles = "SELECT * FROM ROL";
$stmt_roles = $conn->query($query_roles);
$roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nombre</th></tr>";
foreach ($roles as $rol) {
    echo "<tr>";
    echo "<td>" . $rol['ID'] . "</td>";
    echo "<td>" . $rol['Nombre'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Consulta para verificar los empleados
echo "<h3>Empleados encontrados:</h3>";
$query_empleados = "SELECT * FROM EMPLEADO";
$stmt_empleados = $conn->query($query_empleados);
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>ID_Rol</th></tr>";
foreach ($empleados as $emp) {
    echo "<tr>";
    echo "<td>" . $emp['ID'] . "</td>";
    echo "<td>" . $emp['Nombre'] . "</td>";
    echo "<td>" . $emp['Email'] . "</td>";
    echo "<td>" . $emp['ID_Rol'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Prueba de login directo para usuario 'admin'
echo "<h3>Prueba de login para usuario 'admin':</h3>";
$test_username = "admin";
$test_password = "admin123"; // Cambia esto si la contraseña es diferente

// 1. Consulta sin restricciones
$query1 = "SELECT * FROM USUARIO WHERE Username = '$test_username'";
$stmt1 = $conn->query($query1);
$result1 = $stmt1->fetch(PDO::FETCH_ASSOC);

echo "Consulta 1 (sin restricciones): ";
if ($result1) {
    echo "ÉXITO - Usuario encontrado<br>";
} else {
    echo "ERROR - Usuario no encontrado<br>";
}

// 2. Consulta con estado
$query2 = "SELECT * FROM USUARIO WHERE Username = '$test_username' AND Estado = 1";
$stmt2 = $conn->query($query2);
$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "Consulta 2 (con estado): ";
if ($result2) {
    echo "ÉXITO - Usuario encontrado<br>";
} else {
    echo "ERROR - Usuario no encontrado<br>";
}

// 3. Consulta con JOIN simple
$query3 = "SELECT u.* FROM USUARIO u 
           INNER JOIN ROL r ON u.ID_Rol = r.ID 
           WHERE u.Username = '$test_username'";
$stmt3 = $conn->query($query3);
$result3 = $stmt3->fetch(PDO::FETCH_ASSOC);

echo "Consulta 3 (con JOIN a ROL): ";
if ($result3) {
    echo "ÉXITO - Usuario encontrado<br>";
} else {
    echo "ERROR - Usuario no encontrado<br>";
}

// 4. Consulta con ambos JOIN
$query4 = "SELECT u.* FROM USUARIO u 
           INNER JOIN ROL r ON u.ID_Rol = r.ID 
           INNER JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
           WHERE u.Username = '$test_username'";
$stmt4 = $conn->query($query4);
$result4 = $stmt4->fetch(PDO::FETCH_ASSOC);

echo "Consulta 4 (con ambos JOIN): ";
if ($result4) {
    echo "ÉXITO - Usuario encontrado<br>";
} else {
    echo "ERROR - Usuario no encontrado<br>";
}

echo "<h3>Verificación de integridad de relaciones:</h3>";
foreach ($usuarios as $user) {
    echo "Usuario: " . $user['Username'] . "<br>";
    
    // Verificar si el ID_Rol existe en la tabla ROL
    $rol_exists = false;
    foreach ($roles as $rol) {
        if ($rol['ID'] == $user['ID_Rol']) {
            $rol_exists = true;
            break;
        }
    }
    
    echo "- El Rol ID " . $user['ID_Rol'] . " " . ($rol_exists ? "EXISTE" : "NO EXISTE") . " en la tabla ROL<br>";
    
    // Verificar si el ID_Empleado existe en la tabla EMPLEADO
    $emp_exists = false;
    foreach ($empleados as $emp) {
        if ($emp['ID'] == $user['ID_Empleado']) {
            $emp_exists = true;
            break;
        }
    }
    
    echo "- El Empleado ID " . $user['ID_Empleado'] . " " . ($emp_exists ? "EXISTE" : "NO EXISTE") . " en la tabla EMPLEADO<br>";
    echo "<hr>";
}
?>