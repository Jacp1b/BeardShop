USE db_barberia;

-- 1. Insertar Usuarios de Prueba (Roles: Administrador, Barbero, Cliente)
-- Nota: Las contraseñas simuladas están en formato hash seguro (ej. BCrypt)
INSERT INTO usuarios (nombre, correo, telefono, password, rol) VALUES
('Carlos Administrador', 'admin@barbershop.com', '3101234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador'),
('Mateo Rodríguez', 'mateo.barber@barbershop.com', '3209876543', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Barbero'),
('Andrés Gómez', 'andres.barber@barbershop.com', '3115554433', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Barbero'),
('John Conde', 'john.conde@gmail.com', '3007778899', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cliente'),
('Alejandro Pérez', 'alejandro.perez@hotmail.com', '3152223344', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cliente'),
('Felipe Martínez', 'felipe.m@yahoo.com', '3184445566', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cliente');

-- 2. Insertar Perfiles de Barberos (Vinculados a los usuarios barberos id 2 y 3)
INSERT INTO barberos (id_usuario, especialidad, porcentaje_comision, estado) VALUES
(2, 'Cortes clásicos, Fade y Barba tradicional', 40.00, 'Activo'),
(3, 'Diseños modernos, Barbería artística y Cuidado capilar', 45.00, 'Activo');

-- 3. Insertar Catálogo de Servicios
INSERT INTO servicios (nombre_servicio, descripcion, duracion_minutos, precio) VALUES
('Corte Clásico / Moderno', 'Corte de cabello al gusto con acabado de navaja y peinado.', 30, 25000.00),
('Diseño y Perfilado de Barba', 'Arreglo de barba con toalla caliente, vapor y perfilado con navaja.', 25, 20000.00),
('Combo Barbershop (Corte + Barba)', 'Servicio completo de corte de cabello y mantenimiento de barba.', 50, 40000.00),
('Mascarilla Negra / Limpieza Facial', 'Exfoliación y mascarilla para puntos negros e impurezas.', 20, 18000.00),
('Corte Infantil (Niños)', 'Corte especializado para niños menores de 12 años.', 30, 22000.00);

-- 4. Insertar Citas de Prueba (Clientes IDs 4, 5, 6 con Barberos IDs 1 y 2)
INSERT INTO citas (id_cliente, id_barbero, fecha_hora, estado_cita) VALUES
(4, 1, '2026-11-10 10:00:00', 'Confirmada'),
(5, 1, '2026-11-10 11:00:00', 'Confirmada'),
(6, 2, '2026-11-10 15:30:00', 'Completada'),
(4, 2, '2026-11-11 14:00:00', 'Pendiente');

-- 5. Insertar Relación Cita - Servicios (Tabla Intermedia)
-- Cita 1 incluye Corte Clásico
INSERT INTO cita_servicios (id_cita, id_servicio, precio_aplicado) VALUES
(1, 1, 25000.00);

-- Cita 2 incluye Combo Barbershop
INSERT INTO cita_servicios (id_cita, id_servicio, precio_aplicado) VALUES
(2, 3, 40000.00);

-- Cita 3 incluye Barba y Mascarilla Facial
INSERT INTO cita_servicios (id_cita, id_servicio, precio_aplicado) VALUES
(3, 2, 20000.00),
(3, 4, 18000.00);

-- Cita 4 incluye Corte Clásico
INSERT INTO cita_servicios (id_cita, id_servicio, precio_aplicado) VALUES
(4, 1, 25000.00);

-- 6. Insertar Pagos Asociados (Para la cita 3 que ya está completada, y cita 2)
INSERT INTO pagos (id_cita, metodo_pago, monto, comprobante_url, estado_pago, fecha_pago) VALUES
(3, 'Nequi', 38000.00, 'uploads/comprobantes/nequi_pago_3.jpg', 'Aprobado', '2026-11-10 16:30:00'),
(2, 'Efectivo', 40000.00, NULL, 'Aprobado', '2026-11-10 12:00:00');