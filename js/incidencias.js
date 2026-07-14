let incidenciasReadonly = false;

async function inicializarIncidencias() {
    document.getElementById('incidencia-fecha').value = new Date().toISOString().split('T')[0];
    await cargarCombosIncidencias();
    await cargarIncidencias();
    aplicarPermisosIncidencias();

    const btnExportarPdf = document.getElementById('btn-exportar-incidencias-pdf');
    if (btnExportarPdf) {
        btnExportarPdf.addEventListener('click', () => {
            window.open('api/exportar_incidencias.php', '_blank');
        });
    }
}

async function cargarCombosIncidencias() {
    const result = await cargarDatos('incidencias', 'combo');
    if (!result || !result.success) return;

    const selEst = document.getElementById('incidencia-estudiante');
    const selTipo = document.getElementById('incidencia-tipo');
    const selDocente = document.getElementById('incidencia-docente');

    selEst.innerHTML = '<option value="">Seleccione estudiante</option>';
    result.estudiantes.forEach(e => selEst.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre}</option>`);

    selTipo.innerHTML = '<option value="">Seleccione tipo</option>';
    result.tipos.forEach(t => selTipo.innerHTML += `<option value="${t.id_tipo_incidencia}">${t.nombre}</option>`);

    selDocente.innerHTML = '<option value="">Sistema / Auxiliar</option>';
    result.docentes.forEach(d => selDocente.innerHTML += `<option value="${d.id_docente}">${d.apellidos}, ${d.nombres}</option>`);
}

async function cargarIncidencias() {
    const respuesta = await cargarDatos('incidencias', 'obtener');
    if (!respuesta) return;
    const incidencias = respuesta.data || [];
    incidenciasReadonly = !!respuesta.readonly;

    const tbody = document.querySelector('#tabla-incidencias tbody');
    tbody.innerHTML = '';
    incidencias.forEach(i => {
        const data = JSON.stringify(i).replace(/'/g, '&apos;');
        const acciones = incidenciasReadonly
            ? '-'
            : `<button class="btn btn-secondary btn-sm" onclick='editarIncidencia(${data})'>Editar</button>
               <button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarIncidencia(${i.id_incidencia})">Eliminar</button>`;
        tbody.innerHTML += `
            <tr>
                <td>${i.fecha}</td>
                <td>${i.estudiante}</td>
                <td><span class="tag tag-red">${i.tipo}</span></td>
                <td>${i.descripcion}</td>
                <td>${i.accion_tomada || ''}</td>
                <td>${i.docente || 'Sistema'}</td>
                <td style="display:flex; gap:5px;">${acciones}</td>
            </tr>`;
    });
    aplicarPermisosIncidencias();
}

function editarIncidencia(i) {
    document.getElementById('incidencia-id').value = i.id_incidencia;
    document.getElementById('incidencia-fecha').value = i.fecha;
    document.getElementById('incidencia-estudiante').value = i.id_estudiante;
    document.getElementById('incidencia-tipo').value = i.id_tipo_incidencia;
    document.getElementById('incidencia-descripcion').value = i.descripcion;
    document.getElementById('incidencia-accion').value = i.accion_tomada || '';
    document.getElementById('incidencia-docente').value = i.id_docente || '';
}

async function guardarIncidencia() {
    const data = {
        id_incidencia: document.getElementById('incidencia-id').value,
        fecha: document.getElementById('incidencia-fecha').value,
        id_estudiante: document.getElementById('incidencia-estudiante').value,
        id_tipo_incidencia: document.getElementById('incidencia-tipo').value,
        descripcion: document.getElementById('incidencia-descripcion').value.trim(),
        accion_tomada: document.getElementById('incidencia-accion').value.trim(),
        id_docente: document.getElementById('incidencia-docente').value
    };
    const id = data.id_incidencia;
    if (!data.fecha || !data.id_estudiante || !data.id_tipo_incidencia || !data.descripcion) {
        return alert('Los campos fecha, estudiante, tipo y descripción son obligatorios.');
    }
    if (await guardarDatos('incidencias', id ? 'actualizar' : 'crear', data)) {
        limpiarFormularioIncidencia();
        cargarIncidencias();
    }
}

function limpiarFormularioIncidencia() {
    document.querySelectorAll('#incidencia-id, #incidencia-estudiante, #incidencia-tipo, #incidencia-descripcion, #incidencia-accion, #incidencia-docente').forEach(el => el.value = '');
    document.getElementById('incidencia-fecha').value = new Date().toISOString().split('T')[0];
}

async function eliminarIncidencia(id) {
    if (confirm('¿Está seguro de eliminar esta incidencia?') && await guardarDatos('incidencias', 'eliminar', { id_incidencia: id })) cargarIncidencias();
}

function aplicarPermisosIncidencias() {
    const form = document.getElementById('card-form-incidencia');
    if (form && (incidenciasReadonly || (window.USUARIO_ACTUAL && Number(window.USUARIO_ACTUAL.rol) === 3))) {
        form.style.display = 'none';
    }
}