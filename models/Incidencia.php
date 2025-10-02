<?php
/**
 * Modelo Incidencia - Gestiona la información y lógica relacionada con las incidencias
 */
class Incidencia {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "INCIDENCIA";
    
    // Propiedades del objeto
    public $id;
    public $descripcion;
    public $fecha_inicio;
    public $fecha_terminacion;
    public $id_prioridad;
    public $id_ci;
    public $id_tecnico;
    public $id_stat;
    public $created_by;
    public $created_date;
    public $modified_by;
    public $modified_date;
    
    // Propiedades relacionadas
    public $prioridad;
    public $estado;
    public $ci_nombre;
    public $ci_tipo;
    public $tecnico_nombre;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todas las incidencias con filtros opcionales
     * @param array $filtros Array asociativo con los filtros a aplicar
     * @return PDOStatement Resultado de la consulta
     */
    public function getAll($filtros = []) {
        // Construir la consulta base
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                         p.Descripcion as Prioridad, p.ID as ID_Prioridad,
                         s.Descripcion as Estado, s.ID as ID_Estado,
                         ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                         e.Nombre as Tecnico_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if(isset($filtros['estado']) && !empty($filtros['estado'])) {
            $query .= " AND i.ID_Stat = ?";
            $params[] = $filtros['estado'];
        }
        
        if(isset($filtros['prioridad']) && !empty($filtros['prioridad'])) {
            $query .= " AND i.ID_Prioridad = ?";
            $params[] = $filtros['prioridad'];
        }
        
        if(isset($filtros['tecnico']) && !empty($filtros['tecnico'])) {
            $query .= " AND i.ID_Tecnico = ?";
            $params[] = $filtros['tecnico'];
        }
        
        if(isset($filtros['ci']) && !empty($filtros['ci'])) {
            $query .= " AND i.ID_CI = ?";
            $params[] = $filtros['ci'];
        }
        
        if(isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $query .= " AND (i.Descripcion LIKE ? OR ci.Nombre LIKE ? OR e.Nombre LIKE ?)";
            $busqueda = "%" . $filtros['busqueda'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        // Filtrar por incidencias reportadas por un usuario específico
        if(isset($filtros['creado_por']) && !empty($filtros['creado_por'])) {
            $query .= " AND i.CreatedBy = ?";
            $params[] = $filtros['creado_por'];
        }
        
        // Ordenar los resultados (más recientes primero)
        $query .= " ORDER BY i.FechaInicio DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute($params);
        
        return $stmt;
    }
    
    /**
     * Obtener una incidencia por su ID
     * @param integer $id ID de la incidencia
     * @return boolean True si se encontró la incidencia
     */
    public function getById($id) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, 
                         i.ID_Prioridad, i.ID_CI, i.ID_Tecnico, i.ID_Stat,
                         i.CreatedBy, i.CreatedDate, i.ModifiedBy, i.ModifiedDate,
                         p.Descripcion as Prioridad, s.Descripcion as Estado,
                         ci.Nombre as CI_Nombre, t.Nombre as CI_Tipo,
                         e.Nombre as Tecnico_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  WHERE i.ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id]);
        
        // Verificar si se encontró la incidencia
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Asignar valores a las propiedades
            $this->id = $row['ID'];
            $this->descripcion = $row['Descripcion'];
            $this->fecha_inicio = $row['FechaInicio'];
            $this->fecha_terminacion = $row['FechaTerminacion'];
            $this->id_prioridad = $row['ID_Prioridad'];
            $this->id_ci = $row['ID_CI'];
            $this->id_tecnico = $row['ID_Tecnico'];
            $this->id_stat = $row['ID_Stat'];
            $this->created_by = $row['CreatedBy'];
            $this->created_date = $row['CreatedDate'];
            $this->modified_by = $row['ModifiedBy'];
            $this->modified_date = $row['ModifiedDate'];
            
            // Propiedades relacionadas
            $this->prioridad = $row['Prioridad'];
            $this->estado = $row['Estado'];
            $this->ci_nombre = $row['CI_Nombre'];
            $this->ci_tipo = $row['CI_Tipo'];
            $this->tecnico_nombre = $row['Tecnico_Nombre'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear una nueva incidencia
     * @return mixed ID de la incidencia creada o false en caso de error
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (Descripcion, FechaInicio, ID_Prioridad, ID_CI, ID_Tecnico, ID_Stat, CreatedBy, CreatedDate) 
                  VALUES (?, GETDATE(), ?, ?, ?, ?, ?, GETDATE()); SELECT SCOPE_IDENTITY() as ID;";
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            $stmt->execute([
                $this->descripcion,
                $this->id_prioridad,
                $this->id_ci,
                $this->id_tecnico,
                $this->id_stat,
                $this->created_by
            ]);
            
            // Obtener el ID de la incidencia creada
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row['ID'];
            }
            
            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::create(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar una incidencia existente
     * @return boolean True si se actualizó correctamente
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET Descripcion = ?, ID_Prioridad = ?, ID_CI = ?, 
                      ID_Tecnico = ?, ID_Stat = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si la incidencia está resuelta o cerrada, registrar la fecha de terminación
        if($this->id_stat == 5 || $this->id_stat == 6) { // 5 = Resuelta, 6 = Cerrada
            $query = "UPDATE " . $this->table_name . " 
                      SET Descripcion = ?, ID_Prioridad = ?, ID_CI = ?, 
                          ID_Tecnico = ?, ID_Stat = ?, FechaTerminacion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Parámetros a ejecutar
            $params = [
                $this->descripcion,
                $this->id_prioridad,
                $this->id_ci,
                $this->id_tecnico,
                $this->id_stat,
                $this->modified_by,
                $this->id
            ];
            
            // Ejecutar la consulta
            return $stmt->execute($params);
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::update(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar el estado de una incidencia
     * @param integer $id ID de la incidencia
     * @param integer $id_stat ID del nuevo estado
     * @param integer $modified_by ID del usuario que modifica
     * @return boolean True si se actualizó correctamente
     */
    public function cambiarEstado($id, $id_stat, $modified_by) {
        $query = "UPDATE " . $this->table_name . " 
                  SET ID_Stat = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si la incidencia está resuelta o cerrada, registrar la fecha de terminación
        if($id_stat == 5 || $id_stat == 6) { // 5 = Resuelta, 6 = Cerrada
            $query = "UPDATE " . $this->table_name . " 
                      SET ID_Stat = ?, FechaTerminacion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            return $stmt->execute([$id_stat, $modified_by, $id]);
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::cambiarEstado(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Asignar técnico a una incidencia
     * @param integer $id ID de la incidencia
     * @param integer $id_tecnico ID del técnico a asignar
     * @param integer $modified_by ID del usuario que modifica
     * @return boolean True si se actualizó correctamente
     */
    public function asignarTecnico($id, $id_tecnico, $modified_by) {
        $query = "UPDATE " . $this->table_name . " 
                  SET ID_Tecnico = ?, ID_Stat = 2, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta (2 = Asignada)
            return $stmt->execute([$id_tecnico, $modified_by, $id]);
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::asignarTecnico(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Eliminar una incidencia
     * @param integer $id ID de la incidencia a eliminar
     * @return boolean True si se eliminó correctamente
     */
    public function delete($id) {
        try {
            // Comenzar transacción para eliminar la incidencia y sus relaciones
            $this->conn->beginTransaction();
            
            // Eliminar registros relacionados en CONTROL_RESPUESTA
            $query_control = "DELETE FROM CONTROL_RESPUESTA WHERE ID_Incidencia = ?";
            $stmt_control = $this->conn->prepare($query_control);
            $stmt_control->execute([$id]);
            
            // Eliminar registros relacionados en INCIDENCIA_COMENTARIO
            $query_comentarios = "DELETE FROM INCIDENCIA_COMENTARIO WHERE ID_Incidencia = ?";
            $stmt_comentarios = $this->conn->prepare($query_comentarios);
            $stmt_comentarios->execute([$id]);
            
            // Eliminar registros relacionados en INCIDENCIA_HISTORIAL
            $query_historial = "DELETE FROM INCIDENCIA_HISTORIAL WHERE ID_Incidencia = ?";
            $stmt_historial = $this->conn->prepare($query_historial);
            $stmt_historial->execute([$id]);
            
            // Eliminar registros relacionados en INCIDENCIA_SOLUCION
            $query_solucion = "DELETE FROM INCIDENCIA_SOLUCION WHERE ID_Incidencia = ?";
            $stmt_solucion = $this->conn->prepare($query_solucion);
            $stmt_solucion->execute([$id]);
            
            // Eliminar registros relacionados en INCIDENCIA_EVALUACION
            $query_evaluacion = "DELETE FROM INCIDENCIA_EVALUACION WHERE ID_Incidencia = ?";
            $stmt_evaluacion = $this->conn->prepare($query_evaluacion);
            $stmt_evaluacion->execute([$id]);
            
            // Eliminar la incidencia
            $query = "DELETE FROM " . $this->table_name . " WHERE ID = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$id]);
            
            // Confirmar la transacción
            $this->conn->commit();
            
            return $result;
        } catch (Exception $e) {
            // Si ocurrió un error, revertir los cambios
            $this->conn->rollBack();
            
            // Registrar el error y devolver false
            error_log("Error en Incidencia::delete(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener todas las prioridades disponibles
     * @return PDOStatement Resultado de la consulta
     */
    public function getPrioridades() {
        $query = "SELECT ID, Descripcion FROM PRIORIDAD ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los estados disponibles
     * @return PDOStatement Resultado de la consulta
     */
    public function getEstados() {
        $query = "SELECT ID, Descripcion FROM ESTATUS_INCIDENCIA ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener técnicos disponibles para asignar
     * @return PDOStatement Resultado de la consulta
     */
    public function getTecnicos() {
        $query = "SELECT e.ID, e.Nombre, e.Email 
                  FROM EMPLEADO e 
                  JOIN ROL r ON e.ID_Rol = r.ID 
                  WHERE r.Nombre = 'Técnico TI'
                  ORDER BY e.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Agregar comentario a una incidencia
     * @param integer $id_incidencia ID de la incidencia
     * @param integer $id_usuario ID del usuario que comenta
     * @param string $comentario Texto del comentario
     * @param string $tipo_comentario Tipo del comentario (COMENTARIO, ACTUALIZACION, SOLUCION, etc.)
     * @param boolean $publico Si el comentario es visible para el usuario
     * @return boolean True si se agregó correctamente
     */
    public function agregarComentario($id_incidencia, $id_usuario, $comentario, $tipo_comentario = 'COMENTARIO', $publico = true) {
        $query = "INSERT INTO INCIDENCIA_COMENTARIO (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                  VALUES (?, ?, ?, ?, GETDATE(), ?)";
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            return $stmt->execute([$id_incidencia, $id_usuario, $comentario, $tipo_comentario, $publico ? 1 : 0]);
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::agregarComentario(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar historial de cambio de estado
     * @param integer $id_incidencia ID de la incidencia
     * @param integer $id_estado_anterior ID del estado anterior
     * @param integer $id_estado_nuevo ID del nuevo estado
     * @param integer $id_usuario ID del usuario que realiza el cambio
     * @return boolean True si se registró correctamente
     */
    public function registrarCambioEstado($id_incidencia, $id_estado_anterior, $id_estado_nuevo, $id_usuario) {
        $query = "INSERT INTO INCIDENCIA_HISTORIAL (ID_Incidencia, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                  VALUES (?, ?, ?, ?, GETDATE())";
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            return $stmt->execute([$id_incidencia, $id_estado_anterior, $id_estado_nuevo, $id_usuario]);
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::registrarCambioEstado(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar solución de una incidencia
     * @param integer $id_incidencia ID de la incidencia
     * @param string $descripcion Descripción de la solución
     * @param integer $id_usuario ID del usuario que registra la solución
     * @return boolean True si se registró correctamente
     */
    public function registrarSolucion($id_incidencia, $descripcion, $id_usuario) {
        $query = "INSERT INTO INCIDENCIA_SOLUCION (ID_Incidencia, Descripcion, FechaRegistro, ID_Usuario) 
                  VALUES (?, ?, GETDATE(), ?)";
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            return $stmt->execute([$id_incidencia, $descripcion, $id_usuario]);
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::registrarSolucion(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Evaluar la resolución de una incidencia
     * @param integer $id_incidencia ID de la incidencia
     * @param integer $id_usuario ID del usuario que evalúa
     * @param integer $id_tecnico ID del técnico evaluado
     * @param integer $calificacion Calificación (1-5)
     * @param string $comentario Comentario de la evaluación
     * @return boolean True si se registró correctamente
     */
    public function evaluarResolucion($id_incidencia, $id_usuario, $id_tecnico, $calificacion, $comentario = '') {
        $query = "INSERT INTO INCIDENCIA_EVALUACION (ID_Incidencia, ID_Usuario, ID_Tecnico, Calificacion, Comentario, FechaRegistro) 
                  VALUES (?, ?, ?, ?, ?, GETDATE())";
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            return $stmt->execute([$id_incidencia, $id_usuario, $id_tecnico, $calificacion, $comentario]);
        } catch (Exception $e) {
            // Registrar el error y devolver false
            error_log("Error en Incidencia::evaluarResolucion(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener estadísticas de incidencias (para dashboard)
     * @param integer $id_tecnico ID del técnico (opcional, para filtrar)
     * @return array Resultado con estadísticas
     */
    public function getEstadisticas($id_tecnico = null) {
        // Preparar array de resultados
        $estadisticas = [];
        
        try {
            // Condición para filtrar por técnico si se proporciona
            $tecnico_condition = "";
            $params = [];
            
            if ($id_tecnico) {
                $tecnico_condition = " WHERE i.ID_Tecnico = ?";
                $params[] = $id_tecnico;
            }
            
            // 1. Total de incidencias
            $queryTotal = "SELECT COUNT(*) as total FROM " . $this->table_name . " i" . $tecnico_condition;
            $stmtTotal = $this->conn->prepare($queryTotal);
            $stmtTotal->execute($params);
            $estadisticas['total'] = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 2. Incidencias abiertas (Nueva, Asignada, En proceso, En espera)
            $params_abiertas = $params;
            $queryAbiertas = "SELECT COUNT(*) as total FROM " . $this->table_name . " i 
                             " . ($tecnico_condition ? $tecnico_condition . " AND" : " WHERE") . " i.ID_Stat IN (1, 2, 3, 4)";
            $stmtAbiertas = $this->conn->prepare($queryAbiertas);
            $stmtAbiertas->execute($params_abiertas);
            $estadisticas['abiertas'] = $stmtAbiertas->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 3. Incidencias resueltas/cerradas
            $params_resueltas = $params;
            $queryResueltas = "SELECT COUNT(*) as total FROM " . $this->table_name . " i 
                             " . ($tecnico_condition ? $tecnico_condition . " AND" : " WHERE") . " i.ID_Stat IN (5, 6)";
            $stmtResueltas = $this->conn->prepare($queryResueltas);
            $stmtResueltas->execute($params_resueltas);
            $estadisticas['resueltas'] = $stmtResueltas->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 4. Incidencias críticas
            $params_criticas = $params;
            $queryCriticas = "SELECT COUNT(*) as total FROM " . $this->table_name . " i 
                            " . ($tecnico_condition ? $tecnico_condition . " AND" : " WHERE") . " i.ID_Prioridad = 1"; // 1 = Crítica
            $stmtCriticas = $this->conn->prepare($queryCriticas);
            $stmtCriticas->execute($params_criticas);
            $estadisticas['criticas'] = $stmtCriticas->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 5. Tiempo promedio de resolución (en días)
            $params_tiempo = $params;
            $queryTiempo = "SELECT AVG(DATEDIFF(day, FechaInicio, FechaTerminacion)) as promedio 
                           FROM " . $this->table_name . " i 
                           " . ($tecnico_condition ? $tecnico_condition . " AND" : " WHERE") . " i.FechaTerminacion IS NOT NULL";
            $stmtTiempo = $this->conn->prepare($queryTiempo);
            $stmtTiempo->execute($params_tiempo);
            $estadisticas['tiempo_promedio'] = $stmtTiempo->fetch(PDO::FETCH_ASSOC)['promedio'];
            
            // 6. Incidencias creadas en los últimos 30 días
            $params_recientes = $params;
            $queryRecientes = "SELECT COUNT(*) as total FROM " . $this->table_name . " i 
                             " . ($tecnico_condition ? $tecnico_condition . " AND" : " WHERE") . " i.FechaInicio >= DATEADD(day, -30, GETDATE())";
            $stmtRecientes = $this->conn->prepare($queryRecientes);
            $stmtRecientes->execute($params_recientes);
            $estadisticas['recientes'] = $stmtRecientes->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 7. Incidencias por estado
            $queryPorEstado = "SELECT s.Descripcion as Estado, COUNT(i.ID) as Total 
                              FROM ESTATUS_INCIDENCIA s
                              LEFT JOIN " . $this->table_name . " i ON s.ID = i.ID_Stat
                              " . ($tecnico_condition ? str_replace("i.", "", $tecnico_condition) : "") . "
                              GROUP BY s.Descripcion
                              ORDER BY Total DESC";
            $stmtPorEstado = $this->conn->prepare($queryPorEstado);
            $stmtPorEstado->execute($params);
            $estadisticas['por_estado'] = $stmtPorEstado->fetchAll(PDO::FETCH_ASSOC);
            
            // 8. Incidencias por prioridad
            $queryPorPrioridad = "SELECT p.Descripcion as Prioridad, COUNT(i.ID) as Total 
                                 FROM PRIORIDAD p
                                 LEFT JOIN " . $this->table_name . " i ON p.ID = i.ID_Prioridad
                                 " . ($tecnico_condition ? str_replace("i.", "", $tecnico_condition) : "") . "
                                 GROUP BY p.Descripcion
                                 ORDER BY Total DESC";
            $stmtPorPrioridad = $this->conn->prepare($queryPorPrioridad);
            $stmtPorPrioridad->execute($params);
            $estadisticas['por_prioridad'] = $stmtPorPrioridad->fetchAll(PDO::FETCH_ASSOC);
            
            return $estadisticas;
        } catch (Exception $e) {
            // Registrar el error y devolver un array vacío
            error_log("Error en Incidencia::getEstadisticas(): " . $e->getMessage());
            return [
                'total' => 0,
                'abiertas' => 0,
                'resueltas' => 0,
                'criticas' => 0,
                'tiempo_promedio' => 0,
                'recientes' => 0,
                'por_estado' => [],
                'por_prioridad' => []
            ];
        }
    }
    
    /**
     * Obtener incidencias asignadas a un técnico
     * @param integer $id_tecnico ID del técnico
     * @param integer $limit Límite de resultados (opcional)
     * @return PDOStatement Resultado de la consulta
     */
    public function getAsignadasATecnico($id_tecnico, $limit = null) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, p.Descripcion as Prioridad,
                         s.Descripcion as Estado, ci.Nombre as CI_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  WHERE i.ID_Tecnico = ?
                  AND i.ID_Stat IN (2, 3, 4) -- Asignada, En proceso, En espera
                  ORDER BY i.ID_Prioridad ASC, i.FechaInicio DESC";
        
        if ($limit !== null && is_numeric($limit)) {
            $query .= " OFFSET 0 ROWS FETCH NEXT $limit ROWS ONLY";
        }
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            $stmt->execute([$id_tecnico]);
            
            return $stmt;
        } catch (Exception $e) {
            // Registrar el error y devolver null
            error_log("Error en Incidencia::getAsignadasATecnico(): " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener incidencias reportadas por un usuario
     * @param integer $id_usuario ID del usuario que reportó
     * @param integer $limit Límite de resultados (opcional)
     * @return PDOStatement Resultado de la consulta
     */
    public function getReportadasPorUsuario($id_usuario, $limit = null) {
        $query = "SELECT i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion,
                         p.Descripcion as Prioridad, s.Descripcion as Estado,
                         ci.Nombre as CI_Nombre, e.Nombre as Tecnico_Nombre
                  FROM " . $this->table_name . " i
                  LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
                  LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
                  LEFT JOIN CI ci ON i.ID_CI = ci.ID
                  LEFT JOIN EMPLEADO e ON i.ID_Tecnico = e.ID
                  WHERE i.CreatedBy = ?
                  ORDER BY i.FechaInicio DESC";
        
        if ($limit !== null && is_numeric($limit)) {
            $query .= " OFFSET 0 ROWS FETCH NEXT $limit ROWS ONLY";
        }
        
        try {
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            $stmt->execute([$id_usuario]);
            
            return $stmt;
        } catch (Exception $e) {
            // Registrar el error y devolver null
            error_log("Error en Incidencia::getReportadasPorUsuario(): " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Registrar respuestas a preguntas de control
     * @param integer $id_incidencia ID de la incidencia
     * @param array $respuestas Array asociativo con ID_Pregunta => Respuesta
     * @return boolean True si se registraron correctamente
     */
    public function registrarRespuestasControl($id_incidencia, $respuestas) {
        if (empty($respuestas)) {
            return true; // No hay respuestas para registrar
        }
        
        try {
            // Comenzar transacción
            $this->conn->beginTransaction();
            
            $query = "INSERT INTO CONTROL_RESPUESTA (ID_Incidencia, ID_Pregunta, Respuesta, FechaRegistro) 
                      VALUES (?, ?, ?, GETDATE())";
            $stmt = $this->conn->prepare($query);
            
            foreach ($respuestas as $pregunta_id => $respuesta) {
                $stmt->execute([$id_incidencia, $pregunta_id, $respuesta]);
            }
            
            // Confirmar transacción
            $this->conn->commit();
            
            return true;
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $this->conn->rollBack();
            
            // Registrar el error y devolver false
            error_log("Error en Incidencia::registrarRespuestasControl(): " . $e->getMessage());
            return false;
        }
    }
}