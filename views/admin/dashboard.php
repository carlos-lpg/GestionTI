<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de administrador
check_permission('admin');
?>

<h1 class="h2">Panel de Administración</h1>

<div class="row my-4">
    <div class="col-md-4">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Usuarios</h5>
                <p class="card-text">Gestión de usuarios y asignación de roles.</p>
                <a href="usuarios.php" class="btn btn-primary">Gestionar Usuarios</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Elementos de Configuración</h5>
                <p class="card-text">Administración de todos los elementos de configuración.</p>
                <a href="gestion-ci.php" class="btn btn-primary">Gestionar CIs</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Incidencias</h5>
                <p class="card-text">Gestión y seguimiento de incidencias reportadas.</p>
                <a href="incidencias.php" class="btn btn-primary">Gestionar Incidencias</a>
            </div>
        </div>
    </div>
</div>

<div class="row my-4">
    
    <div class="col-md-4">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Problemas (Causa Raíz)</h5>
                <p class="card-text">Gestión de problemas conocidos y corrección de causa raíz.</p>
                <a href="../tecnico/problemas.php" class="btn btn-warning">Gestionar Problemas</a>
            </div>
        </div>
    </div>

    </div>

<div class="row my-4">
    <div class="col-12">
        <div class="table-container">
            <h5>Resumen General</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Total</th>
                        <th>Últimos 30 días</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Usuarios Activos</td>
                        <td>25</td>
                        <td>+3</td>
                    </tr>
                    <tr>
                        <td>Elementos de Configuración</td>
                        <td>150</td>
                        <td>+12</td>
                    </tr>
                    <tr>
                        <td>Incidencias Abiertas</td>
                        <td>8</td>
                        <td>-2</td>
                    </tr>
                    <tr>
                        <td>Incidencias Resueltas</td>
                        <td>45</td>
                        <td>+15</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>