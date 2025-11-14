<?php
require_once 'db_config.php';
$error = '';
$success = '';

// Seguridad: Solo Admins
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') {
    header("Location: login.php");
    exit;
}

// 1. Validar que tengamos un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID de usuario no válido.");
}
$id_usuario_editar = $_GET['id'];

// 2. Cargar datos de las cajas (para el dropdown)
try {
    $stmt_cajas = $pdo->query("SELECT * FROM pisos_cajas ORDER BY nombre_ubicacion");
    $cajas_disponibles = $stmt_cajas->fetchAll();
} catch (Exception $e) {
    $cajas_disponibles = [];
    $error = "Error al cargar las cajas.";
}

// 3. PROCESAR EL FORMULARIO (CUANDO SE ENVÍA)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_completo = $_POST['nombre_completo'];
    $usuario = $_POST['usuario'];
    $rol = $_POST['rol'];
    $id_caja = $_POST['id_caja'];
    
    // Validaciones
    if (empty($nombre_completo) || empty($usuario) || empty($rol) || empty($id_caja)) {
        $error = "Por favor, complete todos los campos requeridos.";
    } elseif (!in_array($rol, ['admin', 'cajero'])) {
        $error = "Rol no válido.";
    } else {
        try {
            // Verificar si el nuevo nombre de usuario ya está en uso por OTRO usuario
            $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $stmt_check->execute([$usuario, $id_usuario_editar]);
            if ($stmt_check->fetch()) {
                $error = "El nombre de usuario '{$usuario}' ya está en uso por otro usuario.";
            }

            if (empty($error)) { // Continuar solo si no hay error de usuario duplicado

            // Verificar si la contraseña se va a cambiar
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            $sql_parts = [];
            $params = [];
            
            // Campos base
            $sql_parts[] = "nombre_completo = ?"; $params[] = $nombre_completo;
            $sql_parts[] = "usuario = ?"; $params[] = $usuario;
            $sql_parts[] = "rol = ?"; $params[] = $rol;
            $sql_parts[] = "id_caja_asignada = ?"; $params[] = $id_caja;
            
            // Si se escribió una contraseña nueva
            if (!empty($password)) {
                if ($password != $password_confirm) {
                    $error = "Las contraseñas no coinciden.";
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $sql_parts[] = "password_hash = ?"; $params[] = $password_hash;
                }
            }
            
            // Si no hay errores, actualizar
            if (empty($error)) {
                $sql = "UPDATE usuarios SET " . implode(", ", $sql_parts) . " WHERE id = ?";
                $params[] = $id_usuario_editar;
                
                $stmt_update = $pdo->prepare($sql);
                $stmt_update->execute($params);
                
                $success = "¡Usuario actualizado con éxito!";
                
                // Registrar en auditoría
                registrarLog($pdo, 'Admin Edita Usuario', "ID Usuario: $id_usuario_editar, Cambios: " . implode(", ", $sql_parts));
            }
            } // Fin del if (empty($error))

        } catch (Exception $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

// 4. CARGAR DATOS DEL USUARIO (PARA MOSTRAR EN EL FORMULARIO)
try {
    $stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt_user->execute([$id_usuario_editar]);
    $user = $stmt_user->fetch();
    
    if (!$user) {
        die("Error: Usuario no encontrado.");
    }
} catch (Exception $e) {
    die("Error al cargar usuario: " . $e->getMessage());
}

// Función de log (la necesitamos aquí también)
function registrarLog($pdo, $accion, $detalles = '') {
    try {
        $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $usuario = isset($_SESSION['user_usuario']) ? $_SESSION['user_usuario'] : 'Sistema';
        $stmt_log = $pdo->prepare("INSERT INTO logs_auditoria (id_usuario_accion, usuario_accion, accion, detalles) VALUES (?, ?, ?, ?)");
        $stmt_log->execute([$id_usuario, $usuario, $accion, $detalles]);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - SmarQuee</title>
    <!-- Carga de Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body, html { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #F0F4F8; height: 100%; display: flex; flex-direction: column; }
        .main-header { width: 100%; background-color: #1E3A5F; color: white; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .logo { display: flex; align-items: center; gap: 8px; }
        .logo-icon { font-size: 2rem; }
        .logo-text { font-size: 1.5rem; font-weight: bold; }
        .main-content { flex-grow: 1; display: flex; align-items: center; justify-content: center; width: 100%; padding: 20px 0; }
        .container { width: 90%; max-width: 450px; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 40px; box-sizing: border-box; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-size: 1.1em; font-weight: 600; margin-bottom: 10px; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 15px; font-size: 1.2em; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #005A9C; box-shadow: 0 0 5px rgba(0,90,156,0.3); }
        .btn { display: inline-block; width: 100%; padding: 18px; font-size: 1.2em; font-weight: 700; color: white; background-color: #005A9C; border: none; border-radius: 5px; cursor: pointer; text-align: center; }
        .btn:hover { background-color: #004a80; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-size: 1.1em; text-align: center; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">
            <i class="ph-fill ph-shield logo-icon"></i>
            <span class="logo-text">SmartQuee</span>
        </div>
        <h1 style="font-size: 1.25rem; font-weight: 600; margin-right: 4rem;">Panel de Administración</h1>
    </header>
    <div class="main-content">
        <div class="container">
            <form method="POST" action="editar_usuario.php?id=<?php echo $id_usuario_editar; ?>">
                <h2 style="text-align: center; color: #333; margin-top: 0;">Editar Usuario</h2>
                
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($user['nombre_completo']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="usuario">Nombre de Usuario (para login)</label>
                    <input type="text" id="usuario" name="usuario" value="<?php echo htmlspecialchars($user['usuario']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="id_caja">Asignar a Caja / Piso</label>
                    <select id="id_caja" name="id_caja" required>
                        <option value="" disabled>Seleccione...</option>
                        <?php
                        foreach ($cajas_disponibles as $caja) {
                            $selected = ($user['id_caja_asignada'] == $caja['id']) ? 'selected' : '';
                            echo "<option value='{$caja['id']}' $selected>" . htmlspecialchars($caja['nombre_ubicacion']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="rol">Rol del Usuario</label>
                    <select id="rol" name="rol" required>
                        <option value="cajero" <?php echo ($user['rol'] == 'cajero') ? 'selected' : ''; ?>>Cajero / Asesor</option>
                        <option value="admin" <?php echo ($user['rol'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>

                <hr style="border: 1px solid #eee; margin: 25px 0;">
                <p style="text-align: center; color: #555;">Deje los campos de contraseña vacíos si no desea cambiarla.</p>

                <div class="form-group">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Solo si desea cambiarla">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmar Nueva Contraseña</label>
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Solo si desea cambiarla">
                </div>

                <button type="submit" class="btn">Actualizar Usuario</button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                <a href="admin.php?vista=usuarios">Volver a la lista de usuarios</a>
            </p>
        </div>
    </div>
</body>
</html>