let periodosActuales = [];

function inicializarPeriodos() {
    cargarPeriodos();
}

async function cargarPeriodos() {
    try {
        const res = await fetch('./api/periodos.php?action=obtener');
        const data = await res.json();
        if(data.success) {
            periodosActuales = data.data;
            renderizarTablaPeriodos(periodosActuales);
        }
    } catch (err) {
        console.error("Error cargando periodos:", err);
    }
}

function renderizarTablaPeriodos(periodos) {
    const tbody = document.querySelector('#tabla-periodos tbody');
    tbody.innerHTML = '';
    
    if(periodos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--muted)">No hay periodos registrados</td></tr>';
        return;
    }
    
    periodos.forEach(p => {
        let estadoText = (p.estado === 'acti' || p.estado === 'activo') ? 'ACTIVO' : 'INACTIVO';
        let badgeClass = (estadoText === 'ACTIVO') ? 'tag-green' : 'tag-gray';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${p.anio}</strong></td>
            <td>${p.nombre}</td>
            <td>${p.fecha_inicio}</td>
            <td>${p.fecha_fin}</td>
            <td><span class="tag ${badgeClass}">${estadoText}</span></td>
            <td>
                <button class="btn btn-sm btn-secondary" onclick="editarPeriodo(${p.id_periodo})">Editar</button>
                <button class="btn btn-sm btn-danger" onclick="eliminarPeriodo(${p.id_periodo})">Borrar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function mostrarFormularioPeriodo() {
    document.getElementById('vista-lista-periodos').style.display = 'none';
    document.getElementById('vista-form-periodos').style.display = 'block';
    document.getElementById('form-periodo').reset();
    document.getElementById('periodo_id').value = '';
    document.getElementById('titulo-form-periodo').textContent = 'Registrar Nuevo Periodo';
}

function ocultarFormularioPeriodo() {
    document.getElementById('vista-lista-periodos').style.display = 'block';
    document.getElementById('vista-form-periodos').style.display = 'none';
}

async function guardarPeriodo() {
    const id = document.getElementById('periodo_id').value;
    const data = {
        anio: document.getElementById('periodo_anio').value,
        nombre: document.getElementById('periodo_nombre').value,
        fecha_inicio: document.getElementById('periodo_inicio').value,
        fecha_fin: document.getElementById('periodo_fin').value,
        estado: document.getElementById('periodo_estado').value
    };
    if(id) data.id_periodo = id;
    if(await guardarDatos('periodos', id ? 'actualizar' : 'crear', data)) {
        ocultarFormularioPeriodo();
        cargarPeriodos();
    }
}

function editarPeriodo(id) {
    const p = periodosActuales.find(x => x.id_periodo == id);
    if(!p) return;
    mostrarFormularioPeriodo();
    document.getElementById('titulo-form-periodo').textContent = 'Editar Periodo';
    document.getElementById('periodo_id').value = p.id_periodo; document.getElementById('periodo_anio').value = p.anio; document.getElementById('periodo_nombre').value = p.nombre; document.getElementById('periodo_inicio').value = p.fecha_inicio; document.getElementById('periodo_fin').value = p.fecha_fin; document.getElementById('periodo_estado').value = p.estado;
}

async function eliminarPeriodo(id) {
    if(confirm('¿Seguro de eliminar este periodo?')) {
        if(await guardarDatos('periodos', 'eliminar', {id_periodo: id})) cargarPeriodos();
    }
}