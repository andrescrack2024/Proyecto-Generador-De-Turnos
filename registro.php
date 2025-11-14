<?php
require_once 'db_config.php'; // db_config.php debe tener session_start()
$error = '';
$success = '';

// Cargar las cajas disponibles
try {
    $stmt_cajas = $pdo->query("SELECT * FROM pisos_cajas ORDER BY nombre_ubicacion");
    $cajas_disponibles = $stmt_cajas->fetchAll();
} catch (Exception $e) {
    $cajas_disponibles = [];
    $error = "Error al cargar las cajas.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nombre_completo = $_POST['nombre_completo'];
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $id_caja = $_POST['id_caja'] ?? null; // Permitir que sea nulo si no se selecciona
    
    // --- MEJORA DE SEGURIDAD (ROL) ---
    // El rol ya no viene del POST. Se fuerza a 'cajero'.
    $rol = 'cajero'; 

    // Validaciones
    if (empty($nombre_completo) || empty($usuario) || empty($password) || empty($id_caja)) {
        $error = "Por favor, complete todos los campos.";
    } elseif ($password != $password_confirm) {
        $error = "Las contraseñas no coinciden.";
    } elseif (empty($_POST['g-recaptcha-response'])) {
        $error = "Por favor, complete el reCAPTCHA.";
    } else {
        // --- Verificación de reCAPTCHA ---
        $recaptcha_secret = '6LeEjQksAAAAAGhlfertmGHl30dzNIz--LU8JqV2'; // <-- ¡IMPORTANTE! Pega tu clave secreta aquí
        $recaptcha_response = $_POST['g-recaptcha-response'];
        
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $verify_data = http_build_query([
            'secret'   => $recaptcha_secret,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]);

        $options = ['http' => ['method' => 'POST', 'header' => 'Content-type: application/x-www-form-urlencoded', 'content' => $verify_data]];
        $context = stream_context_create($options);
        $verify_result = file_get_contents($verify_url, false, $context);
        $result_json = json_decode($verify_result);

        if ($result_json->success !== true) {
            $error = "La verificación reCAPTCHA ha fallado. Por favor, inténtelo de nuevo.";
        } else {
        try {
            // 1. Verificar si el nombre de usuario ya existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
            $stmt_check->execute([$usuario]);
            
            if ($stmt_check->fetchColumn() > 0) {
                $error = "El nombre de usuario '{$usuario}' ya está en uso.";
            } else {
                // 2. Si no existe, cifrar la contraseña
                $password_hash = password_hash($password, PASSWORD_BCRYPT);

                // 3. Insertar el nuevo usuario (ROL FORZADO A 'cajero')
                $stmt_insert = $pdo->prepare(
                    "INSERT INTO usuarios (usuario, password_hash, nombre_completo, rol, id_caja_asignada) 
                     VALUES (?, ?, ?, 'cajero', ?)" // El rol se inserta directamente
                );
                
                // Se quita $rol de los parámetros
                $stmt_insert->execute([$usuario, $password_hash, $nombre_completo, $id_caja]);

                // Registrar en auditoría
                try {
                    $actor = isset($_SESSION['user_usuario']) ? $_SESSION['user_usuario'] : 'Sistema';
                    $actor_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                    
                    $stmt_log = $pdo->prepare("INSERT INTO logs_auditoria (id_usuario_accion, usuario_accion, accion, detalles) VALUES (?, ?, 'Creación Usuario', ?)");
                    $stmt_log->execute([$actor_id, $actor, "Nuevo usuario: $usuario, Rol: cajero"]);
                } catch (Exception $e) { /* Ignorar error de log */ }

                header("Location: login.php?registro=exitoso");
                exit;
            }
        } catch (Exception $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- (NUEVO) Script de reCAPTCHA de Google -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <title>Smarque Bank - Registro de Usuario</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /*
        * =================================
        * CSS DE REGISTRO (TEMA SMARQUE BANK)
        * =================================
        */

        /* Paleta de colores importada de principal.php */
        :root {
            --color-principal: #582C83; 
            --color-principal-hover: #4a236d;
            --color-acento: #FBC90E;      
            --color-fondo: #F8F9FA;      
            --color-texto-oscuro: #333;
            --color-texto-claro: #FFFFFF;
            --color-borde-input: #ddd;
        }

        body, html { 
            margin: 0; 
            padding: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background-color: var(--color-fondo);
            /* Permitir que el contenido crezca si es necesario (para el scroll) */
            min-height: 100%; 
            display: flex; 
            flex-direction: column; 
        }
        
        /* Header con el color principal (morado) */
        .main-header { 
            width: 100%; 
            background-color: var(--color-principal); /* Color morado */
            color: var(--color-texto-claro); 
            padding: 12px 24px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            box-sizing: border-box;
            flex-shrink: 0; /* Evita que el header se encoja */
        }
        .logo { display: flex; align-items: center; gap: 8px; }
        .logo-icon { font-size: 2rem; }
        .logo-text { font-size: 1.5rem; font-weight: bold; }
        
        .main-content { 
            flex-grow: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            width: 100%; 
            padding: 40px 20px; /* Más padding vertical */
            box-sizing: border-box;
        }
        
        /* Contenedor estilo tarjeta (de principal.php) */
        .container { 
            width: 90%; 
            max-width: 550px; /* Ancho para el formulario de registro */
            background: var(--color-texto-claro); 
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 40px; 
            box-sizing: border-box; 
        }
        
        .form-group { margin-bottom: 20px; } /* Menos margen */
        .form-group label { display: block; font-size: 1em; font-weight: 600; margin-bottom: 8px; color: #333; }
        
        /* Estilo unificado para input Y select */
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 14px; 
            font-size: 1.1em; 
            border: 1px solid var(--color-borde-input); 
            border-radius: 5px; 
            box-sizing: border-box; 
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        /* Foco con acento morado */
        .form-group input:focus, .form-group select:focus { 
            outline: none; 
            border-color: var(--color-principal); /* Color morado */
            box-shadow: 0 0 5px rgba(88, 44, 131, 0.5); 
        }
        
        /* --- (NUEVO) Estilo para el Captcha --- */
        .captcha-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .captcha-group label {
            margin-bottom: 0;
            font-size: 1.1em;
            font-weight: 600;
            color: var(--color-texto-oscuro);
            white-space: nowrap; /* Evita que la pregunta se rompa */
        }
        .captcha-group input {
            font-size: 1.2em;
            font-weight: bold;
            text-align: center;
            width: 80px; /* Ancho fijo para la respuesta */
        }
        
        /* Botón con acento morado */
        .btn { 
            display: inline-block; 
            width: 100%; 
            padding: 18px; 
            font-size: 1.2em; 
            font-weight: 700; 
            color: var(--color-texto-claro); 
            background-color: var(--color-principal); /* Color morado */
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            text-align: center; 
            transition: background-color 0.3s;
            text-decoration: none;
            margin-top: 10px; /* Espacio antes del botón */
        }
        .btn:hover { 
            background-color: var(--color-principal-hover); /* Morado más oscuro */
        }
        
        .alert { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
            font-size: 1.1em; 
            text-align: center; 
        }
        .alert-danger { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        .alert-success { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }

        /* Enlace de formulario con acento morado */
        .form-link {
            color: var(--color-principal); /* Color morado */
            font-weight: 600;
            text-decoration: none;
        }
        .form-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">
            <!-- Icono de "añadir usuario", más apropiado para registro -->
            <i class="ph-fill ph-user-plus logo-icon"></i>
            <span class="logo-text">Smarque Bank</span>
        </div>
        <h1 style="font-size: 1.25rem; font-weight: 600; margin-right: 4rem;">Registro de Personal</h1>
    </header>
    
    <div class="main-content">
        <div class="container">
            <form method="POST" action="registro.php">
                <h2 style="text-align: center; color: #333; margin-top: 0;">Registrar Nuevo Cajero</h2>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" required value="<?php echo htmlspecialchars($_POST['nombre_completo'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="usuario">Nombre de Usuario (para login)</label>
                    <input type="text" id="usuario" name="usuario" required value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmar Contraseña</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                <div class="form-group">
                    <label for="id_caja">Asignar a Caja / Piso</label>
                    <select id="id_caja" name="id_caja" required>
                        <option value="" disabled selected>Seleccione una ubicación...</option>
                        <?php
                        foreach ($cajas_disponibles as $caja) {
                            echo "<option value='{$caja['id']}'>" . htmlspecialchars($caja['nombre_ubicacion']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- === (ELIMINADO) Campo de Rol ===
                Ya no se permite seleccionar el rol. Se fuerza a 'cajero'.
                -->

                <!-- === (NUEVO) Widget de reCAPTCHA === -->
                <div class="g-recaptcha" data-sitekey="6LeEjQksAAAAAFhlE3mt6CD779CQpOh7-1XpvUco" style="margin-bottom: 20px; transform:scale(1.05); transform-origin:0 0;"></div>

                <button type="submit" class="btn">Registrar Usuario</button>
            </form>
            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="form-link">¿Ya tienes cuenta? Volver a Iniciar Sesión</a>
            </p>
        </div>
    </div>
</body>
</html>