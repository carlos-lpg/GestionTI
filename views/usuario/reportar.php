<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de reportar incidencias
check_permission('reportar_incidencia');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener la lista de CIs a los que el usuario tiene acceso
$query_ci = "SELECT ci.ID, ci.Nombre, t.Nombre as TipoCI 
             FROM CI ci
             LEFT JOIN TIPO_CI t ON ci.ID_TipoCI = t.ID
             WHERE 1=1";

// Si no es administrador, filtrar por departamento del usuario
if ($_SESSION['role_name'] != 'Administrador') {
    // Obtener departamentos asignados al usuario
    $deptos_query = "SELECT d.ID
                     FROM EMPLEADO_DEPTO ed 
                     JOIN DEPARTAMENTO d ON ed.ID_Depto = d.ID 
                     WHERE ed.ID_Empleado = ?";
    $deptos_stmt = $conn->prepare($deptos_query);
    $deptos_stmt->execute([$_SESSION['empleado_id']]);
    $departamentos = $deptos_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($departamentos)) {
        $placeholders = str_repeat('?,', count($departamentos) - 1) . '?';
        $query_ci .= " AND ci.ID_Departamento IN ($placeholders)";
        $params = $departamentos;
    } else {
        // Si no tiene departamentos asignados, mostrar solo los CIs de su departamento directo
        $query_ci .= " AND ci.ID_Departamento = (SELECT ID_Departamento FROM EMPLEADO WHERE ID = ?)";
        $params = [$_SESSION['empleado_id']];
    }
} else {
    $params = [];
}

$query_ci .= " ORDER BY t.Nombre, ci.Nombre";
$stmt_ci = $conn->prepare($query_ci);
$stmt_ci->execute($params);

// Obtener la lista de prioridades
$query_prioridad = "SELECT ID, Descripcion FROM PRIORIDAD ORDER BY ID";
$stmt_prioridad = $conn->prepare($query_prioridad);
$stmt_prioridad->execute();

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos del formulario
        $ci_id = $_POST['ci_id'];
        $descripcion = $_POST['descripcion'];
        $prioridad = $_POST['prioridad'];
        $respuestas_control = isset($_POST['control']) ? $_POST['control'] : [];
        
        // Validaciones básicas
        if (empty($ci_id) || empty($descripcion) || empty($prioridad)) {
            $error = "Por favor complete todos los campos obligatorios.";
        } else {
            // Insertar la incidencia sin usar transacciones
            $query = "INSERT INTO INCIDENCIA (Descripcion, FechaInicio, ID_Prioridad, ID_CI, ID_Stat, CreatedBy, CreatedDate) 
                      VALUES (?, GETDATE(), ?, ?, 1, ?, GETDATE())";
            $stmt = $conn->prepare($query);
            
            // 1 = Estado "Nueva"
            $stmt->execute([
                $descripcion,
                $prioridad,
                $ci_id,
                $_SESSION['user_id']
            ]);
            
            // Obtener el ID de la incidencia recién creada
            $incidencia_id = $conn->lastInsertId();
            
            // Guardar las respuestas a las preguntas de control
            if (!empty($respuestas_control)) {
                $query_control = "INSERT INTO CONTROL_RESPUESTA (ID_Incidencia, ID_Pregunta, Respuesta, FechaRegistro) 
                                VALUES (?, ?, ?, GETDATE())";
                $stmt_control = $conn->prepare($query_control);
                
                foreach ($respuestas_control as $pregunta_id => $respuesta) {
                    $stmt_control->execute([
                        $incidencia_id,
                        $pregunta_id,
                        $respuesta
                    ]);
                }
            }
            
            // Mostrar mensaje de éxito y redirigir
            $success = "Incidencia reportada correctamente con el número #" . $incidencia_id;
            header("Refresh: 3; URL=mis-incidencias.php");
        }
    } catch (PDOException $e) {
        // Manejar el error sin usar rollBack
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// Obtener preguntas de control
$query_preguntas = "SELECT ID, Pregunta, Tipo FROM CONTROL_PREGUNTA WHERE Activo = 1 ORDER BY Orden";
$stmt_preguntas = $conn->prepare($query_preguntas);
$stmt_preguntas->execute();
$preguntas = $stmt_preguntas->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Título de la página -->
<h1 class="h2">Reportar Incidencia</h1>

<!-- Formulario para reportar incidencia -->
<div class="row">
    <div class="col-12">
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <p class="mt-2 mb-0">Redirigiendo a la lista de incidencias...</p>
                </div>
            <?php else: ?>
                <form action="" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ci_id" class="form-label">Elemento afectado *</label>
                            <select class="form-select" id="ci_id" name="ci_id" required>
                                <option value="">Seleccionar elemento...</option>
                                <?php
                                $current_tipo = '';
                                while ($ci = $stmt_ci->fetch(PDO::FETCH_ASSOC)):
                                    // Crear grupos por tipo de CI
                                    if ($current_tipo != $ci['TipoCI']):
                                        if ($current_tipo != '') echo '</optgroup>';
                                        $current_tipo = $ci['TipoCI'];
                                        echo '<optgroup label="' . htmlspecialchars($current_tipo) . '">';
                                    endif;
                                ?>
                                    <option value="<?php echo $ci['ID']; ?>"><?php echo htmlspecialchars($ci['Nombre']); ?></option>
                                <?php
                                endwhile;
                                if ($current_tipo != '') echo '</optgroup>';
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="prioridad" class="form-label">Prioridad *</label>
                            <select class="form-select" id="prioridad" name="prioridad" required>
                                <option value="">Seleccionar prioridad...</option>
                                <?php while ($prioridad = $stmt_prioridad->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $prioridad['ID']; ?>"><?php echo htmlspecialchars($prioridad['Descripcion']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción del problema *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4" required placeholder="Describa detalladamente el problema que está experimentando..."></textarea>
                            <small class="form-text text-muted">Incluya la mayor cantidad de detalles posible para facilitar la resolución.</small>
                        </div>
                    </div>
                    
                    <?php if (!empty($preguntas)): ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <h5>Preguntas de control</h5>
                            <p class="text-muted small">Por favor responda estas preguntas para ayudarnos a diagnosticar mejor el problema.</p>
                            
                            <?php foreach ($preguntas as $pregunta): ?>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo htmlspecialchars($pregunta['Pregunta']); ?></label>
                                    
                                    <?php if ($pregunta['Tipo'] == 'SI_NO'): ?>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="control[<?php echo $pregunta['ID']; ?>]" id="control_si_<?php echo $pregunta['ID']; ?>" value="SI" required>
                                                <label class="form-check-label" for="control_si_<?php echo $pregunta['ID']; ?>">Sí</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="control[<?php echo $pregunta['ID']; ?>]" id="control_no_<?php echo $pregunta['ID']; ?>" value="NO">
                                                <label class="form-check-label" for="control_no_<?php echo $pregunta['ID']; ?>">No</label>
                                            </div>
                                        </div>
                                    <?php elseif ($pregunta['Tipo'] == 'TEXTO'): ?>
                                        <input type="text" class="form-control" name="control[<?php echo $pregunta['ID']; ?>]" id="control_<?php echo $pregunta['ID']; ?>" required>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-success">Enviar Reporte</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>