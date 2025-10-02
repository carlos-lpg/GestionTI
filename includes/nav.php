<?php
// Determinar la página actual
$current_page = basename($_SERVER['PHP_SELF']);

// Función para marcar un enlace como activo
function active($page) {
    global $current_page;
    return ($current_page == $page) ? 'active' : '';
}
?>

<!-- Enlace al Dashboard siempre visible para todos los usuarios -->
<!--
<li class="nav-item">
    <a class="nav-link <?php echo active('dashboard.php'); ?>" href="dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
    </a>
</li>
-->
<?php if (has_permission('admin')): ?>
<!-- Enlaces específicos para Administradores -->

<li class="nav-item">
    <a class="nav-link <?php echo active('usuarios.php'); ?>" href="usuarios.php">
        <i class="fas fa-users me-2"></i> Gestión de Usuarios
    </a>
</li>

<?php endif; ?>

<?php if (has_permission('gestionar_ci')): ?>
<!-- Enlaces para gestión de CIs -->
<li class="nav-item">
    <a class="nav-link <?php echo active('gestion-ci.php'); ?>" href="gestion-ci.php">
        <i class="fas fa-desktop me-2"></i> Elementos de Configuración
    </a>
</li>
<?php endif; ?>

<?php if (has_permission('gestionar_incidencias')): ?>
<!-- Enlaces para gestión de incidencias -->
 <!--
<li class="nav-item">
    <a class="nav-link <?php echo active('incidencias.php'); ?>" href="incidencias.php">
        <i class="fas fa-ticket-alt me-2"></i> Gestión de Incidencias
    </a>
</li>
-->
<?php endif; ?>

<?php if (has_permission('reportar_incidencia')): ?>
<!-- Enlaces para usuarios finales -->
<li class="nav-item">
    <a class="nav-link <?php echo active('reportar.php'); ?>" href="reportar.php">
        <i class="fas fa-exclamation-circle me-2"></i> Reportar Incidencia
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo active('mis-incidencias.php'); ?>" href="mis-incidencias.php">
        <i class="fas fa-list-alt me-2"></i> Mis Incidencias
    </a>
</li>
<?php endif; ?>

<?php if (has_permission('ver_reportes')): ?>
<!-- Enlaces para reportes -->
 <!--
<li class="nav-item">
    <a class="nav-link <?php echo active('reportes.php'); ?>" href="reportes.php">
        <i class="fas fa-chart-bar me-2"></i> Reportes
    </a>
</li>
-->
<?php endif; ?>
<?php if (has_permission('gestionar_ci')): ?>
<!--
<li class="nav-item">
    <a class="nav-link <?php echo active('proveedores.php'); ?>" href="proveedores.php">
        <i class="fas fa-truck-loading me-2"></i> Proveedores
    </a>
</li>
-->
<?php endif; ?>

<?php if ($_SESSION['role_name'] === 'Técnico TI'): ?>
    <!-- Menú para Técnicos -->
    <li class="nav-item">
        <a class="nav-link" href="../tecnico/dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Panel Principal
        </a>
    </li>
    
    <li class="nav-item">
        <a class="nav-link" href="../tecnico/mis-incidencias.php">
            <i class="fas fa-tasks me-2"></i>Mis Incidencias
        </a>
    </li>
    
    <!-- NUEVA OPCIÓN: Gestión de Problemas -->
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="problemasDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-exclamation-triangle me-2"></i>Problemas
        </a>
        <ul class="dropdown-menu" aria-labelledby="problemasDropdown">
            <li><a class="dropdown-item" href="../tecnico/problemas.php">
                <i class="fas fa-list me-2"></i>Ver Problemas
            </a></li>
            <li><a class="dropdown-item" href="../tecnico/agregar-problema.php">
                <i class="fas fa-plus me-2"></i>Registrar Problema
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../tecnico/problemas.php?mis_incidencias=1">
                <i class="fas fa-user-check me-2"></i>Mis Problemas Relacionados
            </a></li>
        </ul>
    </li>
    
    <li class="nav-item">
        <a class="nav-link" href="../tecnico/reportes.php">
            <i class="fas fa-chart-bar me-2"></i>Reportes
        </a>
    </li>
<?php endif; ?>