<?php
// Incluir el encabezado
require_once '../../includes/header.php';

// Verificar permiso de reportar incidencias
check_permission('reportar_incidencia');
?>

<!-- Título de la página -->
<h1 class="h2">Panel de Usuario</h1>

<div class="row my-4">
    <!-- Tarjeta de reportar incidencia -->
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Reportar Incidencia</h5>
                <p class="card-text">Reporte problemas con elementos de TI.</p>
                <a href="reportar.php" class="btn btn-primary">Reportar Problema</a>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de mis incidencias -->
    <div class="col-md-6">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Mis Incidencias</h5>
                <p class="card-text">Consulte el estado de sus incidencias reportadas.</p>
                <a href="mis-incidencias.php" class="btn btn-primary">Ver Mis Incidencias</a>
            </div>
        </div>
    </div>
</div>


<!-- Información de contacto de soporte -->
<div class="row my-4">
    <div class="col-12">
        <div class="card card-dashboard">
            <div class="card-body">
                <h5 class="card-title">Información de Contacto de Soporte</h5>
                <div class="row">
                    <div class="col-md-4">
                        <h6><i class="fas fa-phone me-2"></i> Teléfono de Soporte</h6>
                        <p>Ext. 1234</p>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="fas fa-envelope me-2"></i> Email de Soporte</h6>
                        <p>soporte.ti@culiacan.tecnm.mx</p>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="fas fa-clock me-2"></i> Horario de Atención</h6>
                        <p>Lunes a Viernes: 8:00 - 18:00<br>Sábado: 9:00 - 14:00</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página
require_once '../../includes/footer.php';
?>