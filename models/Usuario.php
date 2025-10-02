<?php
/**
 * Modelo Usuario - Gestiona la información y lógica relacionada con los usuarios del sistema
 */
class Usuario {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "USUARIO";
    
    // Propiedades del objeto
    public $id;
    public $username;
    public $password;
    public $ultimo_acceso;
    public $estado;
    public $id_empleado;
    public $id_rol;
    public $nombre_rol;
    public $nombre_empleado;
    public $email_empleado;
    
    // Constructor con conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Verificar login del usuario
     * @param string $username Nombre de usuario
     * @param string $password Contraseña
     * @return boolean True si las credenciales son correctas, False en caso contrario
     */
    public function login($username, $password) {
        // Log de inicio de intento de login
        error_log("Intentando login: Username = $username");
    
        // Query para verificar si existe el usuario
        $query = "SELECT u.ID, u.Username, u.Password, u.UltimoAcceso, u.Estado, 
                         u.ID_Empleado, u.ID_Rol, r.Nombre as rol_nombre, e.Nombre as empleado_nombre,
                         e.Email as empleado_email
                  FROM " . $this->table_name . " u 
                  INNER JOIN ROL r ON u.ID_Rol = r.ID
                  INNER JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  WHERE u.Username = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Vincular parámetros
        $stmt->execute([$username]);
        
        // Obtener los detalles del usuario
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log de información del usuario encontrado
        if ($row) {
            error_log("Usuario encontrado - ID: {$row['ID']}, Username: {$row['Username']}, Estado: {$row['Estado']}");
            error_log("Password en BD: {$row['Password']}, Password ingresada: $password");
        } else {
            error_log("Usuario NO encontrado para: $username");
            return false;
        }
    
        // Verificar estado del usuario
        if($row['Estado'] != 1) {
            error_log("Login fallido: Usuario inactivo");
            return false;
        }
        
        // Verificar la contraseña 
        if($password === $row['Password']) {  // Usar === para comparación estricta
            // Asignar valores a las propiedades del objeto
            $this->id = $row['ID'];
            $this->username = $row['Username'];
            $this->password = $row['Password'];
            $this->ultimo_acceso = $row['UltimoAcceso'];
            $this->estado = $row['Estado'];
            $this->id_empleado = $row['ID_Empleado'];
            $this->id_rol = $row['ID_Rol'];
            $this->nombre_rol = $row['rol_nombre'];
            $this->nombre_empleado = $row['empleado_nombre'];
            $this->email_empleado = $row['empleado_email'];
            
            // Log de login exitoso
            error_log("Login EXITOSO para usuario: {$this->username}");
            
            // Actualizar último acceso
            $this->update_last_login();
            
            return true;
        } else {
            error_log("Login FALLIDO: Contraseña incorrecta");
            return false;
        }
    }
    
    /**
     * Actualizar la fecha del último acceso del usuario
     * @return boolean True si se actualizó correctamente
     */
    public function update_last_login() {
        $query = "UPDATE " . $this->table_name . " 
                  SET UltimoAcceso = GETDATE() 
                  WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Obtener todos los usuarios
     * @return PDOStatement Resultado de la consulta
     */
    public function getAll() {
        $query = "SELECT u.ID, u.Username, u.Estado, u.UltimoAcceso, 
                         e.Nombre as NombreEmpleado, r.Nombre as NombreRol
                  FROM " . $this->table_name . " u 
                  INNER JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  INNER JOIN ROL r ON u.ID_Rol = r.ID
                  ORDER BY u.Username";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener un usuario por su ID
     * @param integer $id ID del usuario
     * @return boolean True si se encontró el usuario
     */
    public function getById($id) {
        $query = "SELECT u.ID, u.Username, u.Password, u.Estado, u.UltimoAcceso, 
                         u.ID_Empleado, u.ID_Rol, r.Nombre as rol_nombre, e.Nombre as empleado_nombre,
                         e.Email as empleado_email
                  FROM " . $this->table_name . " u 
                  INNER JOIN ROL r ON u.ID_Rol = r.ID
                  INNER JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  WHERE u.ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute([$id]);
        
        // Verificar si se encontró el usuario
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Asignar valores a las propiedades
            $this->id = $row['ID'];
            $this->username = $row['Username'];
            $this->password = $row['Password'];
            $this->ultimo_acceso = $row['UltimoAcceso'];
            $this->estado = $row['Estado'];
            $this->id_empleado = $row['ID_Empleado'];
            $this->id_rol = $row['ID_Rol'];
            $this->nombre_rol = $row['rol_nombre'];
            $this->nombre_empleado = $row['empleado_nombre'];
            $this->email_empleado = $row['empleado_email'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Crear un nuevo usuario
     * @return boolean True si se creó correctamente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (Username, Password, Estado, ID_Empleado, ID_Rol) 
                  VALUES (?, ?, ?, ?, ?)";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([
            $this->username,
            $this->password, // En un entorno real, usar password_hash()
            $this->estado,
            $this->id_empleado,
            $this->id_rol
        ]);
    }
    
    /**
     * Actualizar un usuario existente
     * @return boolean True si se actualizó correctamente
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET Username = ?, Estado = ?, ID_Empleado = ?, ID_Rol = ? 
                  WHERE ID = ?";
        
        // Si hay una nueva contraseña, actualizarla también
        if(!empty($this->password)) {
            $query = "UPDATE " . $this->table_name . " 
                      SET Username = ?, Password = ?, Estado = ?, ID_Empleado = ?, ID_Rol = ? 
                      WHERE ID = ?";
            
            // Preparar la consulta
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            return $stmt->execute([
                $this->username,
                $this->password, // En un entorno real, usar password_hash()
                $this->estado,
                $this->id_empleado,
                $this->id_rol,
                $this->id
            ]);
        } else {
            // Preparar la consulta sin actualizar contraseña
            $stmt = $this->conn->prepare($query);
            
            // Ejecutar la consulta
            return $stmt->execute([
                $this->username,
                $this->estado,
                $this->id_empleado,
                $this->id_rol,
                $this->id
            ]);
        }
    }
    
    /**
     * Eliminar un usuario
     * @param integer $id ID del usuario a eliminar
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
     * Cambiar el estado de un usuario (activar/desactivar)
     * @param integer $id ID del usuario
     * @param integer $estado Nuevo estado (1 = activo, 0 = inactivo)
     * @return boolean True si se actualizó correctamente
     */
    public function cambiarEstado($id, $estado) {
        $query = "UPDATE " . $this->table_name . " SET Estado = ? WHERE ID = ?";
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        return $stmt->execute([$estado, $id]);
    }
    
    /**
     * Obtener los permisos del usuario basados en su rol
     * @return array Array con los permisos del usuario
     */
    public function obtenerPermisos() {
        // Permisos basados en roles
        $permisos = [];
        
        switch($this->nombre_rol) {
            case 'Administrador':
                $permisos = [
                    'admin' => true,
                    'gestionar_usuarios' => true,
                    'gestionar_ci' => true,
                    'gestionar_incidencias' => true,
                    'ver_reportes' => true,
                    'gestionar_problemas' => true
                ];
                break;
                
            case 'Coordinador TI CEDIS':
            case 'Coordinador TI Sucursales':
            case 'Coordinador TI Corporativo':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => true,
                    'gestionar_incidencias' => true,
                    'ver_reportes' => true,
                    'gestionar_problemas' => true
                ];
                break;
                
            case 'Técnico TI':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => false,
                    'gestionar_incidencias' => true,
                    'ver_reportes' => false
                ];
                break;
                
            case 'Supervisor Infraestructura':
            case 'Supervisor Sistemas':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => true,
                    'gestionar_incidencias' => false,
                    'ver_reportes' => true
                ];
                break;
                
            case 'Encargado Inventario':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => true,
                    'gestionar_incidencias' => false,
                    'ver_reportes' => false
                ];
                break;
                
            case 'Gerente TI':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => false,
                    'gestionar_incidencias' => false,
                    'ver_reportes' => true
                ];
                break;
                
            default: // Usuario Final
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => false,
                    'gestionar_incidencias' => false,
                    'ver_reportes' => false,
                    'reportar_incidencia' => true
                ];
        }
        
        return $permisos;
    }
    
    /**
     * Verificar si el nombre de usuario ya existe
     * @param string $username Nombre de usuario a verificar
     * @param integer $exclude_id ID del usuario a excluir de la verificación (para actualizaciones)
     * @return boolean True si el nombre de usuario ya existe
     */
    public function usernameExists($username, $exclude_id = null) {
        $query = "SELECT ID FROM " . $this->table_name . " WHERE Username = ?";
        $params = [$username];
        
        if($exclude_id) {
            $query .= " AND ID != ?";
            $params[] = $exclude_id;
        }
        
        // Preparar la consulta
        $stmt = $this->conn->prepare($query);
        
        // Ejecutar la consulta
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }
}