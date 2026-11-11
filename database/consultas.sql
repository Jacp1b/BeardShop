-- Ingresos aprobados por barbero, sin unir los detalles de servicios.
SELECT u.nombre AS barbero, SUM(p.monto) AS total_recaudado
FROM pagos p
JOIN citas c ON p.id_cita = c.id_cita
JOIN barberos b ON c.id_barbero = b.id_barbero
JOIN usuarios u ON b.id_usuario = u.id_usuario
WHERE p.estado_pago = 'Aprobado'
GROUP BY b.id_barbero, u.nombre
HAVING SUM(p.monto) > 30000.00;

-- Citas por barbero (incluye barberos que aún no tienen citas).
SELECT u.nombre AS barbero, COUNT(c.id_cita) AS total_citas
FROM barberos b
JOIN usuarios u ON b.id_usuario = u.id_usuario
LEFT JOIN citas c ON b.id_barbero = c.id_barbero
GROUP BY b.id_barbero, u.nombre;

-- Próximas citas confirmadas.
SELECT c.id_cita, u.nombre AS cliente, c.fecha_hora, c.estado_cita 
FROM citas c
JOIN usuarios u ON c.id_cliente = u.id_usuario
WHERE c.estado_cita = 'Confirmada'
ORDER BY c.fecha_hora ASC;

-- Citas con todos sus servicios y el total aplicado.
SELECT 
    c.id_cita, 
    u_cli.nombre AS cliente, 
    u_bar.nombre AS barbero, 
    GROUP_CONCAT(s.nombre_servicio ORDER BY s.nombre_servicio SEPARATOR ', ') AS servicios,
    SUM(cs.precio_aplicado) AS total_cita,
    c.fecha_hora,
    c.estado_cita
FROM citas c
JOIN usuarios u_cli ON c.id_cliente = u_cli.id_usuario
JOIN barberos b ON c.id_barbero = b.id_barbero
JOIN usuarios u_bar ON b.id_usuario = u_bar.id_usuario
JOIN cita_servicios cs ON c.id_cita = cs.id_cita
JOIN servicios s ON cs.id_servicio = s.id_servicio
GROUP BY c.id_cita, u_cli.nombre, u_bar.nombre, c.fecha_hora, c.estado_cita
ORDER BY c.fecha_hora DESC;

-- Pagos aprobados en orden cronológico inverso.
SELECT 
    p.id_pago, 
    u.nombre AS cliente, 
    p.metodo_pago, 
    p.monto, 
    p.estado_pago, 
    p.fecha_pago
FROM pagos p
JOIN citas c ON p.id_cita = c.id_cita
JOIN usuarios u ON c.id_cliente = u.id_usuario
WHERE p.estado_pago = 'Aprobado'
ORDER BY p.fecha_pago DESC;

-- Cancelar una cita sin borrar el historial ni sus pagos.
UPDATE citas
SET estado_cita = 'Cancelada'
WHERE id_cita = 4 AND estado_cita = 'Pendiente';