let estudiantesReadonly = false;

async function inicializarEstudiantes() {
    await cargarCombosEstudiante();
    await cargarEstudiantes();
    aplicarPermisosEstudiantes();

    const btnExportarPdf = document.getElementById('btn-exportar-estudiantes-pdf');
    if (btnExportarPdf) {
        btnExportarPdf.addEventListener('click', () => {
            window.open('api/exportar_estudiantes.php', '_blank');
        });
    }
}

async function cargarCombosEstudiante() {
    const respuesta = await cargarDatos('estudiantes', 'combo');
    if (!respuesta || !respuesta.grados) return;
    const selGrado = document.getElementById('est-grado');
    const selSeccion = document.getElementById('est-seccion');
    if (!selGrado || !selSeccion) return;
    selGrado.innerHTML = '<option value="">Seleccione grado</option>';
    respuesta.grados.forEach(g => selGrado.innerHTML += `<option value="${g.id_grado}">${g.nombre}</option>`);
    selSeccion.innerHTML = '<option value="">Seleccione sección</option>';
    respuesta.secciones.forEach(s => selSeccion.innerHTML += `<option value="${s.id_seccion}">${s.nombre}</option>`);
}

async function cargarEstudiantes() {
    const respuesta = await cargarDatos('estudiantes', 'obtener');
    if (!respuesta || !respuesta.data) return;
    const estudiantes = respuesta.data;
    estudiantesReadonly = !!respuesta.readonly;
    const tbody = document.querySelector('#tabla-estudiantes tbody');
    tbody.innerHTML = '';
    estudiantes.forEach(est => {
        const data = JSON.stringify(est).replace(/'/g, '&apos;');
        const acciones = estudiantesReadonly
            ? '-'
            : `<button class="btn btn-secondary btn-sm" onclick='editarEstudiante(${data})'>Editar</button>
               <button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarEstudiante(${est.id_estudiante})">Eliminar</button>`;
        tbody.innerHTML += `
            <tr>
                <td>${est.codigo}</td>
                <td>${est.apellido}, ${est.nombre}</td>
                <td>${est.dni}</td>
                <td>${est.grado} - ${est.seccion}</td>
                <td><span class="tag tag-green">${est.estado}</span></td>
                <td style="display:flex; gap:5px;">${acciones}</td>
            </tr>`;
    });
    aplicarPermisosEstudiantes();
}

function editarEstudiante(est) {
    document.getElementById('est-id').value = est.id_estudiante;
    document.getElementById('est-codigo').value = est.codigo;
    document.getElementById('est-nombre').value = est.nombre;
    document.getElementById('est-apellido').value = est.apellido;
    document.getElementById('est-dni').value = est.dni;
    document.getElementById('est-fecha-nac').value = est.fecha_nacimiento;
    document.getElementById('est-grado').value = est.id_grado;
    document.getElementById('est-seccion').value = est.id_seccion;
    document.getElementById('est-estado').value = est.estado;
}

async function guardarEstudiante() {
    const id = document.getElementById('est-id').value;
    const data = {
        codigo: document.getElementById('est-codigo').value,
        nombre: document.getElementById('est-nombre').value,
        apellido: document.getElementById('est-apellido').value,
        dni: document.getElementById('est-dni').value,
        fecha_nacimiento: document.getElementById('est-fecha-nac').value,
        id_grado: document.getElementById('est-grado').value,
        id_seccion: document.getElementById('est-seccion').value,
        estado: document.getElementById('est-estado').value
    };
    if (id) data.id_estudiante = id;
    if (await guardarDatos('estudiantes', id ? 'actualizar' : 'crear', data)) {
        limpiarFormularioEstudiante();
        cargarEstudiantes();
    }
}

function limpiarFormularioEstudiante() {
    document.querySelectorAll('#card-form-estudiante input, #card-form-estudiante select').forEach(el => el.value = '');
}

async function eliminarEstudiante(id) {
    if (confirm('¿Está seguro? Se eliminarán matrículas y pagos asociados.') && await guardarDatos('estudiantes', 'eliminar', { id_estudiante: id })) cargarEstudiantes();
}

function aplicarPermisosEstudiantes() {
    const form = document.getElementById('card-form-estudiante');
    if (form && estudiantesReadonly) {
        form.style.display = 'none';
    }
}