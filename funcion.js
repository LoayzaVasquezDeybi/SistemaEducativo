document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verificar si el usuario ha iniciado sesión
    try {
        const response = await fetch('./api/session.php');
        const data = await response.json();
        
        if (!data.success) {
            // Si no hay sesión, botarlo al login
            window.location.href = 'login.html';
            return;
        }

        // 2. Mostrar sus datos reales en la barra superior
        const user = data.usuario;
        const rolesTexto = { 1: 'Admin', 2: 'Docente', 3: 'Alumno' };
        const nombreRol = rolesTexto[user.rol] || 'Usuario';
        document.getElementById('user-name-display').textContent = `${nombreRol}: ${user.nombres} ${user.apellidos}`;

        // 3. Permisos por rol (Qué paneles puede ver cada quién)
        const permisos = {
            1: ['dashboard', 'usuarios', 'estudiantes', 'docentes', 'apoderados', 'roles', 'cursos', 'aulas', 'horarios', 'periodos', 'vacantes', 'notas', 'asistencia', 'incidencias', 'matricula', 'ficha', 'pagos', 'documentos'], // Admin ve TODO
            2: ['dashboard', 'estudiantes', 'horarios', 'notas', 'asistencia', 'incidencias'], // Docente solo ve lo académico
            3: ['dashboard', 'horarios', 'notas', 'ficha'] // Alumno solo ve su info básica
        };
        
        const misPermisos = permisos[user.rol] || permisos[3];

        // 4. Filtrar el menú lateral (sidebar) ocultando lo que no puede ver
        document.querySelectorAll('.nav-item').forEach(item => {
            const panel = item.getAttribute('data-panel');
            if (!misPermisos.includes(panel)) {
                item.style.display = 'none'; // Ocultar
            } else {
                item.addEventListener('click', () => {
                    if(panel) navigate(panel);
                });
            }
        });

        // Ocultar cabeceras de secciones enteras que se hayan quedado vacías
        document.querySelectorAll('.nav-section').forEach(section => {
            const itemsVisibles = Array.from(section.querySelectorAll('.nav-item')).filter(i => i.style.display !== 'none');
            if (itemsVisibles.length === 0) section.style.display = 'none';
        });

        // 5. Cargar el dashboard y asignar evento a cerrar sesión
        navigate('dashboard');
        
        document.getElementById('btn-logout').addEventListener('click', async () => {
            await fetch('./api/logout.php');
            window.location.href = 'login.html';
        });

    } catch (error) {
        console.error("Error al verificar sesión:", error);
    }
});

function navigate(panelName) {
    const container = document.getElementById('panel-container');
    const title = document.getElementById('topbar-title');

    const titles = {
        'dashboard': 'Dashboard',
        'usuarios': 'Gestión de Usuarios',
        'estudiantes': 'Gestión de Estudiantes',
        'docentes': 'Gestión de Docentes',
        'apoderados': 'Gestión de Apoderados',
        'roles': 'Gestión de Roles',
        'cursos': 'Gestión de Cursos',
        'aulas': 'Gestión de Aulas',
        'horarios': 'Horarios',
        'periodos': 'Periodos Académicos',
        'vacantes': 'Vacantes',
        'notas': 'Registro de Notas',
        'asistencia': 'Control de Asistencia',
        'incidencias': 'Incidencias',
        'matricula': 'Registro de Matrícula',
        'ficha': 'Ficha de Matrícula',
        'pagos': 'Gestión de Pagos',
        'documentos': 'Gestión de Documentos'
    };

    // ANTI-CACHÉ: Agrega una marca de tiempo para obligar al navegador a descargar el archivo real
    const urlReal = `./modulos/${panelName}.html?v=${new Date().getTime()}`;

    fetch(urlReal)
        .then(response => {
            if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
            title.textContent = titles[panelName] || panelName.charAt(0).toUpperCase() + panelName.slice(1);
            actualizarSidebar(panelName);
            
            // EJECUTAR LA FUNCIÓN INICIALIZADORA DEL MÓDULO
            if (typeof window['inicializar' + panelName.charAt(0).toUpperCase() + panelName.slice(1)] === 'function') {
                window['inicializar' + panelName.charAt(0).toUpperCase() + panelName.slice(1)]();
            } else if (panelName === 'usuarios' && typeof inicializarUsuarios === 'function') {
                inicializarUsuarios();
            } else if (panelName === 'estudiantes' && typeof inicializarEstudiantes === 'function') {
                inicializarEstudiantes();
            }
        })
        .catch(error => {
            console.error("Fallo el Fetch:", error);
            container.innerHTML = `
                <div class="sec-title" style="color:var(--danger);">¡Ups! Hubo un problema</div>
                <div class="sec-sub">No se pudo cargar la ruta: <b>./modulos/${panelName}.html</b></div>
                <div style="margin-top:20px; padding:15px; background:rgba(255,0,0,0.1); color:var(--danger); border-radius:8px; font-family:'DM Mono', monospace; font-size:13px;">
                    Motivo: ${error.message}<br><br>
                    Tip de Ingeniero: Abre este proyecto en tu navegador normal (Chrome/Brave) y presiona <b>F12</b> para ver la pestaña "Consola".
                </div>
            `;
        });
}

function actualizarSidebar(panelName) {
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    const itemActivo = document.querySelector(`.nav-item[data-panel="${panelName}"]`);
    if (itemActivo) itemActivo.classList.add('active');
}