<?php
/**
 * Modelo CIComponente - Gestiona la relación entre CIs y sus componentes
 */
class CIComponente {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "CI_COMPONENTE";
    
    // Propiedades del objeto
    public $id;
    public $id_ci;
    public $id_componente;
    public $cantidad;
    public $notas;
    public $created_by;
    public $created_date;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los componentes asociados a un CI
     * @param integer $ci_id ID del elemento de configuración
     * @return PDOStatement Resultado de la consulta
     */
    public function getComponentesByCi($ci_id) {
        $query = "SELECT cc.ID, cc.ID_CI, cc.ID_Componente, cc.Cantidad, cc.Notas, 
                         c.Nombre, c.Descripcion, c.Tipo, c.Categoria, c.Fabricante, c.Modelo
                  FROM " . $this->table_name . " cc
                  JOIN COMPONENTE c ON cc.ID_Componente = c.ID
                  WHERE cc.ID_CI = ?
                  ORDER BY c.Tipo, c.Categoria, c.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$ci_id]);
        
        return $stmt;
    }
    
    /**
     * Obtener componentes por tipo para un CI específico
     * @param integer $ci_id ID del elemento de configuración
     * @param string $tipo Tipo de componente (HW o SW)
     * @return PDOStatement Resultado de la consulta
     */
    public function getComponentesByCiYTipo($ci_id, $tipo) {
        $query = "SELECT cc.ID, cc.ID_CI, cc.ID_Componente, cc.Cantidad, cc.Notas, 
                         c.Nombre, c.Descripcion, c.Tipo, c.Categoria, c.Fabricante, c.Modelo
                  FROM " . $this->table_name . " cc
                  JOIN COMPONENTE c ON cc.ID_Componente = c.ID
                  WHERE cc.ID_CI = ? AND c.Tipo = ?
                  ORDER BY c.Categoria, c.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros directamente
        $stmt->bindParam(1, $ci_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tipo, PDO::PARAM_STR);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    /**
     * Guardar la asociación entre un CI y un componente
     * @return boolean True si se guardó correctamente
     */
    public function save() {
        // Verificar si ya existe esta asociación
        $check_query = "SELECT ID FROM " . $this->table_name . " 
                        WHERE ID_CI = ? AND ID_Componente = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->execute([$this->id_ci, $this->id_componente]);
        
        if ($check_stmt->rowCount() > 0) {
            // Ya existe, entonces actualizamos
            $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['ID'];
            
            $query = "UPDATE " . $this->table_name . " 
                      SET Cantidad = ?, Notas = ? 
                      WHERE ID = ?";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$this->cantidad, $this->notas, $this->id]);
        } else {
            // No existe, entonces creamos
            $query = "INSERT INTO " . $this->table_name . " 
                      (ID_CI, ID_Componente, Cantidad, Notas, CreatedBy, CreatedDate) 
                      VALUES (?, ?, ?, ?, ?, GETDATE())";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                $this->id_ci, 
                $this->id_componente,
                $this->cantidad,
                $this->notas,
                $this->created_by
            ]);
        }
    }
    
    /**
     * Eliminar la asociación entre un CI y un componente
     * @param integer $id ID de la asociación a eliminar
     * @return boolean True si se eliminó correctamente
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id]);
    }
    
    /**
     * Eliminar todas las asociaciones de un CI
     * @param integer $ci_id ID del CI
     * @return boolean True si se eliminaron correctamente
     */
    public function deleteAllByCi($ci_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE ID_CI = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$ci_id]);
    }
    
    /**
     * Obtener detalles de una asociación específica
     * @param integer $id ID de la asociación
     * @return boolean True si se encontró la asociación
     */
    public function getById($id) {
        $query = "SELECT cc.ID, cc.ID_CI, cc.ID_Componente, cc.Cantidad, cc.Notas, cc.CreatedBy, cc.CreatedDate
                  FROM " . $this->table_name . " cc
                  WHERE cc.ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id]);
        
        // Verificar si se encontró la asociación
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Asignar valores a las propiedades
            $this->id = $row['ID'];
            $this->id_ci = $row['ID_CI'];
            $this->id_componente = $row['ID_Componente'];
            $this->cantidad = $row['Cantidad'];
            $this->notas = $row['Notas'];
            $this->created_by = $row['CreatedBy'];
            $this->created_date = $row['CreatedDate'];
            
            return true;
        }
        
        return false;
    }
}