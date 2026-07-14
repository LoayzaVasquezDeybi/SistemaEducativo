async function inicializarApoderados() {
    await cargarCombosApoderado();
    await cargarApoderados();
}

async function cargarCombosApoderado() {
    const respuesta = await cargarDatos('apoderados', 'combo_estudiantes');
    if (!respuesta || !respuesta.estudiantes) return;
    const selEst = document.getElementById('apo-estudiante');
    selEst.innerHTML = '<option value="">Seleccione estudiante a vincular</option>';
    respuesta.estudiantes.forEach(e => selEst.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre} (${e.dni})</option>`);
}

async function cargarApoderados() {
    const respuesta = await cargarDatos('apoderados', 'obtener');
    if (!respuesta || !respuesta.data) return;
    const apoderados = respuesta.data;
    const tbody = document.querySelector('#tabla-apoderados tbody');
    tbody.innerHTML = '';
    apoderados.forEach(apo => {
        const data = JSON.stringify(apo).replace(/'/g, '&apos;');
        const estado = apo.id_estado_usuario == 1 ? 'activo' : 'inactivo';
        tbody.innerHTML += `
            <tr>
                <td>${apo.apellido}, ${apo.nombre}</td>
                <td>${apo.dni}</td>
                <td>${apo.email}</td>
                <td>${apo.estudiantes || 'Ninguno'}</td>
                <td><span class="tag tag-${estado === 'activo' ? 'green' : 'red'}">${estado}</span></td>
                <td style="display:flex; gap:5px;">
                    <button class="btn btn-secondary btn-sm" onclick='editarApoderado(${data})'>Editar</button>
                    <button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarApoderado(${apo.id_apoderado})">Eliminar</button>
                </td>
            </tr>`;
    });
}

function editarApoderado(apo) {
    document.getElementById('apo-id').value = apo.id_apoderado;
    document.getElementById('apo-nombre').value = apo.nombre;
    document.getElementById('apo-apellido').value = apo.apellido;
    document.getElementById('apo-dni').value = apo.dni;
    document.getElementById('apo-email').value = apo.email;
    document.getElementById('apo-estudiante').value = apo.id_estudiante || '';
    document.getElementById('apo-parentesco').value = apo.parentesco || '';
    document.getElementById('apo-estado').value = apo.id_estado_usuario;
}

async function guardarApoderado() {
    const id = document.getElementById('apo-id').value;
    const data = {
        nombre: document.getElementById('apo-nombre').value,
        apellido: document.getElementById('apo-apellido').value,
        dni: document.getElementById('apo-dni').value,
        email: document.getElementById('apo-email').value,
        id_estudiante: document.getElementById('apo-estudiante').value,
        parentesco: document.getElementById('apo-parentesco').value,
        id_estado_usuario: document.getElementById('apo-estado').value
    };
    if (id) data.id_apoderado = id;
    if (await guardarDatos('apoderados', id ? 'actualizar' : 'crear', data)) {
        limpiarFormularioApoderado();
        cargarApoderados();
    }
}

function limpiarFormularioApoderado() {
    document.querySelectorAll('#card-form-apoderado input, #card-form-apoderado select').forEach(el => el.value = '');
    document.getElementById('apo-estado').value = 1;
}

async function eliminarApoderado(id) {
    if (confirm('¿Está seguro de eliminar este apoderado? Se eliminará su usuario asociado.') && await guardarDatos('apoderados', 'eliminar', { id_apoderado: id })) {
        cargarApoderados();
    }
}