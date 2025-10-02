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

<!-- Formulario rápido para reportar incidencia -->
<div class="row my-4">
    <div class="col-12">
        <div class="form-container">
            <h5>Reporte Rápido de Incidencia</h5>
            <form action="procesar-reporte.php" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="ci_afectado" class="form-label">Elemento afectado</label>
                        <select class="form-select" id="ci_afectado" name="ci_afectado" required>
                            <option value="">Seleccionar elemento...</option>
                            <option value="1">Computadora Personal</option>
                            <option value="2">Impresora</option>
                            <option value="3">Terminal Punto de Venta</option>
                            <option value="4">Teléfono</option>
                            <option value="5">Otro (especificar)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="prioridad" class="form-label">Prioridad</label>
                        <select class="form-select" id="prioridad" name="prioridad" required>
                            <option value="">Seleccionar prioridad...</option>
                            <option value="1">Crítica - No puedo trabajar</option>
                            <option value="2">Alta - Afecta severamente mi trabajo</option>
                            <option value="3">Media - Puedo trabajar con limitaciones</option>
                            <option value="4">Baja - Inconveniente menor</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 mb-3">
                        <label for="descripcion" class="form-label">Descripción del problema</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required placeholder="Describa detalladamente el problema que está experimentando..."></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">Enviar Reporte</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Últimas incidencias reportadas -->
<div class="row my-4">
    <div class="col-12">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Mis Incidencias Recientes</h5>
                <a href="mis-incidencias.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>Fecha</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1005</td>
                        <td>Computadora muy lenta</td>
                        <td>09/03/2025</td>
                        <td><span class="badge bg-warning text-dark">Media</span></td>
                        <td><span class="badge bg-primary">Asignada</span></td>
                        <td>
                            <a href="incidencia-detalle.php?id=1005" class="btn btn-sm btn-info">Ver detalles</a>
                        </td>
                    </tr>
                    <tr>
                        <td>998</td>
                        <td>Problemas con impresora</td>
                        <td>05/03/2025</td>
                        <td><span class="badge bg-info">Baja</span></td>
                        <td><span class="badge bg-success">Resuelta</span></td>
                        <td>
                            <a href="incidencia-detalle.php?id=998" class="btn btn-sm btn-info">Ver detalles</a>
                        </td>
                    </tr>
                    <tr>
                        <td>982</td>
                        <td>Error en TPV al procesar pagos</td>
                        <td>28/02/2025</td>
                        <td><span class="badge bg-danger">Alta</span></td>
                        <td><span class="badge bg-secondary">Cerrada</span></td>
                        <td>
                            <a href="incidencia-detalle.php?id=982" class="btn btn-sm btn-info">Ver detalles</a>
                        </td>
                    </tr>
                </tbody>
            </table>
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
                        <p>soporte.ti@dportenis.com.mx</p>
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