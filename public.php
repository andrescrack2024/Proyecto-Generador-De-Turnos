<?php require_once 'db_config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smarque Bank - Pantalla de Turnos</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        /*
        * =================================
        * DISEÑO PANTALLA PÚBLICA (SMARQUE BANK)
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

        /* Animación de brillo (de principal.php) */
        @keyframes pulse-glow {
            0%, 100% {
                color: var(--color-principal);
                text-shadow: 0 0 20px rgba(88, 44, 131, 0.4);
            }
            50% {
                color: #7E48B4; 
                text-shadow: 0 0 40px rgba(126, 72, 180, 0.8);
            }
        }
    
        /* Estilos Globales */
        body, html {
            margin: 0; padding: 0; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background-color: var(--color-fondo); 
            height: 100vh; /* Alto total de la pantalla */
            display: flex; 
            flex-direction: column; 
            overflow: hidden; /* Evitar scroll en la pantalla pública */
        }

        /* Header (IDÉNTICO a principal.php para consistencia) */
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
            flex-shrink: 0; /* Evita que el header se encoja */
            z-index: 10;
        }
        .logo { display: flex; align-items: center; gap: 8px; }
        .logo-icon { font-size: 2rem; }
        .logo-text { font-size: 1.5rem; font-weight: bold; }

        /* Reloj Digital (Reemplaza el H1 de principal.php) */
        #reloj-digital {
            font-size: 1.75rem;
            font-weight: 500;
            color: var(--color-texto-claro);
            position: absolute; 
            left: 50%; 
            transform: translateX(-50%);
        }

        .turno-facil-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: var(--color-texto-claro);
            color: var(--color-principal);
            border-radius: 9999px;
            padding: 6px 14px;
            font-weight: 600;
            font-size: 0.9em;
        }
        .turno-facil-logo i {
            font-size: 1.2rem;
            font-weight: bold;
        }

        /* --- Layout de Pantalla Pública --- */
        .public-wrapper {
            display: flex;
            flex-grow: 1; /* Ocupa todo el espacio restante (clave para el footer) */
            padding: 25px;
            gap: 25px;
            box-sizing: border-box;
            overflow-y: auto; /* Permite scroll interno si es necesario */
            min-height: 0; /* Soluciona problemas de flexbox en algunos navegadores */
        }

        /* --- Estilo de Tarjetas (General) --- */
        .card {
            background: var(--color-texto-claro);
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Para que el header redondeado funcione */
        }
        .card-header {
            background-color: var(--color-principal);
            color: var(--color-texto-claro);
            padding: 15px 25px;
            font-size: 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .card-body {
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
        }

        /* --- Columna Izquierda: Turno Actual --- */
        .turno-actual-card {
            flex-grow: 1; /* Ocupa la mayor parte del espacio */
        }

        #turno-actual-numero {
            font-size: 14rem; /* Tamaño gigante para verse de lejos */
            font-weight: 800;
            line-height: 1.1;
            color: var(--color-principal);
            animation: pulse-glow 2.5s ease-in-out infinite;
        }
        
        /* Contenedor para la caja (con acento dorado) */
        .caja-display {
            width: 100%;
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 6px solid var(--color-acento);
        }

        #turno-actual-caja {
            font-size: 4rem; /* Tamaño grande */
            font-weight: 600;
            color: var(--color-texto-oscuro);
        }

        /* --- Columna Derecha: Próximos Turnos --- */
        .proximos-card {
            flex-basis: 380px; /* Ancho fijo para la columna de próximos */
            flex-shrink: 0;
        }

        .proximos-card .card-body {
            padding: 0;
            justify-content: flex-start; /* Alinea la lista arriba */
        }

        #proximos-turnos-lista {
            list-style: none;
            padding: 0;
            margin: 0;
            width: 100%;
        }
        #proximos-turnos-lista li {
            font-size: 2.2rem;
            font-weight: 600;
            color: var(--color-texto-oscuro);
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
        }
        #proximos-turnos-lista li:last-child {
            border-bottom: none;
        }
        
        /* Estado de "sin turnos" (se usa en el JS) */
        .placeholder-turno {
            font-size: 5rem;
            font-weight: 700;
            color: #ccc;
        }
        .placeholder-caja {
            font-size: 2rem;
            font-weight: 500;
            color: #ccc;
        }

        /* === (NUEVO) ESTILOS DE PIE DE PÁGINA === */
        .main-footer {
            flex-shrink: 0; /* Evita que se encoja */
            background-color: var(--color-principal);
            color: var(--color-texto-claro);
            padding: 18px 25px;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 500;
            box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.1); /* Sombra superior */
            z-index: 10; /* Asegura que esté sobre el wrapper */
        }
        .main-footer p {
            margin: 0;
            padding: 0;
        }

    </style>
</head>
<body>

    <!-- === HEADER (IDÉNTICO A PRINCIPAL.PHP) === -->
    <header class="main-header">
        <div class="logo">
            <i class="ph-fill ph-bank logo-icon"></i> 
            <span class="logo-text">Smarque Bank</span> 
        </div>
        
        <!-- Reloj digital en lugar del título estático -->
        <div id="reloj-digital">00:00:00</div>

        <div class="turno-facil-logo">
            <i class="ph-fill ph-ticket"></i>
            <span>TURNO FÁCIL</span>
        </div>
    </header>

    <!-- === CONTENIDO DE LA PANTALLA PÚBLICA === -->
    <div class="public-wrapper">

        <!-- Columna Izquierda (TURNO ACTUAL) -->
        <div class="turno-actual-card card">
            <div class="card-header">
                Turno Actual
            </div>
            <div class="card-body">
                <!-- ID para tu JS -->
                <div id="turno-actual-numero" class="placeholder-turno">---</div>
                
                <div class="caja-display">
                    <!-- ID para tu JS -->
                    <div id="turno-actual-caja" class="placeholder-caja">Esperando...</div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha (PRÓXIMOS) -->
        <div class="proximos-card card">
            <div class="card-header">
                Próximos
            </div>
            <div class="card-body">
                <!-- ID para tu JS -->
                <ul id="proximos-turnos-lista">
                    <!-- El JS llenará esta lista -->
                </ul>
            </div>
        </div>
    </div> <!-- FIN DE .public-wrapper -->

    <!-- === (NUEVO) PIE DE PÁGINA === -->
    <footer class="main-footer">
        <p>Bienvenido a su Banco. Su tiempo es valioso.</p>
    </footer>


    <script>
        // --- LÓGICA DEL RELOJ DIGITAL ---
        function actualizarReloj() {
            const relojEl = document.getElementById('reloj-digital');
            if (relojEl) { // Solo si existe el elemento
                const ahora = new Date();
                const hora = ahora.getHours().toString().padStart(2, '0');
                const minutos = ahora.getMinutes().toString().padStart(2, '0');
                const segundos = ahora.getSeconds().toString().padStart(2, '0');
                relojEl.textContent = `${hora}:${minutos}:${segundos}`;
            }
        }
        setInterval(actualizarReloj, 1000);
        actualizarReloj(); // Llama inmediatamente

        
        // --- TU LÓGICA ORIGINAL PARA ACTUALIZAR TURNOS ---
        const turnoNumeroEl = document.getElementById('turno-actual-numero');
        const turnoCajaEl = document.getElementById('turno-actual-caja');
        const proximosListaEl = document.getElementById('proximos-turnos-lista');

        async function actualizarPantalla() {
            try {
                const formData = new FormData();
                formData.append('accion', 'obtener_datos_publicos');

                const response = await fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    // --- Actualizar Turno Actual ---
                    if (data.turno_actual && data.turno_actual.codigo_turno) {
                        // Si hay turno, quita los estilos placeholder
                        turnoNumeroEl.textContent = data.turno_actual.codigo_turno;
                        turnoNumeroEl.classList.remove('placeholder-turno');
                        
                        turnoCajaEl.textContent = data.turno_actual.nombre_ubicacion;
                        turnoCajaEl.classList.remove('placeholder-caja');

                        // (Opcional) Sonido de alerta si el turno cambió
                        reproducirSonido(data.turno_actual.codigo_turno);

                    } else {
                        // Si no hay turnos, pone los estilos placeholder
                        turnoNumeroEl.textContent = '---';
                        turnoNumeroEl.classList.add('placeholder-turno');
                        
                        turnoCajaEl.textContent = 'Esperando...';
                        turnoCajaEl.classList.add('placeholder-caja');
                    }

                    // --- Actualizar Próximos Turnos ---
                    proximosListaEl.innerHTML = ''; // Limpiar lista
                    if (data.proximos && data.proximos.length > 0) {
                        data.proximos.forEach(turno => {
                            const li = document.createElement('li');
                            li.textContent = turno.codigo_turno;
                            proximosListaEl.appendChild(li);
                        });
                    } else {
                        // (Opcional) Mostrar un mensaje si no hay próximos
                        const li = document.createElement('li');
                        li.textContent = '-';
                        li.style.color = '#ccc';
                        proximosListaEl.appendChild(li);
                    }
                }

            } catch (error) {
                console.error("Error al actualizar la pantalla:", error);
            }
        }

        // --- Polling (Llamar a la función cada 3 segundos) ---
        setInterval(actualizarPantalla, 3000); 
        actualizarPantalla(); // Llama inmediatamente al cargar

        
        // (Opcional) Lógica de sonido de alerta 
        let ultimoTurnoSonado = '';
        // Variable global para el AudioContext
        let audioContext; 
        
        // Función para inicializar el AudioContext (requerido por los navegadores)
        function inicializarAudio() {
            if (!audioContext) {
                try {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                } catch (e) {
                    console.error("AudioContext no es soportado por este navegador.");
                }
            }
        }
        
        // Evento para inicializar el audio al primer clic del usuario
        document.body.addEventListener('click', inicializarAudio, { once: true });
        document.body.addEventListener('touchend', inicializarAudio, { once: true });


        function reproducirSonido(codigoTurno) {
            // Solo sonar si el turno es nuevo y diferente al último que sonó, y si el audioContext está listo.
            if (codigoTurno && codigoTurno !== ultimoTurnoSonado && audioContext) {
                
                // --- Lógica de sonido simple (sin archivos externos) ---
                try {
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.type = 'sine'; // Tipo de onda
                    oscillator.frequency.setValueAtTime(660, audioContext.currentTime); // Tono (La)
                    gainNode.gain.setValueAtTime(0.5, audioContext.currentTime); // Volumen
                    
                    // Iniciar y detener el sonido
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.3); // Duración de 300ms
                    
                    // Segundo tono (un poco más agudo)
                    const oscillator2 = audioContext.createOscillator();
                    oscillator2.connect(gainNode);
                    oscillator2.type = 'sine';
                    oscillator2.frequency.setValueAtTime(880, audioContext.currentTime + 0.4);
                    oscillator2.start(audioContext.currentTime + 0.4);
                    oscillator2.stop(audioContext.currentTime + 0.7);

                } catch (e) {
                    console.error("Error al reproducir sonido:", e);
                }
            }
            // Actualizar siempre el último turno, incluso si no sonó
            ultimoTurnoSonado = codigoTurno;
        }
        

    </script>

</body>
</html>