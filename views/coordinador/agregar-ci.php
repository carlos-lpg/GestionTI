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
require_once '../../models/Componente.php';

// Conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Instanciar modelo de componentes
$componente = new Componente($conn);

// Obtener los departamentos asignados al usuario actual
$deptos_query = "SELECT d.ID, d.Nombre 
                 FROM EMPLEADO_DEPTO ed 
                 JOIN DEPARTAMENTO d ON ed.ID_Depto = d.ID 
                 WHERE ed.ID_Empleado = ?";
$deptos_stmt = $conn->prepare($deptos_query);
$deptos_stmt->execute([$_SESSION['empleado_id']]);
$departamentos_usuario = $deptos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Si el usuario no tiene departamentos asignados y no es administrador, mostrar error
if (count($departamentos_usuario) == 0 && $_SESSION['role_name'] != 'Administrador') {
    $error = "No tiene departamentos asignados para agregar elementos de configuración.";
}

// Obtener listas para los selectores
$tipoStmt = $conn->prepare("SELECT ID, Nombre FROM TIPO_CI ORDER BY Nombre");
$tipoStmt->execute();

$proveedorStmt = $conn->prepare("SELECT ID, Nombre FROM PROVEEDOR ORDER BY Nombre");
$proveedorStmt->execute();

// Para administradores, mostrar todos los departamentos
if ($_SESSION['role_name'] == 'Administrador') {
    $deptoStmt = $conn->prepare("SELECT ID, Nombre FROM DEPARTAMENTO ORDER BY Nombre");
    $deptoStmt->execute();
    $departamentos = $deptoStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Para otros usuarios, usar los departamentos ya obtenidos
    $departamentos = $departamentos_usuario;
}

// Obtener componentes de hardware
$componentesHWStmt = $componente->getByTipo('HW');
$componentesHW = $componentesHWStmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar componentes de hardware por categoría
$hardwareByCategoria = [];
foreach ($componentesHW as $hw) {
    if (!isset($hardwareByCategoria[$hw['Categoria']])) {
        $hardwareByCategoria[$hw['Categoria']] = [];
    }
    $hardwareByCategoria[$hw['Categoria']][] = $hw;
}

// Obtener componentes de software
$componentesSWStmt = $componente->getByTipo('SW');
$componentesSW = $componentesSWStmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar componentes de software por categoría
$softwareByCategoria = [];
foreach ($componentesSW as $sw) {
    if (!isset($softwareByCategoria[$sw['Categoria']])) {
        $softwareByCategoria[$sw['Categoria']] = [];
    }
    $softwareByCategoria[$sw['Categoria']][] = $sw;
}

// Obtener categorías de hardware y software
$categoriasHW = $componente->getCategoriasHardware();
$categoriasSW = $componente->getCategoriasSoftware();

// Obtener los edificios permitidos según el rol
$edificios_permitidos = array();

if ($_SESSION['role_name'] == 'Administrador' || 
    $_SESSION['role_name'] == 'Gerente TI' || 
    $_SESSION['role_name'] == 'Técnico TI' || 
    $_SESSION['role_name'] == 'Encargado Inventario' || 
    $_SESSION['role_name'] == 'Supervisor Infraestructura' || 
    $_SESSION['role_name'] == 'Supervisor Sistemas') {
    // Estos roles pueden agregar elementos en cualquier edificio
    $edificios_query = "SELECT ID, Nombre FROM EDIFICIO ORDER BY Nombre";
    $edificioStmt = $conn->prepare($edificios_query);
    $edificioStmt->execute();
    $edificios = $edificioStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Para roles específicos de ubicación, filtrar por categoría de ubicación
    switch ($_SESSION['role_name']) {
        case 'Coordinador TI CEDIS':
        case 'Usuario CEDIS':
            // Solo puede agregar en edificios CEDIS
            $edificios_query = "SELECT e.ID, e.Nombre 
                               FROM EDIFICIO e
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE cu.Nombre = 'CEDIS'
                               ORDER BY e.Nombre";
            break;
            
        case 'Coordinador TI Sucursales':
            // Solo puede agregar en edificios de Sucursales
            $edificios_query = "SELECT e.ID, e.Nombre 
                               FROM EDIFICIO e
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE cu.Nombre = 'Sucursal'
                               ORDER BY e.Nombre";
            break;
            
        case 'Coordinador TI Corporativo':
            // Solo puede agregar en edificios Corporativos
            $edificios_query = "SELECT e.ID, e.Nombre 
                               FROM EDIFICIO e
                               JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                               WHERE cu.Nombre = 'Corporativo'
                               ORDER BY e.Nombre";
            break;
            
        default:
            // Usuario Final y otros roles no especificados - no permitir agregar
            $edificios = array();
            $error = "Su rol no tiene permisos para agregar elementos de configuración.";
    }
    
    if (!isset($error)) {
        $edificioStmt = $conn->prepare($edificios_query);
        $edificioStmt->execute();
        $edificios = $edificioStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$empleadoStmt = $conn->prepare("SELECT ID, Nombre FROM EMPLEADO ORDER BY Nombre");
$empleadoStmt->execute();

// Variable para almacenar localizaciones (se llenará mediante AJAX)
$localizaciones = [];

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recoger datos del formulario
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $numSerie = $_POST['num_serie'];
        $fechaAdquisicion = $_POST['fecha_adquisicion'];
        $tipoCI = $_POST['tipo_ci'];
        $departamento = $_POST['departamento'];
        $localizacion = $_POST['localizacion'];
        $edificio = $_POST['edificio'];
        $encargado = $_POST['encargado'];
        $proveedor = $_POST['proveedor'];
        
        // Verificar si el usuario tiene permiso para este departamento
        $tiene_permiso = false;
        if ($_SESSION['role_name'] == 'Administrador') {
            $tiene_permiso = true;
        } else {
            foreach ($departamentos_usuario as $depto) {
                if ($depto['ID'] == $departamento) {
                    $tiene_permiso = true;
                    break;
                }
            }
        }
        
        if (!$tiene_permiso) {
            $error = "No tiene permiso para agregar elementos a este departamento.";
        } else {
            // Verificar que el edificio seleccionado esté permitido para este usuario
            $edificio_permitido = false;
            if ($_SESSION['role_name'] == 'Administrador' || 
                $_SESSION['role_name'] == 'Gerente TI' || 
                $_SESSION['role_name'] == 'Técnico TI' || 
                $_SESSION['role_name'] == 'Encargado Inventario' || 
                $_SESSION['role_name'] == 'Supervisor Infraestructura' || 
                $_SESSION['role_name'] == 'Supervisor Sistemas') {
                $edificio_permitido = true;
            } else {
                // Verificar que el edificio pertenezca a la categoría correcta
                $verificar_query = "SELECT 1 FROM EDIFICIO e
                                  JOIN CATEGORIA_UBICACION cu ON e.ID_CategoriaUbicacion = cu.ID
                                  WHERE e.ID = ?";
                
                // Añadir la condición según el rol
                switch ($_SESSION['role_name']) {
                    case 'Coordinador TI CEDIS':
                    case 'Usuario CEDIS':
                        $verificar_query .= " AND cu.Nombre = 'CEDIS'";
                        break;
                    case 'Coordinador TI Sucursales':
                        $verificar_query .= " AND cu.Nombre = 'Sucursal'";
                        break;
                    case 'Coordinador TI Corporativo':
                        $verificar_query .= " AND cu.Nombre = 'Corporativo'";
                        break;
                    default:
                        break;
                }
                
                $verificar_stmt = $conn->prepare($verificar_query);
                $verificar_stmt->execute([$edificio]);
                $edificio_permitido = ($verificar_stmt->rowCount() > 0);
            }
            
            if (!$edificio_permitido) {
                $error = "No tiene permiso para agregar elementos en este edificio.";
            } else {
                // Preparar la consulta SQL para insertar el CI
                $query = "INSERT INTO CI (Nombre, Descripcion, NumSerie, FechaAdquisicion, ID_TipoCI, 
                         ID_Departamento, ID_Localizacion, ID_Encargado, ID_Proveedor, CreatedBy, CreatedDate) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
                
                $stmt = $conn->prepare($query);
                
                // Vincular parámetros
                $stmt->execute([
                    $nombre,
                    $descripcion,
                    $numSerie,
                    $fechaAdquisicion,
                    $tipoCI,
                    $departamento,
                    $localizacion,
                    $encargado,
                    $proveedor,
                    $_SESSION['user_id']
                ]);
                
                // Obtener el ID del CI recién insertado
                $ci_id = $conn->lastInsertId();
                
                // Guardar componentes de hardware seleccionados
                if (isset($_POST['hw_componentes']) && is_array($_POST['hw_componentes'])) {
                    foreach ($_POST['hw_componentes'] as $componente_id) {
                        // Obtener cantidad y notas si existen
                        $cantidad = isset($_POST['hw_cantidad'][$componente_id]) ? $_POST['hw_cantidad'][$componente_id] : 1;
                        $notas = isset($_POST['hw_notas'][$componente_id]) ? $_POST['hw_notas'][$componente_id] : '';
                        
                        $comp_query = "INSERT INTO CI_COMPONENTE (ID_CI, ID_Componente, Cantidad, Notas, CreatedBy, CreatedDate) 
                                      VALUES (?, ?, ?, ?, ?, GETDATE())";
                        $comp_stmt = $conn->prepare($comp_query);
                        $comp_stmt->execute([
                            $ci_id,
                            $componente_id,
                            $cantidad,
                            $notas,
                            $_SESSION['user_id']
                        ]);
                    }
                }
                
                // Guardar componentes de software seleccionados
                if (isset($_POST['sw_componentes']) && is_array($_POST['sw_componentes'])) {
                    foreach ($_POST['sw_componentes'] as $componente_id) {
                        // Obtener notas si existen
                        $notas = isset($_POST['sw_notas'][$componente_id]) ? $_POST['sw_notas'][$componente_id] : '';
                        
                        $comp_query = "INSERT INTO CI_COMPONENTE (ID_CI, ID_Componente, Cantidad, Notas, CreatedBy, CreatedDate) 
                                      VALUES (?, ?, ?, ?, ?, GETDATE())";
                        $comp_stmt = $conn->prepare($comp_query);
                        $comp_stmt->execute([
                            $ci_id,
                            $componente_id,
                            1, // La cantidad de software siempre es 1
                            $notas,
                            $_SESSION['user_id']
                        ]);
                    }
                }
                
                // Redireccionar a la página de gestión de CIs con mensaje de éxito
                header("Location: gestion-ci.php?success=1");
                exit;
            }
        }
    } catch (PDOException $e) {
        // Simplemente capturar el error sin hacer rollBack
        $error = "Error al guardar el elemento de configuración: " . $e->getMessage();
    }
}   
?>

<!-- Título de la página -->
<h1 class="h2">Agregar Nuevo Elemento de Configuración</h1>

<!-- Formulario para agregar CI -->
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
                                <input type="text" class="form-control" id="nombre" name="nombre" required maxlength="20">
                            </div>
                            <div class="col-md-6">
                                <label for="tipo_ci" class="form-label">Tipo de CI *</label>
                                <select class="form-select" id="tipo_ci" name="tipo_ci" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <?php while ($tipo = $tipoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $tipo['ID']; ?>"><?php echo $tipo['Nombre']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="descripcion" class="form-label">Descripción *</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" required maxlength="200"></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="num_serie" class="form-label">Número de Serie *</label>
                                <input type="text" class="form-control" id="num_serie" name="num_serie" required maxlength="30">
                            </div>
                            <div class="col-md-6">
                                <label for="fecha_adquisicion" class="form-label">Fecha de Adquisición *</label>
                                <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="departamento" class="form-label">Departamento *</label>
                                <select class="form-select" id="departamento" name="departamento" required>
                                    <option value="">Seleccionar departamento...</option>
                                    <?php foreach ($departamentos as $depto): ?>
                                        <option value="<?php echo $depto['ID']; ?>"><?php echo $depto['Nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="proveedor" class="form-label">Proveedor *</label>
                                <select class="form-select" id="proveedor" name="proveedor" required>
                                    <option value="">Seleccionar proveedor...</option>
                                    <?php while ($proveedor = $proveedorStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $proveedor['ID']; ?>"><?php echo $proveedor['Nombre']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edificio" class="form-label">Edificio *</label>
                                <select class="form-select" id="edificio" name="edificio" required>
                                    <option value="">Seleccionar edificio...</option>
                                    <?php foreach ($edificios as $edificio): ?>
                                        <option value="<?php echo $edificio['ID']; ?>"><?php echo $edificio['Nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="localizacion" class="form-label">Localización *</label>
                                <select class="form-select" id="localizacion" name="localizacion" required disabled>
                                    <option value="">Primero seleccione un edificio...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="encargado" class="form-label">Encargado *</label>
                                <select class="form-select" id="encargado" name="encargado" required>
                                    <option value="">Seleccionar encargado...</option>
                                    <?php while ($empleado = $empleadoStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $empleado['ID']; ?>"><?php echo $empleado['Nombre']; ?></option>
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
                                                                <tr>
                                                                    <td>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" 
                                                                                   name="hw_componentes[]" 
                                                                                   value="<?php echo $hw['ID']; ?>" 
                                                                                   id="hw-check-<?php echo $hw['ID']; ?>">
                                                                        </div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($hw['Nombre']); ?></td>
                                                                    <td><?php echo htmlspecialchars($hw['Fabricante']); ?></td>
                                                                    <td><?php echo htmlspecialchars($hw['Modelo']); ?></td>
                                                                    <td>
                                                                        <input type="number" class="form-control form-control-sm" 
                                                                               name="hw_cantidad[<?php echo $hw['ID']; ?>]" 
                                                                               value="1" min="1">
                                                                    </td>
                                                                    <td>
                                                                        <input type="text" class="form-control form-control-sm" 
                                                                               name="hw_notas[<?php echo $hw['ID']; ?>]" 
                                                                               placeholder="Notas adicionales">
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
                                                                <tr>
                                                                    <td>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" 
                                                                                   name="sw_componentes[]" 
                                                                                   value="<?php echo $sw['ID']; ?>" 
                                                                                   id="sw-check-<?php echo $sw['ID']; ?>">
                                                                        </div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($sw['Nombre']); ?></td>
                                                                    <td><?php echo htmlspecialchars($sw['Fabricante']); ?></td>
                                                                    <td><?php echo htmlspecialchars($sw['Modelo']); ?></td>
                                                                    <td>
                                                                        <input type="text" class="form-control form-control-sm" 
                                                                               name="sw_notas[<?php echo $sw['ID']; ?>]" 
                                                                               placeholder="Notas adicionales">
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
                        <button type="submit" class="btn btn-success">Guardar</button>
                        <a href="gestion-ci.php" class="btn btn-secondary">Cancelar</a>
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
    
    // Función para cargar localizaciones
    function cargarLocalizaciones() {
        const edificioId = edificioSelect.value;
        
        // Resetear selector de localización
        localizacionSelect.innerHTML = '<option value="">Seleccionar localización...</option>';
        localizacionSelect.disabled = true;
        
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
                            localizacionSelect.appendChild(option);
                        });
                        
                        // Habilitar selector
                        localizacionSelect.disabled = false;
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
    
    // Scripts adicionales para componentes
    const hwCheckboxes = document.querySelectorAll('input[name="hw_componentes[]"]');
    const swCheckboxes = document.querySelectorAll('input[name="sw_componentes[]"]');
    
    // Función para manejar los campos de cantidad y notas según selección
    function toggleInputFields(checkbox, inputName) {
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
        // Inicializar estado
        toggleInputFields(checkbox, 'hw');
        
        // Asignar evento change
        checkbox.addEventListener('change', function() {
            toggleInputFields(this, 'hw');
        });
    });
    
    // Asignar eventos a los checkboxes de software
    swCheckboxes.forEach(checkbox => {
        // Inicializar estado
        toggleInputFields(checkbox, 'sw');
        
        // Asignar evento change
        checkbox.addEventListener('change', function() {
            toggleInputFields(this, 'sw');
        });
    });
});
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>