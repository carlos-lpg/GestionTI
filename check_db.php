<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $test_id = 5;
    
    // Consulta simplificada - solo usar la tabla PROBLEMA
    echo "<h2>Verificando PROBLEMA tabla</h2>";
    $query = "SELECT * FROM PROBLEMA WHERE ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$test_id]);
    
    if ($stmt->rowCount() > 0) {
        echo "<p>El problema con ID=$test_id existe en la base de datos.</p>";
        echo "<pre>";
        print_r($stmt->fetch(PDO::FETCH_ASSOC));
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>No existe un problema con ID=$test_id en la base de datos.</p>";
        
        // Mostrar todos los IDs disponibles
        echo "<h3>IDs disponibles en la tabla PROBLEMA:</h3>";
        $query_all = "SELECT ID FROM PROBLEMA ORDER BY ID";
        $stmt_all = $conn->prepare($query_all);
        $stmt_all->execute();
        
        if ($stmt_all->rowCount() > 0) {
            echo "<ul>";
            while ($row = $stmt_all->fetch(PDO::FETCH_ASSOC)) {
                echo "<li>ID: " . $row['ID'] . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No hay registros en la tabla PROBLEMA.</p>";
        }
    }
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>