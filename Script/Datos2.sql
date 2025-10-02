-- Script para crear usuarios de prueba para cada rol
USE ControlIncidenciasDB
GO

-- Primero nos aseguramos que existan los roles necesarios
SELECT * FROM ROL;
-- Si no tienes los roles, ejecuta el siguiente comando:
-- INSERT INTO ROL (Nombre) VALUES 
-- ('Administrador'), ('Coordinador TI CEDIS'), ('Coordinador TI Sucursales'), 
-- ('Coordinador TI Corporativo'), ('Técnico TI'), ('Supervisor Infraestructura'), 
-- ('Supervisor Sistemas'), ('Encargado Inventario'), ('Gerente TI'), ('Usuario Final');

-- Crear empleados de prueba (uno para cada rol)
INSERT INTO EMPLEADO (Nombre, Email, Celular, Direccion, ID_Rol)
VALUES 
('Admin Test', 'admin@dportenis.com.mx', '5511223344', 'Oficina Central', 1),
('Coord CEDIS Test', 'coord.cedis@dportenis.com.mx', '5522334455', 'CEDIS Principal', 2),
('Coord Sucursales Test', 'coord.suc@dportenis.com.mx', '5533445566', 'Oficina Central', 3),
('Coord Corporativo Test', 'coord.corp@dportenis.com.mx', '5544556677', 'Oficina Central', 4),
('Técnico Test', 'tecnico@dportenis.com.mx', '5555667788', 'Soporte Técnico', 5),
('Supervisor Infra Test', 'sup.infra@dportenis.com.mx', '5566778899', 'CEDIS Principal', 6),
('Supervisor Sistemas Test', 'sup.sistemas@dportenis.com.mx', '5577889900', 'Oficina Central', 7),
('Inventario Test', 'inventario@dportenis.com.mx', '5588990011', 'CEDIS Principal', 8),
('Gerente Test', 'gerente.ti@dportenis.com.mx', '5599001122', 'Oficina Central', 9),
('Usuario Test', 'usuario@dportenis.com.mx', '5500112233', 'Sucursal Reforma', 10);

-- Obtener los IDs de los empleados recién creados
DECLARE @id_admin INT, @id_coord_cedis INT, @id_coord_suc INT, @id_coord_corp INT, 
        @id_tecnico INT, @id_sup_infra INT, @id_sup_sistemas INT, @id_inventario INT, 
        @id_gerente INT, @id_usuario INT;

SELECT @id_admin = ID FROM EMPLEADO WHERE Email = 'admin@dportenis.com.mx';
SELECT @id_coord_cedis = ID FROM EMPLEADO WHERE Email = 'coord.cedis@dportenis.com.mx';
SELECT @id_coord_suc = ID FROM EMPLEADO WHERE Email = 'coord.suc@dportenis.com.mx';
SELECT @id_coord_corp = ID FROM EMPLEADO WHERE Email = 'coord.corp@dportenis.com.mx';
SELECT @id_tecnico = ID FROM EMPLEADO WHERE Email = 'tecnico@dportenis.com.mx';
SELECT @id_sup_infra = ID FROM EMPLEADO WHERE Email = 'sup.infra@dportenis.com.mx';
SELECT @id_sup_sistemas = ID FROM EMPLEADO WHERE Email = 'sup.sistemas@dportenis.com.mx';
SELECT @id_inventario = ID FROM EMPLEADO WHERE Email = 'inventario@dportenis.com.mx';
SELECT @id_gerente = ID FROM EMPLEADO WHERE Email = 'gerente.ti@dportenis.com.mx';
SELECT @id_usuario = ID FROM EMPLEADO WHERE Email = 'usuario@dportenis.com.mx';

-- Crear usuarios (para simplificar, usamos contraseñas en texto plano con el mismo valor que el usuario)
INSERT INTO USUARIO (Username, Password, Estado, ID_Empleado, ID_Rol) 
VALUES 
('admin', 'admin123', 1, @id_admin, 1),
('coord_cedis', 'coord123', 1, @id_coord_cedis, 2),
('coord_suc', 'coord123', 1, @id_coord_suc, 3),
('coord_corp', 'coord123', 1, @id_coord_corp, 4),
('tecnico', 'tecnico123', 1, @id_tecnico, 5),
('sup_infra', 'sup123', 1, @id_sup_infra, 6),
('sup_sistemas', 'sup123', 1, @id_sup_sistemas, 7),
('inventario', 'inv123', 1, @id_inventario, 8),
('gerente', 'gerente123', 1, @id_gerente, 9),
('usuario', 'usuario123', 1, @id_usuario, 10);

-- Mostrar los usuarios creados
SELECT u.ID, u.Username, u.Password, e.Nombre as 'Empleado', r.Nombre as 'Rol' 
FROM USUARIO u 
JOIN EMPLEADO e ON u.ID_Empleado = e.ID 
JOIN ROL r ON u.ID_Rol = r.ID;