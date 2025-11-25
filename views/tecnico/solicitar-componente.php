<?php
// Incluir el encabezado y verificar permisos
require_once '../../includes/header.php';
has_permission('gestionar_incidencias'); 

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar ID de incidencia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: mis-incidencias.php?error=missing_id");
    exit;
}

$incidencia_id = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();

// OBTENER DETALLES DE LA INCIDENCIA 
$stmt = $conn->prepare("SELECT ID, ID_Tecnico, RequiereComponente, ID_Stat 
                        FROM INCIDENCIA 
                        WHERE ID = ?");
$stmt->execute([$incidencia_id]);
$incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

// Reglas básicas de permiso y existencia
if (!$incidencia || $incidencia['ID_Tecnico'] != $_SESSION['empleado_id']) {
    header("Location: ver-incidencia.php?id=$incidencia_id&error=permission_denied");
    exit;
}

// **VERIFICACIÓN MODIFICADA:**
// 1. Prioriza el parámetro &req=1 de la URL (viene de actualizar-incidencia.php)
$requiere_de_url = isset($_GET['req']) && $_GET['req'] === '1';

// 2. Si no viene en la URL, se verifica la base de datos (para accesos directos)
if (!$requiere_de_url && $incidencia['RequiereComponente'] !== 1) { 
    header("Location: ver-incidencia.php?id=$incidencia_id&error=component_not_required");
    exit;
}
// **FIN VERIFICACIÓN MODIFICADA**


// OBTENER COMPONENTES DISPONIBLES EN ALMACÉN (Consultando la nueva tabla ALMACEN_COMPONENTE)
$query_almacen = "SELECT ID, NombreComponente, NumeroParte, CantidadStock, CostoPromedio
                  FROM ALMACEN_COMPONENTE 
                  WHERE CantidadStock > 0
                  ORDER BY NombreComponente";

$stmt_almacen = $conn->prepare($query_almacen);
$stmt_almacen->execute();
$componentes_disponibles = $stmt_almacen->fetchAll(PDO::FETCH_ASSOC);

// OBTENER SOLICITUDES PENDIENTES (Opcional, pero bueno para prevenir duplicados)
$stmt_check = $conn->prepare("SELECT ID FROM SOLICITUD_COMPONENTE WHERE ID_Incidencia = ? AND Estatus = 'Pendiente'");
$stmt_check->execute([$incidencia_id]);
if ($stmt_check->rowCount() > 0) {
    // Si ya existe una solicitud pendiente, redirigir
    header("Location: ver-incidencia.php?id=$incidencia_id&error=pending_request_exists");
    exit;
}

$error = null;

// Procesar el formulario de solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $almacen_id_seleccionado = $_POST['almacen_id_seleccionado'] ?? null; 
    $componente_solicitado = trim($_POST['componente_solicitado'] ?? '');
    $cantidad = 0; 

    $costo_maximo = floatval($_POST['costo_maximo'] ?? 0.00); 
    
    // Lógica: Se prioriza el Stock de Almacén.
    if (!empty($almacen_id_seleccionado)) {
        // Opción 1: Se seleccionó un componente del Almacén (Petición de Stock)
        $selected_comp_data = array_filter($componentes_disponibles, function($comp) use ($almacen_id_seleccionado) {
            return $comp['ID'] == $almacen_id_seleccionado;
        });
        $selected_comp = reset($selected_comp_data); 

        if ($selected_comp) {
            $cantidad_solicitada = intval($_POST['cantidad_almacen'] ?? 1); 
            
            if ($cantidad_solicitada > $selected_comp['CantidadStock']) {
                $error = "La cantidad solicitada (" . $cantidad_solicitada . ") excede el stock disponible (" . $selected_comp['CantidadStock'] . ").";
                goto end_processing;
            }
            if ($cantidad_solicitada <= 0) {
                 $error = "La cantidad solicitada debe ser mayor a cero.";
                goto end_processing;
            }

            // Datos que se guardarán en SOLICITUD_COMPONENTE
            $componente_solicitado = $selected_comp['NombreComponente'] . " (Parte: " . $selected_comp['NumeroParte'] . ")";
            $costo_maximo = $selected_comp['CostoPromedio']; 
            $cantidad = $cantidad_solicitada;
        } else {
            $error = "El componente seleccionado no es válido o ya no está disponible.";
            goto end_processing;
        }

    } else {
        // Opción 2: Solicitud genérica (No hay stock o se requiere algo específico)
        $cantidad = intval($_POST['cantidad_generica'] ?? 0); 
        
        if (empty($componente_solicitado) || $cantidad <= 0 || $costo_maximo <= 0) {
            $error = "Debe seleccionar un componente de stock O especificar el nombre, la cantidad y el costo máximo.";
            goto end_processing;
        }
    }
    
    try {
        // 1. Insertar la solicitud de componente con estatus "Pendiente"
        $stmt_insert = $conn->prepare("INSERT INTO SOLICITUD_COMPONENTE 
            (ID_Incidencia, ComponenteSolicitado, Cantidad, CostoMaximo, Estatus, ID_UsuarioTecnico, FechaRegistro) 
            VALUES (?, ?, ?, ?, 'Pendiente', ?, GETDATE())");
            
        if ($stmt_insert->execute([$incidencia_id, $componente_solicitado, $cantidad, $costo_maximo, $_SESSION['user_id']])) {
            
            // 2. Agregar un comentario interno sobre la solicitud
            $comentario = "Se ha generado una nueva solicitud: **$componente_solicitado** (x$cantidad) con costo máximo de $$costo_maximo. Pendiente de aprobación/asignación.";
            // Si es stock, añadir nota
            if (!empty($almacen_id_seleccionado)) {
                 $comentario .= " [Solicitud de Stock ID: $almacen_id_seleccionado]";
            }

            $stmt_comment = $conn->prepare("INSERT INTO INCIDENCIA_COMENTARIO 
                (ID_Incidencia, ID_Usuario, Comentario, TipoComentario, FechaRegistro, Publico) 
                VALUES (?, ?, ?, 'SOLICITUD_COMPONENTE', GETDATE(), 0)"); 
            $stmt_comment->execute([$incidencia_id, $_SESSION['user_id'], $comentario]);
            
            // 3. Redirigir tras éxito
            header("Location: ver-incidencia.php?id=$incidencia_id&success=request_sent");
            exit;
        } else {
            $error = "Error al guardar la solicitud en la base de datos.";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
    
    end_processing:; 
}
?>

<h1 class="h2">Solicitar Componente para Incidencia #<?php echo $incidencia_id; ?></h1>

<div class="row mb-4">
    <div class="col-12">
        <a href="ver-incidencia.php?id=<?php echo $incidencia_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver a la Incidencia
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Detalles de la Petición (Paso 7)</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" id="form_solicitud_componente">
                    
                    <div class="mb-4 p-3 border rounded bg-light">
                        <h6>1. Solicitar Stock de Almacén</h6>
                        <div class="form-text mb-2">
                            Priorice el uso de componentes existentes en bodega.
                        </div>

                        <label for="almacen_id_seleccionado" class="form-label">Componente de Stock Disponible:</label>
                        <select class="form-select mb-3" id="almacen_id_seleccionado" name="almacen_id_seleccionado">
                            <option value="">-- Seleccionar Componente (Stock Actual) --</option>
                            <?php foreach ($componentes_disponibles as $comp): ?>
                                <option value="<?php echo $comp['ID']; ?>" 
                                        data-stock="<?php echo $comp['CantidadStock']; ?>">
                                    <?php echo htmlspecialchars($comp['NombreComponente']); ?> (Stock: <?php echo $comp['CantidadStock']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="stock_info" class="alert alert-info py-1 mb-2" style="display:none;"></div>

                        <div id="cantidad_stock_div" class="mb-3" style="display:none;">
                            <label for="cantidad_almacen" class="form-label">Cantidad a Pedir:</label>
                            <input type="number" class="form-control" id="cantidad_almacen" name="cantidad_almacen" min="1" value="1">
                        </div>

                        <?php if (empty($componentes_disponibles)): ?>
                            <div class="alert alert-warning mt-2 mb-0">No hay componentes disponibles en stock. Use la sección 2.</div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>

                    <div class="mb-3" id="seccion_generica">
                        <h6>2. Solicitud Genérica / Compra</h6>
                        <div class="form-text mb-3">
                            Solo complete esta sección si el componente no está en stock.
                        </div>

                        <div class="mb-3">
                            <label for="componente_solicitado" class="form-label">Nombre del Componente Requerido:</label>
                            <input type="text" class="form-control" id="componente_solicitado" name="componente_solicitado" 
                                   placeholder="Ej: Tarjeta de video RTX 3060" 
                                   value="<?php echo htmlspecialchars($_POST['componente_solicitado'] ?? ''); ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cantidad_generica" class="form-label">Cantidad:</label>
                                <input type="number" class="form-control" id="cantidad_generica" name="cantidad_generica" min="1" 
                                       value="<?php echo htmlspecialchars($_POST['cantidad_generica'] ?? 0); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="costo_maximo" class="form-label">Costo Máximo ($):</label>
                                <input type="number" class="form-control" id="costo_maximo" name="costo_maximo" min="0.01" step="0.01"
                                       value="<?php echo htmlspecialchars($_POST['costo_maximo'] ?? '0.00'); ?>">
                                <div class="form-text">Costo unitario máximo.</div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud al Almacén
                    </button>
                </form>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const form = document.getElementById('form_solicitud_componente');
                        const almacenSelect = document.getElementById('almacen_id_seleccionado');
                        const cantidadStockDiv = document.getElementById('cantidad_stock_div');
                        const cantidadAlmacenInput = document.getElementById('cantidad_almacen');
                        const stockInfo = document.getElementById('stock_info');

                        // Campos de solicitud genérica
                        const nombreComp = document.getElementById('componente_solicitado');
                        const cantidadGen = document.getElementById('cantidad_generica');
                        const costoMax = document.getElementById('costo_maximo');

                        function toggleStockInputs(selectedValue) {
                            if (selectedValue) {
                                // Modo Stock
                                const selectedOption = almacenSelect.options[almacenSelect.selectedIndex];
                                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                                
                                stockInfo.innerHTML = `Stock disponible: <strong>${stock}</strong> unidad(es).`;
                                stockInfo.style.display = 'block';
                                cantidadStockDiv.style.display = 'block';
                                cantidadAlmacenInput.setAttribute('max', stock);
                                cantidadAlmacenInput.value = (stock > 0) ? 1 : 0;
                                cantidadAlmacenInput.required = true;

                                // Desactivar y limpiar campos genéricos
                                nombreComp.value = '';
                                cantidadGen.value = '0';
                                costoMax.value = '0.00';
                                nombreComp.disabled = true;
                                cantidadGen.disabled = true;
                                costoMax.disabled = true;

                            } else {
                                // Modo Genérico
                                stockInfo.style.display = 'none';
                                cantidadStockDiv.style.display = 'none';
                                cantidadAlmacenInput.value = '0';
                                cantidadAlmacenInput.required = false;

                                // Activar campos genéricos
                                nombreComp.disabled = false;
                                cantidadGen.disabled = false;
                                costoMax.disabled = false;
                                cantidadGen.value = '1'; // Resetear cantidad genérica
                            }
                        }

                        // Inicializar y escuchar cambios
                        almacenSelect.addEventListener('change', function() {
                            toggleStockInputs(this.value);
                        });
                        
                        // Si se empieza a escribir en genérico, deseleccionar stock
                        [nombreComp, cantidadGen, costoMax].forEach(input => {
                            input.addEventListener('input', function() {
                                if (almacenSelect.value !== '') {
                                    almacenSelect.value = '';
                                    toggleStockInputs('');
                                }
                            });
                        });
                        
                        // Inicializar el estado al cargar
                        toggleStockInputs(almacenSelect.value);

                        // Validación final al enviar
                        form.addEventListener('submit', function(event) {
                            const ciSelected = almacenSelect.value;
                            const nombreGeneric = nombreComp.value.trim();
                            const cantidadValue = parseInt(cantidadGen.value);
                            const costoValue = parseFloat(costoMax.value);
                            const cantidadStockValue = parseInt(cantidadAlmacenInput.value);
                            
                            if (ciSelected) {
                                if (cantidadStockValue <= 0) {
                                    alert('Debe especificar una cantidad a solicitar mayor a cero.');
                                    event.preventDefault();
                                }
                                return;
                            }
                            
                            // Si NO selecciona stock, debe llenar los campos genéricos
                            if (nombreGeneric === '' || cantidadValue <= 0 || costoValue <= 0) {
                                alert('Debe seleccionar un componente de stock O llenar completamente la sección de solicitud genérica (Nombre, Cantidad y Costo Máximo).');
                                event.preventDefault();
                            }
                        });
                    });
                </script>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>