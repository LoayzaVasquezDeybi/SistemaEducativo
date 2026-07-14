async function inicializarDocumentos() {
    await cargarCombosDocumentos();
    await cargarDocumentos();
    const form = document.getElementById('form-documento');
    if (form) form.onsubmit = subirDocumento;
}

async function cargarCombosDocumentos() {
    const result = await cargarDatos('documentos', 'combo');
    if (!result || !result.success) return;
    const tipo = document.getElementById('doc-tipo');
    const usuario = document.getElementById('doc-usuario');
    const estudiante = document.getElementById('doc-estudiante');
    tipo.innerHTML = '<option value="">Seleccione tipo</option>';
    result.tipos.forEach(t => tipo.innerHTML += `<option value="${t.id_tipo_documento}">${t.nombre}</option>`);
    usuario.innerHTML = '<option value="">Seleccione usuario</option>';
    result.usuarios.forEach(u => usuario.innerHTML += `<option value="${u.id_usuario}">${u.nombre}</option>`);
    estudiante.innerHTML = '<option value="">General / sin estudiante</option>';
    result.estudiantes.forEach(e => estudiante.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre}</option>`);
}

async function cargarDocumentos() {
    const documentos = await cargarDatos('documentos', 'obtener');
    if (!documentos) return;
    const tbody = document.querySelector('#tabla-documentos tbody');
    tbody.innerHTML = '';
    documentos.forEach(d => {
        tbody.innerHTML += `<tr>
            <td>${d.nombre_archivo}</td><td><span class="tag tag-blue">${d.tipo_documento}</span></td><td>${d.usuario}</td><td>${d.estudiante || 'General'}</td><td>${d.fecha_subida}</td>
            <td style="display:flex; gap:5px;"><a class="btn btn-secondary btn-sm" href="${d.ruta_archivo}" target="_blank">Ver</a><a class="btn btn-secondary btn-sm" href="${d.ruta_archivo}" download>Descargar</a><button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarDocumento(${d.id_documento})">Eliminar</button></td>
        </tr>`;
    });
}

async function subirDocumento(event) {
    event.preventDefault();
    const archivo = document.getElementById('doc-archivo').files[0];
    const id_tipo_documento = document.getElementById('doc-tipo').value;
    const id_usuario = document.getElementById('doc-usuario').value;
    const id_estudiante = document.getElementById('doc-estudiante').value;
    if (!archivo || !id_tipo_documento || !id_usuario) return alert('Selecciona archivo, tipo y usuario.');

    const formData = new FormData();
    formData.append('archivo', archivo);
    formData.append('id_tipo_documento', id_tipo_documento);
    formData.append('id_usuario', id_usuario);
    formData.append('id_estudiante', id_estudiante);

    const response = await fetch('./api/documentos.php?action=crear', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) {
        alert(result.message);
        document.getElementById('form-documento').reset();
        cargarDocumentos();
    } else {
        alert('Error: ' + (result.error || result.message));
    }
}

async function eliminarDocumento(id_documento) {
    if (confirm('Eliminar este documento?') && await guardarDatos('documentos', 'eliminar', { id_documento })) cargarDocumentos();
}
