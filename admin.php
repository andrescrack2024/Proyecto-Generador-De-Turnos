<?php
require_once 'db_config.php';

// Seguridad: Verificar que el admin esté logueado Y TENGA ROL 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') {
    header("Location: login.php?error=no_admin");
    exit;
}

// Lógica de cierre de sesión
if (isset($_GET['logout'])) {
    try {
        $stmt_log = $pdo->prepare("INSERT INTO logs_auditoria (id_usuario_accion, usuario_accion, accion, detalles) VALUES (?, ?, 'Logout Admin', ?)");
        $stmt_log->execute([$_SESSION['user_id'], $_SESSION['user_usuario'], 'Cierre de sesión de admin']);
    } catch (Exception $e) { /* Ignorar error de log */ }
    
    $stmt_clear = $pdo->prepare("UPDATE usuarios SET session_id = NULL WHERE id = ?");
    $stmt_clear->execute([$_SESSION['user_id']]);
    session_destroy();
    header("Location: login.php");
    exit;
}

// Determinar la vista actual
$vista = $_GET['vista'] ?? 'dashboard'; // Por defecto, 'dashboard'
$titulo_vista = 'Dashboard';

// --- Lógica para el Dashboard ---
if ($vista == 'dashboard') {
    $titulo_vista = 'Inicio'; // <- Cambiado para coincidir con la imagen
    $stmt_hoy = $pdo->query("SELECT COUNT(*) FROM turnos WHERE DATE(fecha_creacion) = CURDATE()");
    $turnos_hoy = $stmt_hoy->fetchColumn();
    $stmt_espera = $pdo->query("SELECT COUNT(*) FROM turnos WHERE estado = 'espera' AND DATE(fecha_creacion) = CURDATE()");
    $turnos_espera = $stmt_espera->fetchColumn();
    $stmt_atendidos = $pdo->query("SELECT COUNT(*) FROM turnos WHERE estado = 'atendido' AND DATE(fecha_creacion) = CURDATE()");
    $turnos_atendidos = $stmt_atendidos->fetchColumn();
    $stmt_top_cajeros = $pdo->query(
        "SELECT u.usuario, COUNT(t.id) as total_atendidos
         FROM turnos t
         JOIN usuarios u ON t.id_usuario_atendio = u.id
         WHERE t.estado = 'atendido' AND DATE(t.fecha_fin_atencion) = CURDATE()
         GROUP BY u.usuario ORDER BY total_atendidos DESC"
    );
    $top_cajeros = $stmt_top_cajeros->fetchAll();
}

// --- Lógica para Gestionar Usuarios ---
if ($vista == 'usuarios') {
    $titulo_vista = 'Gestionar Usuarios';
    $stmt_users = $pdo->query("SELECT u.*, p.nombre_ubicacion FROM usuarios u LEFT JOIN pisos_cajas p ON u.id_caja_asignada = p.id ORDER BY u.nombre_completo");
    $lista_usuarios = $stmt_users->fetchAll();
}

// --- Lógica para Auditoría ---
if ($vista == 'auditoria') {
    $titulo_vista = 'Registro de Auditoría';
    $stmt_logs = $pdo->query("SELECT * FROM logs_auditoria ORDER BY fecha DESC LIMIT 100");
    $lista_logs = $stmt_logs->fetchAll();
}

// --- Lógica: Ver Todos los Turnos ---
if ($vista == 'turnos') {
    $titulo_vista = 'Gestión de Turnos'; // <- Cambiado para coincidir con la imagen
    $stmt_turnos = $pdo->query(
        "SELECT t.*, ta.nombre as tipo_atencion_nombre, u.usuario as nombre_cajero, p.nombre_ubicacion 
         FROM turnos t
         JOIN tipos_atencion ta ON t.id_tipo_atencion = ta.id
         LEFT JOIN usuarios u ON t.id_usuario_atendio = u.id 
         LEFT JOIN pisos_cajas p ON t.id_caja_atendio = p.id 
         ORDER BY t.fecha_creacion DESC 
         LIMIT 200"
    );
    $lista_turnos = $stmt_turnos->fetchAll();
}

// --- Cargar datos para los modales de formularios ---
$stmt_cajas = $pdo->query("SELECT id, nombre_ubicacion FROM pisos_cajas ORDER BY nombre_ubicacion");
$lista_cajas = $stmt_cajas->fetchAll();
$stmt_tipos_atencion = $pdo->query("SELECT id, nombre FROM tipos_atencion ORDER BY nombre");
$lista_tipos_atencion = $stmt_tipos_atencion->fetchAll();

// Convertir a JSON para que JavaScript los use
$json_lista_cajas = json_encode($lista_cajas);
$json_lista_tipos_atencion = json_encode($lista_tipos_atencion);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Smarque Bank</title>
    <!-- Tailwind se sigue usando para el contenido (tarjetas, tablas) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /*
        * =================================
        * (NUEVO) DISEÑO "GLASSMORPHISM" (SMARQUE BANK)
        * =================================
        */
        :root {
            --color-principal: #582C83; 
            --color-principal-hover: #4a236d;
            --color-principal-light: #7a4fc0; /* Para el hover/active de la sidebar */
            --color-principal-transparente: rgba(45, 22, 70, 0.85); /* Sidebar */
            --color-principal-transparente-header: rgba(69, 33, 105, 0.85); /* Header */
            
            --color-acento: #FBC90E;      
            --color-fondo: #F8F9FA;      
            --color-texto-claro: #FFFFFF;
            --color-texto-oscuro: #333;
            --color-texto-sidebar: #E0D9E9; /* Texto de enlaces de sidebar */
            --color-borde-input: #ddd;
            
            --color-rojo: #DC2626;
            --color-rojo-hover: #B91C1C;
            --color-verde: #059669;
            --color-verde-hover: #047857;
            --color-azul: #2563EB;
            --color-azul-hover: #1D4ED8;
            --color-amarillo: #D97706;
        }

        /* Pequeño ajuste para que el scroll sea bonito */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #999; border-radius: 4px; }
        
        body, html {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            overflow: hidden; /* Evitar scroll de la página entera */
        }
        
        /* Contenedor principal de la página */
        .page-wrapper {
            display: flex;
            height: 100vh;
            /* (MODIFICADO) Fondo cambiado a un gris oscuro */
            background-color: #82868bff; /* Un tono de gris oscuro */
        }

        /* --- (NUEVO) Barra Lateral --- */
        .admin-sidebar {
            width: 260px;
            flex-shrink: 0;
            height: 100vh;
            background-color: var(--color-principal-transparente);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-sizing: border-box;
        }
        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 5px;
            margin-bottom: 30px;
            color: var(--color-texto-claro);
        }
        .sidebar-header i { font-size: 2.2rem; }
        .sidebar-header span { font-size: 1.5rem; font-weight: 700; }

        .admin-sidebar nav {
            flex-grow: 1;
        }
        .admin-sidebar nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--color-texto-sidebar);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }
        .admin-sidebar nav a i {
            font-size: 1.4rem;
        }
        .admin-sidebar nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--color-texto-claro);
        }
        /* Estilo del enlace activo (basado en $vista) */
        .admin-sidebar nav a.active {
            background-color: var(--color-principal-light);
            color: var(--color-texto-claro);
            font-weight: 600;
        }

        .sidebar-footer {
            margin-top: auto;
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: #ffaaaa; /* Rojo claro */
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .sidebar-footer a:hover {
            background-color: rgba(255, 100, 100, 0.1);
            color: #ffdddd;
        }
        .sidebar-footer a i {
            font-size: 1.4rem;
        }

        /* --- (NUEVO) Contenedor de Contenido Principal --- */
        .content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden; /* Evita que el wrapper tenga scroll */
        }
        
        /* --- (NUEVO) Header del Contenido --- */
        .content-header {
            padding: 0 32px;
            height: 80px;
            flex-shrink: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--color-principal-transparente-header);
            backdrop-filter: blur(10px);
            color: var(--color-texto-claro);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .content-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }
        .admin-user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 500;
            background-color: rgba(0,0,0,0.15);
            padding: 8px 14px;
            border-radius: 8px;
        }
        .admin-user-menu i {
            font-size: 1.4rem;
        }

        /* --- (NUEVO) Área de Contenido Desplazable --- */
        .admin-content {
            flex-grow: 1;
            overflow-y: auto; /* Esta es la única área con scroll */
            padding: 2.5rem;
        }

        /* --- Estilos del Contenido (Tarjetas, Tablas, etc.) --- */
        /* Estos estilos son de tu código original, se usan para el contenido */
        /* Tailwind se encargará de esto en el HTML (bg-white, rounded-2xl, etc.) */
        
        /* (NUEVO) Estilos para los modales */
        /* (El CSS de tu modal anterior se ha copiado aquí sin cambios) */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            padding: 10px;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
        }
        .modal-overlay.visible {
            display: flex;
            opacity: 1;
        }
        .modal-content {
            background-color: var(--color-texto-claro);
            margin: auto;
            padding: 24px;
            border-radius: 8px;
            width: 90%;
            max-width: 550px;
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.25);
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.2s ease-in-out;
        }
        .modal-overlay.visible .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .modal-close {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close:hover,
        .modal-close:focus { color: #333; }

        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            font-size: 0.9em; 
            font-weight: 600; 
            margin-bottom: 6px; 
            color: var(--color-texto-oscuro); 
        }
        .form-group input[type="text"], 
        .form-group input[type="password"],
        .form-group select { 
            width: 100%; 
            padding: 12px; 
            font-size: 1em; 
            border: 1px solid var(--color-borde-input); 
            border-radius: 5px; 
            box-sizing: border-box; 
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus { 
            outline: none; 
            border-color: var(--color-principal);
            box-shadow: 0 0 5px rgba(88, 44, 131, 0.5);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            color: var(--color-texto-claro);
        }
        .btn-primary { background-color: var(--color-principal); }
        .btn-primary:hover { background-color: var(--color-principal-hover); }
        .btn-danger { background-color: var(--color-rojo); }
        .btn-danger:hover { background-color: var(--color-rojo-hover); }
        .btn-success { background-color: var(--color-verde); }
        .btn-success:hover { background-color: var(--color-verde-hover); }
        .btn-warning { background-color: var(--color-amarillo); color: var(--color-texto-claro); }
        .btn-secondary { background-color: #6c757d; color: var(--color-texto-claro); }
        .btn-secondary:hover { background-color: #5a6268; }

    </style>
</head>

<!-- (NUEVO) Estructura del Body -->
<body>

    <div class="page-wrapper">
        
        <!-- (NUEVO) Barra Lateral -->
        <aside class="admin-sidebar">
            
            <div class="sidebar-header">
                <i class="ph-fill ph-bank"></i>
                <span>Smarque Bank</span>
            </div>
            
            <nav>
                <a href="?vista=dashboard" class="<?php echo ($vista == 'dashboard') ? 'active' : ''; ?>">
                    <i class="ph-fill ph-house"></i>
                    <span>Inicio</span>
                </a>
                <a href="?vista=turnos" class="<?php echo ($vista == 'turnos') ? 'active' : ''; ?>">
                    <i class="ph-fill ph-list-checks"></i>
                    <span>Gestión de Turnos</span>
                </a>
                <a href="?vista=usuarios" class="<?php echo ($vista == 'usuarios') ? 'active' : ''; ?>">
                    <i class="ph-fill ph-users"></i>
                    <span>Usuarios</span>
                </a>
                <a href="?vista=auditoria" class="<?php echo ($vista == 'auditoria') ? 'active' : ''; ?>">
                    <i class="ph-fill ph-list-dashes"></i>
                    <span>Auditoría</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="admin.php?logout=1">
                    <i class="ph-fill ph-sign-out"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- (NUEVO) Contenedor Principal (Header + Contenido) -->
        <div class="content-wrapper">

            <!-- (NUEVO) Header del Contenido -->
            <header class="content-header">
                <h1><?php echo htmlspecialchars($titulo_vista); ?></h1>
                <div class="admin-user-menu">
                    <i class="ph-fill ph-user-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['user_usuario']); ?> (Admin)</span>
                    <!-- (Opcional) Se podría añadir un dropdown aquí -->
                </div>
            </header>

            <!-- (NUEVO) Contenido con Scroll -->
            <main class="admin-content">
                
                <!--
                * =================================
                * (EXISTENTE) LÓGICA DE VISTAS DE PHP
                * =================================
                * El código de Tailwind (bg-white, rounded-2xl, etc.) 
                * se mantiene para que las tarjetas floten sobre el fondo oscuro.
                -->
            
                <?php if ($vista == 'dashboard'): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white p-6 rounded-2xl shadow-xl flex items-center space-x-4">
                            <i class="ph-fill ph-ticket text-4xl text-[var(--color-azul)]"></i>
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium uppercase">Total Turnos Hoy</h3>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $turnos_hoy; ?></p>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-xl flex items-center space-x-4">
                            <i class="ph-fill ph-clock text-4xl text-[var(--color-amarillo)]"></i>
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium uppercase">Turnos en Espera</h3>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $turnos_espera; ?></p>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-xl flex items-center space-x-4">
                            <i class="ph-fill ph-check-circle text-4xl text-[var(--color-verde)]"></i>
                            <div>
                                <h3 class="text-gray-500 text-sm font-medium uppercase">Turnos Atendidos</h3>
                                <p class="text-3xl font-bold text-gray-800"><?php echo $turnos_atendidos; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-xl p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Rendimiento de Cajeros (Hoy)</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cajero</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Atendidos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_cajeros as $cajero): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($cajero['usuario']); ?></td>
                                        <td class="py-4 px-6 text-gray-700 font-bold"><?php echo $cajero['total_atendidos']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($top_cajeros)): ?>
                                    <tr><td colspan="2" class="py-4 px-6 text-gray-500 text-center">Aún no hay turnos atendidos hoy.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($vista == 'usuarios'): ?>
                    <div class="flex justify-end mb-4">
                        <button onclick="openUserModal()" class="btn btn-primary">
                            <i class="ph-fill ph-plus-circle"></i>
                            <span>Crear Nuevo Usuario</span>
                        </button>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre Completo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Caja Asignada</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado Sesión</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_usuarios as $user): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                                        <td class="py-4 px-6 text-gray-700 font-medium"><?php echo htmlspecialchars($user['usuario']); ?></td>
                                        <td class="py-4 px-6">
                                            <?php if ($user['rol'] == 'admin'): ?>
                                                <span class="text-red-600 font-bold">Admin</span>
                                            <?php else: ?>
                                                <span class="text-blue-600">Cajero</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($user['nombre_ubicacion'] ?? 'N/A'); ?></td>
                                        
                                        <td class="py-4 px-6">
                                            <?php echo empty($user['session_id']) ? '<span class="text-red-600 font-medium">Inactiva</span>' : '<span class="text-green-600 font-bold">Activa</span>'; ?>
                                        </td>
                                        
                                        <td class="py-4 px-6 flex items-center space-x-2">
                                            <button onclick="openUserModal(<?php echo $user['id']; ?>)" class="py-1 px-3 rounded-full text-blue-700 bg-blue-100 hover:bg-blue-200 font-medium transition-colors text-sm">Editar</button>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): // No puedes borrarte a ti mismo ?>
                                            <button onclick="confirmarEliminar(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['usuario']); ?>')" class="py-1 px-3 rounded-full text-red-700 bg-red-100 hover:bg-red-200 font-medium transition-colors text-sm">Eliminar</button>
                                            <?php endif; ?>

                                            <?php if (!empty($user['session_id']) && $user['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="confirmarLimpiar(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['usuario']); ?>')" class="py-1 px-3 rounded-full text-gray-700 bg-gray-100 hover:bg-gray-200 font-medium transition-colors text-sm">Limpiar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($vista == 'turnos'): ?>
                    <div class="flex justify-end mb-4">
                        <button onclick="openTurnModal()" class="btn btn-primary">
                            <i class="ph-fill ph-plus-circle"></i>
                            <span>Crear Turno Manual</span>
                        </button>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                         <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turno</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cédula</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cajero</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ubicación</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Creación</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_turnos as $turno): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-4 px-6 text-gray-700 font-bold text-[var(--color-principal)]"><?php echo htmlspecialchars($turno['codigo_turno']); ?></td>
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($turno['cedula']); ?></td>
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($turno['tipo_atencion_nombre']); ?></td>
                                        <td class="py-4 px-6 font-medium">
                                            <?php 
                                            $estado = $turno['estado'];
                                            if ($estado == 'espera') echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Espera</span>';
                                            elseif ($estado == 'atendiendo') echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Atendiendo</span>';
                                            elseif ($estado == 'atendido') echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Atendido</span>';
                                            elseif ($estado == 'saltado') echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Saltado</span>';
                                            ?>
                                        </td>
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($turno['nombre_cajero'] ?? 'N/A'); ?></td>
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($turno['nombre_ubicacion'] ?? 'N/A'); ?></td>
                                        <td class="py-4 px-6 text-gray-700 text-sm"><?php echo $turno['fecha_creacion']; ?></td>
                                        <td class="py-4 px-6 flex items-center space-x-2">
                                            <?php if ($estado != 'espera'): ?>
                                                <button onclick="confirmRequeue(<?php echo $turno['id']; ?>, '<?php echo htmlspecialchars($turno['codigo_turno']); ?>')" class="py-1 px-3 rounded-full text-yellow-800 bg-yellow-100 hover:bg-yellow-200 font-medium transition-colors text-sm">Re-encolar</button>
                                            <?php endif; ?>
                                            <button onclick="confirmDeleteTurn(<?php echo $turno['id']; ?>, '<?php echo htmlspecialchars($turno['codigo_turno']); ?>')" class="py-1 px-3 rounded-full text-red-700 bg-red-100 hover:bg-red-200 font-medium transition-colors text-sm">Eliminar</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($lista_turnos)): ?>
                                    <tr><td colspan="8" class="py-4 px-6 text-gray-500 text-center">No se han generado turnos.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                         </div>
                    </div>
                <?php endif; ?>

                <?php if ($vista == 'auditoria'): ?>
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha y Hora</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_logs as $log): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-4 px-6 text-gray-700 text-sm"><?php echo $log['fecha']; ?></td>
                                        <td class="py-4 px-6 text-gray-700 font-medium"><?php echo htmlspecialchars($log['usuario_accion'] ?? 'N/A'); ?></td>
                                        <td class="py-4 px-6 text-gray-700"><?php echo htmlspecialchars($log['accion']); ?></td>
                                        <td class="py-4 px-6 text-gray-700 text-sm"><?php echo htmlspecialchars($log['detalles']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <!-- (NUEVO) Modal Unificado para Formularios y Confirmaciones -->
    <!-- Este HTML se mantiene, el CSS de arriba ya lo estiliza -->
    <div id="modal-backdrop" class="modal-overlay">
        <div id="modal-content-area" class="modal-content">
            <!-- El contenido del modal (formulario o confirmación) se inyectará aquí -->
        </div>
    </div>


    <script>
        // --- (NUEVO) Datos de PHP para JS ---
        const LISTA_CAJAS = <?php echo $json_lista_cajas; ?>;
        const LISTA_TIPOS_ATENCION = <?php echo $json_lista_tipos_atencion; ?>;

        // --- (NUEVO) Sistema de Modales ---
        const modalBackdrop = document.getElementById('modal-backdrop');
        const modalContent = document.getElementById('modal-content-area');

        function openModal() {
            modalBackdrop.classList.add('visible');
        }
        function closeModal() {
            modalBackdrop.classList.remove('visible');
            setTimeout(() => {
                modalContent.innerHTML = ''; 
            }, 200);
        }
        // Cerrar modal al hacer clic en el fondo
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) {
                closeModal();
            }
        });

        // --- (NUEVO) Lógica para CRUD de Usuarios ---
        function openUserModal(userId = null) {
            let title = userId ? 'Editar Usuario' : 'Crear Nuevo Usuario';
            let buttonText = userId ? 'Guardar Cambios' : 'Crear Usuario';
            
            let formHtml = `
                <span class="modal-close" onclick="closeModal()">&times;</span>
                <h3 class="text-2xl font-bold text-center text-[var(--color-principal)] mb-6">${title}</h3>
                
                <form id="form-usuario" onsubmit="saveUser(event, ${userId || 'null'})">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="nombre_completo">Nombre Completo</label>
                            <input type="text" id="nombre_completo" required>
                        </div>
                        <div class="form-group">
                            <label for="usuario">Usuario (login)</label>
                            <input type="text" id="usuario" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" ${userId ? 'placeholder="Dejar en blanco para no cambiar"' : 'required'}>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="rol">Rol</label>
                            <select id="rol" required>
                                <option value="cajero">Cajero</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="id_caja_asignada">Caja Asignada</label>
                            <select id="id_caja_asignada">
                                <option value="">N/A (Admin o Sin Asignar)</option>
                                ${LISTA_CAJAS.map(caja => `<option value="${caja.id}">${caja.nombre_ubicacion}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">${buttonText}</button>
                    </div>
                </form>
            `;
            modalContent.innerHTML = formHtml;

            if (userId) {
                const formData = new FormData();
                formData.append('accion', 'get_user_details');
                formData.append('user_id', userId);
                fetch('ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('nombre_completo').value = data.user.nombre_completo;
                        document.getElementById('usuario').value = data.user.usuario;
                        document.getElementById('rol').value = data.user.rol;
                        document.getElementById('id_caja_asignada').value = data.user.id_caja_asignada || '';
                    } else {
                        showAlert(data.message, true);
                    }
                });
            }
            openModal();
        }

        function saveUser(event, userId = null) {
            event.preventDefault();
            const formData = new FormData();
            formData.append('accion', 'save_user');
            if (userId) {
                formData.append('user_id', userId);
            }
            formData.append('nombre_completo', document.getElementById('nombre_completo').value);
            formData.append('usuario', document.getElementById('usuario').value);
            formData.append('password', document.getElementById('password').value);
            formData.append('rol', document.getElementById('rol').value);
            formData.append('id_caja_asignada', document.getElementById('id_caja_asignada').value);

            fetch('ajax.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    showAlert(data.message, false);
                    setTimeout(() => window.location.href = 'admin.php?vista=usuarios', 1500);
                } else {
                    showAlert(data.message, true);
                }
            })
            .catch(error => showAlert('Error de conexión con el servidor.', true));
        }

        // --- (NUEVO) Lógica para CRUD de Turnos ---
        function openTurnModal() {
            let formHtml = `
                <span class="modal-close" onclick="closeModal()">&times;</span>
                <h3 class="text-2xl font-bold text-center text-[var(--color-principal)] mb-6">Crear Turno Manual</h3>
                <form id="form-turno" onsubmit="createTurn(event)">
                    <div class="form-group">
                        <label for="cedula">Cédula Cliente</label>
                        <input type="text" id="cedula" required>
                    </div>
                    <div class="form-group">
                        <label for="id_tipo_atencion">Tipo de Atención</label>
                        <select id="id_tipo_atencion" required>
                            <option value="" disabled selected>Seleccione...</option>
                            ${LISTA_TIPOS_ATENCION.map(tipo => `<option value="${tipo.id}">${tipo.nombre}</option>`).join('')}
                        </select>
                    </div>
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Generar Turno</button>
                    </div>
                </form>
            `;
            modalContent.innerHTML = formHtml;
            openModal();
        }

        function createTurn(event) {
            event.preventDefault();
            const formData = new FormData();
            formData.append('accion', 'generar_turno');
            formData.append('cedula', document.getElementById('cedula').value);
            formData.append('id_tipo_atencion', document.getElementById('id_tipo_atencion').value);

            fetch('ajax.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    showAlert(`Turno ${data.turno.codigo_turno} creado con éxito.`, false);
                    setTimeout(() => window.location.href = 'admin.php?vista=turnos', 1500);
                } else {
                    showAlert(data.message, true);
                }
            })
            .catch(error => showAlert('Error de conexión con el servidor.', true));
        }

        // --- Lógica de Confirmaciones (Adaptada al nuevo modal) ---
        function showAlert(message, isError = false) {
            let title = isError ? 'Error' : 'Éxito';
            let icon = isError ? 'ph-x-circle text-[var(--color-rojo)]' : 'ph-check-circle text-[var(--color-verde)]';
            let html = `
                <div class="flex flex-col items-center">
                    <i class="ph-fill ${icon} text-6xl"></i>
                    <h3 class="text-2xl font-bold text-center text-gray-800 my-4">${title}</h3>
                    <p class="text-gray-600 text-center mb-6">${message}</p>
                    <button class="btn btn-primary" onclick="closeModal()">Entendido</button>
                </div>
            `;
            modalContent.innerHTML = html;
            openModal();
        }

        function showConfirm({ title, message, icon, confirmText, btnClass, onConfirm }) {
            let iconColor = btnClass === 'btn-danger' ? 'text-[var(--color-rojo)]' : 'text-[var(--color-amarillo)]';
            let html = `
                <div class="flex flex-col items-center">
                    <i class="ph-fill ${icon} ${iconColor} text-6xl"></i>
                    <h3 class="text-2xl font-bold text-center text-gray-800 my-4">${title}</h3>
                    <p class="text-gray-600 text-center mb-6">${message}</p>
                    <div class="flex justify-center space-x-4">
                        <button id="modal-btn-cancel" class="btn btn-secondary">Cancelar</button>
                        <button id="modal-btn-confirm" class="btn ${btnClass}">${confirmText}</button>
                    </div>
                </div>
            `;
            modalContent.innerHTML = html;
            document.getElementById('modal-btn-confirm').onclick = () => {
                onConfirm();
                closeModal();
            };
            document.getElementById('modal-btn-cancel').onclick = closeModal;
            openModal();
        }

        function confirmarLimpiar(idUsuario, nombreUsuario) {
            showConfirm({
                title: 'Confirmar Acción',
                message: `¿Está seguro que desea forzar el cierre de sesión del usuario '<strong>${nombreUsuario}</strong>'?`,
                icon: 'ph-warning-circle',
                confirmText: 'Limpiar Sesión',
                btnClass: 'btn-warning',
                onConfirm: () => limpiarSesion(idUsuario)
            });
        }
        
        function confirmarEliminar(idUsuario, nombreUsuario) {
            showConfirm({
                title: '¡ALERTA DE ELIMINACIÓN!',
                message: `¿Está seguro que desea ELIMINAR PERMANENTEMENTE al usuario '<strong>${nombreUsuario}</strong>'?<br><br>Esta acción no se puede deshacer.`,
                icon: 'ph-trash',
                confirmText: 'Eliminar',
                btnClass: 'btn-danger',
                onConfirm: () => eliminarUsuario(idUsuario)
            });
        }

        function confirmRequeue(idTurno, codigoTurno) {
            showConfirm({
                title: 'Re-encolar Turno',
                message: `¿Está seguro que desea mover el turno '<strong>${codigoTurno}</strong>' de nuevo al estado 'Espera'?`,
                icon: 'ph-arrow-clockwise',
                confirmText: 'Re-encolar',
                btnClass: 'btn-warning',
                onConfirm: () => requeueTurn(idTurno)
            });
        }

        function confirmDeleteTurn(idTurno, codigoTurno) {
            showConfirm({
                title: 'Eliminar Turno',
                message: `¿Está seguro que desea ELIMINAR PERMANENTEMENTE el turno '<strong>${codigoTurno}</strong>'?`,
                icon: 'ph-trash',
                confirmText: 'Eliminar',
                btnClass: 'btn-danger',
                onConfirm: () => deleteTurn(idTurno)
            });
        }

        // --- Lógica de Fetch (Sin cambios) ---
        function limpiarSesion(idUsuario) {
            const formData = new FormData();
            formData.append('accion', 'admin_limpiar_sesion');
            formData.append('id_usuario_limpiar', idUsuario);
            fetch('ajax.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, false);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message, true);
                }
            })
            .catch(error => showAlert('Error de conexión con el servidor.', true));
        }

        function eliminarUsuario(idUsuario) {
            const formData = new FormData();
            formData.append('accion', 'admin_eliminar_usuario');
            formData.append('id_usuario_eliminar', idUsuario);
            fetch('ajax.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, false);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message, true);
                }
            })
            .catch(error => showAlert('Error de conexión con el servidor.', true));
        }

        function requeueTurn(idTurno) {
            const formData = new FormData();
            formData.append('accion', 'requeue_turn');
            formData.append('id_turno', idTurno);
            fetch('ajax.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, false);
                    setTimeout(() => window.location.href = 'admin.php?vista=turnos', 1500);
                } else {
                    showAlert(data.message, true);
                }
            })
            .catch(error => showAlert('Error de conexión con el servidor.', true));
        }

        function deleteTurn(idTurno) {
            const formData = new FormData();
            formData.append('accion', 'delete_turn');
            formData.append('id_turno', idTurno);
            fetch('ajax.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, false);
                    setTimeout(() => window.location.href = 'admin.php?vista=turnos', 1500);
                } else {
                    showAlert(data.message, true);
                }
            })
            .catch(error => showAlert('Error de conexión con el servidor.', true));
        }

    </script>

</body>
</html>