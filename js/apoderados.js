// ========== MÓDULO APODERADOS ==========
function inicializarApoderados() {
    cargarApoderados();
    cargarComboEstudiantesApo();
    const btnRegistrar = document.getElementById('btn-registrar-apoderado');
    if (btnRegistrar) btnRegistrar.onclick = registrarApoderado;
    configurarBuscador('buscar-apoderado', 'tabla-apoderados');
}

async function cargarApoderados() {
    const apoderados = await cargarDatos('apoderados', 'obtener');
    if (apoderados) {
        const tbody = document.querySelector('#tabla-apoderados tbody');
        if (tbody) {
            tbody.innerHTML = '';
            apoderados.forEach(apo => {
                const estado = apo.estado ? apo.estado : 'activo';
                const fila = `
                    <tr>
                        <td>${apo.nombre} ${apo.apellido}</td>
                        <td>${apo.dni}</td>
                        <td>${apo.email}</td>
                        <td style="font-size: 13px; color: var(--primary); font-weight:500;">${apo.estudiantes || '<span style="color:var(--muted)">Sin asignar</span>'}</td>
                        <td><span class="tag tag-${estado === 'activo' ? 'green' : 'amber'}">${estado.charAt(0).toUpperCase() + estado.slice(1)}</span></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" style="background:#fee2e2; color:#dc2626; border-color:#fca5a5;" onclick='eliminarApoderado(${apo.id_apoderado})'>Eliminar</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += fila;
            });
        }
    }
}

async function cargarComboEstudiantesApo() {
    const result = await cargarDatos('apoderados', 'combo_estudiantes');
    if (result && result.success) {
        const select = document.getElementById('apo-estudiante');
        if (select) {
            select.innerHTML = '<option value="">Seleccionar Estudiante (Opcional)</option>';
            result.estudiantes.forEach(est => {
                select.innerHTML += `<option value="${est.id_estudiante}">${est.dni} - ${est.apellido}, ${est.nombre}</option>`;
            });
        }
    }
}

async function registrarApoderado() {
    const nombre = document.getElementById('apo-nombre').value;
    const apellido = document.getElementById('apo-apellido').value;
    const dni = document.getElementById('apo-dni').value;
    const email = document.getElementById('apo-email').value;
    const id_estudiante = document.getElementById('apo-estudiante').value;
    const parentesco = document.getElementById('apo-parentesco').value;

    if (!nombre || !apellido || !dni || !email) return alert('Por favor, completa los campos obligatorios (Nombre, Apellido, DNI y Email).');
    if (id_estudiante && !parentesco) return alert('Si seleccionas un estudiante, debes indicar el parentesco.');

    if (await guardarDatos('apoderados', 'crear', { nombre, apellido, dni, email, id_estudiante, parentesco })) {
        document.querySelectorAll('#apo-nombre, #apo-apellido, #apo-dni, #apo-email, #apo-estudiante, #apo-parentesco').forEach(el => el.value = '');
        cargarApoderados();
    }
}

async function eliminarApoderado(id_apoderado) {
    if (confirm('¿Estás seguro de eliminar este Apoderado? Su usuario asociado también será borrado.')) {
        if (await guardarDatos('apoderados', 'eliminar', { id_apoderado })) cargarApoderados();
    }
}