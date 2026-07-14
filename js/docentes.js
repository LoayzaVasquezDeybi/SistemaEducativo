async function inicializarDocentes() {
    await cargarCombosDocente();
    await cargarDocentes();
}

async function cargarCombosDocente() {
    const respuesta = await cargarDatos('docentes', 'combo_cursos');
    if (!respuesta || !respuesta.cursos) return;
    const selCurso = document.getElementById('doc-curso');
    selCurso.innerHTML = '<option value="">Seleccione un curso principal</option>';
    respuesta.cursos.forEach(c => selCurso.innerHTML += `<option value="${c.id_curso}">${c.nombre}</option>`);
}

async function cargarDocentes() {
    const respuesta = await cargarDatos('docentes', 'obtener');
    if (!respuesta || !respuesta.data) return;
    const docentes = respuesta.data;
    const tbody = document.querySelector('#tabla-docentes tbody');
    tbody.innerHTML = '';
    docentes.forEach(doc => {
        const data = JSON.stringify(doc).replace(/'/g, '&apos;');
        const estado = doc.estado === 'activo' ? 'green' : 'red';
        tbody.innerHTML += `
            <tr>
                <td>${doc.codigo_docente}</td>
                <td>${doc.apellido}, ${doc.nombre}</td>
                <td>${doc.dni}</td>
                <td>${doc.especialidad || ''}</td>
                <td><span class="tag tag-${estado}">${doc.estado}</span></td>
                <td style="display:flex; gap:5px;">
                    <button class="btn btn-secondary btn-sm" onclick='editarDocente(${data})'>Editar</button>
                    <button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarDocente(${doc.id_docente})">Eliminar</button>
                </td>
            </tr>`;
    });
}

function editarDocente(doc) {
    document.getElementById('doc-id').value = doc.id_docente;
    document.getElementById('doc-codigo').value = doc.codigo_docente;
    document.getElementById('doc-nombre').value = doc.nombre;
    document.getElementById('doc-apellido').value = doc.apellido;
    document.getElementById('doc-dni').value = doc.dni;
    document.getElementById('doc-email').value = doc.email;
    document.getElementById('doc-especialidad').value = doc.especialidad;
    document.getElementById('doc-curso').value = doc.id_curso || '';
    document.getElementById('doc-estado').value = doc.estado;
}

async function guardarDocente() {
    const id = document.getElementById('doc-id').value;
    const data = {
        codigo_docente: document.getElementById('doc-codigo').value,
        nombre: document.getElementById('doc-nombre').value,
        apellido: document.getElementById('doc-apellido').value,
        dni: document.getElementById('doc-dni').value,
        email: document.getElementById('doc-email').value,
        especialidad: document.getElementById('doc-especialidad').value,
        id_curso: document.getElementById('doc-curso').value,
        estado: document.getElementById('doc-estado').value
    };
    if (id) data.id_docente = id;
    if (await guardarDatos('docentes', id ? 'actualizar' : 'crear', data)) {
        limpiarFormularioDocente();
        cargarDocentes();
    }
}

function limpiarFormularioDocente() {
    document.querySelectorAll('#card-form-docente input, #card-form-docente select').forEach(el => el.value = '');
    document.getElementById('doc-estado').value = 'activo';
}

async function eliminarDocente(id) {
    if (confirm('¿Está seguro de eliminar este docente? Se eliminará su usuario asociado.') && await guardarDatos('docentes', 'eliminar', { id_docente: id })) cargarDocentes();
}