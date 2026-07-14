async function inicializarDocentes() {
    await cargarCombosDocente();
    await cargarDocentes();
    configurarBuscador('buscar-docente', 'tabla-docentes');

    const btnRegistrar = document.getElementById('btn-registrar-docente');
    if (btnRegistrar) {
        btnRegistrar.onclick = guardarDocente;
    }

    const btnExportarPdf = document.getElementById('btn-exportar-docentes-pdf');
    if (btnExportarPdf) {
        btnExportarPdf.onclick = exportarDocentesPdf;
    }
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
    const idInput = document.getElementById('doc-id');
    const codigo = document.getElementById('doc-codigo');
    const nombre = document.getElementById('doc-nombre');
    const apellido = document.getElementById('doc-apellido');
    const dni = document.getElementById('doc-dni');
    const email = document.getElementById('doc-email');
    const especialidad = document.getElementById('doc-especialidad');
    const curso = document.getElementById('doc-curso');
    const estado = document.getElementById('doc-estado');

    if (idInput) idInput.value = doc.id_docente;
    if (codigo) codigo.value = doc.codigo_docente || '';
    if (nombre) nombre.value = doc.nombre;
    if (apellido) apellido.value = doc.apellido;
    if (dni) dni.value = doc.dni;
    if (email) email.value = doc.email;
    if (especialidad) especialidad.value = doc.especialidad || '';
    if (curso) curso.value = doc.id_curso || '';
    if (estado) estado.value = doc.estado || 'activo';
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
    const campos = ['#doc-id', '#doc-codigo', '#doc-nombre', '#doc-apellido', '#doc-dni', '#doc-email', '#doc-especialidad', '#doc-curso'];
    campos.forEach(selector => {
        const el = document.querySelector(selector);
        if (el) el.value = '';
    });

    const estado = document.getElementById('doc-estado');
    if (estado) estado.value = 'activo';
}

function exportarDocentesPdf() {
    window.open('./api/exportar_docentes.php', '_blank');
}

async function eliminarDocente(id) {
    if (confirm('¿Está seguro de eliminar este docente? Se eliminará su usuario asociado.') && await guardarDatos('docentes', 'eliminar', { id_docente: id })) cargarDocentes();
}