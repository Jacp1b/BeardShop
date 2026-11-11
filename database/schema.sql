CREATE DATABASE IF NOT EXISTS db_barberia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_barberia;

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    telefono VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('Administrador', 'Barbero', 'Cliente') NOT NULL DEFAULT 'Cliente',
    fecha_nacimiento DATE NULL,
    notas TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barberos (
    id_barbero INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    especialidad VARCHAR(150),
    porcentaje_comision DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    estado ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    CONSTRAINT fk_barberos_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS servicios (
    id_servicio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_servicio VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    duracion_minutos INT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    estado ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS citas (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_barbero INT NOT NULL,
    fecha_hora DATETIME NOT NULL,
    estado_cita ENUM('Pendiente', 'Confirmada', 'Completada', 'Cancelada') NOT NULL DEFAULT 'Pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_citas_fecha_estado (fecha_hora, estado_cita),
    KEY idx_citas_barbero_fecha (id_barbero, fecha_hora),
    CONSTRAINT fk_citas_cliente
        FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_citas_barbero
        FOREIGN KEY (id_barbero) REFERENCES barberos(id_barbero) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cita_servicios (
    id_cita_servicio INT AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL,
    id_servicio INT NOT NULL,
    precio_aplicado DECIMAL(10,2) NOT NULL,
    UNIQUE KEY uq_cita_servicio (id_cita, id_servicio),
    CONSTRAINT fk_cita_servicios_cita
        FOREIGN KEY (id_cita) REFERENCES citas(id_cita) ON DELETE CASCADE,
    CONSTRAINT fk_cita_servicios_servicio
        FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL,
    metodo_pago ENUM('Efectivo', 'Nequi', 'Daviplata', 'Transferencia') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    comprobante_url VARCHAR(255) NULL,
    estado_pago ENUM('Pendiente_Validacion', 'Aprobado', 'Rechazado') NOT NULL DEFAULT 'Pendiente_Validacion',
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pagos_estado_fecha (estado_pago, fecha_pago),
    CONSTRAINT fk_pagos_cita
        FOREIGN KEY (id_cita) REFERENCES citas(id_cita) ON DELETE CASCADE
) ENGINE=InnoDB;