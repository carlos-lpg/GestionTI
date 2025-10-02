<?php
/**
 * Modelo CI - Gestiona la información y lógica relacionada con los elementos de configuración
 */
class CI {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "CI";
    
    // Propiedades del objeto
    public $id;
    public $nombre;
    public $descripcion;
    public $num_serie;
    public $fecha_adquisicion;
    public $id_tipo_ci;
    public $id_localizacion;
    public $id_encargado;
    public $id_proveedor;
    public $created_by;
    public $created_date;
    public $modified_by;
    public $modified_date;
    
    // Propiedades relacionadas
    public $tipo_ci;
    public $localizacion;
    public $edificio;
    public $categoria_ubicacion;
    public $encargado;
    public $proveedor;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los elementos de configuración con filtros opcionales
     * @param array $filtros Array asociativo con los filtros a aplicar
     * @return PDOStatement Resultado de la consulta
     */
    public function getAll($filtros = []) {
        // Construir la consulta base
        $query = "SELECT ci.ID, ci.Nombre, ci.Descripcion, ci.NumSerie, ci.FechaAdquisicion, 
                         t.Nombre as TipoCI, p.Nombre as Proveedor, e.Nombre as Encargado,
                         l.Nombre as Localizacion, ed.Nombre as Edificio, cat.Nombre as CategoriaUbicacion
                  FROM " . $this->table_name . " ci
                  LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                  LEFT JOIN PROVEEDOR p ON ci.ID_Proveedor = p.ID
                  LEFT JOIN EMPLEADO e ON ci.ID_Encargado = e.ID
                  LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
                  LEFT JOIN EDIFICIO ed ON l.ID_Edificio = ed.ID
                  LEFT JOIN CATEGORIA_UBICACION cat ON ed.ID_CategoriaUbicacion = cat.ID
                  WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if(isset($filtros['tipo_ci']) && !empty($filtros['tipo_ci'])) {
            $query .= " AND ci.ID_TipoCI = ?";
            $params[] = $filtros['tipo_ci'];
        }
        
        if(isset($filtros['edificio']) && !empty($filtros['edificio'])) {
            $query .= " AND ed.ID = ?";
            $params[] = $filtros['edificio'];
        }
        
        if(isset($filtros['localizacion']) && !empty($filtros['localizacion'])) {
            $query .= " AND l.ID = ?";
            $params[] = $filtros['localizacion'];
        }
        
        if(isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $query .= " AND (ci.Nombre LIKE ? OR ci.NumSerie LIKE ? OR ci.Descripcion LIKE ?)";
            $busqueda = "%" . $filtros['busqueda'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        // Ordenar los resultados
        $query .= " ORDER BY ci.ID DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute($params);
        
        return $stmt;
    }
    
    /**
     * Obtener un elemento de configuración por su ID
     * @param integer $id ID del elemento de configuración
     * @return boolean True si se encontró el elemento
     */
    public function getById($id) {
        $query = "SELECT ci.ID, ci.Nombre, ci.Descripcion, ci.NumSerie, ci.FechaAdquisicion, 
                         ci.ID_TipoCI, ci.ID_Localizacion, ci.ID_Encargado, ci.ID_Proveedor,
                         ci.CreatedBy, ci.CreatedDate, ci.ModifiedBy, ci.ModifiedDate,
                         t.Nombre as TipoCI, p.Nombre as Proveedor, e.Nombre as Encargado,
                         l.Nombre as Localizacion, l.NumPlanta, ed.Nombre as Edificio, 
                         cat.Nombre as CategoriaUbicacion
                  FROM " . $this->table_name . " ci
                  LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                  LEFT JOIN PROVEEDOR p ON ci.ID_Proveedor = p.ID
                  LEFT JOIN EMPLEADO e ON ci.ID_Encargado = e.ID
                  LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
                  LEFT JOIN EDIFICIO ed ON l.ID_Edificio = ed.ID
                  LEFT JOIN CATEGORIA_UBICACION cat ON ed.ID_CategoriaUbicacion = cat.ID
                  WHERE ci.ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id]);
        
        // Verificar si se encontró el elemento
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Asignar valores a las propiedades
            $this->id = $row['ID'];
            $this->nombre = $row['Nombre'];
            $this->descripcion = $row['Descripcion'];
            $this->num_serie = $row['NumSerie'];
            $this->fecha_adquisicion = $row['FechaAdquisicion'];
            $this->id_tipo_ci = $row['ID_TipoCI'];
            $this->id_localizacion = $row['ID_Localizacion'];
            $this->id_encargado = $row['ID_Encargado'];
            $this->id_proveedor = $row['ID_Proveedor'];
            $this->created_by = $row['CreatedBy'];
            $this->created_date = $row['CreatedDate'];
            $this->modified_by = $row['ModifiedBy'];
            $this->modified_date = $row['ModifiedDate'];
            
            // Propiedades relacionadas
            $this->tipo_ci = $row['TipoCI'];
            $this->localizacion = $row['Localizacion'];
            $this->edificio = $row['Edificio'];
            $this->categoria_ubicacion = $row['CategoriaUbicacion'];
            $this->encargado = $row['Encargado'];
            $this->proveedor = $row['Proveedor'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear un nuevo elemento de configuración
     * @return boolean True si se creó correctamente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (Nombre, Descripcion, NumSerie, FechaAdquisicion, ID_TipoCI, 
                   ID_Localizacion, ID_Encargado, ID_Proveedor, CreatedBy, CreatedDate) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->nombre,
            $this->descripcion,
            $this->num_serie,
            $this->fecha_adquisicion,
            $this->id_tipo_ci,
            $this->id_localizacion,
            $this->id_encargado,
            $this->id_proveedor,
            $this->created_by
        ]);
    }
    
    /**
     * Actualizar un elemento de configuración existente
     * @return boolean True si se actualizó correctamente
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET Nombre = ?, Descripcion = ?, NumSerie = ?, FechaAdquisicion = ?, 
                      ID_TipoCI = ?, ID_Localizacion = ?, ID_Encargado = ?, ID_Proveedor = ?,
                      ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->nombre,
            $this->descripcion,
            $this->num_serie,
            $this->fecha_adquisicion,
            $this->id_tipo_ci,
            $this->id_localizacion,
            $this->id_encargado,
            $this->id_proveedor,
            $this->modified_by,
            $this->id
        ]);
    }
    
    /**
     * Eliminar un elemento de configuración
     * @param integer $id ID del elemento a eliminar
     * @return boolean True si se eliminó correctamente
     */
    public function delete($id) {
        // Primero verificar si hay incidencias relacionadas
        $incidenciasQuery = "SELECT COUNT(*) as total FROM INCIDENCIA WHERE ID_CI = ?";
        $incStmt = $this->conn->prepare($incidenciasQuery);
        $incStmt->execute([$id]);
        $result = $incStmt->fetch(PDO::FETCH_ASSOC);
        
        if($result['total'] > 0) {
            // No se puede eliminar porque hay incidencias relacionadas
            return false;
        }
        
        // También verificar relaciones con otros CIs
        $relacionesQuery = "SELECT COUNT(*) as total FROM RELACION_CI WHERE ID_CI_Padre = ? OR ID_CI_Hijo = ?";
        $relStmt = $this->conn->prepare($relacionesQuery);
        $relStmt->execute([$id, $id]);
        $result = $relStmt->fetch(PDO::FETCH_ASSOC);
        
        if($result['total'] > 0) {
            // Eliminar primero las relaciones
            $deleteRelacionesQuery = "DELETE FROM RELACION_CI WHERE ID_CI_Padre = ? OR ID_CI_Hijo = ?";
            $deleteRelStmt = $this->conn->prepare($deleteRelacionesQuery);
            $deleteRelStmt->execute([$id, $id]);
        }
        
        // Finalmente eliminar el CI
        $query = "DELETE FROM " . $this->table_name . " WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id]);
    }
    
    /**
     * Obtener incidencias relacionadas con un CI
     * @param integer $ci_id ID del elemento de configuración
     * @return PDOStatement Resultado de la consulta
     */
    public function getIncidencias($ci_id) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                         e.Nombre as Tecnico, s.Descripcion as Estado, p.Descripcion as Prioridad
                  FROM INCIDENCIA i
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  WHERE i.ID_CI = ?
                  ORDER BY i.FechaInicio DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$ci_id]);
        
        return $stmt;
    }
    
    /**
     * Obtener relaciones de un CI con otros elementos
     * @param integer $ci_id ID del elemento de configuración
     * @return PDOStatement Resultado de la consulta
     */
    public function getRelaciones($ci_id) {
        $query = "SELECT r.ID, r.TipoRelacion, 
                         ci_hijo.ID as ID_Hijo, ci_hijo.Nombre as Nombre_Hijo, t_hijo.Nombre as Tipo_Hijo,
                         ci_padre.ID as ID_Padre, ci_padre.Nombre as Nombre_Padre, t_padre.Nombre as Tipo_Padre
                  FROM RELACION_CI r
                  LEFT JOIN CI ci_hijo ON r.ID_CI_Hijo = ci_hijo.ID
                  LEFT JOIN CI ci_padre ON r.ID_CI_Padre = ci_padre.ID
                  LEFT JOIN TIPO_CI t_hijo ON ci_hijo.ID_TipoCI = t_hijo.ID
                  LEFT JOIN TIPO_CI t_padre ON ci_padre.ID_TipoCI = t_padre.ID
                  WHERE r.ID_CI_Hijo = ? OR r.ID_CI_Padre = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$ci_id, $ci_id]);
        
        return $stmt;
    }
    
    /**
     * Agregar una relación entre dos CIs
     * @param integer $id_padre ID del CI padre
     * @param integer $id_hijo ID del CI hijo
     * @param string $tipo_relacion Tipo de relación entre los CIs
     * @param integer $created_by ID del usuario que crea la relación
     * @return boolean True si se creó correctamente
     */
    public function agregarRelacion($id_padre, $id_hijo, $tipo_relacion, $created_by) {
        $query = "INSERT INTO RELACION_CI (ID_CI_Padre, ID_CI_Hijo, TipoRelacion, CreatedBy, CreatedDate) 
                  VALUES (?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id_padre, $id_hijo, $tipo_relacion, $created_by]);
    }
    
    /**
     * Eliminar una relación entre CIs
     * @param integer $relacion_id ID de la relación
     * @return boolean True si se eliminó correctamente
     */
    public function eliminarRelacion($relacion_id) {
        $query = "DELETE FROM RELACION_CI WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$relacion_id]);
    }
    
    /**
     * Obtener todos los tipos de CI
     * @return PDOStatement Resultado de la consulta
     */
    public function getTipos() {
        $query = "SELECT ID, Nombre, Descripcion FROM TIPO_CI ORDER BY Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los edificios
     * @return PDOStatement Resultado de la consulta
     */
    public function getEdificios() {
        $query = "SELECT e.ID, e.Nombre, e.Ubicacion, c.Nombre as Categoria 
                  FROM EDIFICIO e
                  LEFT JOIN CATEGORIA_UBICACION c ON e.ID_CategoriaUbicacion = c.ID
                  ORDER BY e.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener localizaciones por edificio
     * @param integer $edificio_id ID del edificio
     * @return PDOStatement Resultado de la consulta
     */
    public function getLocalizaciones($edificio_id) {
        $query = "SELECT ID, Nombre, NumPlanta, Ubicacion 
                  FROM LOCALIZACION 
                  WHERE ID_Edificio = ? 
                  ORDER BY NumPlanta, Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$edificio_id]);
        
        return $stmt;
    }
    
    /**
     * Obtener todos los empleados (potenciales encargados)
     * @return PDOStatement Resultado de la consulta
     */
    public function getEmpleados() {
        $query = "SELECT e.ID, e.Nombre, e.Email, r.Nombre as Rol 
                  FROM EMPLEADO e
                  LEFT JOIN ROL r ON e.ID_Rol = r.ID
                  ORDER BY e.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los proveedores
     * @return PDOStatement Resultado de la consulta
     */
    public function getProveedores() {
        $query = "SELECT ID, Nombre, RFC, Email, Telefono 
                  FROM PROVEEDOR 
                  ORDER BY Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Contar CIs por tipo (para dashboard)
     * @return PDOStatement Resultado de la consulta
     */
    public function contarPorTipo() {
        $query = "SELECT t.Nombre as Tipo, COUNT(ci.ID) as Total 
                  FROM TIPO_CI t
                  LEFT JOIN CI ci ON t.ID = ci.ID_TipoCI
                  GROUP BY t.Nombre
                  ORDER BY Total DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Contar CIs por ubicación (para dashboard)
     * @return PDOStatement Resultado de la consulta
     */
    public function contarPorUbicacion() {
        $query = "SELECT cat.Nombre as Categoria, COUNT(ci.ID) as Total 
                  FROM CATEGORIA_UBICACION cat
                  JOIN EDIFICIO e ON cat.ID = e.ID_CategoriaUbicacion
                  JOIN LOCALIZACION l ON e.ID = l.ID_Edificio
                  LEFT JOIN CI ci ON l.ID = ci.ID_Localizacion
                  GROUP BY cat.Nombre
                  ORDER BY Total DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
}