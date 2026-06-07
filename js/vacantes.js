let vacantesActuales = [];

function inicializarVacantes() {
    cargarVacantes();
    cargarCombosVacantes();
}

async function cargarVacantes() {
    try {
        const res = await fetch('./api/vacantes.php?action=obtener');
        const data = await res.json();
        if(data.success) {
            vacantesActuales = data.data;
            renderizarTablaVacantes(vacantesActuales);
        }
    } catch (err) {
        console.error("Error cargando vacantes:", err);
    }
}

function renderizarTablaVacantes(vacantes) {
    const tbody = document.querySelector('#tabla-vacantes tbody');
    tbody.innerHTML = '';
    
    if(vacantes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--muted)">No hay vacantes registradas</td></tr>';
        return;
    }
    
    vacantes.forEach(v => {
        // Coloreamos en verde si hay más de 0, en rojo si es 0
        let badgeClass = v.vacantes_disponibles > 0 ? 'tag-green' : 'tag-red';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>#${v.id_vacante}</strong></td>
            <td>${v.periodo_nombre || v.id_periodo || '-'}</td>
            <td>${v.grado_nombre || v.id_grado || '-'}</td>
            <td>${v.seccion_nombre || v.id_seccion || '-'}</td>
            <td>${v.total_vacantes || '0'}</td>
            <td><span class="tag ${badgeClass}">${v.vacantes_disponibles || '0'}</span></td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="editarVacante(${v.id_vacante})">Editar</button>
                <button class="btn btn-sm btn-danger" onclick="eliminarVacante(${v.id_vacante})">Borrar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function cargarCombosVacantes() {
    try {
        const res = await fetch('./api/vacantes.php?action=combo');
        const data = await res.json();
        if(data.success) {
            const selPeriodo = document.getElementById('vacante_periodo');
            const selGrado = document.getElementById('vacante_grado');
            const selSeccion = document.getElementById('vacante_seccion');
            
            if(selPeriodo) {
                selPeriodo.innerHTML = '<option value="">-- Periodo --</option>';
                (data.periodos || []).forEach(p => selPeriodo.innerHTML += `<option value="${p.id_periodo}">${p.nombre}</option>`);
            }
            if(selGrado) {
                selGrado.innerHTML = '<option value="">-- Grado --</option>';
                (data.grados || []).forEach(g => selGrado.innerHTML += `<option value="${g.id_grado}">${g.nombre}</option>`);
            }
            if(selSeccion) {
                selSeccion.innerHTML = '<option value="">-- Sección --</option>';
                (data.secciones || []).forEach(s => selSeccion.innerHTML += `<option value="${s.id_seccion}">${s.nombre}</option>`);
            }
        }
    } catch(e) { console.error(e); }
}

function mostrarFormularioVacante() {
    document.getElementById('vista-lista-vacantes').style.display = 'none';
    document.getElementById('vista-form-vacantes').style.display = 'block';
    document.getElementById('form-vacante').reset();
    document.getElementById('vacante_id').value = '';
    document.getElementById('titulo-form-vacante').textContent = 'Registrar Nueva Vacante';
}

function ocultarFormularioVacante() {
    document.getElementById('vista-lista-vacantes').style.display = 'block';
    document.getElementById('vista-form-vacantes').style.display = 'none';
}

async function guardarVacante() {
    const id = document.getElementById('vacante_id').value;
    const data = {
        id_periodo: document.getElementById('vacante_periodo').value,
        id_grado: document.getElementById('vacante_grado').value,
        id_seccion: document.getElementById('vacante_seccion').value,
        total_vacantes: document.getElementById('vacante_total').value,
        vacantes_disponibles: document.getElementById('vacante_disponibles').value
    };
    
    if(id) data.id_vacante = id;
    
    try {
        const res = await fetch('./api/vacantes.php?action=' + (id ? 'actualizar' : 'crear'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if(result.success) {
            ocultarFormularioVacante();
            cargarVacantes();
        } else {
            alert('Error: ' + (result.error || result.message));
        }
    } catch(e) { console.error(e); }
}

function editarVacante(id) {
    const v = vacantesActuales.find(x => x.id_vacante == id);
    if(!v) return;
    
    mostrarFormularioVacante();
    document.getElementById('titulo-form-vacante').textContent = 'Editar Vacante';
    document.getElementById('vacante_id').value = v.id_vacante;
    document.getElementById('vacante_periodo').value = v.id_periodo;
    document.getElementById('vacante_grado').value = v.id_grado;
    document.getElementById('vacante_seccion').value = v.id_seccion;
    document.getElementById('vacante_total').value = v.total_vacantes;
    document.getElementById('vacante_disponibles').value = v.vacantes_disponibles;
}

async function eliminarVacante(id) {
    if(!confirm('¿Estás seguro de eliminar esta vacante?')) return;
    try {
        const res = await fetch('./api/vacantes.php?action=eliminar', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id_vacante: id})
        });
        const result = await res.json();
        if(result.success) cargarVacantes();
        else alert(result.error || result.message);
    } catch(e) { console.error(e); }
}