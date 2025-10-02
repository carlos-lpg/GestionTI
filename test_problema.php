<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

$test_id = 0;

echo "<h1>Prueba con consulta directa para ID=$test_id</h1>";

try {
    // Consulta 1: Sin esquema
    $query1 = "SELECT * FROM PROBLEMA WHERE ID = ?";
    $stmt1 = $conn->prepare($query1);
    $stmt1->execute([$test_id]);
    
    echo "<h2>Consulta sin esquema:</h2>";
    echo "<p>SQL: $query1</p>";
    
    if ($stmt1->rowCount() > 0) {
        echo "<p>Datos encontrados:</p>";
        echo "<pre>";
        print_r($stmt1->fetch(PDO::FETCH_ASSOC));
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>No se encontraron datos</p>";
    }
    
    // Consulta 2: Con esquema
    $query2 = "SELECT * FROM [ControlIncidenciasDB].[dbo].[PROBLEMA] WHERE ID = ?";
    $stmt2 = $conn->prepare($query2);
    $stmt2->execute([$test_id]);
    
    echo "<h2>Consulta con esquema:</h2>";
    echo "<p>SQL: $query2</p>";
    
    if ($stmt2->rowCount() > 0) {
        echo "<p>Datos encontrados:</p>";
        echo "<pre>";
        print_r($stmt2->fetch(PDO::FETCH_ASSOC));
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>No se encontraron datos</p>";
    }
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>