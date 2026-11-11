<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/CONEXION.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pesos($amount): string
{
    return '$' . number_format((float) $amount, 0, ',', '.');
}

function redirectTo(string $view): void
{
    header('Location: index.php?view=' . rawurlencode($view));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$allowedViews = [
    'dashboard', 'agenda', 'appointment-new', 'clients', 'client',
    'services', 'team', 'reports', 'settings', 'help', 'login', 'logout', 'offline',
];
$requestedView = $_GET['view'] ?? 'dashboard';
$view = is_string($requestedView) ? $requestedView : 'not-found';
if (!in_array($view, $allowedViews, true)) {
    $view = 'not-found';
}

$pdo = null;
$connectionError = '';
try {
    $pdo = conexion();
} catch (PDOException $exception) {
    error_log('BeardShop database connection failed: ' . $exception->getMessage());
    $connectionError = 'No se pudo conectar con la base de datos. Revisa CONEXION.php y ejecuta database/schema.sql.';
}

if ($view === 'logout') {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
    session_regenerate_id(true);
    redirectTo('login');
}
if ($pdo && empty($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $view = 'login';
}
if (
    !empty($_SESSION['user_id']) &&
    ($_SESSION['user_role'] ?? '') === 'Cliente' &&
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    !in_array($view, ['agenda', 'appointment-new', 'logout'], true)
) {
    redirectTo('agenda');
}
$isCustomer = ($_SESSION['user_role'] ?? '') === 'Cliente';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
            throw new RuntimeException('La sesión del formulario venció. Actualiza la página e inténtalo de nuevo.');
        }
        if (!$pdo) {
            throw new RuntimeException($connectionError);
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action !== 'login' && empty($_SESSION['user_id'])) {
            throw new RuntimeException('Inicia sesión para gestionar la información de BeardShop.');
        }
        if ($action === 'login') {
            $email = trim((string) ($_POST['correo'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $statement = $pdo->prepare(
                "SELECT id_usuario, nombre, rol, password
                 FROM usuarios
                 WHERE correo = :correo
                 LIMIT 1"
            );
            $statement->execute(['correo' => $email]);
            $user = $statement->fetch();
            if (!$user || !password_verify($password, $user['password'])) {
                throw new RuntimeException('Correo o contraseña incorrectos.');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_role'] = $user['rol'];
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Sesión iniciada. Bienvenido a BeardShop.'];
            redirectTo($user['rol'] === 'Cliente' ? 'agenda' : 'dashboard');
        }
        if ($isCustomer && !in_array($action, ['create-appointment', 'cancel-customer-appointment'], true)) {
            throw new RuntimeException('Tu cuenta de cliente solo puede gestionar sus propias citas.');
        }

        if ($action === 'create-client') {
            $name = trim((string) ($_POST['nombre'] ?? ''));
            $email = trim((string) ($_POST['correo'] ?? ''));
            $phone = trim((string) ($_POST['telefono'] ?? ''));
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
                throw new RuntimeException('Completa el nombre, un correo válido y el teléfono.');
            }
            $statement = $pdo->prepare(
                "INSERT INTO usuarios (nombre, correo, telefono, password, rol)
                 VALUES (:nombre, :correo, :telefono, :password, 'Cliente')"
            );
            $statement->execute([
                'nombre' => $name,
                'correo' => $email,
                'telefono' => $phone,
                'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Cliente agregado a BeardShop.'];
            redirectTo('clients');
        }

        if ($action === 'update-client') {
            $clientId = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['nombre'] ?? ''));
            $email = trim((string) ($_POST['correo'] ?? ''));
            $phone = trim((string) ($_POST['telefono'] ?? ''));
            if (!$clientId || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
                throw new RuntimeException('Completa el nombre, un correo válido y el teléfono.');
            }
            $statement = $pdo->prepare(
                "UPDATE usuarios SET nombre = :nombre, correo = :correo, telefono = :telefono
                 WHERE id_usuario = :id AND rol = 'Cliente'"
            );
            $statement->execute([
                'nombre' => $name,
                'correo' => $email,
                'telefono' => $phone,
                'id' => $clientId,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Ficha del cliente actualizada.'];
            header('Location: index.php?view=client&id=' . $clientId);
            exit;
        }

        if ($action === 'create-appointment') {
            $clientId = $isCustomer
                ? filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT)
                : filter_var($_POST['id_cliente'] ?? null, FILTER_VALIDATE_INT);
            $barberId = filter_var($_POST['id_barbero'] ?? null, FILTER_VALIDATE_INT);
            $serviceId = filter_var($_POST['id_servicio'] ?? null, FILTER_VALIDATE_INT);
            $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', (string) ($_POST['fecha_hora'] ?? ''));
            $dateErrors = DateTime::getLastErrors();
            $hasDateErrors = $dateErrors !== false
                && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
            if (
                !$clientId || !$barberId || !$serviceId || !$dateTime ||
                $hasDateErrors ||
                $dateTime->format('i') !== '00' ||
                (int) $dateTime->format('G') < 9 ||
                (int) $dateTime->format('G') >= 18 ||
                $dateTime->getTimestamp() < time()
            ) {
                throw new RuntimeException('Elige el cliente, servicio y barbero, y una hora en punto entre las 9:00 a. m. y las 5:00 p. m.');
            }

            $clientCheck = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = :id AND rol = 'Cliente'");
            $clientCheck->execute(['id' => $clientId]);
            $barberCheck = $pdo->prepare(
                "SELECT id_barbero FROM barberos WHERE id_barbero = :id AND estado = 'Activo'"
            );
            $barberCheck->execute(['id' => $barberId]);
            $serviceCheck = $pdo->prepare(
                "SELECT id_servicio, duracion_minutos, precio FROM servicios
                 WHERE id_servicio = :id AND estado = 'Activo'"
            );
            $serviceCheck->execute(['id' => $serviceId]);
            $service = $serviceCheck->fetch();
            if (!$clientCheck->fetch() || !$barberCheck->fetch() || !$service || (int) $service['duracion_minutos'] < 1) {
                throw new RuntimeException('El cliente, barbero o servicio seleccionado ya no está disponible.');
            }

            $start = $dateTime->format('Y-m-d H:i:s');
            $end = (clone $dateTime)->modify('+' . (int) $service['duracion_minutos'] . ' minutes')->format('Y-m-d H:i:s');
            if ($end > $dateTime->format('Y-m-d') . ' 18:00:00') {
                throw new RuntimeException('La cita debe terminar antes de las 6:00 p. m.');
            }
            $overlapCheck = $pdo->prepare(
                "SELECT c.id_cita
                 FROM citas c
                 LEFT JOIN (
                    SELECT cs.id_cita, SUM(s.duracion_minutos) AS duracion
                    FROM cita_servicios cs
                    JOIN servicios s ON s.id_servicio = cs.id_servicio
                    GROUP BY cs.id_cita
                 ) duracion_cita ON duracion_cita.id_cita = c.id_cita
                 WHERE c.id_barbero = :barbero
                   AND c.estado_cita <> 'Cancelada'
                   AND c.fecha_hora < :fin
                   AND DATE_ADD(c.fecha_hora, INTERVAL COALESCE(duracion_cita.duracion, 60) MINUTE) > :inicio
                 LIMIT 1"
            );
            $overlapCheck->execute(['barbero' => $barberId, 'fin' => $end, 'inicio' => $start]);
            if ($overlapCheck->fetch()) {
                throw new RuntimeException('Ese horario se cruza con otra cita del barbero. Elige otro espacio.');
            }

            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                "INSERT INTO citas (id_cliente, id_barbero, fecha_hora, estado_cita)
                 VALUES (:cliente, :barbero, :fecha_hora, 'Pendiente')"
            );
            $statement->execute([
                'cliente' => $clientId,
                'barbero' => $barberId,
                'fecha_hora' => $start,
            ]);
            $appointmentId = (int) $pdo->lastInsertId();
            $serviceInsert = $pdo->prepare(
                'INSERT INTO cita_servicios (id_cita, id_servicio, precio_aplicado)
                 VALUES (:cita, :servicio, :precio)'
            );
            $serviceInsert->execute([
                'cita' => $appointmentId,
                'servicio' => $serviceId,
                'precio' => $service['precio'],
            ]);
            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Cita creada. Revisa los detalles en la agenda.'];
            redirectTo('agenda');
        }

        if ($action === 'cancel-customer-appointment') {
            if (!$isCustomer) {
                throw new RuntimeException('Esta acción está disponible únicamente para el cliente de la cita.');
            }
            $appointmentId = filter_var($_POST['id_cita'] ?? null, FILTER_VALIDATE_INT);
            if (!$appointmentId) {
                throw new RuntimeException('No se pudo identificar la cita que quieres cancelar.');
            }
            $statement = $pdo->prepare(
                "UPDATE citas
                 SET estado_cita = 'Cancelada'
                 WHERE id_cita = :cita
                   AND id_cliente = :cliente
                   AND estado_cita NOT IN ('Completada', 'Cancelada')
                   AND fecha_hora > DATE_ADD(NOW(), INTERVAL 48 HOUR)"
            );
            $statement->execute([
                'cita' => $appointmentId,
                'cliente' => (int) $_SESSION['user_id'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Solo puedes cancelar tus citas pendientes con más de 48 horas de anticipación.');
            }
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Tu cita fue cancelada.'];
            redirectTo('agenda');
        }

        if ($action === 'update-appointment') {
            if ($isCustomer) {
                throw new RuntimeException('Los clientes no pueden cambiar directamente el estado de las citas.');
            }
            $appointmentId = filter_var($_POST['id_cita'] ?? null, FILTER_VALIDATE_INT);
            $status = (string) ($_POST['estado_cita'] ?? '');
            if (!$appointmentId || !in_array($status, ['Confirmada', 'Completada', 'Cancelada'], true)) {
                throw new RuntimeException('No se pudo actualizar el estado de la cita.');
            }
            $statement = $pdo->prepare(
                'UPDATE citas SET estado_cita = :estado WHERE id_cita = :id'
            );
            $statement->execute(['estado' => $status, 'id' => $appointmentId]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Estado de la cita actualizado.'];
            redirectTo('agenda');
        }

        if ($action === 'create-service') {
            $name = trim((string) ($_POST['nombre_servicio'] ?? ''));
            $description = trim((string) ($_POST['descripcion'] ?? ''));
            $duration = filter_var($_POST['duracion_minutos'] ?? null, FILTER_VALIDATE_INT);
            $price = filter_var($_POST['precio'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($name === '' || !$duration || $duration < 5 || $duration > 240 || $price === false || $price < 0) {
                throw new RuntimeException('Revisa el nombre, duración (5–240 min) y precio del servicio.');
            }
            $statement = $pdo->prepare(
                "INSERT INTO servicios (nombre_servicio, descripcion, duracion_minutos, precio)
                 VALUES (:nombre, :descripcion, :duracion, :precio)"
            );
            $statement->execute([
                'nombre' => $name,
                'descripcion' => $description,
                'duracion' => $duration,
                'precio' => $price,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Servicio agregado al catálogo.'];
            redirectTo('services');
        }

        throw new RuntimeException('Acción no reconocida.');
    } catch (PDOException $exception) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('BeardShop database operation failed: ' . $exception->getMessage());
        $flash = ['type' => 'error', 'text' => 'No se pudo guardar. Revisa si el dato ya existe y vuelve a intentar.'];
    } catch (RuntimeException $exception) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $flash = ['type' => 'error', 'text' => $exception->getMessage()];
    }
}

$today = date('Y-m-d');
$spanishMonths = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$spanishWeekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$todaySpanish = $spanishWeekdays[(int) date('w')] . ', ' . date('d') . ' de ' . $spanishMonths[(int) date('n')];
$selectedClientId = filter_var($_GET['client_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$clients = [
    ['id_usuario' => 1, 'nombre' => 'Alejandro Martín', 'correo' => 'alejandro.martin@email.es', 'telefono' => '612 345 678', 'visitas' => 18, 'total_gastado' => 684000, 'ultima_visita' => $today],
    ['id_usuario' => 2, 'nombre' => 'Pablo García', 'correo' => 'pablo.garcia@email.es', 'telefono' => '622 456 789', 'visitas' => 8, 'total_gastado' => 200000, 'ultima_visita' => $today],
    ['id_usuario' => 3, 'nombre' => 'Javier Torres', 'correo' => 'javier.torres@email.es', 'telefono' => '633 567 890', 'visitas' => 5, 'total_gastado' => 100000, 'ultima_visita' => $today],
];
$services = [
    ['id_servicio' => 1, 'nombre_servicio' => 'Corte clásico', 'descripcion' => 'Corte a tijera o máquina con acabado de navaja.', 'duracion_minutos' => 30, 'precio' => 25000, 'estado' => 'Activo'],
    ['id_servicio' => 2, 'nombre_servicio' => 'Ritual de barba', 'descripcion' => 'Toalla caliente, vapor y perfilado tradicional.', 'duracion_minutos' => 25, 'precio' => 20000, 'estado' => 'Activo'],
    ['id_servicio' => 3, 'nombre_servicio' => 'Corte + barba', 'descripcion' => 'El ritual completo de BeardShop.', 'duracion_minutos' => 45, 'precio' => 38000, 'estado' => 'Activo'],
];
$barbers = [
    ['id_barbero' => 1, 'nombre' => 'Diego Ruiz', 'especialidad' => 'Corte clásico y barba', 'estado' => 'Activo', 'citas' => 5],
    ['id_barbero' => 2, 'nombre' => 'Lucas Vidal', 'especialidad' => 'Fade y diseño', 'estado' => 'Activo', 'citas' => 4],
    ['id_barbero' => 3, 'nombre' => 'Álex Santos', 'especialidad' => 'Barbería tradicional', 'estado' => 'Activo', 'citas' => 3],
];
$appointments = [
    ['id_cita' => 1, 'fecha_hora' => $today . ' 10:00:00', 'estado_cita' => 'Confirmada', 'cliente' => 'Alejandro Martín', 'telefono' => '612 345 678', 'barbero' => 'Diego Ruiz', 'servicio' => 'Corte + barba', 'duracion' => 45, 'total' => 38000],
    ['id_cita' => 2, 'fecha_hora' => $today . ' 10:30:00', 'estado_cita' => 'Confirmada', 'cliente' => 'Pablo García', 'telefono' => '622 456 789', 'barbero' => 'Lucas Vidal', 'servicio' => 'Corte clásico', 'duracion' => 30, 'total' => 25000],
    ['id_cita' => 3, 'fecha_hora' => $today . ' 11:00:00', 'estado_cita' => 'Pendiente', 'cliente' => 'Javier Torres', 'telefono' => '633 567 890', 'barbero' => 'Diego Ruiz', 'servicio' => 'Ritual de barba', 'duracion' => 25, 'total' => 20000],
];
$bookingAvailability = [];
$revenueByService = [
    ['nombre_servicio' => 'Corte clásico', 'total' => 1320000],
    ['nombre_servicio' => 'Corte + barba', 'total' => 960000],
    ['nombre_servicio' => 'Ritual de barba', 'total' => 580000],
];
$metrics = ['appointments' => 12, 'revenue' => 326000, 'occupancy' => 75, 'clients' => 248, 'regulars' => 186, 'services' => 292];

if ($pdo) {
    $clients = $pdo->query(
        "SELECT u.id_usuario, u.nombre, u.correo, u.telefono,
                COUNT(DISTINCT c.id_cita) AS visitas,
                COALESCE(SUM(cs.precio_aplicado), 0) AS total_gastado,
                MAX(c.fecha_hora) AS ultima_visita
         FROM usuarios u
         LEFT JOIN citas c ON c.id_cliente = u.id_usuario
         LEFT JOIN cita_servicios cs ON cs.id_cita = c.id_cita
         WHERE u.rol = 'Cliente'
         GROUP BY u.id_usuario, u.nombre, u.correo, u.telefono
         ORDER BY u.nombre"
    )->fetchAll();
    $services = $pdo->query(
        'SELECT id_servicio, nombre_servicio, descripcion, duracion_minutos, precio, estado
         FROM servicios ORDER BY estado, nombre_servicio'
    )->fetchAll();
    $barbers = $pdo->query(
        "SELECT b.id_barbero, u.nombre, b.especialidad, b.estado,
                COUNT(c.id_cita) AS citas
         FROM barberos b
         JOIN usuarios u ON u.id_usuario = b.id_usuario
         LEFT JOIN citas c ON c.id_barbero = b.id_barbero
         GROUP BY b.id_barbero, u.nombre, b.especialidad, b.estado
         ORDER BY u.nombre"
    )->fetchAll();
    $appointmentScope = $isCustomer ? 'WHERE c.id_cliente = :cliente' : '';
    $appointmentStatement = $pdo->prepare(
        "SELECT c.id_cita, c.fecha_hora, c.estado_cita, u.nombre AS cliente,
                u.telefono, ub.nombre AS barbero,
                COALESCE(GROUP_CONCAT(s.nombre_servicio ORDER BY s.nombre_servicio SEPARATOR ' + '), 'Servicio por definir') AS servicio,
                COALESCE(SUM(cs.precio_aplicado), 0) AS total,
                COALESCE(SUM(s.duracion_minutos), 0) AS duracion
         FROM citas c
         JOIN usuarios u ON u.id_usuario = c.id_cliente
         JOIN barberos b ON b.id_barbero = c.id_barbero
         JOIN usuarios ub ON ub.id_usuario = b.id_usuario
         LEFT JOIN cita_servicios cs ON cs.id_cita = c.id_cita
         LEFT JOIN servicios s ON s.id_servicio = cs.id_servicio
         $appointmentScope
         GROUP BY c.id_cita, c.fecha_hora, c.estado_cita, u.nombre, u.telefono, ub.nombre
         ORDER BY c.fecha_hora DESC
         LIMIT 200"
    );
    $appointmentStatement->execute($isCustomer ? ['cliente' => (int) $_SESSION['user_id']] : []);
    $appointments = $appointmentStatement->fetchAll();
    if ($view === 'appointment-new') {
        $bookingAvailability = $pdo->query(
            "SELECT c.id_barbero, c.fecha_hora,
                    COALESCE(SUM(s.duracion_minutos), 60) AS duracion
             FROM citas c
             LEFT JOIN cita_servicios cs ON cs.id_cita = c.id_cita
             LEFT JOIN servicios s ON s.id_servicio = cs.id_servicio
             WHERE c.estado_cita <> 'Cancelada'
               AND c.fecha_hora >= NOW()
             GROUP BY c.id_cita, c.id_barbero, c.fecha_hora"
        )->fetchAll();
    }
    $summary = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM citas WHERE DATE(fecha_hora) = CURDATE() AND estado_cita <> 'Cancelada') AS citas_hoy,
            (SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE DATE(fecha_pago) = CURDATE() AND estado_pago = 'Aprobado') AS ingresos_hoy,
            (SELECT COUNT(*) FROM citas WHERE DATE(fecha_hora) = CURDATE() AND estado_cita = 'Completada') AS servicios_hoy,
            (SELECT COUNT(*) FROM barberos WHERE estado = 'Activo') AS barberos_activos,
            (SELECT COUNT(*) FROM usuarios WHERE rol = 'Cliente') AS clientes"
    )->fetch();
    $metrics['appointments'] = (int) $summary['citas_hoy'];
    $metrics['revenue'] = (float) $summary['ingresos_hoy'];
    $metrics['services'] = (int) $summary['servicios_hoy'];
    $metrics['occupancy'] = min(100, (int) round($metrics['appointments'] / max(1, (int) $summary['barberos_activos'] * 8) * 100));
    $metrics['clients'] = (int) $summary['clientes'];
    $metrics['regulars'] = count(array_filter($clients, static function ($client) {
        return (int) $client['visitas'] >= 5;
    }));
    $revenueByService = $pdo->query(
        "SELECT s.nombre_servicio,
                COALESCE(SUM(CASE WHEN c.id_cita IS NOT NULL THEN cs.precio_aplicado ELSE 0 END), 0) AS total
         FROM servicios s
         LEFT JOIN cita_servicios cs ON cs.id_servicio = s.id_servicio
         LEFT JOIN citas c ON c.id_cita = cs.id_cita AND c.estado_cita = 'Completada'
         GROUP BY s.id_servicio, s.nombre_servicio
         ORDER BY total DESC
         LIMIT 6"
    )->fetchAll();
}

if ($view === 'client' && $pdo) {
    $clientStatement = $pdo->prepare(
        "SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.fecha_nacimiento, u.notas,
                COUNT(DISTINCT c.id_cita) AS visitas,
                COALESCE(SUM(cs.precio_aplicado), 0) AS total_gastado
         FROM usuarios u
         LEFT JOIN citas c ON c.id_cliente = u.id_usuario
         LEFT JOIN cita_servicios cs ON cs.id_cita = c.id_cita
         WHERE u.id_usuario = :id AND u.rol = 'Cliente'
         GROUP BY u.id_usuario, u.nombre, u.correo, u.telefono, u.fecha_nacimiento, u.notas"
    );
    $clientStatement->execute(['id' => filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0]);
    $clientDetail = $clientStatement->fetch();
} else {
    $clientDetail = null;
}

$pageNames = [
    'dashboard' => 'Resumen', 'agenda' => 'Agenda', 'appointment-new' => 'Agenda',
    'clients' => 'Clientes', 'client' => 'Clientes', 'services' => 'Servicios',
    'team' => 'Equipo', 'reports' => 'Reportes', 'settings' => 'Configuración',
    'help' => 'Centro de ayuda', 'login' => 'Acceso', 'offline' => 'Agenda sin conexión',
    'not-found' => 'Página no encontrada',
];
$currentPageName = $pageNames[$view] ?? 'Resumen';
$navigation = $isCustomer
    ? [
        ['agenda', 'Mis citas', 'calendar'],
        ['appointment-new', 'Nueva cita', 'calendar'],
    ]
    : [
        ['dashboard', 'Resumen', 'grid'],
        ['agenda', 'Agenda', 'calendar'],
        ['clients', 'Clientes', 'users'],
        ['services', 'Servicios', 'scissors'],
        ['team', 'Equipo', 'person'],
        ['reports', 'Reportes', 'chart'],
    ];
$maxServiceRevenue = max(1, ...array_map(static function ($service) {
    return (float) $service['total'];
}, $revenueByService));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#242623">
    <title><?= h($currentPageName) ?> · BeardShop</title>
    <link rel="stylesheet" href="app.css">
</head>
<body>
<?php if ($view === 'login'): ?>
    <main class="login-layout">
        <section class="login-story">
            <a class="brand brand-light" href="index.php"><span class="brand-mark">✂</span><span>BeardShop<small>BARBERÍA · EST. 2018</small></span></a>
            <div class="login-copy">
                <p class="eyebrow">EL ESPACIO DETRÁS DEL OFICIO</p>
                <h1>Buen corte.<br>Buena gestión.</h1>
                <p>Menos tiempo entre papeles. Más tiempo para lo que mejor sabes hacer.</p>
            </div>
            <span class="login-foot">PRECISIÓN · CARÁCTER · CUIDADO</span>
        </section>
        <section class="login-panel">
            <a class="brand" href="index.php"><span class="brand-mark">✂</span><span>BeardShop<small>BARBERÍA · EST. 2018</small></span></a>
            <p class="eyebrow">TU NEGOCIO, EN ORDEN</p>
            <h2>Bienvenido a BeardShop.</h2>
            <p class="muted">Entra y prepara una gran jornada.</p>
            <form class="stack-form" action="index.php?view=login" method="post">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="login">
                <label>Correo electrónico<input type="email" name="correo" placeholder="usuario@beardshop.com" required autocomplete="username"></label>
                <label>Contraseña<input type="password" name="password" placeholder="••••••••••••" required autocomplete="current-password"></label>
                <label class="remember"><input type="checkbox" checked> Recordarme <a href="index.php?view=help">¿Olvidaste tu contraseña?</a></label>
                <button class="button button-primary button-wide" type="submit">→ <span>Entrar a mi barbería</span></button>
            </form>
            <p class="secure-note">♙ &nbsp; Acceso seguro. Tus datos, siempre protegidos.</p>
            <hr>
            <p class="support-link">¿Necesitas una mano? <a href="index.php?view=help">Contactar con soporte</a></p>
        </section>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand brand-light" href="index.php" aria-label="BeardShop, ir al resumen">
                <span class="brand-mark">✂</span><span>BeardShop<small>BARBERÍA · EST. 2018</small></span>
            </a>
            <div class="location-card"><strong>Bogotá · Colombia</strong><span>Dirección por confirmar</span></div>
            <p class="side-label">GESTIÓN DEL NEGOCIO</p>
            <nav class="side-nav" aria-label="Navegación principal">
                <?php foreach ($navigation as [$route, $label, $symbol]): ?>
                    <?php $isActive = $view === $route || (!$isCustomer && $route === 'agenda' && $view === 'appointment-new'); ?>
                    <a class="nav-link <?= $isActive ? 'active' : '' ?>"
                       href="index.php?view=<?= h($route) ?>">
                        <span class="nav-icon"><?= icon($symbol) ?></span><span><?= h($label) ?></span>
                        <?php if (!$isCustomer && $route === 'agenda' && $metrics['appointments']): ?><small><?= (int) $metrics['appointments'] ?></small><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="side-bottom">
                <?php if (!$isCustomer): ?>
                    <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="index.php?view=settings"><span class="nav-icon"><?= icon('settings') ?></span><span>Configuración</span></a>
                    <a class="nav-link <?= $view === 'help' ? 'active' : '' ?>" href="index.php?view=help"><span class="nav-icon"><?= icon('help') ?></span><span>Centro de ayuda</span></a>
                <?php endif; ?>
                <a class="profile-link" href="index.php?view=<?= isset($_SESSION['user_id']) ? 'logout' : 'login' ?>"><span class="avatar avatar-small"><?= h(initials((string) ($_SESSION['user_name'] ?? 'Marina López'))) ?></span><span><strong><?= h($_SESSION['user_name'] ?? 'Marina López') ?></strong><small><?= h($_SESSION['user_role'] ?? 'Vista previa') ?></small></span><span class="profile-arrow"><?= isset($_SESSION['user_id']) ? '↗' : '→' ?></span></a>
            </div>
        </aside>

        <main class="main-area">
            <header class="topbar">
                <button class="menu-toggle" type="button" aria-label="Abrir navegación" aria-expanded="false">☰</button>
                <div class="breadcrumbs">Mi negocio <span>/</span> <strong><?= h($currentPageName) ?></strong></div>
                <a class="topbar-avatar" href="index.php?view=login" aria-label="Perfil de Marina López">ML</a>
            </header>
            <div class="content">
                <?php if ($connectionError !== ''): ?>
                    <div class="notice notice-warning"><?= h($connectionError) ?> <a href="index.php?view=help">Ver ayuda</a></div>
                <?php endif; ?>
                <?php if ($flash): ?>
                    <div class="notice notice-<?= h($flash['type']) ?>" role="status"><?= h($flash['text']) ?></div>
                <?php endif; ?>

                <?php if ($view === 'dashboard'): ?>
                    <section class="page-heading">
                        <p class="eyebrow"><?= h(strtoupper($todaySpanish)) ?></p>
                        <h1>Buenos días, Marina.</h1>
                        <p>Todo listo para un día de buen oficio.</p>
                    </section>
                    <section class="metric-grid">
                        <article class="metric-card"><span>Citas de hoy</span><strong><?= (int) $metrics['appointments'] ?></strong><small><?= count(array_filter($appointments, static function ($appointment) { return substr($appointment['fecha_hora'], 0, 10) === date('Y-m-d') && $appointment['estado_cita'] === 'Confirmada'; })) ?> confirmadas · <?= count(array_filter($appointments, static function ($appointment) { return substr($appointment['fecha_hora'], 0, 10) === date('Y-m-d') && $appointment['estado_cita'] === 'Pendiente'; })) ?> pendientes</small></article>
                        <article class="metric-card"><span>Ingresos de hoy</span><strong><?= h(pesos($metrics['revenue'])) ?></strong><small class="trend">↑ Tu trabajo, bien hecho</small></article>
                        <article class="metric-card"><span>Ocupación</span><strong><?= (int) $metrics['occupancy'] ?> %</strong><small>Agenda del equipo para hoy</small></article>
                    </section>
                    <section class="section-card schedule-card">
                        <div class="section-head"><div><p class="eyebrow"><?= h(strtoupper($todaySpanish)) ?></p><h2>Agenda del día</h2></div><div class="head-actions"><a class="button button-secondary" href="index.php?view=agenda">Vista diaria</a><a class="button button-primary" href="index.php?view=appointment-new">＋ Nueva cita</a></div></div>
                        <div class="schedule-list">
                            <?php foreach (array_filter($appointments, static function ($appointment) { return substr($appointment['fecha_hora'], 0, 10) === date('Y-m-d'); }) as $appointment): ?>
                                <article class="schedule-item">
                                    <time><?= h(date('H:i', strtotime($appointment['fecha_hora']))) ?><small><?= (int) $appointment['duracion'] ?> min</small></time>
                                    <div class="schedule-client"><span class="avatar"><?= h(initials($appointment['cliente'])) ?></span><div><strong><?= h($appointment['cliente']) ?></strong><small><?= h($appointment['servicio']) ?></small></div></div>
                                    <div class="schedule-barber"><?= h($appointment['barbero']) ?><small><?= h($appointment['estado_cita']) ?></small></div>
                                    <strong class="schedule-price"><?= h(pesos($appointment['total'])) ?></strong>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$appointments): ?><div class="empty-state">Todavía no hay citas. <a href="index.php?view=appointment-new">Agenda la primera.</a></div><?php endif; ?>
                        </div>
                    </section>
                    <section class="quick-note"><span>✦</span><p><strong>Un buen día empieza con orden.</strong><br>Revisa la agenda, saluda a tus clientes y disfruta el oficio.</p><a href="index.php?view=reports">Ver el rendimiento →</a></section>

                <?php elseif ($view === 'agenda'): ?>
                    <section class="page-heading"><p class="eyebrow">AGENDA / CITAS</p><h1>Cada cita cuenta.</h1><p>Organiza las reservas y cuida cada visita, desde el primer minuto.</p></section>
                    <div class="tabs appointment-tabs"><a class="tab active" href="#todas" data-appointment-status="">Todas las citas (<?= count($appointments) ?>)</a><a class="tab" href="#confirmadas" data-appointment-status="Confirmada">Confirmadas</a><a class="tab" href="#pendientes" data-appointment-status="Pendiente">Pendientes</a><a class="tab" href="index.php?view=offline">Modo sin conexión</a></div>
                    <section class="section-card filters">
                        <label class="search-field">Buscar cita<input data-filter="appointment" type="search" placeholder="Nombre, teléfono o referencia"></label>
                        <label>Fecha (vacía = todas)<input type="date" value="" id="agenda-date"></label>
                        <label>Barbero<select id="barber-filter"><option value="">Todo el equipo</option><?php foreach ($barbers as $barber): ?><option><?= h($barber['nombre']) ?></option><?php endforeach; ?></select></label>
                        <a class="button button-primary" href="index.php?view=appointment-new">＋ Nueva cita</a>
                    </section>
                    <section class="section-card table-card">
                        <div class="table-wrap"><table><thead><tr><th>Hora</th><th>Cliente</th><th>Servicio</th><th>Barbero</th><th>Importe</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr class="filter-row" data-appointment-date="<?= h(substr($appointment['fecha_hora'], 0, 10)) ?>" data-barber="<?= h($appointment['barbero']) ?>" data-status="<?= h($appointment['estado_cita']) ?>" data-search="<?= h(strtolower($appointment['cliente'] . ' ' . $appointment['telefono'] . ' ' . $appointment['servicio'])) ?>">
                                <td><strong><?= h(date('H:i', strtotime($appointment['fecha_hora']))) ?></strong><small><?= h(date('d/m', strtotime($appointment['fecha_hora']))) ?></small></td>
                                <td><strong><?= h($appointment['cliente']) ?></strong><small><?= h($appointment['telefono']) ?></small></td>
                                <td><?= h($appointment['servicio']) ?><small><?= (int) $appointment['duracion'] ?> minutos</small></td>
                                <td><?= h($appointment['barbero']) ?></td><td><?= h(pesos($appointment['total'])) ?></td>
                                <td><span class="status status-<?= h(strtolower($appointment['estado_cita'])) ?>"><?= h($appointment['estado_cita']) ?></span></td>
                                <td>
                                    <?php if ($isCustomer && !in_array($appointment['estado_cita'], ['Completada', 'Cancelada'], true)): ?>
                                        <?php if (strtotime($appointment['fecha_hora']) > time() + 48 * 60 * 60): ?>
                                            <form method="post" class="inline-form" data-confirm="¿Cancelar esta cita?"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="cancel-customer-appointment"><input type="hidden" name="id_cita" value="<?= (int) $appointment['id_cita'] ?>"><button class="text-button text-button-danger" type="submit">Cancelar</button></form>
                                        <?php else: ?>
                                            <span class="muted">No cancelable (menos de 48 h)</span>
                                        <?php endif; ?>
                                    <?php elseif (!$isCustomer && $appointment['estado_cita'] !== 'Completada' && $appointment['estado_cita'] !== 'Cancelada'): ?>
                                        <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="update-appointment"><input type="hidden" name="id_cita" value="<?= (int) $appointment['id_cita'] ?>"><input type="hidden" name="estado_cita" value="<?= $appointment['estado_cita'] === 'Pendiente' ? 'Confirmada' : 'Completada' ?>"><button class="text-button" type="submit"><?= $appointment['estado_cita'] === 'Pendiente' ? 'Confirmar' : 'Completar' ?></button></form>
                                        <form method="post" class="inline-form" data-confirm="¿Cancelar esta cita? Se liberará el horario."><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="update-appointment"><input type="hidden" name="id_cita" value="<?= (int) $appointment['id_cita'] ?>"><input type="hidden" name="estado_cita" value="Cancelada"><button class="text-button text-button-danger" type="submit">Cancelar</button></form>
                                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        <?php if (!$appointments): ?><div class="empty-state">No hay citas registradas todavía. <a href="index.php?view=appointment-new">Crea una cita</a> para empezar.</div><?php endif; ?>
                    </section>

                <?php elseif ($view === 'appointment-new'): ?>
                    <a class="back-link" href="index.php?view=agenda">← Volver a la agenda</a>
                    <section class="page-heading"><p class="eyebrow">AGENDA / NUEVA CITA</p><h1>Un sitio en la silla.</h1><p>Reserva una visita en unos pocos pasos.</p></section>
                    <form class="booking-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="create-appointment">
                        <section class="section-card form-section"><div class="section-head"><h2>Datos de la visita</h2><small>* Obligatorios</small></div>
                                        <?php if ($isCustomer): ?>
                                            <label>Cliente<input value="<?= h($_SESSION['user_name'] ?? '') ?>" readonly></label>
                                        <?php else: ?>
                                            <label>Cliente *<select name="id_cliente" required><option value="">Selecciona un cliente</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id_usuario'] ?>" <?= $selectedClientId === (int) $client['id_usuario'] ? 'selected' : '' ?>><?= h($client['nombre']) ?> · <?= h($client['telefono']) ?></option><?php endforeach; ?></select></label>
                                            <a class="small-link" href="index.php?view=clients">＋ Registrar un nuevo cliente</a>
                                        <?php endif; ?>
                            <label>Servicio *<select id="booking-service" name="id_servicio" required><option value="">Selecciona un servicio</option><?php foreach ($services as $service): if ($service['estado'] !== 'Activo') { continue; } ?><option value="<?= (int) $service['id_servicio'] ?>" data-duration="<?= (int) $service['duracion_minutos'] ?>"><?= h($service['nombre_servicio']) ?> · <?= (int) $service['duracion_minutos'] ?> min · <?= h(pesos($service['precio'])) ?></option><?php endforeach; ?></select></label>
                            <label>Barbero *<select id="booking-barber" name="id_barbero" required><option value="">Selecciona un barbero</option><?php foreach ($barbers as $barber): if ($barber['estado'] !== 'Activo') { continue; } ?><option value="<?= (int) $barber['id_barbero'] ?>"><?= h($barber['nombre']) ?></option><?php endforeach; ?></select></label>
                        </section>
                        <section class="section-card form-section"><div class="section-head"><h2>Fecha y horario</h2><small>Hora local · Bogotá</small></div>
                            <div class="booking-schedule">
                                <label class="booking-date-label" for="booking-date">Elige el día
                                    <input id="booking-date" type="date" name="booking_date" min="<?= h($today) ?>" data-now="<?= h(date('Y-m-d\TH:i')) ?>" required>
                                </label>
                                <fieldset class="time-slot-group">
                                    <legend>Elige la hora</legend>
                                    <div class="time-slot-grid">
                                        <?php for ($hour = 9; $hour <= 17; $hour++): ?>
                                            <?php $time = sprintf('%02d:00', $hour); $slotId = 'booking-time-' . $hour; ?>
                                            <label class="time-slot" for="<?= h($slotId) ?>">
                                                <input id="<?= h($slotId) ?>" type="radio" name="booking_time" value="<?= h($time) ?>" required>
                                                <span><?= h(date('g:i', strtotime($time))) ?><?= $hour < 12 ? ' a. m.' : ' p. m.' ?></span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </fieldset>
                                <input type="hidden" name="fecha_hora" id="booking-datetime">
                            </div>
                            <p class="muted" id="booking-availability-message" aria-live="polite">Selecciona servicio, barbero y fecha para consultar los horarios.</p>
                            <script type="application/json" id="booking-availability"><?= json_encode($bookingAvailability, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                            <p class="muted">Horario disponible: de 9:00 a. m. a 5:00 p. m. La cita debe terminar antes de las 6:00 p. m.</p>
                            <noscript><p class="notice notice-warning">Activa JavaScript para seleccionar el día y la hora de la cita.</p></noscript>
                        </section>
                        <div class="form-actions"><a class="button button-secondary" href="index.php?view=agenda">Cancelar</a><button id="booking-submit" class="button button-primary" type="submit">Guardar cita →</button></div>
                    </form>

                <?php elseif ($view === 'clients'): ?>
                    <section class="page-heading"><p class="eyebrow">RELACIONES QUE DURAN</p><h1>Clientes de la casa.</h1><p>Conoce sus preferencias. Haz que cada visita se sienta personal.</p></section>
                    <section class="metric-grid metric-grid-two"><article class="metric-card"><span>Clientes registrados</span><strong><?= (int) $metrics['clients'] ?></strong><small>Personas que confían en BeardShop</small></article><article class="metric-card"><span>Clientes habituales</span><strong><?= (int) $metrics['regulars'] ?></strong><small>5 o más visitas</small></article></section>
                    <section class="section-card">
                        <div class="filters filters-client"><label class="search-field">Buscar cliente<input data-filter="client" type="search" placeholder="Nombre, correo o teléfono"></label><label>Segmento<select id="client-segment"><option value="">Todos los clientes</option><option value="regular">Clientes habituales</option></select></label><button class="button button-primary" type="button" data-open-dialog="new-client-dialog">＋ Nuevo cliente</button></div>
                        <div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Última visita</th><th>Visitas</th><th>Total gastado</th></tr></thead><tbody>
                        <?php foreach ($clients as $client): ?><tr class="client-row" data-search="<?= h(strtolower($client['nombre'] . ' ' . $client['correo'] . ' ' . $client['telefono'])) ?>" data-regular="<?= (int) $client['visitas'] >= 5 ? '1' : '0' ?>"><td><a class="client-name" href="index.php?view=client&id=<?= (int) $client['id_usuario'] ?>"><span class="avatar"><?= h(initials($client['nombre'])) ?></span><span><strong><?= h($client['nombre']) ?></strong><small><?= h($client['telefono']) ?></small></span></a></td><td><?= !empty($client['ultima_visita']) ? h(date('d/m/Y', strtotime($client['ultima_visita']))) : '—' ?></td><td><?= (int) $client['visitas'] ?></td><td><strong><?= h(pesos($client['total_gastado'])) ?></strong></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                        <?php if (!$clients): ?><div class="empty-state">Aún no hay clientes. Agrega el primero para comenzar.</div><?php endif; ?>
                    </section>
                    <dialog class="app-dialog" id="new-client-dialog"><form method="post" class="stack-form"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="create-client"><button class="dialog-close" type="button" data-close-dialog aria-label="Cerrar">×</button><p class="eyebrow">CLIENTES / NUEVO REGISTRO</p><h2>Una nueva relación.</h2><label>Nombre completo *<input name="nombre" required maxlength="100"></label><label>Correo electrónico *<input type="email" name="correo" required maxlength="100"></label><label>Teléfono *<input name="telefono" required maxlength="20"></label><div class="form-actions"><button type="button" class="button button-secondary" data-close-dialog>Cancelar</button><button type="submit" class="button button-primary">Guardar cliente</button></div></form></dialog>

                <?php elseif ($view === 'client'): ?>
                    <a class="back-link" href="index.php?view=clients">← Volver a clientes</a>
                    <section class="page-heading"><p class="eyebrow">CLIENTES / FICHA PERSONAL</p><h1>Conocer para cuidar.</h1></section>
                    <?php if (!$clientDetail): ?><div class="notice notice-warning">No encontramos este cliente. <a href="index.php?view=clients">Volver a clientes</a></div><?php else: ?>
                    <div class="head-actions detail-actions"><button class="button button-secondary" type="button" data-open-dialog="edit-client-dialog">✎ Editar cliente</button><a class="button button-primary" href="index.php?view=appointment-new&client_id=<?= (int) $clientDetail['id_usuario'] ?>">＋ Nueva cita</a></div>
                    <section class="section-card client-profile"><span class="avatar avatar-large"><?= h(initials($clientDetail['nombre'])) ?></span><h2><?= h($clientDetail['nombre']) ?></h2><span class="status status-confirmada"><?= (int) $clientDetail['visitas'] >= 5 ? 'Cliente habitual' : 'Cliente' ?></span><p class="muted">En BeardShop desde siempre.</p><hr><p>☎ &nbsp; <?= h($clientDetail['telefono']) ?></p><p>✉ &nbsp; <?= h($clientDetail['correo']) ?></p><?php if (!empty($clientDetail['fecha_nacimiento'])): ?><p>▦ &nbsp; <?= h($clientDetail['fecha_nacimiento']) ?></p><?php endif; ?><a class="button button-secondary" href="https://wa.me/<?= h(preg_replace('/\D+/', '', $clientDetail['telefono'])) ?>" target="_blank" rel="noopener noreferrer">◉ &nbsp; Enviar WhatsApp</a><hr><div class="profile-totals"><span><small>Visitas</small><strong><?= (int) $clientDetail['visitas'] ?></strong></span><span><small>Total en servicios</small><strong><?= h(pesos($clientDetail['total_gastado'])) ?></strong></span></div></section>
                    <dialog class="app-dialog" id="edit-client-dialog"><form method="post" class="stack-form"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="update-client"><input type="hidden" name="id_usuario" value="<?= (int) $clientDetail['id_usuario'] ?>"><button class="dialog-close" type="button" data-close-dialog aria-label="Cerrar">×</button><p class="eyebrow">CLIENTES / EDITAR REGISTRO</p><h2>Actualizar ficha.</h2><label>Nombre completo *<input name="nombre" value="<?= h($clientDetail['nombre']) ?>" required maxlength="100"></label><label>Correo electrónico *<input type="email" name="correo" value="<?= h($clientDetail['correo']) ?>" required maxlength="100"></label><label>Teléfono *<input name="telefono" value="<?= h($clientDetail['telefono']) ?>" required maxlength="20"></label><div class="form-actions"><button type="button" class="button button-secondary" data-close-dialog>Cancelar</button><button type="submit" class="button button-primary">Guardar cambios</button></div></form></dialog>
                    <?php endif; ?>

                <?php elseif ($view === 'services'): ?>
                    <section class="page-heading"><p class="eyebrow">EL ARTE DEL DETALLE</p><h1>Servicios con carácter.</h1><p>Cada servicio, una razón para volver.</p></section>
                    <div class="service-grid"><?php foreach ($services as $service): ?><article class="section-card service-card"><span class="service-icon"><?= icon('scissors') ?></span><span class="status status-<?= strtolower($service['estado']) ?>"><?= h($service['estado']) ?></span><h2><?= h($service['nombre_servicio']) ?></h2><p><?= h($service['descripcion'] ?: 'Un servicio hecho con oficio y cuidado.') ?></p><div class="service-meta"><span><?= (int) $service['duracion_minutos'] ?> min</span><strong><?= h(pesos($service['precio'])) ?></strong></div></article><?php endforeach; ?></div>
                    <details class="section-card add-service"><summary>＋ Añadir un servicio</summary><form method="post" class="service-form"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="create-service"><label>Nombre<input name="nombre_servicio" required maxlength="100"></label><label>Duración (min)<input type="number" name="duracion_minutos" min="5" max="240" required></label><label>Precio (COP)<input type="number" name="precio" min="0" step="1000" required></label><label>Descripción<input name="descripcion"></label><button class="button button-primary" type="submit">Guardar servicio</button></form></details>

                <?php elseif ($view === 'team'): ?>
                    <section class="page-heading"><p class="eyebrow">MANOS EXPERTAS</p><h1>El equipo detrás del oficio.</h1><p>Personas que hacen de cada visita un buen recuerdo.</p></section>
                    <section class="team-grid"><?php foreach ($barbers as $barber): ?><article class="section-card team-card"><span class="avatar avatar-large"><?= h(initials($barber['nombre'])) ?></span><span class="status status-<?= strtolower($barber['estado']) ?>"><?= h($barber['estado']) ?></span><h2><?= h($barber['nombre']) ?></h2><p><?= h($barber['especialidad'] ?: 'Barbero BeardShop') ?></p><div class="service-meta"><span><?= (int) $barber['citas'] ?> citas registradas</span><strong>✦</strong></div></article><?php endforeach; ?></section>

                <?php elseif ($view === 'reports'): ?>
                    <section class="page-heading"><p class="eyebrow">UNA MIRADA A TU NEGOCIO</p><h1>Los números del oficio.</h1><p>Ingresos, servicios y rendimiento para decidir con claridad.</p></section>
                    <div class="tabs"><a class="tab active" href="index.php?view=reports">Resumen general</a><a class="tab" href="index.php?view=reports#ingresos">Ingresos</a><a class="tab" href="index.php?view=reports#servicios">Servicios</a><a class="tab" href="index.php?view=reports#equipo">Equipo</a></div>
                    <section class="metric-grid"><article class="metric-card"><span>Ingresos cobrados</span><strong><?= h(pesos($metrics['revenue'])) ?></strong><small>Pagos aprobados hoy</small></article>                    <article class="metric-card"><span>Servicios realizados</span><strong><?= (int) $metrics['services'] ?></strong><small>Citas completadas hoy</small></article><article class="metric-card"><span>Ticket medio</span><strong><?= h(pesos($metrics['appointments'] ? $metrics['revenue'] / $metrics['appointments'] : 0)) ?></strong><small>Por cita de hoy</small></article></section>
                    <section class="section-card report-card" id="ingresos"><div class="section-head"><div><h2>Servicios con más movimiento</h2><p class="muted">Ingresos registrados por servicio completado</p></div><span class="date-pill"><?= h($spanishMonths[(int) date('n')] . ' ' . date('Y')) ?></span></div><div class="bar-chart"><?php foreach ($revenueByService as $service): ?><div class="bar-row"><span><?= h($service['nombre_servicio']) ?></span><div class="bar-track"><div class="bar-fill" style="width: <?= max(4, (int) round((float) $service['total'] / $maxServiceRevenue * 100)) ?>%"></div></div><strong><?= h(pesos($service['total'])) ?></strong></div><?php endforeach; ?></div><?php if (!$revenueByService): ?><div class="empty-state">Completa citas para comenzar a ver tus resultados.</div><?php endif; ?></section>
                    <section class="section-card report-card" id="equipo"><h2>Equipo activo</h2><div class="report-team"><?php foreach ($barbers as $barber): ?><div><span class="avatar"><?= h(initials($barber['nombre'])) ?></span><strong><?= h($barber['nombre']) ?></strong><span><?= (int) $barber['citas'] ?> citas</span></div><?php endforeach; ?></div></section>

                <?php elseif ($view === 'settings'): ?>
                    <section class="page-heading"><p class="eyebrow">TU CASA, TUS REGLAS</p><h1>Así es BeardShop.</h1><p>La información que ayuda a tus clientes a encontrarte.</p></section>
                    <section class="section-card settings-card"><p class="eyebrow">DATOS DEL NEGOCIO</p><h2>El espacio detrás del oficio.</h2><dl><div><dt>Nombre</dt><dd>BeardShop · Barbería</dd></div><div><dt>Ubicación</dt><dd>Bogotá, Colombia</dd></div><div><dt>Horario</dt><dd>Lunes a sábado · 9:00 a. m. – 7:00 p. m.</dd></div><div><dt>Moneda</dt><dd>Peso colombiano (COP)</dd></div><div><dt>Zona horaria</dt><dd>America/Bogota</dd></div></dl><p class="muted">Para cambiar los datos de conexión o la información del negocio, consulta el archivo <a href="README.md">README.md</a>.</p></section>

                <?php elseif ($view === 'help'): ?>
                    <section class="page-heading"><p class="eyebrow">SIEMPRE A MANO</p><h1>¿En qué te ayudamos?</h1><p>Respuestas claras para que puedas volver al oficio.</p></section>
                    <section class="help-grid"><a class="section-card help-card" href="index.php?view=agenda"><span>01</span><h2>Gestionar la agenda</h2><p>Consulta, confirma y completa las citas de hoy.</p></a><a class="section-card help-card" href="index.php?view=clients"><span>02</span><h2>Cuidar a tus clientes</h2><p>Revisa las fichas y el historial de cada visita.</p></a><a class="section-card help-card" href="index.php?view=services"><span>03</span><h2>Actualizar servicios</h2><p>Organiza precios, duración y descripción.</p></a><a class="section-card help-card" href="index.php?view=offline"><span>04</span><h2>Sin conexión</h2><p>Entiende qué puedes consultar cuando no hay red.</p></a></section>

                <?php elseif ($view === 'offline'): ?>
                    <div class="offline-banner">⌁ &nbsp; MODO SIN CONEXIÓN &nbsp; <span>Vista de ejemplo · <?= h(date('H:i')) ?></span></div>
                    <section class="page-heading"><p class="eyebrow">AGENDA / MODO SIN CONEXIÓN</p><h1>El oficio no se detiene.</h1><p>Tu agenda sigue disponible para consultar. Reconecta para gestionar las citas.</p></section>
                    <section class="section-card offline-card"><span class="offline-mark">⌁</span><h2>Parece que nos hemos desconectado.</h2><p>Esta pantalla muestra el estado cuando no hay red. Si la agenda está guardada en el dispositivo, se puede consultar; para gestionar citas necesitarás conexión.</p><hr><p>✓ &nbsp; Consultar citas guardadas previamente.</p><p>Ⅱ &nbsp; Crear, editar y cobrar citas requiere conexión.</p><p>↻ &nbsp; La agenda se actualizará al recuperar la conexión.</p><a class="button button-primary" href="index.php?view=agenda">↻ &nbsp; Volver a la agenda</a></section>

                <?php else: ?>
                    <section class="not-found"><p class="eyebrow">ERROR 404 / FUERA DE AGENDA</p><strong>404</strong><h1>Esta página se nos ha ido de las manos.</h1><p>El enlace puede haber cambiado o la página ya no existe. Tu negocio sigue en su sitio. Volvamos a él.</p><a class="button button-primary" href="index.php">Ir al resumen</a><a class="button button-secondary" href="index.php?view=agenda">Volver a la agenda</a></section>
                <?php endif; ?>
            </div>
        </main>
    </div>
<?php endif; ?>
<script src="app.js" defer></script>
</body>
</html>
<?php
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        if (preg_match('/^./us', $part, $match)) {
            $letters .= $match[0];
        }
    }
    return strtoupper($letters);
}

function icon(string $name): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'scissors' => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8.1 8.1 12.8 12.8M14 6l-3.5 3.5M8.1 15.9 12 12"/>',
        'person' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="8" r="4"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-5 5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.7 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.7-1l-1.7.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.7-1l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.7 1l1.7-.7 1.4 2.4-1.4 1.1a7 7 0 0 1 0 2Z"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.5 1.5c-1.2 1.2-2.1 1.4-2.1 3M12 17h.01"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}
