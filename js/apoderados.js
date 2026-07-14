async function inicializarApoderados() {
    await cargarCombosApoderado();
    await cargarApoderados();
    configurarBuscador('buscar-apoderado', 'tabla-apoderados');

    const btnRegistrar = document.getElementById('btn-registrar-apoderado');
    if (btnRegistrar) {
        btnRegistrar.onclick = guardarApoderado;
    }
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
    const idInput = document.getElementById('apo-id');
    const nombre = document.getElementById('apo-nombre');
    const apellido = document.getElementById('apo-apellido');
    const dni = document.getElementById('apo-dni');
    const email = document.getElementById('apo-email');
    const estudiante = document.getElementById('apo-estudiante');
    const parentesco = document.getElementById('apo-parentesco');
    const estado = document.getElementById('apo-estado');

    if (idInput) idInput.value = apo.id_apoderado;
    if (nombre) nombre.value = apo.nombre;
    if (apellido) apellido.value = apo.apellido;
    if (dni) dni.value = apo.dni;
    if (email) email.value = apo.email;
    if (estudiante) estudiante.value = apo.id_estudiante || '';
    if (parentesco) parentesco.value = apo.parentesco || '';
    if (estado) estado.value = apo.id_estado_usuario || '1';
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
    const campos = ['#apo-id', '#apo-nombre', '#apo-apellido', '#apo-dni', '#apo-email', '#apo-estudiante', '#apo-parentesco'];
    campos.forEach(selector => {
        const el = document.querySelector(selector);
        if (el) el.value = '';
    });

    const estado = document.getElementById('apo-estado');
    if (estado) estado.value = '1';
}

async function eliminarApoderado(id) {
    if (confirm('¿Está seguro de eliminar este apoderado? Se eliminará su usuario asociado.') && await guardarDatos('apoderados', 'eliminar', { id_apoderado: id })) {
        cargarApoderados();
    }
}