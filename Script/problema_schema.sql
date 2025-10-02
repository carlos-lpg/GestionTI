-- Script para crear las tablas del módulo de Gestión de Problemas

-- Tabla de estatus de problema
CREATE TABLE ESTATUS_PROBLEMA (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    Descripcion VARCHAR(50) NOT NULL
);

-- Tabla de categorías de problemas
CREATE TABLE CATEGORIA_PROBLEMA (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Descripcion VARCHAR(255) NULL
);

-- Tabla de impacto
CREATE TABLE IMPACTO (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    Descripcion VARCHAR(50) NOT NULL
);

-- Tabla principal de problemas
CREATE TABLE PROBLEMA (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    Titulo VARCHAR(200) NOT NULL,
    Descripcion TEXT NOT NULL,
    FechaIdentificacion DATETIME NOT NULL DEFAULT GETDATE(),
    FechaResolucion DATETIME NULL,
    ID_Prioridad INT NOT NULL,
    ID_Categoria INT NOT NULL,
    ID_Impacto INT NOT NULL,
    ID_Stat INT NOT NULL,
    ID_Responsable INT NULL,
    CreatedBy INT NOT NULL,
    CreatedDate DATETIME NOT NULL DEFAULT GETDATE(),
    ModifiedBy INT NULL,
    ModifiedDate DATETIME NULL,
    CONSTRAINT FK_Problema_Prioridad FOREIGN KEY (ID_Prioridad) REFERENCES PRIORIDAD(ID),
    CONSTRAINT FK_Problema_Categoria FOREIGN KEY (ID_Categoria) REFERENCES CATEGORIA_PROBLEMA(ID),
    CONSTRAINT FK_Problema_Impacto FOREIGN KEY (ID_Impacto) REFERENCES IMPACTO(ID),
    CONSTRAINT FK_Problema_Estado FOREIGN KEY (ID_Stat) REFERENCES ESTATUS_PROBLEMA(ID),
    CONSTRAINT FK_Problema_Responsable FOREIGN KEY (ID_Responsable) REFERENCES EMPLEADO(ID),
    CONSTRAINT FK_Problema_CreatedBy FOREIGN KEY (CreatedBy) REFERENCES USUARIO(ID),
    CONSTRAINT FK_Problema_ModifiedBy FOREIGN KEY (ModifiedBy) REFERENCES USUARIO(ID)
);

-- Tabla de relación entre problemas e incidencias
CREATE TABLE PROBLEMA_INCIDENCIA (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Problema INT NOT NULL,
    ID_Incidencia INT NOT NULL,
    CreatedBy INT NOT NULL,
    CreatedDate DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_ProblemaIncidencia_Problema FOREIGN KEY (ID_Problema) REFERENCES PROBLEMA(ID),
    CONSTRAINT FK_ProblemaIncidencia_Incidencia FOREIGN KEY (ID_Incidencia) REFERENCES INCIDENCIA(ID),
    CONSTRAINT FK_ProblemaIncidencia_CreatedBy FOREIGN KEY (CreatedBy) REFERENCES USUARIO(ID),
    CONSTRAINT UQ_ProblemaIncidencia UNIQUE (ID_Problema, ID_Incidencia)
);

-- Tabla de comentarios de problemas
CREATE TABLE PROBLEMA_COMENTARIO (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Problema INT NOT NULL,
    ID_Usuario INT NOT NULL,
    Comentario TEXT NOT NULL,
    TipoComentario VARCHAR(50) NOT NULL DEFAULT 'COMENTARIO',
    FechaRegistro DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_ProblemaComentario_Problema FOREIGN KEY (ID_Problema) REFERENCES PROBLEMA(ID),
    CONSTRAINT FK_ProblemaComentario_Usuario FOREIGN KEY (ID_Usuario) REFERENCES USUARIO(ID)
);

-- Tabla de historial de estados de problema
CREATE TABLE PROBLEMA_HISTORIAL (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Problema INT NOT NULL,
    ID_EstadoAnterior INT NULL,
    ID_EstadoNuevo INT NOT NULL,
    ID_Usuario INT NOT NULL,
    FechaCambio DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_ProblemaHistorial_Problema FOREIGN KEY (ID_Problema) REFERENCES PROBLEMA(ID),
    CONSTRAINT FK_ProblemaHistorial_EstadoAnterior FOREIGN KEY (ID_EstadoAnterior) REFERENCES ESTATUS_PROBLEMA(ID),
    CONSTRAINT FK_ProblemaHistorial_EstadoNuevo FOREIGN KEY (ID_EstadoNuevo) REFERENCES ESTATUS_PROBLEMA(ID),
    CONSTRAINT FK_ProblemaHistorial_Usuario FOREIGN KEY (ID_Usuario) REFERENCES USUARIO(ID)
);

-- Tabla de soluciones propuestas
CREATE TABLE PROBLEMA_SOLUCION_PROPUESTA (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Problema INT NOT NULL,
    Titulo VARCHAR(200) NOT NULL,
    Descripcion TEXT NOT NULL,
    TipoSolucion VARCHAR(50) NOT NULL, -- WORKAROUND, SOLUCION_PERMANENTE
    ID_Usuario INT NOT NULL,
    FechaRegistro DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_ProblemaSolucion_Problema FOREIGN KEY (ID_Problema) REFERENCES PROBLEMA(ID),
    CONSTRAINT FK_ProblemaSolucion_Usuario FOREIGN KEY (ID_Usuario) REFERENCES USUARIO(ID)
);

-- Insertar valores predeterminados para los estados de problemas
INSERT INTO ESTATUS_PROBLEMA (Descripcion) VALUES 
('Identificado'),
('En análisis'),
('En implementación'),
('Resuelto'),
('Cerrado');

-- Insertar valores predeterminados para las categorías de problemas
INSERT INTO CATEGORIA_PROBLEMA (Nombre, Descripcion) VALUES 
('Hardware', 'Problemas relacionados con equipos físicos'),
('Software', 'Problemas relacionados con aplicaciones y sistemas operativos'),
('Red', 'Problemas relacionados con conectividad y redes'),
('Seguridad', 'Problemas relacionados con seguridad informática'),
('Base de datos', 'Problemas relacionados con bases de datos'),
('Aplicativo Interno', 'Problemas relacionados con aplicativos internos'),
('Infraestructura', 'Problemas relacionados con infraestructura de TI'),
('Servicio', 'Problemas relacionados con servicios de TI');

-- Insertar valores predeterminados para impacto
INSERT INTO IMPACTO (Descripcion) VALUES 
('Alto'),
('Medio'),
('Bajo');