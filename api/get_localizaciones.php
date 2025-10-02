<?php
// Incluir configuración de base de datos
require_once '../config/database.php';

// Verificar si hay una sesión activa
session_start();
if (!isset($_SESSION['user_id'])) {
    // Devolver error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar si se proporcionó el ID del edificio
if (!isset($_GET['edificio_id']) || empty($_GET['edificio_id'])) {
    // Devolver error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de edificio no proporcionado']);
    exit;
}

// Obtener el ID del edificio
$edificioId = $_GET['edificio_id'];

try {
    // Conexión a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Preparar la consulta
    $query = "SELECT ID, Nombre, NumPlanta, Ubicacion 
              FROM LOCALIZACION 
              WHERE ID_Edificio = ? 
              ORDER BY NumPlanta, Nombre";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$edificioId]);
    
    // Obtener resultados
    $localizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devolver como JSON
    header('Content-Type: application/json');
    echo json_encode($localizaciones);
    
} catch (PDOException $e) {
    // Devolver error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}