// ========== MÓDULO CURSOS ==========
let editandoCursoID = null;

function inicializarCursos() {
    cargarCursos();
    cargarCombosCurso();
    const btnRegistrar = document.getElementById('btn-registrar-curso');
    if (btnRegistrar) {
        btnRegistrar.onclick = registrarCurso;
    }
}

async function cargarCursos() {
    const cursos = await cargarDatos('cursos', 'obtener');
    if (cursos) {
        const tbody = document.querySelector('#tabla-cursos tbody');
        if (tbody) {
            tbody.innerHTML = '';
            cursos.forEach(curso => {
                const cursoJSON = JSON.stringify(curso).replace(/'/g, "&apos;");
                const estado = curso.estado ? curso.estado : 'activo';
                const docenteNombre = curso.docente_nombre ? `${curso.docente_nombre} ${curso.docente_apellido}` : '<span style="color:var(--muted)">Sin asignar</span>';
                
                const fila = `
                    <tr>
                        <td>${curso.nombre}</td>
                        <td>${curso.descripcion || ''}</td>
                        <td>${curso.creditos || 0}</td>
                        <td>${docenteNombre}</td>
                        <td><span class="tag tag-${estado === 'activo' ? 'green' : 'amber'}">${estado.charAt(0).toUpperCase() + estado.slice(1)}</span></td>
                        <td><button class="btn btn-secondary btn-sm" onclick='prepararEdicionCurso(${cursoJSON})'>Editar</button></td>
                    </tr>
                `;
                tbody.innerHTML += fila;
            });
        }
    }
}

async function cargarCombosCurso() {
    const result = await cargarDatos('cursos', 'combo');
    if (result && result.success) {
        const select = document.getElementById('curso-docente');
        if (select) {
            select.innerHTML = '<option value="">Sin asignar</option>';
            result.docentes.forEach(doc => {
                select.innerHTML += `<option value="${doc.id_docente}">${doc.nombres} ${doc.apellidos}</option>`;
            });
        }
    }
}

function prepararEdicionCurso(curso) {
    editandoCursoID = curso.id_curso;
    document.getElementById('curso-nombre').value = curso.nombre || '';
    document.getElementById('curso-descripcion').value = curso.descripcion || '';
    document.getElementById('curso-creditos').value = curso.creditos || '';
    document.getElementById('curso-docente').value = curso.id_docente || '';

    const btnRegistrar = document.getElementById('btn-registrar-curso');
    if (btnRegistrar) { btnRegistrar.textContent = "Actualizar curso"; btnRegistrar.classList.replace('btn-primary', 'btn-amber'); }
}

async function registrarCurso() {
    const nombre = document.getElementById('curso-nombre').value;
    const descripcion = document.getElementById('curso-descripcion').value;
    const creditos = document.getElementById('curso-creditos').value;
    const id_docente = document.getElementById('curso-docente').value;

    if (!nombre) return alert('Por favor, ingresa el nombre del curso.');

    const datos = { nombre, descripcion, creditos, id_docente };
    let accion = editandoCursoID ? 'actualizar' : 'crear';
    if (editandoCursoID) { datos.id_curso = editandoCursoID; datos.estado = 'activo'; }

    if (await guardarDatos('cursos', accion, datos)) {
        limpiarFormularioEdicionCurso();
        cargarCursos();
    }
}

function limpiarFormularioEdicionCurso() {
    editandoCursoID = null;
    const btnRegistrar = document.getElementById('btn-registrar-curso');
    if(btnRegistrar) { btnRegistrar.textContent = "Registrar curso"; btnRegistrar.classList.replace('btn-amber', 'btn-primary'); }
    document.querySelectorAll('#curso-nombre, #curso-descripcion, #curso-creditos, #curso-docente').forEach(el => el.value = '');
}