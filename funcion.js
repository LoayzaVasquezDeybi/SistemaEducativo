window.USUARIO_ACTUAL = null;
window.MODO_SOLO_LECTURA = false;
window.ES_ALUMNO = false;
window.ES_DOCENTE = false;

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const response = await fetch('./api/session.php');
        const data = await response.json();

        if (!data.success) {
            window.location.href = 'login.html';
            return;
        }

        const user = data.usuario;
        window.USUARIO_ACTUAL = user;
        const rolNombre = user.rol_nombre || ({ 1: 'Administrador', 2: 'Docente', 3: 'Alumno' }[user.rol] || 'Usuario');
        const rolKey = rolNombre.toLowerCase();
        window.ES_ALUMNO = rolKey.includes('alumno') || rolKey.includes('estudiante');
        window.ES_DOCENTE = rolKey.includes('docente');
        document.getElementById('user-name-display').textContent = `${rolNombre}: ${user.nombres} ${user.apellidos}`;

        const permisos = obtenerPermisosPorRol(user.rol, rolKey);
        window.MODO_SOLO_LECTURA = window.ES_ALUMNO;

        document.querySelectorAll('.nav-item').forEach(item => {
            const panel = item.getAttribute('data-panel');
            if (!permisos.includes(panel)) {
                item.style.display = 'none';
            } else {
                item.addEventListener('click', () => {
                    if (panel) navigate(panel);
                });
            }
        });

        document.querySelectorAll('.nav-section').forEach(section => {
            const itemsVisibles = Array.from(section.querySelectorAll('.nav-item')).filter(i => i.style.display !== 'none');
            if (itemsVisibles.length === 0) section.style.display = 'none';
        });

        navigate(permisos.includes('dashboard') ? 'dashboard' : permisos[0]);

        document.getElementById('btn-logout').addEventListener('click', async () => {
            await fetch('./api/logout.php');
            window.location.href = 'login.html';
        });
    } catch (error) {
        console.error('Error al verificar sesion:', error);
    }
});

function obtenerPermisosPorRol(idRol, rolKey) {
    const todo = ['dashboard', 'usuarios', 'estudiantes', 'docentes', 'apoderados', 'roles', 'cursos', 'aulas', 'horarios', 'periodos', 'vacantes', 'notas', 'asistencia', 'incidencias', 'matricula', 'ficha', 'tutorias', 'pagos', 'documentos'];
    if (Number(idRol) === 1 || rolKey.includes('admin')) return todo;
    if (rolKey.includes('docente')) return ['dashboard', 'estudiantes', 'horarios', 'notas', 'asistencia', 'incidencias', 'tutorias']; // Sin acceso a 'aulas'
    if (rolKey.includes('recepcion')) return ['dashboard', 'matricula', 'pagos', 'ficha'];
    if (rolKey.includes('alumno') || rolKey.includes('estudiante')) return ['dashboard', 'cursos', 'horarios', 'notas', 'asistencia']; // Sin acceso a 'aulas'
    return ['dashboard'];
}

function navigate(panelName) {
    const container = document.getElementById('panel-container');
    const title = document.getElementById('topbar-title');

    const titles = {
        dashboard: 'Dashboard',
        usuarios: 'Gestion de Usuarios',
        estudiantes: 'Estudiantes',
        docentes: 'Gestion de Docentes',
        apoderados: 'Gestion de Apoderados',
        roles: 'Gestion de Roles',
        cursos: 'Cursos',
        aulas: 'Aulas',
        horarios: 'Horarios',
        periodos: 'Periodos Academicos',
        vacantes: 'Vacantes',
        notas: 'Notas',
        asistencia: 'Asistencia',
        incidencias: 'Incidencias',
        matricula: 'Registro de Matricula',
        ficha: 'Ficha de Matricula',
        tutorias: 'Tutoría y Libretas',
        pagos: 'Gestion de Pagos',
        documentos: 'Gestion de Documentos'
    };

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
            ejecutarInicializador(panelName);
            aplicarSoloLecturaSiCorresponde();
        })
        .catch(error => {
            console.error('Fallo el Fetch:', error);
            container.innerHTML = `
                <div class="sec-title" style="color:var(--danger);">Hubo un problema</div>
                <div class="sec-sub">No se pudo cargar la ruta: <b>./modulos/${panelName}.html</b></div>
                <div style="margin-top:20px; padding:15px; background:rgba(255,0,0,0.1); color:var(--danger); border-radius:8px; font-family:'DM Mono', monospace; font-size:13px;">
                    Motivo: ${error.message}
                </div>
            `;
        });
}

function ejecutarInicializador(panelName) {
    const nombre = 'inicializar' + panelName.charAt(0).toUpperCase() + panelName.slice(1);
    if (typeof window[nombre] === 'function') {
        window[nombre]();
    } else if (panelName === 'matricula' && typeof inicializarMatricula === 'function') {
        inicializarMatricula();
    }
}

function aplicarSoloLecturaSiCorresponde() {
    if (!window.MODO_SOLO_LECTURA) return;
    const container = document.getElementById('panel-container');
    container.querySelectorAll('form, .card').forEach(el => {
        const hasTable = el.querySelector('table');
        if (!hasTable) el.style.display = 'none';
    });
    container.querySelectorAll('button').forEach(btn => {
        const text = (btn.textContent || '').toLowerCase();
        if (!text.includes('ver') && !text.includes('imprimir') && !text.includes('descargar')) {
            btn.style.display = 'none';
        }
    });
    container.querySelectorAll('input, select, textarea').forEach(el => {
        if (!el.closest('.table-wrap')) el.disabled = true;
    });
}

function actualizarSidebar(panelName) {
    document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
    const itemActivo = document.querySelector(`.nav-item[data-panel="${panelName}"]`);
    if (itemActivo) itemActivo.classList.add('active');
}
