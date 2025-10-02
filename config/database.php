<?php
/**
 * config/database.php
 * Configuración de la conexión a la base de datos
 */
class Database {
    // Configuración exacta basada en tu instancia SQL Server
    private $host = "localhost,1433";
    private $db_name = "ControlIncidenciasDB";
    private $username = "carlos_padilla";
    private $password = "123456789";
    public $conn;
    
    // Obtener la conexión a la base de datos
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Intenta conexión con los parámetros exactos de tu instancia
            $this->conn = new PDO(
                "sqlsrv:Server=" . $this->host . ";Database=" . $this->db_name,
                $this->username,
                $this->password
            );
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Error de conexión: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}