// ========== MÓDULO DASHBOARD ==========
function inicializarDashboard() {
    cargarEstadisticasDashboard();
}

async function cargarEstadisticasDashboard() {
    const respuesta = await cargarDatos('dashboard', 'obtener');
    
    if (respuesta && respuesta.data) {
        const estadisticas = respuesta.data;
        const statEstudiantes = document.getElementById('stat-estudiantes');
        const statDocentes = document.getElementById('stat-docentes');
        const statCursos = document.getElementById('stat-cursos');
        const statUsuarios = document.getElementById('stat-usuarios');

        if (statEstudiantes) statEstudiantes.textContent = estadisticas.estudiantes;
        if (statDocentes) statDocentes.textContent = estadisticas.docentes;
        if (statCursos) statCursos.textContent = estadisticas.cursos;
        if (statUsuarios) statUsuarios.textContent = estadisticas.usuarios;
    }
}