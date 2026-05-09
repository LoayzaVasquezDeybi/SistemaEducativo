document.addEventListener('DOMContentLoaded', () => {
    // 1. Asignar el evento click a todos los items del sidebar
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            const panel = item.getAttribute('data-panel');
            if(panel) navigate(panel);
        });
    });

    // 2. Cargar el dashboard por defecto al iniciar
    navigate('dashboard');
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