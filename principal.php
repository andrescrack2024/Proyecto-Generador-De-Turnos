<?php require_once 'db_config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smarque Bank - Gestor de Turnos en Línea</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        /*
        * =================================
        * DISEÑO LANDING PAGE (SMARQUE BANK)
        * =================================
        */

        :root {
            --color-principal: #582C83;
            --color-principal-hover: #4a236d;
            --color-acento: #FBC90E;
            --color-fondo: #F8F9FA;
            --color-texto-oscuro: #333;
            --color-texto-claro: #FFFFFF;
            --color-borde-input: #ddd;
        }

        /* --- Estilos Globales --- */
        body, html {
            margin: 0; padding: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background-color: var(--color-fondo); 
            height: 100vh; /* Alto total de la pantalla */
            display: flex; 
            flex-direction: column; 
            overflow: hidden; /* Evitar scroll en la página principal */
        }
        
        /* --- Header --- */
        .main-header { 
            width: 100%; 
            background-color: var(--color-principal); 
            color: var(--color-texto-claro); 
            padding: 12px 24px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            box-sizing: border-box;
            flex-shrink: 0;
        }
        .logo { display: flex; align-items: center; gap: 8px; }
        .logo-icon { font-size: 2rem; }
        .logo-text { font-size: 1.5rem; font-weight: bold; }
        .turno-facil-logo {
            display: flex; align-items: center; gap: 8px;
            background-color: var(--color-texto-claro);
            color: var(--color-principal);
            border-radius: 9999px;
            padding: 6px 14px;
            font-weight: 600;
            font-size: 0.9em;
        }
        .turno-facil-logo i { font-size: 1.2rem; font-weight: bold; }

        /* --- Hero Section --- */
        .hero-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 5rem 2rem;
            color: var(--color-texto-claro);
            background-image: 
                linear-gradient(rgba(88, 44, 131, 0.9), rgba(88, 44, 131, 0.9)), 
                url('https://cdn.prod.website-files.com/63b042656c21e611f6f8be44/6720a70c50776f3eee5ae814_6395f90578c5b96b82e91b1b_group-diverse-people-having-business-meeting_1_40.webp');
            background-size: cover;
            background-position: center;
            text-align: center;
            flex-grow: 1; 
            min-height: 0; 
        }
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .hero-subtitle {
            font-size: 1.2rem;
            max-width: 500px;
            margin: 1rem 0 2rem 0;
            opacity: 0.9;
        }

        /* (NUEVO) Contenedor para los botones */
        .hero-button-group {
            display: flex;
            flex-wrap: wrap; /* Para que en móviles se pongan uno debajo del otro */
            justify-content: center;
            gap: 1rem;
        }

        .hero-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background-color: var(--color-texto-claro);
            color: var(--color-principal);
            font-size: 1rem;
            font-weight: 600;
            padding: 1rem 2rem;
            border-radius: 9999px;
            border: 2px solid var(--color-texto-claro); /* Borde añadido */
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
        }
        .hero-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .hero-button i {
            font-size: 1.3rem;
        }

        /* (NUEVO) Estilo para el botón secundario (Solicitar Turno) */
        .hero-button.secondary {
            background-color: transparent;
            color: var(--color-texto-claro);
        }
        .hero-button.secondary:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }


        /* --- Estilos de Modales --- */
        .modal-overlay {
            display: none; 
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            align-items: center; /* Se aplicará cuando display sea flex */
            justify-content: center;
            padding: 10px;
        }
        .modal-content {
            background-color: #fff;
            margin: auto;
            padding: 24px;
            border-radius: 8px;
            width: 90%;
            max-width: 550px;
            position: relative;
            box-shadow: 0 5px 20px rgba(0,0,0,0.25);
            animation: slide-down 0.3s ease-out;
        }
        @keyframes slide-down {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-close {
            color: #aaa;
            position: absolute;
            top: 15px; right: 20px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close:hover,
        .modal-close:focus { color: #333; text-decoration: none; }
        
        /* Estilos del formulario (para el modal) */
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-size: 1.1em; font-weight: 600; margin-bottom: 10px; color: var(--color-texto-oscuro); }
        .form-group input, .form-group select { 
            width: 100%; padding: 15px; font-size: 1.2em; 
            border: 1px solid var(--color-borde-input); border-radius: 5px; 
            box-sizing: border-box; transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus, .form-group select:focus { 
            outline: none; border-color: var(--color-principal);
            box-shadow: 0 0 5px rgba(88, 44, 131, 0.5);
        }

        /* Botón primario (para el modal) */
        .btn { 
            display: inline-block; width: 100%; padding: 18px; 
            font-size: 1.2em; font-weight: 700; color: var(--color-texto-claro); 
            background-color: var(--color-principal); border: none; 
            border-radius: 5px; cursor: pointer; text-align: center; 
            transition: background-color 0.3s; text-decoration: none;
            box-sizing: border-box;
        }
        .btn:hover { background-color: var(--color-principal-hover); }

        /* (RESTAURADO) Botón secundario */
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* (RESTAURADO) Estilos de Alerta y Ticket */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-size: 1.1em; text-align: center; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .ticket-info {
            text-align: center; background-color: var(--color-texto-claro);
            border: 1px solid #e5e7eb; border-left: 8px solid var(--color-acento); 
            padding: 30px; border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .ticket-info h2 { margin-top: 0; color: var(--color-principal); font-size: 1.8em; }
        .ticket-info .turno-numero {
            font-size: 6rem; font-weight: 800; color: var(--color-principal);
            margin: 10px 0; line-height: 1;
            animation: pulse-glow 2.5s ease-in-out infinite;
        }
        .ticket-info p { font-size: 1.3em; color: var(--color-texto-oscuro); margin: 5px 0; }
        
        /* Animación de brillo (de principal.php) */
        @keyframes pulse-glow {
            0%, 100% {
                color: var(--color-principal);
                text-shadow: 0 0 10px rgba(88, 44, 131, 0.3);
            }
            50% {
                color: #7E48B4; /* Un morado intermedio más brillante */
                text-shadow: 0 0 20px rgba(126, 72, 180, 0.7);
            }
        }

        /* Estilos de la tabla de consulta (para el modal) */
        #resultados-consulta table {
            width: 100%; border-collapse: collapse; margin-top: 20px;
            font-size: 0.9em; border: 1px solid #eee;
            border-radius: 8px; overflow: hidden;
        }
        #resultados-consulta th, #resultados-consulta td {
            padding: 12px 15px; text-align: left;
            border-bottom: 1px solid #eee;
        }
        #resultados-consulta th {
            background-color: #f8f6fa; color: var(--color-principal);
            font-size: 0.85em; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        #resultados-consulta tr:last-child td { border-bottom: none; }
        #resultados-consulta td strong { color: var(--color-principal); font-weight: 600; }
        #resultados-consulta .estado-espera { color: #D97706; font-weight: bold; }
        #resultados-consulta .estado-atendido { color: #059669; font-weight: bold; }
        #resultados-consulta .estado-saltado { color: #DC2626; font-weight: bold; }
        #resultados-consulta .estado-atendiendo { color: #2563EB; font-weight: bold; }
    </style>
</head>
<body>

    <!-- === HEADER === -->
    <header class="main-header">
        <div class="logo">
            <i class="ph-fill ph-bank logo-icon"></i> 
            <span class="logo-text">Smarque Bank</span> 
        </div>
        <h1 style="font-size: 1.25rem; font-weight: 500; margin: 0; position: absolute; left: 50%; transform: translateX(-50%);">
            Sistema de Gestión Turnus
        </h1>
        <div class="turno-facil-logo">
            <i class="ph-fill ph-ticket"></i>
            <span>TURNO FÁCIL</span>
        </div>
    </header>

    <!-- === HERO SECTION === -->
    <div class="hero-section">
        <h1 class="hero-title">GESTOR DE TURNOS EN LÍNEA</h1>
        <p class="hero-subtitle">Solicita y consulta tus turnos de forma rápida y segura desde cualquier lugar.</p>
        
        <!-- (MODIFICADO) Grupo de botones -->
        <div class="hero-button-group">
            <button id="btn-abrir-modal-consulta" class="hero-button">
                <i class="ph ph-magnifying-glass"></i>
                CONSULTAR MI TURNO O CÉDULA
            </button>
            <!-- (NUEVO) Botón para Generar Turno -->
            <button id="btn-abrir-modal-generar" class="hero-button secondary">
                <i class="ph ph-ticket"></i>
                SOLICITAR UN TURNO
            </button>
        </div>
    </div>


    <!-- === MODAL DE CONSULTA === -->
    <div id="modal-consulta" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close" id="btn-cerrar-modal-consulta">&times;</span>
            
            <h2 style="text-align: center; color: var(--color-principal); margin-top: 0;">Consulta tus Turnos</h2>
            <p style="text-align: center; margin-bottom: 25px; color: #555;">Ingresa tu cédula para ver tu historial de turnos.</p>

            <div class="form-group">
                <label for="cedula_consulta" style="font-weight: 600; font-size: 1em;">Número de Cédula</label>
                <input type="text" id="cedula_consulta" name="cedula_consulta" required>
            </div>
            
            <button id="btn-consultar-cedula" class="btn" style="width: 100%;">Buscar Turnos</button>

            <div id="resultados-consulta" style="margin-top: 20px;">
                <!-- Los resultados de la consulta se insertarán aquí -->
            </div>
        </div>
    </div>

    <!-- === (RESTAURADO) MODAL DE GENERACIÓN DE TURNO === -->
    <div id="modal-generar" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close" id="btn-cerrar-modal-generar">&times;</span>
            
            <!-- Contenedor de Formulario -->
            <div id="form-container">
                <div id="mensaje"></div>
                <form id="turno-form">
                    <h2 style="text-align: center; color: var(--color-texto-oscuro); margin-top: 0; margin-bottom: 30px;">Genere su Turno</h2>
                    <div class="form-group">
                        <label for="cedula">Ingrese su Cédula</label>
                        <input type="text" id="cedula" name="cedula" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_atencion">Seleccione Tipo de Atención</label>
                        <select id="tipo_atencion" name="tipo_atencion" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, nombre FROM tipos_atencion ORDER BY nombre");
                            while ($fila = $stmt->fetch()) {
                                echo "<option value='{$fila['id']}'>{$fila['nombre']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn" id="btn-generar">Generar Turno</button>
                </form>
            </div>

            <!-- Contenedor de Ticket -->
            <div id="ticket-container" style="display: none;">
                <div class="ticket-info">
                    <h2>¡Turno generado!</h2>
                    <p>Usted tiene el turno:</p>
                    <div id="turno-numero" class="turno-numero">---</div> 
                    <p id="turno-estado">Estado: En espera...</p>
                    <p>Será llamado en:</p>
                    <p id="turno-ubicacion" style="font-weight: 700; font-size: 1.5em;">(Pendiente de asignación)</p>
                    <br>
                    <button onclick="nuevoTurno()" class="btn btn-secondary">Generar otro turno</button>
                    <a href="public.php" target="_blank" class="btn" style="margin-top: 10px;">Ver Pantalla de Turnos</a>
                </div>
            </div>

        </div>
    </div>


    <script>
        // --- (RESTAURADA) LÓGICA DE FORMULARIO/TICKET ---
        
        const formContainer = document.getElementById('form-container');
        const ticketContainer = document.getElementById('ticket-container');
        const mensajeDiv = document.getElementById('mensaje');
        const turnoForm = document.getElementById('turno-form');

        turnoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const cedula = document.getElementById('cedula').value;
            const tipo_atencion = document.getElementById('tipo_atencion').value;

            if (!cedula || !tipo_atencion) {
                mostrarMensaje('Por favor complete todos los campos.', 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'generar_turno');
            formData.append('cedula', cedula);
            formData.append('id_tipo_atencion', tipo_atencion);

            fetch('ajax.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    formContainer.style.display = 'none'; 
                    document.getElementById('turno-numero').innerText = data.turno.codigo_turno;
                    ticketContainer.style.display = 'block'; 
                    mostrarMensaje('Turno generado con éxito', 'success', document.querySelector('#ticket-container .ticket-info')); 
                } else {
                    mostrarMensaje(data.message || 'Error al generar el turno.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('Error de conexión con el servidor.', 'danger');
            });
        });

        function nuevoTurno() {
            ticketContainer.style.display = 'none';
            formContainer.style.display = 'block';
            turnoForm.reset();
            mensajeDiv.innerHTML = '';
            const ticketAlert = document.querySelector('#ticket-container .alert');
            if(ticketAlert) ticketAlert.remove();
        }

        function mostrarMensaje(texto, tipo, contenedor = mensajeDiv) {
            const alertasViejas = contenedor.querySelectorAll('.alert');
            alertasViejas.forEach(alerta => alerta.remove());
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo}`;
            alertDiv.innerText = texto;
            if (contenedor.classList.contains('ticket-info')) {
                 contenedor.prepend(alertDiv);
            } else {
                 contenedor.innerHTML = ''; 
                 contenedor.appendChild(alertDiv);
            }
        }
        
        // --- LÓGICA DEL MODAL DE CONSULTA ---
        
        const modalConsulta = document.getElementById('modal-consulta');
        const btnAbrirConsulta = document.getElementById('btn-abrir-modal-consulta');
        const btnCerrarConsulta = document.getElementById('btn-cerrar-modal-consulta');
        const btnConsultarCedula = document.getElementById('btn-consultar-cedula');
        const inputCedulaConsulta = document.getElementById('cedula_consulta');
        const resultadosDiv = document.getElementById('resultados-consulta');

        btnAbrirConsulta.onclick = function() {
            modalConsulta.style.display = 'flex';
            inputCedulaConsulta.value = '';
            resultadosDiv.innerHTML = '';
        }
        btnCerrarConsulta.onclick = function() {
            modalConsulta.style.display = 'none';
        }
        
        btnConsultarCedula.onclick = function() { 
            consultarTurnos();
        };
        inputCedulaConsulta.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                consultarTurnos();
            }
        });

        function consultarTurnos() {
            const cedula = inputCedulaConsulta.value;
            if (cedula.trim() === '') {
                resultadosDiv.innerHTML = '<p style="color: red; text-align: center;">Por favor, ingrese una cédula.</p>';
                return;
            }
            resultadosDiv.innerHTML = '<p style="text-align: center;">Buscando...</p>';
            const formData = new FormData();
            formData.append('accion', 'consultar_turnos');
            formData.append('cedula', cedula);
            fetch('ajax.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                renderizarResultados(data);
            })
            .catch(error => {
                console.error('Error en consulta:', error);
                resultadosDiv.innerHTML = '<p style="color: red; text-align: center;">Error de conexión con el servidor.</p>';
            });
        }

        function renderizarResultados(data) {
            if (!data.success) {
                resultadosDiv.innerHTML = `<p style="color: red; text-align: center;">${data.message}</p>`;
                return;
            }
            if (data.turnos.length === 0) {
                resultadosDiv.innerHTML = '<p style="text-align: center;">No se encontraron turnos para esta cédula.</p>';
                return;
            }
            let html = '<table><thead><tr><th>Turno</th><th>Servicio</th><th>Estado</th><th>Lugar</th><th>Fecha Solicitud</th></tr></thead><tbody>';
            data.turnos.forEach(turno => {
                let claseEstado = `estado-${turno.estado.toLowerCase()}`;
                let fecha = new Date(turno.fecha_creacion).toLocaleString('es-CO', { 
                    year: 'numeric', month: 'numeric', day: 'numeric', 
                    hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: true 
                });
                let caja = turno.caja || 'N/A';
                html += `
                    <tr>
                        <td><strong>${turno.codigo_turno}</strong></td>
                        <td>${turno.tipo_atencion}</td>
                        <td class="${claseEstado}">${turno.estado.charAt(0).toUpperCase() + turno.estado.slice(1)}</td>
                        <td>${caja}</td>
                        <td>${fecha}</td>
                    </tr>
                `;
            });
            html += '</tbody></table>';
            resultadosDiv.innerHTML = html;
        }

        // --- (RESTAURADA) LÓGICA PARA EL MODAL DE GENERACIÓN ---
        const modalGenerar = document.getElementById('modal-generar');
        const btnAbrirGenerar = document.getElementById('btn-abrir-modal-generar');
        const btnCerrarGenerar = document.getElementById('btn-cerrar-modal-generar');

        btnAbrirGenerar.onclick = function() {
            // Reiniciar el modal al estado de "formulario" cada vez que se abre
            nuevoTurno(); // Esta función ya resetea el form y oculta el ticket
            modalGenerar.style.display = 'flex';
        }
        btnCerrarGenerar.onclick = function() {
            modalGenerar.style.display = 'none';
        }


        // --- Lógica de Cierre de Modales (clic afuera) ---
        window.onclick = function(event) {
            if (event.target == modalConsulta) {
                modalConsulta.style.display = 'none';
            }
            // (RESTAURADO)
            if (event.target == modalGenerar) {
                modalGenerar.style.display = 'none';
            }
        }
        
    </script>

</body>
</html>