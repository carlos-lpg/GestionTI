<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de gestión
check_permission('gestionar_ci');

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Conectar a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener la lista de proveedores
$query = "SELECT ID, Nombre, RFC, Email, Telefono, Direccion FROM PROVEEDOR ORDER BY Nombre";
$stmt = $conn->prepare($query);
$stmt->execute();
?>

<!-- Título de la página -->
<h1 class="h2">Gestión de Proveedores</h1>

<!-- Botón para agregar nuevo proveedor -->
<div class="row mb-4">
    <div class="col-12">
        <a href="agregar-proveedor.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Proveedor
        </a>
    </div>
</div>

<!-- Tabla de proveedores -->
<div class="row">
    <div class="col-12">
        <div class="table-container">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>RFC</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Dirección</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (count($proveedores) > 0): 
                        foreach ($proveedores as $proveedor): 
                    ?>
                        <tr>
                            <td><?php echo $proveedor['ID']; ?></td>
                            <td><?php echo htmlspecialchars($proveedor['Nombre']); ?></td>
                            <td><?php echo htmlspecialchars($proveedor['RFC']); ?></td>
                            <td><?php echo htmlspecialchars($proveedor['Email']); ?></td>
                            <td><?php echo htmlspecialchars($proveedor['Telefono']); ?></td>
                            <td><?php echo htmlspecialchars($proveedor['Direccion']); ?></td>
                            <td>
                                <a href="editar-proveedor.php?id=<?php echo $proveedor['ID']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($_SESSION['permisos']['admin']): ?>
                                <a href="javascript:void(0);" onclick="confirmarEliminacion(<?php echo $proveedor['ID']; ?>)" class="btn btn-sm btn-danger" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay proveedores registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Script para confirmación de eliminación -->
<script>
function confirmarEliminacion(id) {
    if (confirm('¿Está seguro de que desea eliminar este proveedor?')) {
        window.location.href = 'eliminar-proveedor.php?id=' + id;
    }
}
</script>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>