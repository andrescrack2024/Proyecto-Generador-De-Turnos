<?php
// Este archivo maneja todas las peticiones AJAX/Fetch
require_once 'db_config.php';

// Asegurarnos de que la sesión esté iniciada si no lo está
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Acción no válida.'];

// Función de ayuda para registrar en Auditoría
function registrarLog($pdo, $accion, $detalles = '') {
    try {
        $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $usuario = isset($_SESSION['user_usuario']) ? $_SESSION['user_usuario'] : 'Cliente';
        
        $stmt_log = $pdo->prepare(
            "INSERT INTO logs_auditoria (id_usuario_accion, usuario_accion, accion, detalles) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt_log->execute([$id_usuario, $usuario, $accion, $detalles]);
    } catch (Exception $e) {
        // No fallar la operación principal si el log falla
    }
}


if (isset($_POST['accion'])) {
    
    // (Añadido) Variable $pdo para la función de log
    // $pdo se define en db_config.php
    
    switch ($_POST['accion']) {
        
        // --- Acción: Cliente genera un turno (RF-01, RF-02, RF-03) ---
        case 'generar_turno':
            if (empty($_POST['cedula']) || empty($_POST['id_tipo_atencion'])) {
                $response['message'] = 'Faltan datos (cédula o tipo de atención).';
                break;
            }
            try {
                $pdo->beginTransaction();
                $cedula = $_POST['cedula'];
                $id_tipo_atencion = $_POST['id_tipo_atencion'];
                $stmt_prefijo = $pdo->prepare("SELECT prefijo FROM tipos_atencion WHERE id = ?");
                $stmt_prefijo->execute([$id_tipo_atencion]);
                $prefijo = $stmt_prefijo->fetchColumn();
                if (!$prefijo) { throw new Exception("Tipo de atención no válido."); }
                $stmt_conteo = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE id_tipo_atencion = ? AND DATE(fecha_creacion) = CURDATE()");
                $stmt_conteo->execute([$id_tipo_atencion]);
                $numero_turno = $stmt_conteo->fetchColumn() + 1;
                $codigo_turno = $prefijo . '-' . str_pad($numero_turno, 3, '0', STR_PAD_LEFT);
                $stmt_insert = $pdo->prepare("INSERT INTO turnos (cedula, id_tipo_atencion, numero_turno, codigo_turno, estado) VALUES (?, ?, ?, ?, 'espera')");
                $stmt_insert->execute([$cedula, $id_tipo_atencion, $numero_turno, $codigo_turno]);
                $id_nuevo_turno = $pdo->lastInsertId();
                $pdo->commit();
                registrarLog($pdo, 'Turno Generado', "Cédula: $cedula, Turno: $codigo_turno");
                $response['success'] = true;
                $response['turno'] = ['id' => $id_nuevo_turno, 'codigo_turno' => $codigo_turno];
            } catch (Exception $e) { $pdo->rollBack(); $response['message'] = 'Error en la base de datos: ' . $e->getMessage(); }
            break;

        // --- (NUEVO) Acción: Cliente consulta sus turnos (de principal.php) ---
        case 'consultar_turnos':
            $cedula = $_POST['cedula'] ?? '';
            if (empty($cedula)) {
                $response['message'] = 'No se proporcionó cédula.';
                break;
            }
            try {
                $sql = "SELECT 
                            t.codigo_turno, 
                            t.estado, 
                            t.fecha_creacion, 
                            ta.nombre AS tipo_atencion, 
                            pc.nombre_ubicacion AS caja
                        FROM turnos AS t
                        JOIN tipos_atencion AS ta ON t.id_tipo_atencion = ta.id
                        LEFT JOIN pisos_cajas AS pc ON t.id_caja_atendio = pc.id
                        WHERE t.cedula = :cedula
                        ORDER BY t.fecha_creacion DESC
                        LIMIT 20";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['cedula' => $cedula]);
                $turnos = $stmt->fetchAll();
                
                registrarLog($pdo, 'Consulta Turnos', "Cédula consultada: $cedula");
                $response['success'] = true;
                $response['turnos'] = $turnos;
            } catch (Exception $e) { $response['message'] = 'Error en la base de datos: ' . $e->getMessage(); }
            break;

        // --- Acción: Pantalla pública pide datos (RF-06, RF-07, RF-08) ---
        case 'obtener_datos_publicos':
            try {
                $stmt_actual = $pdo->query("SELECT t.codigo_turno, p.nombre_ubicacion FROM turnos t JOIN pisos_cajas p ON t.id_caja_atendio = p.id WHERE t.estado = 'atendiendo' ORDER BY t.fecha_atencion DESC LIMIT 1");
                $turno_actual = $stmt_actual->fetch();
                $stmt_proximos = $pdo->query("SELECT codigo_turno FROM turnos WHERE estado = 'espera' AND DATE(fecha_creacion) = CURDATE() ORDER BY fecha_creacion ASC LIMIT 4");
                $proximos = $stmt_proximos->fetchAll();
                $response['success'] = true; $response['turno_actual'] = $turno_actual; $response['proximos'] = $proximos;
            } catch (Exception $e) { $response['message'] = 'Error al consultar datos públicos: ' . $e->getMessage(); }
            break;

        // --- Acción: Admin llama al siguiente turno ---
        case 'llamar_siguiente':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'cajero') { $response['message'] = 'Acceso denegado.'; break; }
            try {
                $pdo->beginTransaction();
                $id_usuario = $_SESSION['user_id']; $id_caja = $_SESSION['user_caja_id'];
                $stmt_check = $pdo->prepare("SELECT id FROM turnos WHERE id_usuario_atendio = ? AND estado = 'atendiendo'");
                $stmt_check->execute([$id_usuario]);
                if ($stmt_check->fetch()) {
                    $response['message'] = 'Ya tiene un turno activo. Finalícelo antes de llamar a otro.'; $response['code'] = 'ALREADY_ACTIVE'; $pdo->rollBack(); break;
                }
                $stmt_find = $pdo->prepare("SELECT * FROM turnos WHERE estado = 'espera' AND DATE(fecha_creacion) = CURDATE() ORDER BY fecha_creacion ASC LIMIT 1 FOR UPDATE"); // FOR UPDATE previene concurrencia
                $stmt_find->execute();
                $turno = $stmt_find->fetch();
                if (!$turno) {
                    $response['message'] = 'No hay turnos en espera.'; $response['code'] = 'NO_TURNS'; $pdo->rollBack(); break;
                }
                $stmt_update = $pdo->prepare("UPDATE turnos SET estado = 'atendiendo', id_usuario_atendio = ?, id_caja_atendio = ?, fecha_atencion = NOW() WHERE id = ?");
                $stmt_update->execute([$id_usuario, $id_caja, $turno['id']]);
                $pdo->commit();
                registrarLog($pdo, 'Llamar Turno', "Turno: " . $turno['codigo_turno']);
                $response['success'] = true; $response['turno'] = $turno;
            } catch (Exception $e) { $pdo->rollBack(); $response['message'] = 'Error al llamar turno: ' . $e->getMessage(); }
            break;

        // --- Acción: Cajero re-llama un turno activo ---
        case 'rellamar_turno':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'cajero') { $response['message'] = 'Acceso denegado.'; break; }
            if (empty($_POST['id_turno'])) { $response['message'] = 'Falta ID de turno.'; break; }
            try {
                $id_turno = $_POST['id_turno'];
                $stmt_update = $pdo->prepare("UPDATE turnos SET fecha_atencion = NOW() WHERE id = ? AND id_usuario_atendio = ? AND estado = 'atendiendo'");
                $stmt_update->execute([$id_turno, $_SESSION['user_id']]);
                $response['success'] = true;
            } catch (Exception $e) { $response['message'] = 'Error al re-llamar: ' . $e->getMessage(); }
            break;

        // --- Acción: Admin finaliza o salta un turno ---
        case 'actualizar_turno':
             if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'cajero') { $response['message'] = 'Acceso denegado.'; break; }
            if (empty($_POST['id_turno']) || !in_array($_POST['estado'], ['atendido', 'saltado'])) { $response['message'] = 'Datos incorrectos.'; break; }
            try {
                $id_turno = $_POST['id_turno']; $estado = $_POST['estado']; $id_usuario = $_SESSION['user_id'];
                $stmt_update = $pdo->prepare("UPDATE turnos SET estado = ?, fecha_fin_atencion = IF(? = 'atendido', NOW(), NULL) WHERE id = ? AND id_usuario_atendio = ?");
                $stmt_update->execute([$estado, $estado, $id_turno, $id_usuario]);
                if ($stmt_update->rowCount() > 0) {
                    registrarLog($pdo, "Actualizar Turno: $estado", "ID Turno: $id_turno"); $response['success'] = true;
                } else { $response['message'] = 'No se pudo actualizar el turno.'; }
            } catch (Exception $e) { $response['message'] = 'Error al actualizar: ' . $e->getMessage(); }
            break;

        // --- Acción: Cargar turno activo del cajero ---
        case 'cargar_turno_activo':
            if (!isset($_SESSION['user_id'])) { $response['message'] = 'Acceso denegado.'; break; }
            $stmt_find = $pdo->prepare("SELECT * FROM turnos WHERE id_usuario_atendio = ? AND estado = 'atendiendo' ORDER BY fecha_atencion DESC LIMIT 1");
            $stmt_find->execute([$_SESSION['user_id']]);
            $turno = $stmt_find->fetch();
            if ($turno) { $response['success'] = true; $response['turno'] = $turno; }
            break;

        // --- ACCIÓN: Admin limpia sesión de un usuario ---
        case 'admin_limpiar_sesion':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') { $response['message'] = 'Acceso denegado (Solo Admins).'; break; }
            if (empty($_POST['id_usuario_limpiar'])) { $response['message'] = 'Falta ID de usuario.'; break; }
            try {
                $id_usuario_limpiar = $_POST['id_usuario_limpiar'];
                $stmt_clear = $pdo->prepare("UPDATE usuarios SET session_id = NULL WHERE id = ?");
                $stmt_clear->execute([$id_usuario_limpiar]);
                registrarLog($pdo, 'Admin Limpia Sesión', "ID Usuario afectado: $id_usuario_limpiar");
                $response['success'] = true; $response['message'] = 'Sesión del usuario limpiada.';
            } catch (Exception $e) { $response['message'] = 'Error al limpiar sesión: ' . $e->getMessage(); }
            break;

        // --- ACCIÓN: Admin elimina un usuario ---
        case 'admin_eliminar_usuario':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') {
                $response['message'] = 'Acceso denegado (Solo Admins).';
                break;
            }
            if (empty($_POST['id_usuario_eliminar'])) {
                 $response['message'] = 'Falta ID de usuario.';
                 break;
            }
            // Seguridad: No permitir que un admin se borre a sí mismo
            if ($_POST['id_usuario_eliminar'] == $_SESSION['user_id']) {
                $response['message'] = 'No puede eliminarse a sí mismo.';
                break;
            }
            
            try {
                $id_usuario_eliminar = $_POST['id_usuario_eliminar'];
                
                // Antes de eliminar, reasignar logs de auditoría a NULL
                $stmt_logs = $pdo->prepare("UPDATE logs_auditoria SET id_usuario_accion = NULL WHERE id_usuario_accion = ?");
                $stmt_logs->execute([$id_usuario_eliminar]);
                
                // (Opcional) Reasignar turnos atendidos a NULL
                $stmt_turnos = $pdo->prepare("UPDATE turnos SET id_usuario_atendio = NULL WHERE id_usuario_atendio = ?");
                $stmt_turnos->execute([$id_usuario_eliminar]);

                // Eliminar usuario
                $stmt_delete = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt_delete->execute([$id_usuario_eliminar]);

                if ($stmt_delete->rowCount() > 0) {
                    registrarLog($pdo, 'Admin Elimina Usuario', "ID Usuario eliminado: $id_usuario_eliminar");
                    $response['success'] = true;
                    $response['message'] = 'Usuario eliminado permanentemente.';
                } else {
                    $response['message'] = 'No se encontró al usuario para eliminar.';
                }

            } catch (Exception $e) {
                 $response['message'] = 'Error al eliminar: ' . $e->getMessage();
            }
            break;
            
        // --- (NUEVO) Acción: Admin obtiene datos de 1 usuario para editar ---
        case 'get_user_details':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') { $response['message'] = 'Acceso denegado.'; break; }
            if (empty($_POST['user_id'])) { $response['message'] = 'Falta ID de usuario.'; break; }
            
            try {
                $stmt = $pdo->prepare("SELECT id, nombre_completo, usuario, rol, id_caja_asignada FROM usuarios WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    $response['success'] = true;
                    $response['user'] = $user;
                } else {
                    $response['message'] = 'Usuario no encontrado.';
                }
            } catch (Exception $e) { $response['message'] = 'Error de base de datos: ' . $e->getMessage(); }
            break;

        // --- (NUEVO) Acción: Admin guarda (Crea o Edita) un usuario ---
        case 'save_user':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') { $response['message'] = 'Acceso denegado.'; break; }
            
            // Recolección de datos
            $user_id = $_POST['user_id'] ?? null;
            $nombre_completo = $_POST['nombre_completo'];
            $usuario = $_POST['usuario'];
            $password = $_POST['password'];
            $rol = $_POST['rol'];
            $id_caja_asignada = $_POST['id_caja_asignada'] ?? null;
            if (empty($id_caja_asignada)) $id_caja_asignada = null; // Asegurar que sea NULL, no string vacío

            // Validaciones
            if (empty($nombre_completo) || empty($usuario) || empty($rol)) {
                $response['message'] = 'Nombre, Usuario y Rol son obligatorios.';
                break;
            }
            if (empty($user_id) && empty($password)) { // Password es obligatorio solo al crear
                $response['message'] = 'La contraseña es obligatoria al crear un nuevo usuario.';
                break;
            }

            try {
                if ($user_id) {
                    // --- Es un UPDATE (Editar) ---
                    if (empty($password)) {
                        // No actualizar contraseña
                        $sql = "UPDATE usuarios SET nombre_completo = ?, usuario = ?, rol = ?, id_caja_asignada = ? WHERE id = ?";
                        $params = [$nombre_completo, $usuario, $rol, $id_caja_asignada, $user_id];
                    } else {
                        // Actualizar contraseña
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE usuarios SET nombre_completo = ?, usuario = ?, password_hash = ?, rol = ?, id_caja_asignada = ? WHERE id = ?";
                        $params = [$nombre_completo, $usuario, $password_hash, $rol, $id_caja_asignada, $user_id];
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    registrarLog($pdo, 'Admin Edita Usuario', "ID Usuario: $user_id");
                    $response['message'] = 'Usuario actualizado con éxito.';

                } else {
                    // --- Es un INSERT (Crear) ---
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO usuarios (nombre_completo, usuario, password_hash, rol, id_caja_asignada) VALUES (?, ?, ?, ?, ?)";
                    $params = [$nombre_completo, $usuario, $password_hash, $rol, $id_caja_asignada];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    registrarLog($pdo, 'Admin Crea Usuario', "Nuevo usuario: $usuario, Rol: $rol");
                    $response['message'] = 'Usuario creado con éxito.';
                }
                $response['success'] = true;

            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $response['message'] = 'Error: El nombre de usuario ya existe.';
                } else {
                    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
                }
            }
            break;

        // --- (NUEVO) Acción: Admin re-encola un turno ---
        case 'requeue_turn':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') { $response['message'] = 'Acceso denegado.'; break; }
            if (empty($_POST['id_turno'])) { $response['message'] = 'Falta ID de turno.'; break; }

            try {
                $id_turno = $_POST['id_turno'];
                // Resetear el turno a 'espera' y limpiar datos de atención
                $sql = "UPDATE turnos SET 
                            estado = 'espera', 
                            id_usuario_atendio = NULL, 
                            id_caja_atendio = NULL, 
                            fecha_atencion = NULL, 
                            fecha_fin_atencion = NULL 
                        WHERE id = :id_turno";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id_turno' => $id_turno]);

                if ($stmt->rowCount() > 0) {
                    registrarLog($pdo, 'Admin Re-encola Turno', "ID Turno: $id_turno");
                    $response['success'] = true;
                    $response['message'] = 'Turno re-encolado con éxito.';
                } else {
                    $response['message'] = 'No se pudo re-encolar el turno (quizás ya estaba en espera).';
                }
            } catch (Exception $e) { $response['message'] = 'Error de base de datos: ' . $e->getMessage(); }
            break;

        // --- (NUEVO) Acción: Admin elimina un turno ---
        case 'delete_turn':
            if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] != 'admin') { $response['message'] = 'Acceso denegado.'; break; }
            if (empty($_POST['id_turno'])) { $response['message'] = 'Falta ID de turno.'; break; }

            try {
                $id_turno = $_POST['id_turno'];
                $sql = "DELETE FROM turnos WHERE id = :id_turno";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id_turno' => $id_turno]);

                if ($stmt->rowCount() > 0) {
                    registrarLog($pdo, 'Admin Elimina Turno', "ID Turno: $id_turno");
                    $response['success'] = true;
                    $response['message'] = 'Turno eliminado permanentemente.';
                } else {
                    $response['message'] = 'No se encontró el turno para eliminar.';
                }
            } catch (Exception $e) { $response['message'] = 'Error de base de datos: ' . $e->getMessage(); }
            break;

            
    } // Fin del switch
}

echo json_encode($response);
?>