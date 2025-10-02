-- Creación de la base de datos
CREATE DATABASE ControlIncidenciasDB
GO
USE ControlIncidenciasDB
GO

-- Tablas originales
CREATE TABLE CATEGORIA_UBICACION (
    ID INT IDENTITY,
    Nombre NVARCHAR(30) NOT NULL
)
GO

CREATE TABLE EDIFICIO(
    ID INT IDENTITY,
    Nombre NVARCHAR(20) NOT NULL, 
    Ubicacion NVARCHAR(100) NOT NULL,
    ID_Depto INT,
    ID_CategoriaUbicacion INT
)
GO

CREATE TABLE LOCALIZACION( 
    ID INT IDENTITY,
    Nombre NVARCHAR(25) NOT NULL,
    NumPlanta INT,
    ID_Edificio INT,
    Ubicacion NVARCHAR(100) NOT NULL
)
GO

CREATE TABLE DEPARTAMENTO(
    ID INT IDENTITY,
    Nombre NVARCHAR(20) NOT NULL,
    ID_EmpEncar INT
)
GO

CREATE TABLE ROL(
    ID INT IDENTITY,
    Nombre NVARCHAR(50) NOT NULL
)
GO

CREATE TABLE EMPLEADO(
    ID INT IDENTITY,
    Nombre NVARCHAR(40) NOT NULL,
    Email NVARCHAR(35) NOT NULL,
    Celular NVARCHAR(12) NOT NULL,
    Direccion NVARCHAR(30) NOT NULL,
    ID_Rol INT
)
GO

CREATE TABLE USUARIO (
    ID INT IDENTITY,
    Username NVARCHAR(50) NOT NULL,
    Password NVARCHAR(255) NOT NULL, -- Almacenar hash de contraseña
    UltimoAcceso DATETIME,
    Estado BIT DEFAULT 1, -- Activo/Inactivo
    ID_Empleado INT,
    ID_Rol INT
)
GO

CREATE TABLE EMPLEADO_DEPTO(
    ID_Empleado INT,
    ID_Depto INT
)
GO

CREATE TABLE PROVEEDOR(
    ID INT IDENTITY,
    Nombre NVARCHAR(20) NOT NULL,
    RFC NVARCHAR(13) NOT NULL,
    Email NVARCHAR(20) NOT NULL,
    Telefono NVARCHAR(12) NOT NULL,
    Direccion NVARCHAR(40) NOT NULL
)
GO

CREATE TABLE TIPO_CI (
    ID INT IDENTITY,
    Nombre NVARCHAR(30) NOT NULL,
    Descripcion NVARCHAR(100)
)
GO

CREATE TABLE CI(
    ID INT IDENTITY,
    Nombre NVARCHAR(20) NOT NULL,
    Descripcion NVARCHAR(40) NOT NULL,
    NumSerie NVARCHAR(30) NOT NULL,
    FechaAdquisicion DATE NOT NULL,
    ID_Localizacion INT,
    ID_Encargado INT,
    ID_Proveedor INT,
    ID_TipoCI INT,
    CreatedBy INT,
    CreatedDate DATETIME DEFAULT GETDATE(),
    ModifiedBy INT,
    ModifiedDate DATETIME
)
GO

CREATE TABLE RELACION_CI (
    ID INT IDENTITY,
    ID_CI_Padre INT,
    ID_CI_Hijo INT,
    TipoRelacion NVARCHAR(30) NOT NULL, -- Por ejemplo: "Contiene", "Depende de", "Instalado en"
    CreatedBy INT,
    CreatedDate DATETIME DEFAULT GETDATE()
)
GO

CREATE TABLE PRIORIDAD(
    ID INT IDENTITY,
    Descripcion NVARCHAR(20) NOT NULL
)
GO

CREATE TABLE ESTATUS_INCIDENCIA(
    ID INT IDENTITY,
    Descripcion NVARCHAR(15) NOT NULL
)
GO

CREATE TABLE INCIDENCIA(
    ID INT IDENTITY,
    Descripcion NVARCHAR(50) NOT NULL,
    FechaInicio DATE,
    FechaTerminacion DATE,
    ID_Prioridad INT,
    ID_CI INT,
    ID_Tecnico INT,
    ID_Stat INT,
    CreatedBy INT,
    CreatedDate DATETIME DEFAULT GETDATE(),
    ModifiedBy INT,
    ModifiedDate DATETIME
)
GO

-- Primary Keys
ALTER TABLE CATEGORIA_UBICACION
ADD CONSTRAINT PK_CategoriaUbicacion PRIMARY KEY(ID)

ALTER TABLE EDIFICIO
ADD CONSTRAINT PK_Edificio PRIMARY KEY(ID)

ALTER TABLE LOCALIZACION
ADD CONSTRAINT PK_Localizacion PRIMARY KEY(ID)

ALTER TABLE DEPARTAMENTO
ADD CONSTRAINT PK_Departamento PRIMARY KEY(ID)

ALTER TABLE EMPLEADO
ADD CONSTRAINT PK_Empleado PRIMARY KEY(ID)

ALTER TABLE USUARIO
ADD CONSTRAINT PK_Usuario PRIMARY KEY(ID)

ALTER TABLE ROL
ADD CONSTRAINT PK_Rol PRIMARY KEY(ID)

ALTER TABLE TIPO_CI
ADD CONSTRAINT PK_TipoCI PRIMARY KEY(ID)

ALTER TABLE PROVEEDOR
ADD CONSTRAINT PK_Proveedor PRIMARY KEY(ID)

ALTER TABLE CI
ADD CONSTRAINT PK_CI PRIMARY KEY(ID)

ALTER TABLE RELACION_CI
ADD CONSTRAINT PK_RelacionCI PRIMARY KEY(ID)

ALTER TABLE ESTATUS_INCIDENCIA
ADD CONSTRAINT PK_EstatusIncidencia PRIMARY KEY(ID)

ALTER TABLE INCIDENCIA
ADD CONSTRAINT PK_Incidencia PRIMARY KEY(ID)

ALTER TABLE PRIORIDAD
ADD CONSTRAINT PK_Prioridad PRIMARY KEY(ID)
GO

-- Foreign Keys
ALTER TABLE EDIFICIO
ADD CONSTRAINT FK_Edificio_Depto FOREIGN KEY(ID_Depto) REFERENCES DEPARTAMENTO (ID)

ALTER TABLE EDIFICIO
ADD CONSTRAINT FK_Edificio_CategoriaUbicacion FOREIGN KEY(ID_CategoriaUbicacion) REFERENCES CATEGORIA_UBICACION (ID)

ALTER TABLE LOCALIZACION
ADD CONSTRAINT FK_Localizacion_Edificio FOREIGN KEY(ID_Edificio) REFERENCES EDIFICIO (ID)

ALTER TABLE DEPARTAMENTO
ADD CONSTRAINT FK_Encargado_Depto FOREIGN KEY(ID_EmpEncar) REFERENCES EMPLEADO (ID)

ALTER TABLE EMPLEADO
ADD CONSTRAINT FK_Empleado_Rol FOREIGN KEY(ID_Rol) REFERENCES ROL (ID)

ALTER TABLE USUARIO
ADD CONSTRAINT FK_Usuario_Empleado FOREIGN KEY(ID_Empleado) REFERENCES EMPLEADO (ID)

ALTER TABLE USUARIO
ADD CONSTRAINT FK_Usuario_Rol FOREIGN KEY(ID_Rol) REFERENCES ROL (ID)

ALTER TABLE EMPLEADO_DEPTO
ADD CONSTRAINT FK_EmpleadoDepto_Empleado FOREIGN KEY(ID_Empleado) REFERENCES EMPLEADO (ID)

ALTER TABLE EMPLEADO_DEPTO
ADD CONSTRAINT FK_EmpleadoDepto_Depto FOREIGN KEY(ID_Depto) REFERENCES DEPARTAMENTO (ID)

ALTER TABLE CI
ADD CONSTRAINT FK_CI_Localizacion FOREIGN KEY(ID_Localizacion) REFERENCES LOCALIZACION (ID)

ALTER TABLE CI
ADD CONSTRAINT FK_CI_Encargado FOREIGN KEY(ID_Encargado) REFERENCES EMPLEADO (ID)

ALTER TABLE CI
ADD CONSTRAINT FK_CI_Provedor FOREIGN KEY(ID_Proveedor) REFERENCES PROVEEDOR (ID)

ALTER TABLE CI
ADD CONSTRAINT FK_CI_TipoCI FOREIGN KEY(ID_TipoCI) REFERENCES TIPO_CI (ID)

ALTER TABLE CI
ADD CONSTRAINT FK_CI_CreatedBy FOREIGN KEY(CreatedBy) REFERENCES USUARIO (ID)

ALTER TABLE CI
ADD CONSTRAINT FK_CI_ModifiedBy FOREIGN KEY(ModifiedBy) REFERENCES USUARIO (ID)

ALTER TABLE RELACION_CI
ADD CONSTRAINT FK_RelacionCI_CIPadre FOREIGN KEY(ID_CI_Padre) REFERENCES CI (ID)

ALTER TABLE RELACION_CI
ADD CONSTRAINT FK_RelacionCI_CIHijo FOREIGN KEY(ID_CI_Hijo) REFERENCES CI (ID)

ALTER TABLE RELACION_CI
ADD CONSTRAINT FK_RelacionCI_CreatedBy FOREIGN KEY(CreatedBy) REFERENCES USUARIO (ID)

ALTER TABLE INCIDENCIA
ADD CONSTRAINT FK_Indcidencia_CI FOREIGN KEY(ID_CI) REFERENCES CI (ID)

ALTER TABLE INCIDENCIA
ADD CONSTRAINT FK_Indcidencia_Tecnico FOREIGN KEY(ID_Tecnico) REFERENCES EMPLEADO (ID)

ALTER TABLE INCIDENCIA
ADD CONSTRAINT FK_Incidencia_Estatus FOREIGN KEY(ID_Stat) REFERENCES ESTATUS_INCIDENCIA (ID)

ALTER TABLE INCIDENCIA
ADD CONSTRAINT FK_Incidencia_CreatedBy FOREIGN KEY(CreatedBy) REFERENCES USUARIO (ID)

ALTER TABLE INCIDENCIA
ADD CONSTRAINT FK_Incidencia_ModifiedBy FOREIGN KEY(ModifiedBy) REFERENCES USUARIO (ID)
GO

-- Datos iniciales para roles
INSERT INTO ROL (Nombre) VALUES 
('Administrador'), -- Control total del sistema
('Coordinador TI CEDIS'), -- Gestión completa de CIs en CEDIS
('Coordinador TI Sucursales'), -- Gestión completa de CIs en sucursales
('Coordinador TI Corporativo'), -- Gestión completa de CIs en corporativo
('Técnico TI'), -- Atención y resolución de incidencias
('Supervisor Infraestructura'), -- Gestión de infraestructura física
('Supervisor Sistemas'), -- Gestión de aplicaciones y sistemas
('Encargado Inventario'), -- Control de inventario de CIs
('Gerente TI'), -- Reportes y visibilidad completa, sin modificación
('Usuario Final') -- Reporte de incidencias
GO

-- Datos iniciales para categorías de ubicación
INSERT INTO CATEGORIA_UBICACION (Nombre) VALUES 
('CEDIS'),
('Sucursal'),
('Corporativo')
GO

-- Datos iniciales para tipos de CI
INSERT INTO TIPO_CI (Nombre, Descripcion) VALUES 
('Servidor', 'Equipos de procesamiento central'),
('Computadora', 'Equipos de cómputo personal o kioscos'),
('Impresora', 'Dispositivos de impresión'),
('Red', 'Equipos de conectividad como switches, routers'),
('Software', 'Aplicaciones y sistemas operativos'),
('Terminal Punto Venta', 'Equipos para transacciones de venta'),
('Móvil', 'Dispositivos móviles como handhelds'),
('Teléfono', 'Equipos de comunicación telefónica'),
('Mobiliario TI', 'Muebles específicos para equipos de TI')
GO

-- Datos iniciales para estados de incidencias
INSERT INTO ESTATUS_INCIDENCIA (Descripcion) VALUES
('Nueva'),
('Asignada'),
('En proceso'),
('En espera'),
('Resuelta'),
('Cerrada'),
('Cancelada')
GO

-- Datos iniciales para prioridades
INSERT INTO PRIORIDAD (Descripcion) VALUES
('Crítica'),
('Alta'),
('Media'),
('Baja')
GO

