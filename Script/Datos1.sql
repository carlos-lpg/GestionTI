-- Script para crear datos de prueba adicionales
USE ControlIncidenciasDB
GO

-- Crear departamentos si no existen
IF NOT EXISTS (SELECT 1 FROM DEPARTAMENTO WHERE Nombre = 'TI')
BEGIN
    -- Obtener ID del empleado encargado (Gerente TI)
    DECLARE @id_gerente_ti INT;
    SELECT @id_gerente_ti = ID FROM EMPLEADO WHERE Email = 'gerente.ti@dportenis.com.mx';
    
    -- Insertar departamentos
    INSERT INTO DEPARTAMENTO (Nombre, ID_EmpEncar) 
    VALUES 
    ('TI', @id_gerente_ti),
    ('Ventas', NULL),
    ('Logística', NULL),
    ('Recursos Humanos', NULL);
END

-- Asociar empleados a departamentos
INSERT INTO EMPLEADO_DEPTO (ID_Empleado, ID_Depto)
SELECT e.ID, d.ID 
FROM EMPLEADO e, DEPARTAMENTO d
WHERE d.Nombre = 'TI' 
AND e.Email IN (
    'admin@dportenis.com.mx',
    'coord.cedis@dportenis.com.mx',
    'coord.suc@dportenis.com.mx',
    'coord.corp@dportenis.com.mx',
    'tecnico@dportenis.com.mx',
    'sup.infra@dportenis.com.mx',
    'sup.sistemas@dportenis.com.mx',
    'inventario@dportenis.com.mx',
    'gerente.ti@dportenis.com.mx'
)
AND NOT EXISTS (SELECT 1 FROM EMPLEADO_DEPTO ed WHERE ed.ID_Empleado = e.ID AND ed.ID_Depto = d.ID);

-- Crear ubicaciones (CEDIS, Sucursales, Corporativo)
IF NOT EXISTS (SELECT 1 FROM EDIFICIO WHERE Nombre = 'CEDIS Principal')
BEGIN
    -- Obtener ID del departamento de Logística
    DECLARE @id_depto_logistica INT;
    SELECT @id_depto_logistica = ID FROM DEPARTAMENTO WHERE Nombre = 'Logística';
    
    -- Obtener ID de la categoría CEDIS
    DECLARE @id_cat_cedis INT, @id_cat_sucursal INT, @id_cat_corporativo INT;
    SELECT @id_cat_cedis = ID FROM CATEGORIA_UBICACION WHERE Nombre = 'CEDIS';
    SELECT @id_cat_sucursal = ID FROM CATEGORIA_UBICACION WHERE Nombre = 'Sucursal';
    SELECT @id_cat_corporativo = ID FROM CATEGORIA_UBICACION WHERE Nombre = 'Corporativo';
    
    -- Insertar edificios
    INSERT INTO EDIFICIO (Nombre, Ubicacion, ID_Depto, ID_CategoriaUbicacion)
    VALUES 
    ('CEDIS Principal', 'Av. Distribución 123, Zona Industrial', @id_depto_logistica, @id_cat_cedis),
    ('Corporativo Central', 'Paseo de la Reforma 222, CDMX', NULL, @id_cat_corporativo),
    ('Sucursal Reforma', 'Paseo de la Reforma 300, CDMX', NULL, @id_cat_sucursal),
    ('Sucursal Polanco', 'Av. Presidente Masaryk 123, Polanco', NULL, @id_cat_sucursal),
    ('Sucursal Santa Fe', 'Centro Comercial Santa Fe, CDMX', NULL, @id_cat_sucursal);
END

-- Crear localizaciones dentro de los edificios
IF NOT EXISTS (SELECT 1 FROM LOCALIZACION WHERE Nombre = 'Sala de Servidores')
BEGIN
    -- Obtener IDs de los edificios
    DECLARE @id_cedis INT, @id_corporativo INT, @id_suc_reforma INT, @id_suc_polanco INT, @id_suc_santa_fe INT;
    SELECT @id_cedis = ID FROM EDIFICIO WHERE Nombre = 'CEDIS Principal';
    SELECT @id_corporativo = ID FROM EDIFICIO WHERE Nombre = 'Corporativo Central';
    SELECT @id_suc_reforma = ID FROM EDIFICIO WHERE Nombre = 'Sucursal Reforma';
    SELECT @id_suc_polanco = ID FROM EDIFICIO WHERE Nombre = 'Sucursal Polanco';
    SELECT @id_suc_santa_fe = ID FROM EDIFICIO WHERE Nombre = 'Sucursal Santa Fe';
    
    -- Insertar localizaciones
    INSERT INTO LOCALIZACION (Nombre, NumPlanta, ID_Edificio, Ubicacion)
    VALUES 
    -- CEDIS
    ('Sala de Servidores', 0, @id_cedis, 'Área restringida'),
    ('Área TI', 1, @id_cedis, 'Oficina central'),
    ('Almacén Principal', 0, @id_cedis, 'Nave principal'),
    ('Área de Recepción', 0, @id_cedis, 'Entrada principal'),
    
    -- Corporativo
    ('Sala de Servidores', -1, @id_corporativo, 'Sótano'),
    ('Área TI', 3, @id_corporativo, 'Piso 3, ala norte'),
    ('Gerencia', 5, @id_corporativo, 'Piso 5, ala sur'),
    ('Recursos Humanos', 2, @id_corporativo, 'Piso 2, ala este'),
    
    -- Sucursales
    ('Área Cajas', 0, @id_suc_reforma, 'Entrada tienda'),
    ('Bodega', 0, @id_suc_reforma, 'Parte trasera'),
    ('Área Cajas', 0, @id_suc_polanco, 'Entrada tienda'),
    ('Bodega', 0, @id_suc_polanco, 'Parte trasera'),
    ('Área Cajas', 0, @id_suc_santa_fe, 'Entrada tienda'),
    ('Bodega', 0, @id_suc_santa_fe, 'Parte trasera');
END

-- Crear proveedores
IF NOT EXISTS (SELECT 1 FROM PROVEEDOR WHERE Nombre = 'Dell')
BEGIN
    INSERT INTO PROVEEDOR (Nombre, RFC, Email, Telefono, Direccion)
    VALUES 
    ('Dell', 'DELL901234AB5', 'ventas@dell.com.mx', '5512345678', 'Corporativo Dell México'),
    ('HP', 'HEWL901234CD6', 'ventas@hp.com.mx', '5523456789', 'Corporativo HP México'),
    ('Cisco', 'CISC901234EF7', 'ventas@cisco.com.mx', '5534567890', 'Oficinas Cisco México'),
    ('Microsoft', 'MICR901234GH8', 'ventas@microsoft.com.mx', '5545678901', 'Microsoft México'),
    ('Lenovo', 'LENO901234IJ9', 'ventas@lenovo.com.mx', '5556789012', 'Oficinas Lenovo México');
END

-- Crear elementos de configuración (CIs)
IF NOT EXISTS (SELECT 1 FROM CI WHERE Nombre = 'SRV-CEDIS-01')
BEGIN
    -- Obtener IDs necesarios
    DECLARE @id_tipo_servidor INT, @id_tipo_computadora INT, @id_tipo_impresora INT, @id_tipo_red INT, @id_tipo_tpv INT;
    SELECT @id_tipo_servidor = ID FROM TIPO_CI WHERE Nombre = 'Servidor';
    SELECT @id_tipo_computadora = ID FROM TIPO_CI WHERE Nombre = 'Computadora';
    SELECT @id_tipo_impresora = ID FROM TIPO_CI WHERE Nombre = 'Impresora';
    SELECT @id_tipo_red = ID FROM TIPO_CI WHERE Nombre = 'Red';
    SELECT @id_tipo_tpv = ID FROM TIPO_CI WHERE Nombre = 'Terminal Punto Venta';
    
    DECLARE @id_loc_srv_cedis INT, @id_loc_ti_cedis INT, @id_loc_srv_corp INT, @id_loc_ti_corp INT;
    SELECT @id_loc_srv_cedis = ID FROM LOCALIZACION WHERE Nombre = 'Sala de Servidores' AND ID_Edificio = (SELECT ID FROM EDIFICIO WHERE Nombre = 'CEDIS Principal');
    SELECT @id_loc_ti_cedis = ID FROM LOCALIZACION WHERE Nombre = 'Área TI' AND ID_Edificio = (SELECT ID FROM EDIFICIO WHERE Nombre = 'CEDIS Principal');
    SELECT @id_loc_srv_corp = ID FROM LOCALIZACION WHERE Nombre = 'Sala de Servidores' AND ID_Edificio = (SELECT ID FROM EDIFICIO WHERE Nombre = 'Corporativo Central');
    SELECT @id_loc_ti_corp = ID FROM LOCALIZACION WHERE Nombre = 'Área TI' AND ID_Edificio = (SELECT ID FROM EDIFICIO WHERE Nombre = 'Corporativo Central');
    
    DECLARE @id_prov_dell INT, @id_prov_hp INT, @id_prov_cisco INT;
    SELECT @id_prov_dell = ID FROM PROVEEDOR WHERE Nombre = 'Dell';
    SELECT @id_prov_hp = ID FROM PROVEEDOR WHERE Nombre = 'HP';
    SELECT @id_prov_cisco = ID FROM PROVEEDOR WHERE Nombre = 'Cisco';
    
    DECLARE @id_encargado_infra INT, @id_encargado_sistemas INT;
    SELECT @id_encargado_infra = ID FROM EMPLEADO WHERE Email = 'sup.infra@dportenis.com.mx';
    SELECT @id_encargado_sistemas = ID FROM EMPLEADO WHERE Email = 'sup.sistemas@dportenis.com.mx';
    
    DECLARE @id_usuario_admin INT;
    SELECT @id_usuario_admin = ID FROM USUARIO WHERE Username = 'admin';
    
    -- Insertar CIs
    INSERT INTO CI (Nombre, Descripcion, NumSerie, FechaAdquisicion, ID_TipoCI, ID_Localizacion, ID_Encargado, ID_Proveedor, CreatedBy, CreatedDate)
    VALUES 
    -- Servidores
    ('SRV-CEDIS-01', 'Servidor Principal CEDIS', 'DELL-SRV-123456', '2023-01-15', @id_tipo_servidor, @id_loc_srv_cedis, @id_encargado_infra, @id_prov_dell, @id_usuario_admin, GETDATE()),
    ('SRV-CORP-01', 'Servidor Principal Corporativo', 'DELL-SRV-234567', '2023-02-20', @id_tipo_servidor, @id_loc_srv_corp, @id_encargado_infra, @id_prov_dell, @id_usuario_admin, GETDATE()),
    ('SRV-CORP-02', 'Servidor Backup Corporativo', 'HP-SRV-345678', '2023-02-25', @id_tipo_servidor, @id_loc_srv_corp, @id_encargado_infra, @id_prov_hp, @id_usuario_admin, GETDATE()),
    
    -- Computadoras
    ('PC-CEDIS-01', 'PC Supervisor Almacén', 'DELL-PC-456789', '2023-03-10', @id_tipo_computadora, @id_loc_ti_cedis, @id_encargado_sistemas, @id_prov_dell, @id_usuario_admin, GETDATE()),
    ('PC-CORP-01', 'PC Gerente TI', 'HP-PC-567890', '2023-03-15', @id_tipo_computadora, @id_loc_ti_corp, @id_encargado_sistemas, @id_prov_hp, @id_usuario_admin, GETDATE()),
    
    -- Equipos de red
    ('SW-CEDIS-01', 'Switch Principal CEDIS', 'CISCO-SW-678901', '2023-04-05', @id_tipo_red, @id_loc_srv_cedis, @id_encargado_infra, @id_prov_cisco, @id_usuario_admin, GETDATE()),
    ('SW-CORP-01', 'Switch Principal Corporativo', 'CISCO-SW-789012', '2023-04-10', @id_tipo_red, @id_loc_srv_corp, @id_encargado_infra, @id_prov_cisco, @id_usuario_admin, GETDATE());
END

-- Crear algunas incidencias de prueba
IF NOT EXISTS (SELECT 1 FROM INCIDENCIA WHERE Descripcion LIKE '%servidor%')
BEGIN
    -- Obtener IDs necesarios
    DECLARE @id_srv_cedis INT, @id_pc_corp INT, @id_sw_corp INT;
    SELECT @id_srv_cedis = ID FROM CI WHERE Nombre = 'SRV-CEDIS-01';
    SELECT @id_pc_corp = ID FROM CI WHERE Nombre = 'PC-CORP-01';
    SELECT @id_sw_corp = ID FROM CI WHERE Nombre = 'SW-CORP-01';
    
    DECLARE @id_tecnico INT, @id_usuario_tecnico INT;
    SELECT @id_tecnico = ID FROM EMPLEADO WHERE Email = 'tecnico@dportenis.com.mx';
    SELECT @id_usuario_tecnico = ID FROM USUARIO WHERE Username = 'tecnico';
    
    DECLARE @id_estado_nueva INT, @id_estado_asignada INT, @id_estado_proceso INT, @id_estado_resuelta INT;
    SELECT @id_estado_nueva = ID FROM ESTATUS_INCIDENCIA WHERE Descripcion = 'Nueva';
    SELECT @id_estado_asignada = ID FROM ESTATUS_INCIDENCIA WHERE Descripcion = 'Asignada';
    SELECT @id_estado_proceso = ID FROM ESTATUS_INCIDENCIA WHERE Descripcion = 'En proceso';
    SELECT @id_estado_resuelta = ID FROM ESTATUS_INCIDENCIA WHERE Descripcion = 'Resuelta';
    
    DECLARE @id_prioridad_critica INT, @id_prioridad_alta INT, @id_prioridad_media INT;
    SELECT @id_prioridad_critica = ID FROM PRIORIDAD WHERE Descripcion = 'Crítica';
    SELECT @id_prioridad_alta = ID FROM PRIORIDAD WHERE Descripcion = 'Alta';
    SELECT @id_prioridad_media = ID FROM PRIORIDAD WHERE Descripcion = 'Media';
    
    -- Insertar incidencias
    INSERT INTO INCIDENCIA (Descripcion, FechaInicio, FechaTerminacion, ID_Prioridad, ID_CI, ID_Tecnico, ID_Stat, CreatedBy, CreatedDate)
    VALUES 
    ('Servidor CEDIS no responde', GETDATE()-5, NULL, @id_prioridad_critica, @id_srv_cedis, @id_tecnico, @id_estado_proceso, @id_usuario_tecnico, GETDATE()-5),
    ('PC Gerente presenta lentitud', GETDATE()-3, NULL, @id_prioridad_media, @id_pc_corp, @id_tecnico, @id_estado_asignada, @id_usuario_tecnico, GETDATE()-3),
    ('Switch Corporativo con puertos fallando', GETDATE()-1, NULL, @id_prioridad_alta, @id_sw_corp, NULL, @id_estado_nueva, @id_usuario_tecnico, GETDATE()-1);
END

-- Mostrar registros creados
SELECT 'Departamentos:' AS Tabla, COUNT(*) AS Cantidad FROM DEPARTAMENTO
UNION ALL
SELECT 'Edificios:', COUNT(*) FROM EDIFICIO
UNION ALL
SELECT 'Localizaciones:', COUNT(*) FROM LOCALIZACION
UNION ALL
SELECT 'Proveedores:', COUNT(*) FROM PROVEEDOR
UNION ALL
SELECT 'CIs:', COUNT(*) FROM CI
UNION ALL
SELECT 'Incidencias:', COUNT(*) FROM INCIDENCIA;

ALTER TABLE PROVEEDOR ALTER COLUMN Email VARCHAR(50); -- O un tamaño mayor si es necesario
