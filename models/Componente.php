<?php
/**
 * Modelo Componente - Gestiona la información y lógica relacionada con los componentes de hardware/software
 */
class Componente {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "COMPONENTE";
    
    // Propiedades del objeto
    public $id;
    public $nombre;
    public $descripcion;
    public $tipo; // HW = Hardware, SW = Software
    public $categoria; // CPU, RAM, HDD, PSU, etc. para HW / SO, Ofimática, etc. para SW
    public $fabricante;
    public $modelo;
    public $created_by;
    public $created_date;
    public $modified_by;
    public $modified_date;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los componentes con filtros opcionales
     * @param array $filtros Array asociativo con los filtros a aplicar
     * @return PDOStatement Resultado de la consulta
     */
    public function getAll($filtros = []) {
        // Construir la consulta base
        $query = "SELECT ID, Nombre, Descripcion, Tipo, Categoria, Fabricante, Modelo 
                  FROM " . $this->table_name . " 
                  WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if(isset($filtros['tipo']) && !empty($filtros['tipo'])) {
            $query .= " AND Tipo = ?";
            $params[] = $filtros['tipo'];
        }
        
        if(isset($filtros['categoria']) && !empty($filtros['categoria'])) {
            $query .= " AND Categoria = ?";
            $params[] = $filtros['categoria'];
        }
        
        if(isset($filtros['fabricante']) && !empty($filtros['fabricante'])) {
            $query .= " AND Fabricante = ?";
            $params[] = $filtros['fabricante'];
        }
        
        if(isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $query .= " AND (Nombre LIKE ? OR Descripcion LIKE ? OR Modelo LIKE ?)";
            $busqueda = "%" . $filtros['busqueda'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        // Ordenar los resultados
        $query .= " ORDER BY Tipo, Categoria, Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute($params);
        
        return $stmt;
    }
    
    /**
     * Obtener un componente por su ID
     * @param integer $id ID del componente
     * @return boolean True si se encontró el componente
     */
    public function getById($id) {
        $query = "SELECT ID, Nombre, Descripcion, Tipo, Categoria, Fabricante, Modelo,
                         CreatedBy, CreatedDate, ModifiedBy, ModifiedDate
                  FROM " . $this->table_name . " 
                  WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id]);
        
        // Verificar si se encontró el componente
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Asignar valores a las propiedades
            $this->id = $row['ID'];
            $this->nombre = $row['Nombre'];
            $this->descripcion = $row['Descripcion'];
            $this->tipo = $row['Tipo'];
            $this->categoria = $row['Categoria'];
            $this->fabricante = $row['Fabricante'];
            $this->modelo = $row['Modelo'];
            $this->created_by = $row['CreatedBy'];
            $this->created_date = $row['CreatedDate'];
            $this->modified_by = $row['ModifiedBy'];
            $this->modified_date = $row['ModifiedDate'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear un nuevo componente
     * @return boolean True si se creó correctamente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (Nombre, Descripcion, Tipo, Categoria, Fabricante, Modelo, CreatedBy, CreatedDate) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->nombre,
            $this->descripcion,
            $this->tipo,
            $this->categoria,
            $this->fabricante,
            $this->modelo,
            $this->created_by
        ]);
    }
    
    /**
     * Actualizar un componente existente
     * @return boolean True si se actualizó correctamente
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET Nombre = ?, Descripcion = ?, Tipo = ?, Categoria = ?,
                      Fabricante = ?, Modelo = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->nombre,
            $this->descripcion,
            $this->tipo,
            $this->categoria,
            $this->fabricante,
            $this->modelo,
            $this->modified_by,
            $this->id
        ]);
    }
    
    /**
     * Eliminar un componente
     * @param integer $id ID del componente a eliminar
     * @return boolean True si se eliminó correctamente
     */
    public function delete($id) {
        // Primero verificar si hay CIs relacionados
        $ciQuery = "SELECT COUNT(*) as total FROM CI_COMPONENTE WHERE ID_Componente = ?";
        $ciStmt = $this->conn->prepare($ciQuery);
        $ciStmt->execute([$id]);
        $result = $ciStmt->fetch(PDO::FETCH_ASSOC);
        
        if($result['total'] > 0) {
            // No se puede eliminar porque hay CIs relacionados
            return false;
        }
        
        // Si no hay relaciones, eliminar el componente
        $query = "DELETE FROM " . $this->table_name . " WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id]);
    }
    
    /**
     * Obtener categorías de componentes de hardware
     * @return array Lista de categorías de hardware
     */
    public function getCategoriasHardware() {
        return [
            'CPU' => 'Procesador',
            'RAM' => 'Memoria RAM',
            'HDD' => 'Disco Duro',
            'SSD' => 'Disco SSD',
            'PSU' => 'Fuente de Poder',
            'GPU' => 'Tarjeta Gráfica',
            'MB' => 'Placa Madre',
            'CASE' => 'Gabinete',
            'MONITOR' => 'Monitor',
            'KB' => 'Teclado',
            'MOUSE' => 'Mouse',
            'OTRO' => 'Otro'
        ];
    }
    
    /**
     * Obtener categorías de componentes de software
     * @return array Lista de categorías de software
     */
    public function getCategoriasSoftware() {
        return [
            'OS' => 'Sistema Operativo',
            'OFFICE' => 'Suite Ofimática',
            'ANTIVIRUS' => 'Antivirus',
            'DEVEL' => 'Herramientas de Desarrollo',
            'ERP' => 'Sistema ERP',
            'GRAPHICS' => 'Edición Gráfica',
            'DB' => 'Base de Datos',
            'UTILITY' => 'Utilidades',
            'BROWSER' => 'Navegador Web',
            'OTRO' => 'Otro'
        ];
    }
    
    /**
     * Obtener componentes por tipo
     * @param string $tipo Tipo de componente (HW o SW)
     * @return PDOStatement Resultado de la consulta
     */
    public function getByTipo($tipo) {
        $query = "SELECT ID, Nombre, Descripcion, Categoria, Fabricante, Modelo 
                  FROM " . $this->table_name . " 
                  WHERE Tipo = ? 
                  ORDER BY Categoria, Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$tipo]);
        
        return $stmt;
    }
    
    /**
     * Obtener componentes por categoría
     * @param string $categoria Categoría del componente
     * @return PDOStatement Resultado de la consulta
     */
    public function getByCategoria($categoria) {
        $query = "SELECT ID, Nombre, Descripcion, Tipo, Fabricante, Modelo 
                  FROM " . $this->table_name . " 
                  WHERE Categoria = ? 
                  ORDER BY Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$categoria]);
        
        return $stmt;
    }
}