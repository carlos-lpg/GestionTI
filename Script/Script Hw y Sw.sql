-- Crear tabla de componentes
CREATE TABLE COMPONENTE (
    ID INT PRIMARY KEY IDENTITY(1,1),
    Nombre NVARCHAR(50) NOT NULL,
    Descripcion NVARCHAR(200),
    Tipo CHAR(2) NOT NULL, -- HW = Hardware, SW = Software
    Categoria NVARCHAR(20) NOT NULL, -- CPU, RAM, HDD, PSU, etc.
    Fabricante NVARCHAR(50),
    Modelo NVARCHAR(50),
    CreatedBy INT,
    CreatedDate DATETIME,
    ModifiedBy INT,
    ModifiedDate DATETIME,
    CONSTRAINT FK_COMPONENTE_CREATED_BY FOREIGN KEY (CreatedBy) REFERENCES USUARIO(ID),
    CONSTRAINT FK_COMPONENTE_MODIFIED_BY FOREIGN KEY (ModifiedBy) REFERENCES USUARIO(ID),
    CONSTRAINT CHK_COMPONENTE_TIPO CHECK (Tipo IN ('HW', 'SW'))
);

-- Crear tabla de relación entre CIs y componentes
CREATE TABLE CI_COMPONENTE (
    ID INT PRIMARY KEY IDENTITY(1,1),
    ID_CI INT NOT NULL,
    ID_Componente INT NOT NULL,
    Cantidad INT DEFAULT 1,
    Notas NVARCHAR(200),
    CreatedBy INT,
    CreatedDate DATETIME,
    CONSTRAINT FK_CI_COMPONENTE_CI FOREIGN KEY (ID_CI) REFERENCES CI(ID),
    CONSTRAINT FK_CI_COMPONENTE_COMPONENTE FOREIGN KEY (ID_Componente) REFERENCES COMPONENTE(ID),
    CONSTRAINT FK_CI_COMPONENTE_CREATED_BY FOREIGN KEY (CreatedBy) REFERENCES USUARIO(ID)
);

-- Crear índices para optimizar consultas
CREATE INDEX IDX_CI_COMPONENTE_CI ON CI_COMPONENTE(ID_CI);
CREATE INDEX IDX_CI_COMPONENTE_COMPONENTE ON CI_COMPONENTE(ID_Componente);
CREATE INDEX IDX_COMPONENTE_TIPO ON COMPONENTE(Tipo);
CREATE INDEX IDX_COMPONENTE_CATEGORIA ON COMPONENTE(Categoria);

-- Insertar componentes de hardware comunes
INSERT INTO COMPONENTE (Nombre, Descripcion, Tipo, Categoria, Fabricante, Modelo, CreatedDate)
VALUES 
-- Procesadores
('Intel Core i3-10100', 'Procesador 4 núcleos, 8 hilos, 3.6GHz', 'HW', 'CPU', 'Intel', 'Core i3-10100', GETDATE()),
('Intel Core i5-11600', 'Procesador 6 núcleos, 12 hilos, 3.9GHz', 'HW', 'CPU', 'Intel', 'Core i5-11600', GETDATE()),
('Intel Core i7-11700', 'Procesador 8 núcleos, 16 hilos, 4.9GHz', 'HW', 'CPU', 'Intel', 'Core i7-11700', GETDATE()),
('AMD Ryzen 5 5600X', 'Procesador 6 núcleos, 12 hilos, 3.7GHz', 'HW', 'CPU', 'AMD', 'Ryzen 5 5600X', GETDATE()),
('AMD Ryzen 7 5800X', 'Procesador 8 núcleos, 16 hilos, 3.8GHz', 'HW', 'CPU', 'AMD', 'Ryzen 7 5800X', GETDATE()),

-- Memorias RAM
('Crucial 8GB DDR4', 'Memoria RAM 8GB DDR4 2666MHz', 'HW', 'RAM', 'Crucial', 'DDR4-2666', GETDATE()),
('Kingston 16GB DDR4', 'Memoria RAM 16GB DDR4 3200MHz', 'HW', 'RAM', 'Kingston', 'DDR4-3200', GETDATE()),
('Corsair 32GB DDR4', 'Memoria RAM 32GB DDR4 3600MHz', 'HW', 'RAM', 'Corsair', 'Vengeance DDR4-3600', GETDATE()),

-- Discos duros
('Seagate Barracuda 1TB', 'Disco duro 1TB 7200RPM', 'HW', 'HDD', 'Seagate', 'Barracuda', GETDATE()),
('Western Digital Blue 2TB', 'Disco duro 2TB 5400RPM', 'HW', 'HDD', 'Western Digital', 'Blue', GETDATE()),
('Samsung 860 EVO 500GB', 'Disco SSD 500GB SATA', 'HW', 'SSD', 'Samsung', '860 EVO', GETDATE()),
('Samsung 970 EVO Plus 1TB', 'Disco SSD 1TB NVMe', 'HW', 'SSD', 'Samsung', '970 EVO Plus', GETDATE()),

-- Fuentes de poder
('EVGA 500W', 'Fuente de poder 500W 80 Plus', 'HW', 'PSU', 'EVGA', '500W 80+', GETDATE()),
('Corsair 650W', 'Fuente de poder 650W 80 Plus Gold', 'HW', 'PSU', 'Corsair', 'RM650', GETDATE()),
('Seasonic 750W', 'Fuente de poder 750W 80 Plus Platinum', 'HW', 'PSU', 'Seasonic', 'Prime 750W', GETDATE());

-- Insertar software permitido
INSERT INTO COMPONENTE (Nombre, Descripcion, Tipo, Categoria, Fabricante, Modelo, CreatedDate)
VALUES 
-- Sistemas operativos
('Windows 10 Pro', 'Sistema operativo Windows 10 Professional', 'SW', 'OS', 'Microsoft', 'Windows 10 Pro', GETDATE()),
('Windows 11 Pro', 'Sistema operativo Windows 11 Professional', 'SW', 'OS', 'Microsoft', 'Windows 11 Pro', GETDATE()),
('Ubuntu 22.04 LTS', 'Sistema operativo Linux Ubuntu 22.04 LTS', 'SW', 'OS', 'Canonical', 'Ubuntu 22.04', GETDATE()),

-- Suites ofimáticas
('Microsoft Office 2019', 'Suite ofimática Office 2019 Professional', 'SW', 'OFFICE', 'Microsoft', 'Office 2019', GETDATE()),
('Microsoft Office 365', 'Suite ofimática Office 365 Enterprise', 'SW', 'OFFICE', 'Microsoft', 'Office 365', GETDATE()),
('LibreOffice 7.3', 'Suite ofimática de código abierto', 'SW', 'OFFICE', 'The Document Foundation', 'LibreOffice 7.3', GETDATE()),

-- Antivirus
('Kaspersky Endpoint Security', 'Software antivirus y seguridad', 'SW', 'ANTIVIRUS', 'Kaspersky', 'Endpoint Security', GETDATE()),
('McAfee Total Protection', 'Software antivirus y seguridad', 'SW', 'ANTIVIRUS', 'McAfee', 'Total Protection', GETDATE()),
('Bitdefender Endpoint Security', 'Software antivirus y seguridad', 'SW', 'ANTIVIRUS', 'Bitdefender', 'Endpoint Security', GETDATE()),

-- Navegadores
('Google Chrome Enterprise', 'Navegador web Google Chrome versión empresarial', 'SW', 'BROWSER', 'Google', 'Chrome Enterprise', GETDATE()),
('Mozilla Firefox ESR', 'Navegador web Firefox Extended Support Release', 'SW', 'BROWSER', 'Mozilla', 'Firefox ESR', GETDATE()),
('Microsoft Edge Business', 'Navegador web Edge para empresas', 'SW', 'BROWSER', 'Microsoft', 'Edge Business', GETDATE());