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
        
        // Asignar valores
        $username = htmlspecialchars(strip_tags($username));
        $stmt->bindParam(1, $username);
        
        // Ejecutar la consulta
        if (!$stmt->execute()) {
            error_log("Error de ejecución de consulta de login: " . implode(", ", $stmt->errorInfo()));
            return false;
        }
    
        // Obtener los detalles del usuario
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar si se encontró el usuario
        if (!$row) {
            error_log("Login fallido: Usuario no encontrado");
            return false;
        }

        // Verificar estado del usuario
        if($row['Estado'] != 1) {
            error_log("Login fallido: Usuario inactivo");
            return false;
        }
        
        // ----------------------------------------------------------------------------------
        // MODIFICACIÓN DE SEGURIDAD: Verificar la contraseña hasheada o en texto plano
        // ----------------------------------------------------------------------------------
        $password_ok = false;
        
        // 1. Verificar si la contraseña almacenada es un hash (empieza con '$')
        if (strpos($row['Password'], '$') === 0) {
            // Usar password_verify() para contraseñas hasheadas (MÉTODO SEGURO)
            $password_ok = password_verify($password, $row['Password']);
        } else {
            // 2. Si no es un hash, comparar como texto plano (COMPATIBILIDAD INSEGURA)
            $password_ok = ($password === $row['Password']);
        }
        // ----------------------------------------------------------------------------------
        
        if($password_ok) { 
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
            error_log("Login FALLIDO: Contraseña incorrecta para usuario {$username}");
            return false;
        }
    }

    /**
     * Actualiza el campo UltimoAcceso del usuario
     */
    public function update_last_login() {
        $query = "UPDATE " . $this->table_name . " SET UltimoAcceso = GETDATE() WHERE ID = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        if (!$stmt->execute()) {
             error_log("Error al actualizar último acceso para usuario {$this->username}: " . implode(", ", $stmt->errorInfo()));
        }
    }
    
    // --- MÉTODOS CRUD (EJEMPLOS SIMPLIFICADOS) ---

    /**
     * Leer todos los usuarios (para gestión de usuarios)
     */
    public function readAll() {
        $query = "SELECT u.ID, u.Username, u.UltimoAcceso, u.Estado, r.Nombre as rol_nombre, e.Nombre as empleado_nombre 
                  FROM " . $this->table_name . " u 
                  INNER JOIN ROL r ON u.ID_Rol = r.ID
                  INNER JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  ORDER BY u.ID DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Leer un solo usuario por ID
     */
    public function readOne($id) {
        $query = "SELECT u.ID, u.Username, u.Password, u.UltimoAcceso, u.Estado, 
                         u.ID_Empleado, u.ID_Rol, r.Nombre as rol_nombre, e.Nombre as empleado_nombre,
                         e.Email as empleado_email, e.Celular, e.Direccion
                  FROM " . $this->table_name . " u 
                  INNER JOIN ROL r ON u.ID_Rol = r.ID
                  INNER JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                  WHERE u.ID = ?
                  LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
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
     * (Este método es generalmente más complejo ya que debe crear el EMPLEADO primero)
     */
    public function create($username, $password, $estado, $id_rol, $id_empleado) {
        // En el archivo agregar-usuario.php, la contraseña ya debe venir hasheada.
        
        $query = "INSERT INTO " . $this->table_name . " 
                  (Username, Password, Estado, ID_Empleado, ID_Rol) 
                  VALUES (:username, :password, :estado, :id_empleado, :id_rol)";
        
        $stmt = $this->conn->prepare($query);
        
        // Sanear
        $username = htmlspecialchars(strip_tags($username));
        $estado = intval($estado);
        $id_empleado = intval($id_empleado);
        $id_rol = intval($id_rol);
        // $password ya está hasheada y se asume saneada

        // Asignar
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':id_empleado', $id_empleado);
        $stmt->bindParam(':id_rol', $id_rol);

        if($stmt->execute()){
            // Obtener el ID del usuario recién creado
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        error_log("Error al crear usuario: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // --- FIN MÉTODOS CRUD ---

    /**
     * Obtener los permisos del usuario basado en su rol
     * @return array Permisos del rol
     */
    public function obtenerPermisos() {
        $permisos = [];
        
        switch ($this->nombre_rol) {
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
            case 'Coordinador':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => true,
                    'gestionar_incidencias' => true,
                    'ver_reportes' => true,
                    'gestionar_problemas' => true
                ];
                break;
            case 'Técnico':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => false,
                    'gestionar_incidencias' => false,
                    'ver_reportes' => true,
                    'gestionar_problemas' => true
                ];
                break;
            // ******************************************************
            // NUEVO ROL: INVESTIGADOR (Mismos permisos que Técnico + Problemas)
            // ******************************************************
            case 'Investigador': 
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => false,
                    'gestionar_incidencias' => false,
                    'ver_reportes' => true,
                    'gestionar_problemas' => true
                ];
                break;
            // ******************************************************
            case 'Supervisor Infraestructura':
                $permisos = [
                    'admin' => false,
                    'gestionar_usuarios' => false,
                    'gestionar_ci' => true, // Puede gestionar CIs
                    'gestionar_incidencias' => true,
                    'ver_reportes' => true
                ];
                break;
                
            default: // Usuario Final y otros roles no definidos
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