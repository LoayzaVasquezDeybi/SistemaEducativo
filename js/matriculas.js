let matriculasActuales = [];

function inicializarMatricula() {
    cargarMatriculas();
    cargarComboEstudiantesMatricula();
    cargarComboVacantesMatricula();
    cargarComboEstadosMatricula();
}

async function cargarMatriculas() {
    try {
        const res = await fetch('./api/matriculas.php?action=obtener');
        const data = await res.json();
        if (data.success) {
            matriculasActuales = data.data;
            renderizarTablaMatriculas(matriculasActuales);
        }
    } catch (err) {
        console.error('Error cargando matriculas:', err);
    }
}

function renderizarTablaMatriculas(matriculas) {
    const tbody = document.querySelector('#tabla-matriculas tbody');
    tbody.innerHTML = '';

    if (matriculas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--muted)">No hay solicitudes de matricula registradas</td></tr>';
        return;
    }

    matriculas.forEach(mat => {
        const estadoTexto = (mat.estado_matricula || 'Desconocido').toUpperCase();
        let badgeClass = 'tag-gray';
        if (estadoTexto.includes('ACTIVO')) badgeClass = 'tag-green';
        else if (estadoTexto.includes('PENDIENTE')) badgeClass = 'tag-amber';
        else if (estadoTexto.includes('RETIRADO') || estadoTexto.includes('INACTIVO')) badgeClass = 'tag-red';

        const pagoRealizado = mat.pago_matricula_realizado == 1;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><span style="font-weight:600">${mat.estudiante}</span><br><small style="color:var(--muted)">Estado estudiante: ${mat.estado_estudiante || '-'}</small></td>
            <td>${mat.dni || 'S/D'}</td>
            <td>Vacante #${mat.id_vacante || '-'}</td>
            <td>${mat.fecha_matricula}</td>
            <td><span class="tag ${badgeClass}">${estadoTexto}</span></td>
            <td><span class="tag ${pagoRealizado ? 'tag-green' : 'tag-amber'}">${pagoRealizado ? 'Pagado' : 'Pendiente'}</span></td>
            <td style="display:flex; gap:5px;">
                <button class="btn btn-sm btn-secondary" onclick="editarMatricula(${mat.id_matricula})">Editar</button>
                <button class="btn btn-sm btn-danger" onclick="eliminarMatricula(${mat.id_matricula})">Borrar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function cargarComboEstudiantesMatricula() {
    try {
        const res = await fetch('./api/matriculas.php?action=combo_estudiantes');
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('matricula_estudiante');
            select.innerHTML = '<option value="">-- Seleccione estudiante --</option>';
            data.estudiantes.forEach(e => {
                select.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre} (${e.dni || 'S/D'}) - ${e.estado}</option>`;
            });
        }
    } catch (e) {
        console.error(e);
    }
}

async function cargarComboVacantesMatricula() {
    try {
        const res = await fetch('./api/matriculas.php?action=combo_vacantes');
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('matricula_vacante');
            select.innerHTML = '<option value="">-- Seleccione vacante --</option>';
            data.vacantes.forEach(v => {
                const disponibles = v.vacantes_disponibles ?? '-';
                select.innerHTML += `<option value="${v.id_vacante}">Vacante #${v.id_vacante} (${disponibles} disponibles)</option>`;
            });
        }
    } catch (e) {
        console.error(e);
    }
}

async function cargarComboEstadosMatricula() {
    try {
        const res = await fetch('./api/matriculas.php?action=combo_estados');
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('matricula_estado');
            select.innerHTML = '';
            data.estados.forEach(e => {
                select.innerHTML += `<option value="${e.id_estado_matricula}">${e.nombre}</option>`;
            });
            const pendiente = data.estados.find(e => String(e.nombre).toLowerCase().includes('pendiente'));
            if (pendiente) select.value = pendiente.id_estado_matricula;
        }
    } catch (e) {
        console.error(e);
    }
}

function mostrarFormularioMatricula() {
    document.getElementById('vista-lista-matriculas').style.display = 'none';
    document.getElementById('vista-form-matriculas').style.display = 'block';
    document.getElementById('form-matricula').reset();
    document.getElementById('matricula_id').value = '';
    document.getElementById('matricula_fecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('titulo-form-matricula').textContent = 'Nueva solicitud de matricula';
    cargarComboEstadosMatricula();
}

function ocultarFormularioMatricula() {
    document.getElementById('vista-lista-matriculas').style.display = 'block';
    document.getElementById('vista-form-matriculas').style.display = 'none';
}

async function guardarMatricula() {
    const id = document.getElementById('matricula_id').value;
    const data = {
        id_estudiante: document.getElementById('matricula_estudiante').value,
        id_vacante: document.getElementById('matricula_vacante').value,
        fecha_matricula: document.getElementById('matricula_fecha').value,
        id_estado_matricula: document.getElementById('matricula_estado').value
    };
    if (id) data.id_matricula = id;
    if (!data.id_estudiante || !data.id_vacante || !data.fecha_matricula) {
        alert('Completa estudiante, vacante y fecha.');
        return;
    }

    try {
        const res = await fetch('./api/matriculas.php?action=' + (id ? 'actualizar' : 'crear'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            alert(result.message || 'Solicitud registrada. Ahora registre el pago de matricula en el modulo Pagos.');
            ocultarFormularioMatricula();
            cargarMatriculas();
        } else {
            alert('Error: ' + (result.error || result.message));
        }
    } catch (e) {
        console.error(e);
    }
}

function editarMatricula(id) {
    const mat = matriculasActuales.find(m => m.id_matricula == id);
    if (!mat) return;

    mostrarFormularioMatricula();
    document.getElementById('titulo-form-matricula').textContent = 'Editar solicitud de matricula';
    document.getElementById('matricula_id').value = mat.id_matricula;
    document.getElementById('matricula_estudiante').value = mat.id_estudiante;
    document.getElementById('matricula_vacante').value = mat.id_vacante;
    document.getElementById('matricula_fecha').value = mat.fecha_matricula;
    document.getElementById('matricula_estado').value = mat.id_estado_matricula;
}

async function eliminarMatricula(id) {
    if (!confirm('Eliminar esta solicitud de matricula y sus pagos asociados?')) return;
    try {
        const res = await fetch('./api/matriculas.php?action=eliminar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_matricula: id })
        });
        const result = await res.json();
        if (result.success) cargarMatriculas();
        else alert(result.error || result.message);
    } catch (e) {
        console.error(e);
    }
}
