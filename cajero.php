<?php
// AHORA ES cajero.php
require_once 'db_config.php';

// Seguridad: Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Seguridad: Verificar que sea un CAJERO (no un admin)
if ($_SESSION['user_rol'] != 'cajero') {
    header("Location: login.php?error=rol_invalido");
    exit;
}

// (El resto del código de seguridad de sesión sigue igual)
$stmt_check = $pdo->prepare("SELECT session_id FROM usuarios WHERE id = ?");
$stmt_check->execute([$_SESSION['user_id']]);
$db_session_id = $stmt_check->fetchColumn();
if ($db_session_id !== session_id()) {
    session_destroy();
    header("Location: login.php?error=duplicate_session");
    exit;
}

// Obtener info de la caja
$stmt = $pdo->prepare("SELECT p.nombre_ubicacion FROM usuarios u JOIN pisos_cajas p ON u.id_caja_asignada = p.id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$caja = $stmt->fetch();
$nombre_caja = $caja ? $caja['nombre_ubicacion'] : 'No asignada';
$_SESSION['user_caja_nombre'] = $nombre_caja;

// Lógica de cierre de sesión
if (isset($_GET['logout'])) {
    // Registrar en auditoría
    try {
        $stmt_log = $pdo->prepare("INSERT INTO logs_auditoria (id_usuario_accion, usuario_accion, accion, detalles) VALUES (?, ?, 'Logout', ?)");
        $stmt_log->execute([$_SESSION['user_id'], $_SESSION['user_usuario'], 'Cierre de sesión exitoso']);
    } catch (Exception $e) { /* Ignorar error de log */ }

    $stmt_clear = $pdo->prepare("UPDATE usuarios SET session_id = NULL WHERE id = ?");
    $stmt_clear->execute([$_SESSION['user_id']]);
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Cajero - SmarQuee</title>
    <style>
        body, html { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #F0F4F8; height: 100%; display: flex; flex-direction: column; }
        .main-header { width: 100%; background-color: #1E3A5F; color: white; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .logo { display: flex; align-items: center; gap: 8px; }
        .logo-icon { font-size: 2rem; }
        .logo-text { font-size: 1.5rem; font-weight: bold; }
        .main-content { flex-grow: 1; display: flex; align-items: center; justify-content: center; width: 100%; }
        .container { width: 90%; max-width: 450px; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 40px; box-sizing: border-box; }
        .btn { display: inline-block; width: 100%; padding: 18px; font-size: 1.2em; font-weight: 700; color: white; background-color: #005A9C; border: none; border-radius: 5px; cursor: pointer; text-align: center; transition: background-color 0.3s; box-sizing: border-box; }
        .btn:hover { background-color: #004a80; }
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #5a6268; }
        .btn-success { background-color: #28a745; }
        .btn-success:hover { background-color: #218838; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .btn:disabled { background-color: #cccccc; cursor: not-allowed; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-size: 1.1em; text-align: center; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .admin-header { width: 100%; text-align: center; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        .admin-header h2 { margin: 0; font-size: 2em; color: #333; }
        .admin-header .caja-info { font-size: 1.5em; font-weight: 700; color: #005A9C; margin: 5px 0; }
        .admin-nav-links { margin-top: 15px; display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
        .admin-nav-links a { text-decoration: none; font-weight: 600; font-size: 1.1em; color: #005A9C; padding: 5px; }
        .admin-nav-links a.logout-link { color: #dc3545; }
        .admin-controls { display: flex; flex-direction: column; gap: 15px; }
        .turno-en-atencion { text-align: center; padding: 25px; background: #f8faff; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px; }
        .turno-en-atencion h3 { margin: 0 0 10px 0; font-size: 1.4em; color: #555; }
        .turno-en-atencion #turno-atendiendo { font-size: 3.5em; font-weight: 700; color: #005A9C; }
    </style>
    <!-- Carga de Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>
    
    <header class="main-header">
        <div class="logo">
            <i class="ph-fill ph-shield logo-icon"></i>
            <span class="logo-text">SmartQuee</span>
        </div>
        <h1 class="text-xl font-semibold" style="margin-right: 4rem;">Panel de Cajero</h1>
    </header>

    <div class="main-content">
        <div class="container">
            <div class="admin-header">
                <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_usuario']); ?></h2>
                <div class="caja-info">Usted está en: <?php echo htmlspecialchars($nombre_caja); ?></div>
                <div class="admin-nav-links">
                    <a href="public.php" target="_blank">Ver Pantalla Pública</a>
                    <a href="cajero.php?logout=1" class="logout-link">Cerrar Sesión</a>
                </div>
            </div>

            <div class="turno-en-atencion">
                <h3>Atendiendo al turno:</h3>
                <div id="turno-atendiendo">---</div>
                <input type="hidden" id="id-turno-atendiendo" value="0">
            </div>
            
            <div id="admin-mensaje" style="margin-bottom: 20px;"></div>

            <div class="admin-controls">
                <button class="btn btn-success" id="btn-llamar" onclick="llamarSiguiente()">Llamar Siguiente</button>
                <button class="btn" id="btn-rellamar" onclick="reLlamar()">Re-Llamar Turno</button>
                <button class="btn btn-secondary" id="btn-saltar" onclick="actualizarTurno('saltado')">Saltar Turno</button>
                <button class="btn btn-danger" id="btn-finalizar" onclick="actualizarTurno('atendido')">Finalizar Atención</button>
            </div>
        </div>
    </div>

    <script>
        const displayTurno = document.getElementById('turno-atendiendo');
        const inputIdTurno = document.getElementById('id-turno-atendiendo');
        const mensajeDiv = document.getElementById('admin-mensaje');
        document.addEventListener('DOMContentLoaded', cargarTurnoActivo);
        function cargarTurnoActivo() {
            const formData = new FormData(); formData.append('accion', 'cargar_turno_activo');
            fetch('ajax.php', { method: 'POST', body: formData }).then(res => res.json())
                .then(data => {
                    if (data.success && data.turno) { actualizarDisplay(data.turno); } else { actualizarDisplay(null); }
                });
        }
        function llamarSiguiente() {
            const formData = new FormData(); formData.append('accion', 'llamar_siguiente');
            fetch('ajax.php', { method: 'POST', body: formData }).then(res => res.json())
                .then(data => {
                    if (data.success) {
                        actualizarDisplay(data.turno);
                        mostrarMensaje('Turno ' + data.turno.codigo_turno + ' llamado.', 'success');
                    } else {
                        mostrarMensaje(data.message, 'danger');
                        if (data.code == 'NO_TURNS' || data.code == 'ALREADY_ACTIVE') { cargarTurnoActivo(); }
                    }
                }).catch(err => mostrarMensaje('Error de red.', 'danger'));
        }
        function reLlamar() {
            const idTurno = inputIdTurno.value;
            if (idTurno == 0) { mostrarMensaje('No hay ningún turno activo para re-llamar.', 'danger'); return; }
            
            // Lógica de re-llamada real
            const formData = new FormData();
            formData.append('accion', 'rellamar_turno');
            formData.append('id_turno', idTurno);

            fetch('ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => { if (!data.success) mostrarMensaje(data.message, 'danger'); });

            mostrarMensaje(`Re-llamando al turno ${displayTurno.innerText}...`, 'success');
        }
        function actualizarTurno(nuevoEstado) {
            const idTurno = inputIdTurno.value;
            if (idTurno == 0) { mostrarMensaje('No hay ningún turno activo para ' + nuevoEstado, 'danger'); return; }
            const formData = new FormData();
            formData.append('accion', 'actualizar_turno');
            formData.append('id_turno', idTurno);
            formData.append('estado', nuevoEstado);
            fetch('ajax.php', { method: 'POST', body: formData }).then(res => res.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensaje(`Turno ${displayTurno.innerText} marcado como ${nuevoEstado}.`, 'success');
                        actualizarDisplay(null);
                    } else { mostrarMensaje(data.message, 'danger'); }
                }).catch(err => mostrarMensaje('Error de red.', 'danger'));
        }
        function actualizarDisplay(turno) {
            if (turno) {
                displayTurno.innerText = turno.codigo_turno; inputIdTurno.value = turno.id; deshabilitarBotones(true);
            } else {
                displayTurno.innerText = '---'; inputIdTurno.value = 0; deshabilitarBotones(false);
            }
        }
        function deshabilitarBotones(atendiendo) {
            document.getElementById('btn-llamar').disabled = atendiendo;
            document.getElementById('btn-rellamar').disabled = !atendiendo;
            document.getElementById('btn-saltar').disabled = !atendiendo;
            document.getElementById('btn-finalizar').disabled = !atendiendo;
        }
        function mostrarMensaje(texto, tipo) {
            mensajeDiv.innerHTML = `<div class="alert alert-${tipo}">${texto}</div>`;
            setTimeout(() => { mensajeDiv.innerHTML = ''; }, 3000);
        }
    </script>
</body>
</html>