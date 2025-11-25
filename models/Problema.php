<?php
/**
 * Modelo Problema - Gestiona la información y lógica relacionada con los problemas
 * Un problema es la causa raíz desconocida de uno o más incidentes 
 */
class Problema {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "PROBLEMA";
    
    // Propiedades del objeto
    public $id;
    public $titulo;
    public $descripcion;
    public $fecha_identificacion;
    public $fecha_resolucion;
    public $id_prioridad;
    public $id_categoria;
    public $id_impacto;
    public $id_stat;
    public $id_responsable;
    public $created_by;
    public $created_date;
    public $modified_by;
    public $modified_date;
    
    // Propiedades relacionadas
    public $prioridad;
    public $categoria;
    public $impacto;
    public $estado;
    public $responsable_nombre;
    public $responsable_rol; // <--- AGREGADO: Nueva propiedad
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todos los problemas con filtros opcionales (Incluye Rol de Responsable)
     * @param array $filtros Array asociativo con los filtros a aplicar
     * @return PDOStatement Resultado de la consulta
     */
    public function getAll($filtros = []) {
        // Construir la consulta base
        $query = "SELECT p.ID, p.Titulo, p.Descripcion, p.FechaIdentificacion, p.FechaResolucion,
                         pri.Descripcion as Prioridad, cat.Nombre as Categoria, 
                         imp.Descripcion as Impacto, s.Descripcion as Estado,
                         e.Nombre as ResponsableNombre, e.ID as ResponsableID,
                         r.Nombre as ResponsableRol -- <--- CAMBIO: Se agrega el Rol
                  FROM " . $this->table_name . " p
                  LEFT JOIN PRIORIDAD pri ON p.ID_Prioridad = pri.ID
                  LEFT JOIN CATEGORIA_PROBLEMA cat ON p.ID_Categoria = cat.ID
                  LEFT JOIN IMPACTO imp ON p.ID_Impacto = imp.ID
                  LEFT JOIN ESTATUS_PROBLEMA s ON p.ID_Stat = s.ID
                  LEFT JOIN EMPLEADO e ON p.ID_Responsable = e.ID
                  LEFT JOIN ROL r ON e.ID_Rol = r.ID -- <--- CAMBIO: JOIN a ROL
                  WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if(isset($filtros['estado']) && !empty($filtros['estado'])) {
            $query .= " AND p.ID_Stat = ?";
            $params[] = $filtros['estado'];
        }
        
        if(isset($filtros['prioridad']) && !empty($filtros['prioridad'])) {
            $query .= " AND p.ID_Prioridad = ?";
            $params[] = $filtros['prioridad'];
        }
        
        if(isset($filtros['categoria']) && !empty($filtros['categoria'])) {
            $query .= " AND p.ID_Categoria = ?";
            $params[] = $filtros['categoria'];
        }
        
        if(isset($filtros['responsable'])) {
            if ($filtros['responsable'] === 'null') {
                $query .= " AND p.ID_Responsable IS NULL";
            } elseif (!empty($filtros['responsable'])) {
                $query .= " AND p.ID_Responsable = ?";
                $params[] = $filtros['responsable'];
            }
        }
        
        if(isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $query .= " AND (p.Titulo LIKE ? OR p.Descripcion LIKE ?)";
            $busqueda = "%" . $filtros['busqueda'] . "%";
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        // Ordenar por los más recientes primero
        $query .= " ORDER BY p.FechaIdentificacion DESC";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute($params);
        
        return $stmt;
    }
    
/**
 * Obtener un problema por su ID (Incluye Rol de Responsable)
 * @param integer $id ID del problema
 * @return boolean True si se encontró el problema
 */
public function getById($id) {
    $query = "SELECT p.ID, p.Titulo, p.Descripcion, p.FechaIdentificacion, p.FechaResolucion,
                     p.ID_Prioridad, p.ID_Categoria, p.ID_Impacto, p.ID_Stat, p.ID_Responsable, 
                     p.CreatedBy, p.CreatedDate, p.ModifiedBy, p.ModifiedDate,
                     r.Nombre as ResponsableRol -- <--- CAMBIO: Se obtiene el Rol del Responsable
              FROM PROBLEMA p
              LEFT JOIN EMPLEADO e ON p.ID_Responsable = e.ID
              LEFT JOIN ROL r ON e.ID_Rol = r.ID
              WHERE p.ID = ?";
    
    try {
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        
        // No usar rowCount(), ir directamente al fetch()
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && !empty($row)) {
            // Asignar valores básicos
            $this->id = $row['ID'];
            $this->titulo = $row['Titulo'];
            $this->descripcion = $row['Descripcion'];
            $this->fecha_identificacion = $row['FechaIdentificacion'];
            $this->fecha_resolucion = $row['FechaResolucion'];
            $this->id_prioridad = $row['ID_Prioridad'];
            $this->id_categoria = $row['ID_Categoria'];
            $this->id_impacto = $row['ID_Impacto'];
            $this->id_stat = $row['ID_Stat'];
            $this->id_responsable = $row['ID_Responsable'];
            $this->created_by = $row['CreatedBy'];
            $this->created_date = $row['CreatedDate'];
            $this->modified_by = $row['ModifiedBy'];
            $this->modified_date = $row['ModifiedDate'];
            $this->responsable_rol = $row['ResponsableRol']; // <--- CAMBIO: Asignar Rol

            
            // Obtener datos relacionados
            if ($this->id_categoria) {
                try {
                    $catQuery = "SELECT Nombre FROM CATEGORIA_PROBLEMA WHERE ID = ?";
                    $catStmt = $this->conn->prepare($catQuery);
                    $catStmt->execute([$this->id_categoria]);
                    $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
                    $this->categoria = $catRow ? $catRow['Nombre'] : 'Sin categoría';
                } catch (Exception $e) {
                    $this->categoria = 'Sin categoría';
                }
            }
            
            if ($this->id_stat) {
                try {
                    $statQuery = "SELECT Descripcion FROM ESTATUS_PROBLEMA WHERE ID = ?";
                    $statStmt = $this->conn->prepare($statQuery);
                    $statStmt->execute([$this->id_stat]);
                    $statRow = $statStmt->fetch(PDO::FETCH_ASSOC);
                    $this->estado = $statRow ? $statRow['Descripcion'] : 'Sin estado';
                } catch (Exception $e) {
                    $this->estado = 'Sin estado';
                }
            }
            
            if ($this->id_impacto) {
                try {
                    $impQuery = "SELECT Descripcion FROM IMPACTO WHERE ID = ?";
                    $impStmt = $this->conn->prepare($impQuery);
                    $impStmt->execute([$this->id_impacto]);
                    $impRow = $impStmt->fetch(PDO::FETCH_ASSOC);
                    $this->impacto = $impRow ? $impRow['Descripcion'] : 'Sin impacto';
                } catch (Exception $e) {
                    $this->impacto = 'Sin impacto';
                }
            }
            
            // Obtener Nombre del Responsable
            if ($this->id_responsable) {
                try {
                    $respQuery = "SELECT Nombre FROM EMPLEADO WHERE ID = ?";
                    $respStmt = $this->conn->prepare($respQuery);
                    $respStmt->execute([$this->id_responsable]);
                    $respRow = $respStmt->fetch(PDO::FETCH_ASSOC);
                    $this->responsable_nombre = $respRow ? $respRow['Nombre'] : null;
                } catch (Exception $e) {
                    $this->responsable_nombre = null;
                }
            }
            
            return true;
        }
        
    } catch (Exception $e) {
        error_log("Error en Problema::getById(): " . $e->getMessage());
    }
    
    return false;
}

    /**
     * Crear un nuevo problema
     * @return boolean True si se creó correctamente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (Titulo, Descripcion, FechaIdentificacion, ID_Prioridad, ID_Categoria, 
                   ID_Impacto, ID_Stat, ID_Responsable, CreatedBy, CreatedDate) 
                  VALUES (?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $result = $stmt->execute([
            $this->titulo,
            $this->descripcion,
            $this->id_prioridad,
            $this->id_categoria,
            $this->id_impacto,
            $this->id_stat,
            $this->id_responsable,
            $this->created_by
        ]);
        
        if($result) {
            // Obtener el último ID insertado
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar un problema existente
     * @return boolean True si se actualizó correctamente
     */
    public function update() {
        // La lógica del update se mantiene igual
        $query = "UPDATE " . $this->table_name . " 
                  SET Titulo = ?, Descripcion = ?, ID_Prioridad = ?, 
                      ID_Categoria = ?, ID_Impacto = ?, ID_Stat = ?,
                      ID_Responsable = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si el problema está resuelto, registrar la fecha de resolución
        if($this->id_stat == 4) { // 4 = Resuelto
            $query = "UPDATE " . $this->table_name . " 
                      SET Titulo = ?, Descripcion = ?, ID_Prioridad = ?, 
                          ID_Categoria = ?, ID_Impacto = ?, ID_Stat = ?,
                          ID_Responsable = ?, FechaResolucion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->titulo,
            $this->descripcion,
            $this->id_prioridad,
            $this->id_categoria,
            $this->id_impacto,
            $this->id_stat,
            $this->id_responsable,
            $this->modified_by,
            $this->id
        ]);
    }
    
    /**
     * Eliminar un problema
     * @param integer $id ID del problema a eliminar
     * @return boolean True si se eliminó correctamente
     */
    public function delete($id) {
        // La lógica de eliminación se mantiene igual
        // Primero verificar si hay relaciones con incidencias
        $incidenciasQuery = "SELECT COUNT(*) as total FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ?";
        $incStmt = $this->conn->prepare($incidenciasQuery);
        $incStmt->execute([$id]);
        $result = $incStmt->fetch(PDO::FETCH_ASSOC);
        
        if($result['total'] > 0) {
            // Eliminar primero las relaciones con incidencias
            $deleteRelacionesQuery = "DELETE FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ?";
            $deleteRelStmt = $this->conn->prepare($deleteRelacionesQuery);
            $deleteRelStmt->execute([$id]);
        }
        
        // Eliminar los comentarios asociados al problema
        $comentariosQuery = "DELETE FROM PROBLEMA_COMENTARIO WHERE ID_Problema = ?";
        $comentariosStmt = $this->conn->prepare($comentariosQuery);
        $comentariosStmt->execute([$id]);
        
        // Eliminar el historial de estados del problema
        $historialQuery = "DELETE FROM PROBLEMA_HISTORIAL WHERE ID_Problema = ?";
        $historialStmt = $this->conn->prepare($historialQuery);
        $historialStmt->execute([$id]);
        
        // Eliminar las soluciones propuestas
        $solucionesQuery = "DELETE FROM PROBLEMA_SOLUCION_PROPUESTA WHERE ID_Problema = ?";
        $solucionesStmt = $this->conn->prepare($solucionesQuery);
        $solucionesStmt->execute([$id]);
        
        // Finalmente eliminar el problema
        $query = "DELETE FROM " . $this->table_name . " WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id]);
    }
    
    /**
     * Asignar responsable a un problema (Usado en asignación rápida)
     * @param integer $problema_id ID del problema
     * @param integer $empleado_id ID del empleado que será responsable
     * @param integer $modified_by ID del usuario que realiza la acción
     * @return boolean True si se actualizó correctamente
     */
    public function asignarResponsable($problema_id, $empleado_id, $modified_by) { // <--- NUEVO MÉTODO
        $query = "UPDATE " . $this->table_name . " 
                  SET ID_Responsable = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$empleado_id, $modified_by, $problema_id]);
    }


  /**
 * Asignar una incidencia a un problema
 * @param integer $problema_id ID del problema
 * @param integer $incidencia_id ID de la incidencia
 * @param integer $created_by ID del usuario que crea la relación
 * @return boolean True si se creó correctamente
 */
public function asignarIncidencia($problema_id, $incidencia_id, $created_by) {
    // La lógica de asignación de incidencia se mantiene igual
    // Primero verificar que la incidencia existe
    $checkIncidenciaQuery = "SELECT COUNT(*) as total FROM INCIDENCIA WHERE ID = ?";
    $checkIncidenciaStmt = $this->conn->prepare($checkIncidenciaQuery);
    $checkIncidenciaStmt->execute([$incidencia_id]);
    $incidenciaResult = $checkIncidenciaStmt->fetch(PDO::FETCH_ASSOC);
    
    if($incidenciaResult['total'] == 0) {
        // La incidencia no existe
        return false;
    }
    
    // Verificar si ya existe la relación
    $checkQuery = "SELECT COUNT(*) as total FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ? AND ID_Incidencia = ?";
    $checkStmt = $this->conn->prepare($checkQuery);
    $checkStmt->execute([$problema_id, $incidencia_id]);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if($result['total'] > 0) {
        // La relación ya existe
        return true;
    }
    
    $query = "INSERT INTO PROBLEMA_INCIDENCIA (ID_Problema, ID_Incidencia, CreatedBy, CreatedDate) 
              VALUES (?, ?, ?, GETDATE())";
    
    // Preparar la consulta
    $stmt = $this->conn->prepare($query);
    
    // Ejecutar la consulta
    return $stmt->execute([$problema_id, $incidencia_id, $created_by]);
}
    /**
     * Desasignar una incidencia de un problema
     * @param integer $problema_id ID del problema
     * @param integer $incidencia_id ID de la incidencia
     * @return boolean True si se eliminó correctamente
     */
    public function desasignarIncidencia($problema_id, $incidencia_id) {
        // La lógica de desasignación se mantiene igual
        $query = "DELETE FROM PROBLEMA_INCIDENCIA WHERE ID_Problema = ? AND ID_Incidencia = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $incidencia_id]);
    }
    
    /**
     * Obtener incidencias relacionadas con un problema - VERSIÓN MODIFICADA para incluir Reportado_Por
     * @param integer $problema_id ID del problema
     * @return array Array con los datos de las incidencias
     */
    public function getIncidenciasAsociadas($problema_id) {
        // Subconsulta que trae todos los datos necesarios en un solo golpe
        $query = "
            SELECT 
                i.ID, i.Descripcion, i.FechaInicio, i.FechaTerminacion, i.ID_Prioridad, 
                i.ID_Stat, i.ID_CI, i.ID_Tecnico, 
                p.Descripcion as Prioridad, 
                s.Descripcion as Estado,
                ci.Nombre as CI_Nombre, 
                t.Nombre as CI_Tipo,
                eTecnico.Nombre as Tecnico,
                eReporta.Nombre as Reportado_Por -- <--- CAMBIO: Nombre del empleado que reportó
            FROM PROBLEMA_INCIDENCIA pi
            JOIN INCIDENCIA i ON pi.ID_Incidencia = i.ID
            LEFT JOIN PRIORIDAD p ON i.ID_Prioridad = p.ID
            LEFT JOIN ESTATUS_INCIDENCIA s ON i.ID_Stat = s.ID
            LEFT JOIN CI ci ON i.ID_CI = ci.ID
            LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
            LEFT JOIN EMPLEADO eTecnico ON i.ID_Tecnico = eTecnico.ID
            LEFT JOIN USUARIO uReporta ON i.CreatedBy = uReporta.ID
            LEFT JOIN EMPLEADO eReporta ON uReporta.ID_Empleado = eReporta.ID -- <--- CAMBIO: Join para Reportado_Por
            WHERE pi.ID_Problema = ?
            ORDER BY i.FechaInicio DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$problema_id]);
        
        $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Devolvemos un objeto que simule PDOStatement para compatibilidad con el código cliente
        return $this->arrayToPDOStatement($incidencias);
    }

/**
 * Convertir array a objeto que simula PDOStatement
 * @param array $data
 * @return object
 */
private function arrayToPDOStatement($data) {
    return new class($data) {
        private $data;
        private $position = 0;
        
        public function __construct($data) {
            $this->data = $data;
        }
        
        public function rowCount() {
            return count($this->data);
        }
        
        public function fetch($mode = PDO::FETCH_ASSOC) {
            if ($this->position < count($this->data)) {
                return $this->data[$this->position++];
            }
            return false;
        }
        
        public function fetchAll($mode = PDO::FETCH_ASSOC) {
            return $this->data;
        }
    };
}
    
    /**
     * Agregar comentario a un problema
     * @param integer $problema_id ID del problema
     * @param integer $usuario_id ID del usuario que comenta
     * @param string $comentario Texto del comentario
     * @param string $tipo_comentario Tipo del comentario (COMENTARIO, ACTUALIZACION, ANÁLISIS, etc.)
     * @return boolean True si se agregó correctamente
     */
    public function agregarComentario($problema_id, $usuario_id, $comentario, $tipo_comentario = 'COMENTARIO') {
        // La lógica de comentarios se mantiene igual
        $query = "INSERT INTO PROBLEMA_COMENTARIO (ID_Problema, ID_Usuario, Comentario, TipoComentario, FechaRegistro) 
                  VALUES (?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $usuario_id, $comentario, $tipo_comentario]);
    }
    
    /**
     * Registrar historial de cambio de estado
     * @param integer $problema_id ID del problema
     * @param integer $estado_anterior ID del estado anterior
     * @param integer $estado_nuevo ID del nuevo estado
     * @param integer $usuario_id ID del usuario que realiza el cambio
     * @return boolean True si se registró correctamente
     */
    public function registrarCambioEstado($problema_id, $estado_anterior, $estado_nuevo, $usuario_id) {
        // La lógica de historial se mantiene igual
        $query = "INSERT INTO PROBLEMA_HISTORIAL (ID_Problema, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio) 
                  VALUES (?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $estado_anterior, $estado_nuevo, $usuario_id]);
    }
    
    /**
     * Agregar solución propuesta a un problema
     * @param integer $problema_id ID del problema
     * @param string $titulo Título de la solución
     * @param string $descripcion Descripción de la solución
     * @param string $tipo_solucion Tipo de solución (WORKAROUND, SOLUCION_PERMANENTE)
     * @param integer $usuario_id ID del usuario que propone la solución
     * @return boolean True si se agregó correctamente
     */
    public function agregarSolucionPropuesta($problema_id, $titulo, $descripcion, $tipo_solucion, $usuario_id) {
        // La lógica de solución propuesta se mantiene igual
        $query = "INSERT INTO PROBLEMA_SOLUCION_PROPUESTA 
                  (ID_Problema, Titulo, Descripcion, TipoSolucion, ID_Usuario, FechaRegistro) 
                  VALUES (?, ?, ?, ?, ?, GETDATE())";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$problema_id, $titulo, $descripcion, $tipo_solucion, $usuario_id]);
    }
    
    /**
 * Obtener soluciones propuestas para un problema - VERSIÓN SIMPLIFICADA
 * @param integer $problema_id ID del problema
 * @return object Objeto que simula PDOStatement
 */
public function getSolucionesPropuestas($problema_id) {
    // La lógica de soluciones propuestas se mantiene igual
    // Obtener soluciones propuestas básicas
    $querySoluciones = "SELECT ID, Titulo, Descripcion, TipoSolucion, ID_Usuario, FechaRegistro 
                       FROM PROBLEMA_SOLUCION_PROPUESTA 
                       WHERE ID_Problema = ? 
                       ORDER BY FechaRegistro DESC";
    $stmtSoluciones = $this->conn->prepare($querySoluciones);
    $stmtSoluciones->execute([$problema_id]);
    
    $soluciones = [];
    
    while ($solucion = $stmtSoluciones->fetch(PDO::FETCH_ASSOC)) {
        // Obtener nombre del usuario que propuso la solución
        if ($solucion['ID_Usuario']) {
            $queryUsuario = "SELECT e.Nombre 
                           FROM USUARIO u 
                           JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
                           WHERE u.ID = ?";
            $stmtUsuario = $this->conn->prepare($queryUsuario);
            $stmtUsuario->execute([$solucion['ID_Usuario']]);
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            $solucion['NombreUsuario'] = $usuario ? $usuario['Nombre'] : 'Usuario desconocido';
        } else {
            $solucion['NombreUsuario'] = 'Usuario desconocido';
        }
        
        $soluciones[] = $solucion;
    }
    
    // Crear un objeto que simule PDOStatement para compatibilidad
    return $this->arrayToPDOStatement($soluciones);
}
    /**
 * Obtener comentarios de un problema - VERSIÓN SIMPLIFICADA
 * @param integer $problema_id ID del problema
 * @return object Objeto que simula PDOStatement
 */
public function getComentarios($problema_id) {
    // La lógica de comentarios se mantiene igual
    // Obtener comentarios básicos
    $queryComentarios = "SELECT ID, Comentario, TipoComentario, ID_Usuario, FechaRegistro 
                        FROM PROBLEMA_COMENTARIO 
                        WHERE ID_Problema = ? 
                        ORDER BY FechaRegistro ASC";
    $stmtComentarios = $this->conn->prepare($queryComentarios);
    $stmtComentarios->execute([$problema_id]);
    
    $comentarios = [];
    
    while ($comentario = $stmtComentarios->fetch(PDO::FETCH_ASSOC)) {
        // Obtener nombre del empleado que hizo el comentario
        if ($comentario['ID_Usuario']) {
            $queryUsuario = "SELECT e.Nombre 
                           FROM USUARIO u 
                           JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
                           WHERE u.ID = ?";
            $stmtUsuario = $this->conn->prepare($queryUsuario);
            $stmtUsuario->execute([$comentario['ID_Usuario']]);
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            $comentario['NombreEmpleado'] = $usuario ? $usuario['Nombre'] : 'Usuario desconocido';
        } else {
            $comentario['NombreEmpleado'] = 'Usuario desconocido';
        }
        
        $comentarios[] = $comentario;
    }
    
    // Crear un objeto que simule PDOStatement para compatibilidad
    return $this->arrayToPDOStatement($comentarios);
}
    
/**
 * Obtener historial de estados de un problema - VERSIÓN SIMPLIFICADA
 * @param integer $problema_id ID del problema
 * @return object Objeto que simula PDOStatement
 */
public function getHistorialEstados($problema_id) {
    // La lógica de historial se mantiene igual
    // Obtener historial básico
    $queryHistorial = "SELECT ID, ID_EstadoAnterior, ID_EstadoNuevo, ID_Usuario, FechaCambio 
                      FROM PROBLEMA_HISTORIAL 
                      WHERE ID_Problema = ? 
                      ORDER BY FechaCambio ASC";
    $stmtHistorial = $this->conn->prepare($queryHistorial);
    $stmtHistorial->execute([$problema_id]);
    
    $historial = [];
    
    while ($cambio = $stmtHistorial->fetch(PDO::FETCH_ASSOC)) {
        // Obtener descripción del estado anterior
        if ($cambio['ID_EstadoAnterior']) {
            $queryEstadoAnt = "SELECT Descripcion FROM ESTATUS_PROBLEMA WHERE ID = ?";
            $stmtEstadoAnt = $this->conn->prepare($queryEstadoAnt);
            $stmtEstadoAnt->execute([$cambio['ID_EstadoAnterior']]);
            $estadoAnt = $stmtEstadoAnt->fetch(PDO::FETCH_ASSOC);
            $cambio['EstadoAnterior'] = $estadoAnt ? $estadoAnt['Descripcion'] : null;
        } else {
            $cambio['EstadoAnterior'] = null;
        }
        
        // Obtener descripción del estado nuevo
        if ($cambio['ID_EstadoNuevo']) {
            $queryEstadoNuevo = "SELECT Descripcion FROM ESTATUS_PROBLEMA WHERE ID = ?";
            $stmtEstadoNuevo = $this->conn->prepare($queryEstadoNuevo);
            $stmtEstadoNuevo->execute([$cambio['ID_EstadoNuevo']]);
            $estadoNuevo = $stmtEstadoNuevo->fetch(PDO::FETCH_ASSOC);
            $cambio['EstadoNuevo'] = $estadoNuevo ? $estadoNuevo['Descripcion'] : 'Estado desconocido';
        } else {
            $cambio['EstadoNuevo'] = 'Estado desconocido';
        }
        
        // Obtener nombre del usuario que hizo el cambio
        if ($cambio['ID_Usuario']) {
            $queryUsuario = "SELECT e.Nombre 
                           FROM USUARIO u 
                           JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
                           WHERE u.ID = ?";
            $stmtUsuario = $this->conn->prepare($queryUsuario);
            $stmtUsuario->execute([$cambio['ID_Usuario']]);
            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            $cambio['NombreEmpleado'] = $usuario ? $usuario['Nombre'] : 'Usuario desconocido';
        } else {
            $cambio['NombreEmpleado'] = 'Usuario desconocido';
        }
        
        $historial[] = $cambio;
    }
    
    // Crear un objeto que simule PDOStatement para compatibilidad
    return $this->arrayToPDOStatement($historial);
}
    
    /**
     * Obtener todas las categorías de problemas
     * @return PDOStatement Resultado de la consulta
     */
    public function getCategorias() {
        // La lógica de categorías se mantiene igual
        $query = "SELECT ID, Nombre, Descripcion FROM CATEGORIA_PROBLEMA ORDER BY Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los niveles de impacto
     * @return PDOStatement Resultado de la consulta
     */
    public function getImpactos() {
        // La lógica de impacto se mantiene igual
        $query = "SELECT ID, Descripcion FROM IMPACTO ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener todos los estados de problema
     * @return PDOStatement Resultado de la consulta
     */
    public function getEstados() {
        // La lógica de estados se mantiene igual
        $query = "SELECT ID, Descripcion FROM ESTATUS_PROBLEMA ORDER BY ID";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener empleados que pueden ser responsables de problemas
     * @return PDOStatement Resultado de la consulta
     */
    public function getResponsablesPotenciales() {
        // La lógica de responsables potenciales se mantiene igual
        $query = "SELECT e.ID, e.Nombre, e.Email, r.Nombre as Rol 
                  FROM EMPLEADO e
                  JOIN ROL r ON e.ID_Rol = r.ID
                  WHERE r.Nombre IN ('Coordinador TI CEDIS', 'Coordinador TI Sucursales', 
                                     'Coordinador TI Corporativo', 'Supervisor Infraestructura', 
                                     'Supervisor Sistemas', 'Técnico') -- Agregando Técnico como potencial responsable
                  ORDER BY e.Nombre";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Cambiar el estado de un problema
     * @param integer $id ID del problema
     * @param integer $id_stat ID del nuevo estado
     * @param integer $modified_by ID del usuario que modifica
     * @return boolean True si se actualizó correctamente
     */
    public function cambiarEstado($id, $id_stat, $modified_by) {
        // La lógica de cambio de estado se mantiene igual
        $query = "UPDATE " . $this->table_name . " 
                  SET ID_Stat = ?, ModifiedBy = ?, ModifiedDate = GETDATE() 
                  WHERE ID = ?";
        
        // Si el problema está resuelto, registrar la fecha de resolución
        if($id_stat == 4) { // 4 = Resuelto
            $query = "UPDATE " . $this->table_name . " 
                      SET ID_Stat = ?, FechaResolucion = GETDATE(),
                          ModifiedBy = ?, ModifiedDate = GETDATE() 
                      WHERE ID = ?";
        }
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$id_stat, $modified_by, $id]);
    }
    
    /**
     * Obtener estadísticas de problemas
     * @param integer $responsable_id ID del responsable para filtrar (opcional)
     * @return array Estadísticas
     */
    public function getEstadisticas($responsable_id = null) {
        // La lógica de estadísticas se mantiene igual
        // Preparar array de resultados
        $estadisticas = [];
        
        try {
            // Condición para filtrar por responsable si se proporciona
            $responsable_condition = "";
            $params = [];
            
            if ($responsable_id) {
                $responsable_condition = " WHERE p.ID_Responsable = ?";
                $params[] = $responsable_id;
            }
            
            // 1. Total de problemas
            $queryTotal = "SELECT COUNT(*) as total FROM " . $this->table_name . " p" . $responsable_condition;
            $stmtTotal = $this->conn->prepare($queryTotal);
            $stmtTotal->execute($params);
            $estadisticas['total'] = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 2. Problemas abiertos (Identificado, En análisis, En implementación)
            $params_abiertos = $params;
            $queryAbiertos = "SELECT COUNT(*) as total FROM " . $this->table_name . " p 
                             " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.ID_Stat IN (1, 2, 3)";
            $stmtAbiertos = $this->conn->prepare($queryAbiertos);
            $stmtAbiertos->execute($params_abiertos);
            $estadisticas['abiertos'] = $stmtAbiertos->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 3. Problemas resueltos
            $params_resueltos = $params;
            $queryResueltos = "SELECT COUNT(*) as total FROM " . $this->table_name . " p 
                             " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.ID_Stat = 4";
            $stmtResueltos = $this->conn->prepare($queryResueltos);
            $stmtResueltos->execute($params_resueltos);
            $estadisticas['resueltos'] = $stmtResueltos->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 4. Problemas con alto impacto
            $params_altoimpacto = $params;
            $queryAltoImpacto = "SELECT COUNT(*) as total FROM " . $this->table_name . " p 
                            " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.ID_Impacto = 1"; // 1 = Alto
            $stmtAltoImpacto = $this->conn->prepare($queryAltoImpacto);
            $stmtAltoImpacto->execute($params_altoimpacto);
            $estadisticas['alto_impacto'] = $stmtAltoImpacto->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 5. Problemas por categoría
            $queryPorCategoria = "SELECT cat.Nombre as Categoria, COUNT(p.ID) as Total 
                                 FROM CATEGORIA_PROBLEMA cat
                                 LEFT JOIN " . $this->table_name . " p ON cat.ID = p.ID_Categoria
                                 " . $responsable_condition . "
                                 GROUP BY cat.Nombre
                                 ORDER BY Total DESC";
            $stmtPorCategoria = $this->conn->prepare($queryPorCategoria);
            $stmtPorCategoria->execute($params);
            $estadisticas['por_categoria'] = $stmtPorCategoria->fetchAll(PDO::FETCH_ASSOC);
            
            // 6. Problemas por estado
            $queryPorEstado = "SELECT ep.Descripcion as Estado, COUNT(p.ID) as Total 
                                 FROM ESTATUS_PROBLEMA ep
                                 LEFT JOIN " . $this->table_name . " p ON ep.ID = p.ID_Stat
                                 " . $responsable_condition . "
                                 GROUP BY ep.Descripcion
                                 ORDER BY Total DESC";
            $stmtPorEstado = $this->conn->prepare($queryPorEstado);
            $stmtPorEstado->execute($params);
            $estadisticas['por_estado'] = $stmtPorEstado->fetchAll(PDO::FETCH_ASSOC);
            
            // 7. Tiempo promedio de resolución (en días)
            $params_tiempo = $params;
            $queryTiempo = "SELECT AVG(DATEDIFF(day, FechaIdentificacion, FechaResolucion)) as promedio 
                           FROM " . $this->table_name . " p 
                           " . ($responsable_condition ? $responsable_condition . " AND" : " WHERE") . " p.FechaResolucion IS NOT NULL";
            $stmtTiempo = $this->conn->prepare($queryTiempo);
            $stmtTiempo->execute($params_tiempo);
            $estadisticas['tiempo_promedio'] = $stmtTiempo->fetch(PDO::FETCH_ASSOC)['promedio'];
            
            return $estadisticas;
        } catch (Exception $e) {
            // Registrar el error y devolver un array vacío
            error_log("Error en Problema::getEstadisticas(): " . $e->getMessage());
            return [
                'total' => 0,
                'abiertos' => 0,
                'resueltos' => 0,
                'alto_impacto' => 0,
                'por_categoria' => [],
                'por_estado' => [],
                'tiempo_promedio' => 0
            ];
        }
    }
}