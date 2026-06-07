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
        if(data.success) {
            matriculasActuales = data.data;
            renderizarTablaMatriculas(matriculasActuales);
        }
    } catch (err) {
        console.error("Error cargando matrículas:", err);
    }
}

function renderizarTablaMatriculas(matriculas) {
    const tbody = document.querySelector('#tabla-matriculas tbody');
    tbody.innerHTML = '';
    
    if(matriculas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--muted)">No hay matrículas registradas</td></tr>';
        return;
    }
    
    matriculas.forEach(mat => {
        let badgeClass = 'tag-gray';
        let estadoTexto = 'Desconocido';
        
        // Asumiendo: 1 = Activa, 2 = Inactiva, 3 = Retirado
        if(mat.id_estado_matricula == 1) { badgeClass = 'tag-green'; estadoTexto = 'ACTIVA'; }
        if(mat.id_estado_matricula == 2) { badgeClass = 'tag-amber'; estadoTexto = 'INACTIVA'; }
        if(mat.id_estado_matricula == 3) { badgeClass = 'tag-red'; estadoTexto = 'RETIRADO'; }
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><span style="font-weight:600">${mat.estudiante}</span></td>
            <td>${mat.dni}</td>
            <td>Vacante #${mat.id_vacante || '-'}</td>
            <td>${mat.fecha_matricula}</td>
            <td><span class="tag ${badgeClass}">${estadoTexto}</span></td>
            <td>
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
        if(data.success) {
            const select = document.getElementById('matricula_estudiante');
            select.innerHTML = '<option value="">-- Seleccione un Estudiante --</option>';
            data.estudiantes.forEach(e => {
                select.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre} (${e.dni})</option>`;
            });
        }
    } catch(e) { console.error(e); }
}

async function cargarComboVacantesMatricula() {
    try {
        const res = await fetch('./api/matriculas.php?action=combo_vacantes');
        const data = await res.json();
        if(data.success) {
            const select = document.getElementById('matricula_vacante');
            select.innerHTML = '<option value="">-- Seleccione Vacante --</option>';
            data.vacantes.forEach(v => {
                select.innerHTML += `<option value="${v.id_vacante}">Vacante #${v.id_vacante}</option>`;
            });
        }
    } catch(e) { console.error(e); }
}

async function cargarComboEstadosMatricula() {
    try {
        const res = await fetch('./api/matriculas.php?action=combo_estados');
        const data = await res.json();
        if(data.success) {
            const select = document.getElementById('matricula_estado');
            select.innerHTML = '<option value="">-- Seleccione Estado --</option>';
            data.estados.forEach(e => {
                select.innerHTML += `<option value="${e.id_estado_matricula}">${e.nombre || e.descripcion || e.estado || 'Estado ' + e.id_estado_matricula}</option>`;
            });
        }
    } catch(e) { console.error(e); }
}

function mostrarFormularioMatricula() {
    document.getElementById('vista-lista-matriculas').style.display = 'none';
    document.getElementById('vista-form-matriculas').style.display = 'block';
    document.getElementById('form-matricula').reset();
    document.getElementById('matricula_id').value = '';
    document.getElementById('matricula_fecha').value = new Date().toISOString().split('T')[0]; // Fecha actual por defecto
    document.getElementById('titulo-form-matricula').textContent = 'Nueva Matrícula';
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
    
    if(id) data.id_matricula = id;
    
    try {
        const res = await fetch('./api/matriculas.php?action=' + (id ? 'actualizar' : 'crear'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if(result.success) {
            ocultarFormularioMatricula();
            cargarMatriculas();
        } else {
            alert('Error: ' + (result.error || result.message));
        }
    } catch(e) { console.error(e); }
}

function editarMatricula(id) {
    const mat = matriculasActuales.find(m => m.id_matricula == id);
    if(!mat) return;
    
    mostrarFormularioMatricula();
    document.getElementById('titulo-form-matricula').textContent = 'Editar Matrícula';
    document.getElementById('matricula_id').value = mat.id_matricula;
    document.getElementById('matricula_estudiante').value = mat.id_estudiante;
    document.getElementById('matricula_vacante').value = mat.id_vacante;
    document.getElementById('matricula_fecha').value = mat.fecha_matricula;
    document.getElementById('matricula_estado').value = mat.id_estado_matricula;
}

async function eliminarMatricula(id) {
    if(!confirm('¿Estás seguro de eliminar esta matrícula? Si tiene pagos asociados, dará error a menos que los borres primero.')) return;
    try {
        const res = await fetch('./api/matriculas.php?action=eliminar', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_matricula: id})
        });
        const result = await res.json();
        if(result.success) cargarMatriculas();
        else alert(result.error || result.message);
    } catch(e) { console.error(e); }
}
