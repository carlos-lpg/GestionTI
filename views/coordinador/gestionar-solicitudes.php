<?php
// Incluir el encabezado y verificar permisos
require_once '../../includes/header.php';
// PERMISO CAMBIADO: Asume que el Coordinador tiene este permiso
has_permission('gestionar_coordinacion'); 

// Incluir configuración de base de datos
require_once '../../config/database.php';

$database = new Database();
$conn = $database->getConnection();
$error = null;
$success = null;
$solicitud_id = isset($_GET['solicitud_id']) ? intval($_GET['solicitud_id']) : null;

// --- LÓGICA DE PROCESAMIENTO (APROBACIÓN/RECHAZO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'gestionar_solicitud') {
    $solicitud_id_post = intval($_POST['solicitud_id']);
    $decision = $_POST['decision']; // 'Aprobada' o 'Rechazada'
    $componente_asignado_id = isset($_POST['componente_asignado']) ? intval($_POST['componente_asignado']) : null;
    $comentario_almacen = trim($_POST['comentario_almacen']);
    
    // Validaciones
    if ($decision === 'Aprobada' && empty($componente_asignado_id)) {
        $error = "Debe seleccionar un Componente de la lista de CI para aprobar la solicitud.";
    } elseif (empty($comentario_almacen)) {
        $error = "Debe ingresar un comentario para registrar la gestión.";
    } else {
        try {
            $conn->beginTransaction();
            
            // 1. Actualizar el estatus de la solicitud
            $query_update = "UPDATE SOLICITUD_COMPONENTE 
                             SET Estatus = ?, ID_JefeAlmacen = ?, 
                                 ID_ComponenteAsignado = ?, FechaGestion = GETDATE()
                             WHERE ID = ? AND Estatus = 'Pendiente'";
            
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->execute([$decision, $_SESSION['empleado_id'], $componente_asignado_id, $solicitud_id_post]);
            
            if ($stmt_update->rowCount() > 0) {
                
                // --- CAMBIO CLAVE: GESTIÓN DE INVENTARIO (Paso 9) ---
                if ($decision === 'Aprobada') {
                    // Actualizar el estado del CI asignado a 'Asignado' (Asumiendo ID=2 para 'Asignado')
                    $ID_ESTADO_ASIGNADO = 2; // DEBE COINCIDIR CON EL ID EN dbo.ESTADO_CI
                    
                    $query_update_ci = "UPDATE CI 
                                        SET ID_EstadoCI = ?, ModifiedDate = GETDATE() 
                                        WHERE ID = ?";
                    $stmt_update_ci = $conn->prepare($query_update_ci);
                    $stmt_update_ci->execute([$ID_ESTADO_ASIGNADO, $componente_asignado_id]);
                    
                    if ($stmt_update_ci->rowCount() == 0) {
                        // Rollback si no se pudo actualizar el estado del CI (ej. ya fue asignado)
                        $conn->rollBack();
                        $error = "Error: No se pudo actualizar el estado del Componente Asignado. Posiblemente ya está en uso.";
                        goto end_transaction;
                    }
                }
                // --- FIN CAMBIO CLAVE ---

                // 2. Obtener ID de la Incidencia para el comentario
                $stmt_inc = $conn->prepare("SELECT ID_Incidencia, ComponenteSolicitado FROM SOLICITUD_COMPONENTE WHERE ID = ?");
                $stmt_inc->execute([$solicitud_id_post]);
                $solicitud_data = $stmt_inc->fetch(PDO::FETCH_ASSOC);
                $incidencia_id = $solicitud_data['ID_Incidencia'];
                $componente_solicitado = $solicitud_data['ComponenteSolicitado'];

                // 3. Registrar comentario en la incidencia (privado)
                $msg = "Solicitud de componente (**$componente_solicitado**) ha sido **$decision** por Coordinación. Razón: " . $comentario_almacen;
                $stmt_comment = $conn->prepare("INSERT INTO INCIDENCIA_COMENTARIO 
                    (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                    VALUES (?, ?, ?, 'GESTION_ALMACEN', GETDATE(), 0)");
                $stmt_comment->execute([$incidencia_id, $_SESSION['user_id'], $msg, 0]);

                $conn->commit();
                $success = "La solicitud #$solicitud_id_post ha sido **$decision** correctamente.";
                // Redirigir para limpiar URL
                header("Location: gestionar-solicitudes.php?success=" . urlencode($success));
                exit;

            } else {
                $conn->rollBack();
                $error = "Error: No se pudo actualizar el estado de la solicitud o la solicitud ya fue gestionada.";
            }

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error de base de datos: " . $e->getMessage();
        }
    }
    end_transaction:; // Etiqueta para el goto en caso de error de CI
}
// --- FIN LÓGICA DE PROCESAMIENTO ---

// Consultar todas las solicitudes pendientes
$query_pendientes = "SELECT sc.ID, sc.ID_Incidencia, sc.ComponenteSolicitado, sc.Cantidad, sc.CostoMaximo, sc.FechaRegistro, 
                             e.Nombre as TecnicoNombre, ci.Nombre as CINombre
                       FROM SOLICITUD_COMPONENTE sc
                       JOIN USUARIO u ON sc.ID_UsuarioTecnico = u.ID
                       JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                       JOIN INCIDENCIA i ON sc.ID_Incidencia = i.ID
                       LEFT JOIN CI ci ON i.ID_CI = ci.ID
                       WHERE sc.Estatus = 'Pendiente'
                       ORDER BY sc.FechaRegistro ASC";
$stmt_pendientes = $conn->prepare($query_pendientes);
$stmt_pendientes->execute();
$solicitudes_pendientes = $stmt_pendientes->fetchAll(PDO::FETCH_ASSOC);

$solicitud_seleccionada = null;
$cis_disponibles = [];

if ($solicitud_id) {
    // 1. Obtener detalles de la solicitud seleccionada
    $stmt_sel = $conn->prepare("SELECT sc.ID, sc.ID_Incidencia, sc.ComponenteSolicitado, sc.Cantidad, sc.CostoMaximo, sc.FechaRegistro, 
                                        e.Nombre as TecnicoNombre, i.Descripcion as IncidenciaDescripcion
                                 FROM SOLICITUD_COMPONENTE sc
                                 JOIN USUARIO u ON sc.ID_UsuarioTecnico = u.ID
                                 JOIN EMPLEADO e ON u.ID_Empleado = e.ID
                                 JOIN INCIDENCIA i ON sc.ID_Incidencia = i.ID
                                 WHERE sc.ID = ? AND sc.Estatus = 'Pendiente'");
    $stmt_sel->execute([$solicitud_id]);
    $solicitud_seleccionada = $stmt_sel->fetch(PDO::FETCH_ASSOC);

    // 2. Obtener lista de CIs disponibles (ASUMIENDO ID_EstadoCI = 1 para 'Disponible')
    $ID_ESTADO_DISPONIBLE = 1; // DEBE COINCIDIR CON EL ID EN dbo.ESTADO_CI
    $query_ci = "SELECT ID, Nombre, NumSerie 
                 FROM CI 
                 WHERE ID_EstadoCI = ? OR ID_EstadoCI IS NULL -- Opcional: permitir CIs sin estado explícito
                 ORDER BY Nombre";
    $stmt_ci = $conn->prepare($query_ci);
    $stmt_ci->execute([$ID_ESTADO_DISPONIBLE]); // <--- CAMBIO: Filtrar por ID_EstadoCI = 1 (Disponible)
    $cis_disponibles = $stmt_ci->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h1 class="h2">Gestión de Solicitudes de Componentes (Coordinación)</h1>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-<?php echo $solicitud_seleccionada ? '7' : '12'; ?>">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Solicitudes Pendientes (<?php echo count($solicitudes_pendientes); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($solicitudes_pendientes)): ?>
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Incidencia</th>
                                <th>Componente Solicitado</th>
                                <th>Técnico</th>
                                <th>Costo Máximo</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes_pendientes as $sol): ?>
                                <tr class="<?php echo ($solicitud_id == $sol['ID']) ? 'table-info' : ''; ?>">
                                    <td>#<?php echo $sol['ID']; ?></td>
                                    <td><a href="ver-incidencia.php?id=<?php echo $sol['ID_Incidencia']; ?>">#<?php echo $sol['ID_Incidencia']; ?> (<?php echo htmlspecialchars($sol['CINombre']); ?>)</a></td>
                                    <td><?php echo htmlspecialchars($sol['ComponenteSolicitado']); ?> (x<?php echo $sol['Cantidad']; ?>)</td>
                                    <td><?php echo htmlspecialchars($sol['TecnicoNombre']); ?></td>
                                    <td>$<?php echo number_format($sol['CostoMaximo'], 2); ?></td>
                                    <td>
                                        <a href="?solicitud_id=<?php echo $sol['ID']; ?>" class="btn btn-sm btn-info">
                                            Gestionar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-success text-center">No hay solicitudes de componentes pendientes.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($solicitud_seleccionada): ?>
    <div class="col-md-5">
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Decisión para Solicitud #<?php echo $solicitud_seleccionada['ID']; ?></h5>
            </div>
            <div class="card-body">
                <h6>Detalles:</h6>
                <p><strong>Incidencia:</strong> #<?php echo $solicitud_seleccionada['ID_Incidencia']; ?></p>
                <p><strong>Solicitado:</strong> <?php echo htmlspecialchars($solicitud_seleccionada['ComponenteSolicitado']); ?> (x<?php echo $solicitud_seleccionada['Cantidad']; ?>)</p>
                <p><strong>Técnico:</strong> <?php echo htmlspecialchars($solicitud_seleccionada['TecnicoNombre']); ?></p>
                <p><strong>Costo Máximo:</strong> $<?php echo number_format($solicitud_seleccionada['CostoMaximo'], 2); ?></p>
                <p class="text-muted small">Descripción de la Incidencia: <?php echo substr(htmlspecialchars($solicitud_seleccionada['IncidenciaDescripcion']), 0, 100) . '...'; ?></p>
                <hr>

                <form action="" method="POST">
                    <input type="hidden" name="action" value="gestionar_solicitud">
                    <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_seleccionada['ID']; ?>">

                    <div class="mb-3">
                        <label for="decision" class="form-label">Decisión (Paso 8): *</label>
                        <select class="form-select" id="decision" name="decision" required>
                            <option value="">Seleccionar...</option>
                            <option value="Aprobada">Aprobar</option>
                            <option value="Rechazada">Rechazar</option>
                        </select>
                    </div>

                    <div class="mb-3" id="ci_select_container" style="display:none;">
                        <label for="componente_asignado" class="form-label">Componente CI a Asignar (Paso 9):</label>
                        <select class="form-select" id="componente_asignado" name="componente_asignado">
                            <option value="">Seleccionar CI Disponible...</option>
                            <?php foreach ($cis_disponibles as $ci): ?>
                                <option value="<?php echo $ci['ID']; ?>">
                                    <?php echo htmlspecialchars($ci['Nombre']); ?> (SN: <?php echo htmlspecialchars($ci['NumSerie']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Si aprueba, debe seleccionar el activo específico de inventario que se utilizará. (Solo se muestran CIs **Disponibles**).</div>
                    </div>

                    <div class="mb-3">
                        <label for="comentario_almacen" class="form-label">Comentarios / Razón: *</label>
                        <textarea class="form-control" id="comentario_almacen" name="comentario_almacen" rows="2" required></textarea>
                        <div class="form-text">Explique la razón de la aprobación/rechazo. Esto será visible para el técnico.</div>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-check-double me-2"></i> Confirmar Decisión
                    </button>
                </form>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const decisionSelect = document.getElementById('decision');
                        const ciContainer = document.getElementById('ci_select_container');
                        const ciSelect = document.getElementById('componente_asignado');

                        function toggleCiSelect() {
                            if (decisionSelect.value === 'Aprobada') {
                                ciContainer.style.display = 'block';
                                // Hacemos requerido el campo solo si hay CIs disponibles para forzar la selección.
                                // Si no hay CIs, el select estará vacío y lanzará un error de validación, lo cual es deseable.
                                if (ciSelect.options.length > 1) { // Cuenta el option "Seleccionar CI Disponible..."
                                    ciSelect.setAttribute('required', 'required'); 
                                }
                            } else {
                                ciContainer.style.display = 'none';
                                ciSelect.removeAttribute('required');
                                ciSelect.value = ''; 
                            }
                        }

                        decisionSelect.addEventListener('change', toggleCiSelect);
                        
                        toggleCiSelect();
                    });
                </script>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>