<?php
// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión de CIs
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar si se proporcionó el ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redireccionar a la lista
    header("Location: gestion-ci.php?error=missing_id");
    exit;
}

$ci_id = $_GET['id'];

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Incluir modelos de componentes
require_once '../../models/Componente.php';
require_once '../../models/CIComponente.php';

// Instanciar modelos
$componente = new Componente($conn);
$ciComponente = new CIComponente($conn);

// Obtener datos del CI
try {
    $query = "SELECT ci.ID, ci.Nombre, ci.Descripcion, ci.NumSerie, ci.FechaAdquisicion, 
              ci.ID_TipoCI, ci.ID_Localizacion, ci.ID_Encargado, ci.ID_Proveedor,
              l.ID_Edificio
              FROM CI ci
              LEFT JOIN LOCALIZACION l ON ci.ID_Localizacion = l.ID
              WHERE ci.ID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$ci_id]);
    
    if ($stmt->rowCount() == 0) {
        // CI no encontrado
        header("Location: gestion-ci.php?error=not_found");
        exit;
    }
    
    $ci = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener componentes de hardware
    $componentesHWStmt = $componente->getByTipo('HW');
    $componentesHW = $componentesHWStmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener componentes de software
    $componentesSWStmt = $componente->getByTipo('SW');
    $componentesSW = $componentesSWStmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener categorías de hardware y software
    $categoriasHW = $componente->getCategoriasHardware();
    $categoriasSW = $componente->getCategoriasSoftware();

    // Agrupar componentes de hardware por categoría
    $hardwareByCategoria = [];
    foreach ($componentesHW as $hw) {
        if (!isset($hardwareByCategoria[$hw['Categoria']])) {
            $hardwareByCategoria[$hw['Categoria']] = [];
        }
        $hardwareByCategoria[$hw['Categoria']][] = $hw;
    }

    // Agrupar componentes de software por categoría
    $softwareByCategoria = [];
    foreach ($componentesSW as $sw) {
        if (!isset($softwareByCategoria[$sw['Categoria']])) {
            $softwareByCategoria[$sw['Categoria']] = [];
        }
        $softwareByCategoria[$sw['Categoria']][] = $sw;
    }

    // Obtener componentes actuales del CI
    $componentesActualesHWStmt = $ciComponente->getComponentesByCiYTipo($ci_id, 'HW');
    $componentesActualesSWStmt = $ciComponente->getComponentesByCiYTipo($ci_id, 'SW');

    // Obtener todos los componentes en arrays
    $componentesActualesHW = $componentesActualesHWStmt->fetchAll(PDO::FETCH_ASSOC);
    $componentesActualesSW = $componentesActualesSWStmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear arrays para marcar los componentes ya seleccionados
    $hwSeleccionados = [];
    $hwCantidades = [];
    $hwNotas = [];
    foreach ($componentesActualesHW as $hw) {
        $hwSeleccionados[] = $hw['ID_Componente'];
        $hwCantidades[$hw['ID_Componente']] = $hw['Cantidad'];
        $hwNotas[$hw['ID_Componente']] = $hw['Notas'];
    }

    $swSeleccionados = [];
    $swNotas = [];
    foreach ($componentesActualesSW as $sw) {
        $swSeleccionados[] = $sw['ID_Componente'];
        $swNotas[$sw['ID_Componente']] = $sw['Notas'];
    }
    
    // Obtener listas para los selectores
    $tipoStmt = $conn->prepare("SELECT ID, Nombre FROM TIPO_CI ORDER BY Nombre");
    $tipoStmt->execute();
    
    $proveedorStmt = $conn->prepare("SELECT ID, Nombre FROM PROVEEDOR ORDER BY Nombre");
    $proveedorStmt->execute();
    
    $edificioStmt = $conn->prepare("SELECT ID, Nombre FROM EDIFICIO ORDER BY Nombre");
    $edificioStmt->execute();
    
    $empleadoStmt = $conn->prepare("SELECT ID, Nombre FROM EMPLEADO ORDER BY Nombre");
    $empleadoStmt->execute();
    
    // Obtener localizaciones del edificio seleccionado
    $localizacionStmt = $conn->prepare("SELECT ID, Nombre FROM LOCALIZACION WHERE ID_Edificio = ? ORDER BY NumPlanta, Nombre");
    $localizacionStmt->execute([$ci['ID_Edificio']]);
    
    // Procesar el formulario si se envió
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Recoger datos del formulario
            $nombre = $_POST['nombre'];
            $descripcion = $_POST['descripcion'];
            $numSerie = $_POST['num_serie'];
            $fechaAdquisicion = $_POST['fecha_adquisicion'];
            $tipoCI = $_POST['tipo_ci'];
            $localizacion = $_POST['localizacion'];
            $encargado = $_POST['encargado'];
            $proveedor = $_POST['proveedor'];
            
            // Preparar la consulta SQL para actualizar el CI
            $updateQuery = "UPDATE CI SET 
                           Nombre = ?, 
                           Descripcion = ?, 
                           NumSerie = ?, 
                           FechaAdquisicion = ?, 
                           ID_TipoCI = ?, 
                           ID_Localizacion = ?, 
                           ID_Encargado = ?, 
                           ID_Proveedor = ?,
                           ModifiedBy = ?,
                           ModifiedDate = GETDATE()
                           WHERE ID = ?";
            
            $updateStmt = $conn->prepare($updateQuery);
            
            // Vincular parámetros
            $updateStmt->bindParam(1, $nombre);
            $updateStmt->bindParam(2, $descripcion);
            $updateStmt->bindParam(3, $numSerie);
            $updateStmt->bindParam(4, $fechaAdquisicion);
            $updateStmt->bindParam(5, $tipoCI);
            $updateStmt->bindParam(6, $localizacion);
            $updateStmt->bindParam(7, $encargado);
            $updateStmt->bindParam(8, $proveedor);
            $updateStmt->bindParam(9, $_SESSION['user_id']);
            $updateStmt->bindParam(10, $ci_id);
            
            // Ejecutar la consulta
            $updateStmt->execute();
            
            // Eliminar todos los componentes actuales para volver a agregarlos
            $ciComponente->deleteAllByCi($ci_id);
            
            // Guardar componentes de hardware seleccionados
            if (isset($_POST['hw_componentes']) && is_array($_POST['hw_componentes'])) {
                foreach ($_POST['hw_componentes'] as $componente_id) {
                    // Obtener cantidad y notas si existen
                    $cantidad = isset($_POST['hw_cantidad'][$componente_id]) ? $_POST['hw_cantidad'][$componente_id] : 1;
                    $notas = isset($_POST['hw_notas'][$componente_id]) ? $_POST['hw_notas'][$componente_id] : '';
                    
                    $ciComponente->id_ci = $ci_id;
                    $ciComponente->id_componente = $componente_id;
                    $ciComponente->cantidad = $cantidad;
                    $ciComponente->notas = $notas;
                    $ciComponente->created_by = $_SESSION['user_id'];
                    $ciComponente->save();
                }
            }
            
            // Guardar componentes de software seleccionados
            if (isset($_POST['sw_componentes']) && is_array($_POST['sw_componentes'])) {
                foreach ($_POST['sw_componentes'] as $componente_id) {
                    // Obtener notas si existen
                    $notas = isset($_POST['sw_notas'][$componente_id]) ? $_POST['sw_notas'][$componente_id] : '';
                    
                    $ciComponente->id_ci = $ci_id;
                    $ciComponente->id_componente = $componente_id;
                    $ciComponente->cantidad = 1; // La cantidad de software siempre es 1
                    $ciComponente->notas = $notas;
                    $ciComponente->created_by = $_SESSION['user_id'];
                    $ciComponente->save();
                }
            }
            
            // Redireccionar a la página de detalle del CI con mensaje de éxito
            header("Location: ver-ci.php?id=" . $ci_id . "&success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    $error = "Error en la base de datos: " . $e->getMessage();
}
?>

<!-- Título de la página -->
<h1 class="h2">Editar Elemento de Configuración</h1>

<!-- Formulario para editar CI -->
<div class="row">
    <div class="col-12">
        <div class="form-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="" method="POST" id="formCI">
                <!-- Información básica del CI -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Información Básica</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="20" value="<?php echo htmlspecialchars($ci['Nombre']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="tipo_ci" class="form-label">Tipo de CI *</label>
                                <select class="form-select" id="tipo_ci" name="tipo_ci" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <?php while ($tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $tipo['ID']; ?>" <?php echo ($ci['ID_TipoCI'] == $tipo['ID']) ? 'selected' : ''; ?>>
                                            <?php echo $tipo['Nombre']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="descripcion" class="form-label">Descripción *</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" required maxlength="200"><?php echo htmlspecialchars($ci['Descripcion']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="num_serie" class="form-label">Número de Serie *</label>
                                <input type="text" class="form-control" id="num_serie" name="num_serie" required maxlength="30" value="<?php echo htmlspecialchars($ci['NumSerie']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="fecha_adquisicion" class="form-label">Fecha de Adquisición *</label>
                                <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion" required value="<?php echo date('Y-m-d', strtotime($ci['FechaAdquisicion'])); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edificio" class="form-label">Edificio *</label>
                                <select class="form-select" id="edificio" name="edificio" required>
                                    <option value="">Seleccionar edificio...</option>
                                    <?php while ($edificio = $edificioStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $edificio['ID']; ?>" <?php echo ($ci['ID_Edificio'] == $edificio['ID']) ? 'selected' : ''; ?>>
                                            <?php echo $edificio['Nombre']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="localizacion" class="form-label">Localización *</label>
                                <select class="form-select" id="localizacion" name="localizacion" required>
                                    <option value="">Seleccionar localización...</option>
                                    <?php while ($localizacion = $localizacionStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $localizacion['ID']; ?>" <?php echo ($ci['ID_Localizacion'] == $localizacion['ID']) ? 'selected' : ''; ?>>
                                            <?php echo $localizacion['Nombre']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="encargado" class="form-label">Encargado *</label>
                                <select class="form-select" id="encargado" name="encargado" required>
                                    <option value="">Seleccionar encargado...</option>
                                    <?php while ($empleado = $empleadoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $empleado['ID']; ?>" <?php echo ($ci['ID_Encargado'] == $empleado['ID']) ? 'selected' : ''; ?>>
                                            <?php echo $empleado['Nombre']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="proveedor" class="form-label">Proveedor *</label>
                                <select class="form-select" id="proveedor" name="proveedor" required>
                                    <option value="">Seleccionar proveedor...</option>
                                    <?php while ($proveedor = $proveedorStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $proveedor['ID']; ?>" <?php echo ($ci['ID_Proveedor'] == $proveedor['ID']) ? 'selected' : ''; ?>>
                                            <?php echo $proveedor['Nombre']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Componentes de Hardware -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Componentes de Hardware</h5>
                        <small class="text-muted">Seleccione los componentes de hardware que conforman este equipo</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Pestañas para las categorías de hardware -->
                            <div class="col-12 mb-3">
                                <ul class="nav nav-tabs" id="hardwareTabs" role="tablist">
                                    <?php $firstHW = true; ?>
                                    <?php foreach ($categoriasHW as $categoria_code => $categoria_name): ?>
                                        <?php if (isset($hardwareByCategoria[$categoria_code])): ?>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link <?php echo $firstHW ? 'active' : ''; ?>" 
                                                        id="hw-<?php echo $categoria_code; ?>-tab" 
                                                        data-bs-toggle="tab" 
                                                        data-bs-target="#hw-<?php echo $categoria_code; ?>" 
                                                        type="button" 
                                                        role="tab" 
                                                        aria-controls="hw-<?php echo $categoria_code; ?>" 
                                                        aria-selected="<?php echo $firstHW ? 'true' : 'false'; ?>">
                                                    <?php echo $categoria_name; ?>
                                                </button>
                                            </li>
                                            <?php $firstHW = false; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <div class="tab-content" id="hardwareTabsContent">
                                    <?php $firstHW = true; ?>
                                    <?php foreach ($categoriasHW as $categoria_code => $categoria_name): ?>
                                        <?php if (isset($hardwareByCategoria[$categoria_code])): ?>
                                            <div class="tab-pane fade <?php echo $firstHW ? 'show active' : ''; ?>" 
                                                 id="hw-<?php echo $categoria_code; ?>" 
                                                 role="tabpanel" 
                                                 aria-labelledby="hw-<?php echo $categoria_code; ?>-tab">
                                                
                                                <div class="table-responsive mt-3">
                                                    <table class="table table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 5%"></th>
                                                                <th style="width: 25%">Componente</th>
                                                                <th style="width: 20%">Fabricante</th>
                                                                <th style="width: 20%">Modelo</th>
                                                                <th style="width: 10%">Cantidad</th>
                                                                <th style="width: 20%">Notas</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($hardwareByCategoria[$categoria_code] as $hw): ?>
                                                                <?php 
                                                                $isSelected = in_array($hw['ID'], $hwSeleccionados);
                                                                $cantidad = $isSelected && isset($hwCantidades[$hw['ID']]) ? $hwCantidades[$hw['ID']] : 1;
                                                                $notas = $isSelected && isset($hwNotas[$hw['ID']]) ? $hwNotas[$hw['ID']] : '';
                                                                ?>
                                                                <tr>
                                                                    <td>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input hw-checkbox" type="checkbox" 
                                                                                   name="hw_componentes[]" 
                                                                                   value="<?php echo $hw['ID']; ?>" 
                                                                                   id="hw-check-<?php echo $hw['ID']; ?>"
                                                                                   <?php echo $isSelected ? 'checked' : ''; ?>>
                                                                        </div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($hw['Nombre']); ?></td>
                                                                    <td><?php echo htmlspecialchars($hw['Fabricante']); ?></td>
                                                                    <td><?php echo htmlspecialchars($hw['Modelo']); ?></td>
                                                                    <td>
                                                                        <input type="number" class="form-control form-control-sm" 
                                                                               name="hw_cantidad[<?php echo $hw['ID']; ?>]" 
                                                                               value="<?php echo $cantidad; ?>" 
                                                                               min="1"
                                                                               <?php echo $isSelected ? '' : 'disabled'; ?>>
                                                                    </td>
                                                                    <td>
                                                                        <input type="text" class="form-control form-control-sm" 
                                                                               name="hw_notas[<?php echo $hw['ID']; ?>]" 
                                                                               placeholder="Notas adicionales"
                                                                               value="<?php echo htmlspecialchars($notas); ?>"
                                                                               <?php echo $isSelected ? '' : 'disabled'; ?>>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <?php $firstHW = false; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Componentes de Software -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Software Instalado</h5>
                        <small class="text-muted">Seleccione el software permitido instalado en este equipo</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Pestañas para las categorías de software -->
                            <div class="col-12 mb-3">
                                <ul class="nav nav-tabs" id="softwareTabs" role="tablist">
                                    <?php $firstSW = true; ?>
                                    <?php foreach ($categoriasSW as $categoria_code => $categoria_name): ?>
                                        <?php if (isset($softwareByCategoria[$categoria_code])): ?>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link <?php echo $firstSW ? 'active' : ''; ?>" 
                                                        id="sw-<?php echo $categoria_code; ?>-tab" 
                                                        data-bs-toggle="tab" 
                                                        data-bs-target="#sw-<?php echo $categoria_code; ?>" 
                                                        type="button" 
                                                        role="tab" 
                                                        aria-controls="sw-<?php echo $categoria_code; ?>" 
                                                        aria-selected="<?php echo $firstSW ? 'true' : 'false'; ?>">
                                                    <?php echo $categoria_name; ?>
                                                </button>
                                            </li>
                                            <?php $firstSW = false; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <div class="tab-content" id="softwareTabsContent">
                                    <?php $firstSW = true; ?>
                                    <?php foreach ($categoriasSW as $categoria_code => $categoria_name): ?>
                                        <?php if (isset($softwareByCategoria[$categoria_code])): ?>
                                            <div class="tab-pane fade <?php echo $firstSW ? 'show active' : ''; ?>" 
                                                 id="sw-<?php echo $categoria_code; ?>" 
                                                 role="tabpanel" 
                                                 aria-labelledby="sw-<?php echo $categoria_code; ?>-tab">
                                                
                                                <div class="table-responsive mt-3">
                                                    <table class="table table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 5%"></th>
                                                                <th style="width: 30%">Software</th>
                                                                <th style="width: 25%">Fabricante</th>
                                                                <th style="width: 25%">Versión</th>
                                                                <th style="width: 15%">Notas</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($softwareByCategoria[$categoria_code] as $sw): ?>
                                                                <?php 
                                                                $isSelected = in_array($sw['ID'], $swSeleccionados);
                                                                $notas = $isSelected && isset($swNotas[$sw['ID']]) ? $swNotas[$sw['ID']] : '';
                                                                ?>
                                                                <tr>
                                                                    <td>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input sw-checkbox" type="checkbox" 
                                                                                   name="sw_componentes[]" 
                                                                                   value="<?php echo $sw['ID']; ?>" 
                                                                                   id="sw-check-<?php echo $sw['ID']; ?>"
                                                                                   <?php echo $isSelected ? 'checked' : ''; ?>>
                                                                        </div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($sw['Nombre']); ?></td>
                                                                    <td><?php echo htmlspecialchars($sw['Fabricante']); ?></td>
                                                                    <td><?php echo htmlspecialchars($sw['Modelo']); ?></td>
                                                                    <td>
                                                                        <input type="text" class="form-control form-control-sm" 
                                                                               name="sw_notas[<?php echo $sw['ID']; ?>]" 
                                                                               placeholder="Notas adicionales"
                                                                               value="<?php echo htmlspecialchars($notas); ?>"
                                                                               <?php echo $isSelected ? '' : 'disabled'; ?>>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <?php $firstSW = false; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-success">Guardar Cambios</button>
                        <a href="ver-ci.php?id=<?php echo $ci_id; ?>" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script para cargar localizaciones basadas en el edificio seleccionado -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Referencia al selector de edificio
    const edificioSelect = document.getElementById('edificio');
    const localizacionSelect = document.getElementById('localizacion');
    const localizacionValorActual = '<?php echo $ci['ID_Localizacion']; ?>';
    
    // Función para cargar localizaciones
    function cargarLocalizaciones() {
        const edificioId = edificioSelect.value;
        
        // Resetear selector de localización
        localizacionSelect.innerHTML = '<option value="">Seleccionar localización...</option>';
        
        if (edificioId) {
            // Realizar petición AJAX para obtener localizaciones
            fetch('../../api/get_localizaciones.php?edificio_id=' + edificioId)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        // Llenar selector de localizaciones
                        data.forEach(loc => {
                            const option = document.createElement('option');
                            option.value = loc.ID;
                            option.textContent = loc.Nombre;
                            
                            // Seleccionar la opción actual
                            if (loc.ID == localizacionValorActual) {
                                option.selected = true;
                            }
                            
                            localizacionSelect.appendChild(option);
                        });
                    } else {
                        localizacionSelect.innerHTML = '<option value="">No hay localizaciones disponibles</option>';
                    }
                })
                .catch(error => {
                    console.error('Error al cargar localizaciones:', error);
                    localizacionSelect.innerHTML = '<option value="">Error al cargar localizaciones</option>';
                });
        }
    }
    
    // Asignar evento al cambio de edificio
    edificioSelect.addEventListener('change', cargarLocalizaciones);
    
    // Scripts para componentes
    const hwCheckboxes = document.querySelectorAll('.hw-checkbox');
    const swCheckboxes = document.querySelectorAll('.sw-checkbox');
    
    // Función para manejar los campos de cantidad y notas según selección
    function toggleInputFields(checkbox) {
        const tr = checkbox.closest('tr');
        const inputs = tr.querySelectorAll('input[type="text"], input[type="number"]');
        
        inputs.forEach(input => {
            input.disabled = !checkbox.checked;
            if (!checkbox.checked) {
                input.value = input.type === 'number' ? '1' : '';
            }
        });
    }
    
    // Asignar eventos a los checkboxes de hardware
    hwCheckboxes.forEach(checkbox => {
        // Asignar evento change
        checkbox.addEventListener('change', function() {
            toggleInputFields(this);
        });
    });
    
    // Asignar eventos a los checkboxes de software
    swCheckboxes.forEach(checkbox => {
        // Asignar evento change
        checkbox.addEventListener('change', function() {
            toggleInputFields(this);
        });
    });
});
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>