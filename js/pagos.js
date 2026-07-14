let pagosActuales = [];

function inicializarPagos() {
    cargarPagos();
    cargarComboMatriculasPagos();
    cargarComboMetodosPagos();
    cargarComboEstadosPagos();
    const form = document.getElementById('form-pago');
    if (form) {
        form.onsubmit = async (e) => {
            e.preventDefault();
            await guardarPago();
        };
    }
}

async function cargarPagos() {
    try {
        const res = await fetch('./api/pagos.php?action=obtener');
        const data = await res.json();
        if (data.success) {
            pagosActuales = data.data;
            renderizarTablaPagos(pagosActuales);
        }
    } catch (err) {
        console.error('Error cargando pagos:', err);
    }
}

function renderizarTablaPagos(pagos) {
    const tbody = document.querySelector('#tabla-pagos tbody');
    tbody.innerHTML = '';

    if (pagos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--muted)">No hay pagos registrados aun</td></tr>';
        return;
    }

    pagos.forEach(pago => {
        let badgeClass = 'tag-gray';
        const estadoStr = String(pago.estado_nombre || pago.id_estado_pago).toUpperCase();
        if (estadoStr.includes('PENDIENTE') || pago.id_estado_pago == 1 || pago.id_estado_pago == 2) badgeClass = 'tag-amber';
        if (estadoStr.includes('PAGADO') || pago.id_estado_pago == 4) badgeClass = 'tag-green';
        if (estadoStr.includes('ANULADO') || estadoStr.includes('CANCELADO') || pago.id_estado_pago == 3) badgeClass = 'tag-red';

        const metodoTexto = pago.metodo_nombre || pago.id_metodo_pago || '-';
        const estadoTexto = pago.estado_nombre || pago.id_estado_pago || 'Desconocido';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><span style="font-weight:600">${pago.estudiante}</span><br><small style="color:var(--muted)">DNI: ${pago.dni} | Matricula: ${pago.estado_matricula || '-'}</small></td>
            <td>${pago.concepto}</td>
            <td style="font-weight:600; color:var(--text)">S/ ${parseFloat(pago.monto).toFixed(2)}</td>
            <td>${pago.fecha_pago}</td>
            <td><small>${metodoTexto}</small></td>
            <td><span class="tag ${badgeClass}">${estadoTexto}</span></td>
            <td style="display:flex; gap:5px;">
                ${pago.id_estado_pago != 4 ? `<button class="btn btn-sm btn-success" onclick="marcarPagado(${pago.id_pago})">Pagar</button>` : ''}
                <button class="btn btn-sm btn-secondary" onclick="editarPago(${pago.id_pago})">Editar</button>
                <button class="btn btn-sm btn-secondary" onclick="generarComprobante(${pago.id_pago})">Comprobante</button>
                <button class="btn btn-sm btn-danger" onclick="eliminarPago(${pago.id_pago})">Borrar</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function cargarComboMatriculasPagos() {
    try {
        const res = await fetch('./api/pagos.php?action=combo_matriculas');
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('pago_matricula');
            select.innerHTML = '<option value="">-- Seleccione matricula --</option>';
            data.matriculas.forEach(m => {
                select.innerHTML += `<option value="${m.id_matricula}">#${m.id_matricula} - ${m.apellido}, ${m.nombre} (${m.dni}) - ${m.estado_matricula || m.estado}</option>`;
            });
        }
    } catch (e) {
        console.error(e);
    }
}

async function cargarComboMetodosPagos() {
    try {
        const res = await fetch('./api/pagos.php?action=combo_metodos');
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('pago_metodo');
            select.innerHTML = '<option value="">-- Seleccione metodo --</option>';
            data.metodos.forEach(m => {
                select.innerHTML += `<option value="${m.id_metodo_pago}">${m.nombre || m.descripcion || 'Metodo ' + m.id_metodo_pago}</option>`;
            });
        }
    } catch (e) {
        console.error(e);
    }
}

async function cargarComboEstadosPagos() {
    try {
        const res = await fetch('./api/pagos.php?action=combo_estados');
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('pago_estado');
            select.innerHTML = '<option value="">-- Seleccione estado --</option>';
            data.estados.forEach(e => {
                select.innerHTML += `<option value="${e.id_estado_pago}">${e.nombre || e.descripcion || 'Estado ' + e.id_estado_pago}</option>`;
            });
            const pendiente = data.estados.find(e => String(e.nombre).toLowerCase().includes('pendiente'));
            if (pendiente) select.value = pendiente.id_estado_pago;
        }
    } catch (e) {
        console.error(e);
    }
}

function mostrarFormularioPago() {
    document.getElementById('vista-lista-pagos').style.display = 'none';
    document.getElementById('vista-form-pagos').style.display = 'block';
    document.getElementById('form-pago').reset();
    document.getElementById('pago_id').value = '';
    document.getElementById('pago_concepto').value = 'Matricula';
    document.getElementById('pago_fecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('titulo-form-pago').textContent = 'Registrar Pago';
    cargarComboEstadosPagos();
}

function ocultarFormularioPago() {
    document.getElementById('vista-lista-pagos').style.display = 'block';
    document.getElementById('vista-form-pagos').style.display = 'none';
}

async function guardarPago() {
    const id = document.getElementById('pago_id').value;
    const data = {
        id_matricula: document.getElementById('pago_matricula').value,
        concepto: document.getElementById('pago_concepto').value,
        monto: document.getElementById('pago_monto').value,
        fecha_pago: document.getElementById('pago_fecha').value,
        id_metodo_pago: document.getElementById('pago_metodo').value,
        id_estado_pago: document.getElementById('pago_estado').value
    };
    if (id) data.id_pago = id;
    if (!data.id_matricula || !data.concepto || !data.monto || !data.fecha_pago || !data.id_metodo_pago || !data.id_estado_pago) {
        alert('Completa todos los datos del pago.');
        return;
    }

    try {
        const res = await fetch('./api/pagos.php?action=' + (id ? 'actualizar' : 'crear'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            alert(result.message || 'Pago guardado correctamente');
            ocultarFormularioPago();
            cargarPagos();
        } else {
            alert('Error: ' + (result.error || result.message));
        }
    } catch (e) {
        console.error(e);
    }
}

function editarPago(id) {
    const pago = pagosActuales.find(p => p.id_pago == id);
    if (!pago) return;

    mostrarFormularioPago();
    document.getElementById('titulo-form-pago').textContent = 'Editar Pago';
    document.getElementById('pago_id').value = pago.id_pago;
    document.getElementById('pago_matricula').value = pago.id_matricula;
    document.getElementById('pago_concepto').value = pago.concepto;
    document.getElementById('pago_monto').value = pago.monto;
    document.getElementById('pago_fecha').value = pago.fecha_pago ? pago.fecha_pago.split(' ')[0] : '';
    document.getElementById('pago_metodo').value = pago.id_metodo_pago;
    document.getElementById('pago_estado').value = pago.id_estado_pago;
}

async function marcarPagado(id) {
    if (!confirm('Confirmar pago? Si el concepto es Matricula, se activara la matricula, la ficha y el estudiante.')) return;
    try {
        const res = await fetch('./api/pagos.php?action=pagar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pago: id })
        });
        const result = await res.json();
        if (result.success) {
            alert(result.message || 'Pago marcado como realizado');
            cargarPagos();
        } else {
            alert(result.error || result.message);
        }
    } catch (e) {
        console.error(e);
    }
}

async function eliminarPago(id) {
    if (!confirm('Eliminar permanentemente este registro de pago?')) return;
    try {
        const res = await fetch('./api/pagos.php?action=eliminar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pago: id })
        });
        const result = await res.json();
        if (result.success) cargarPagos();
        else alert(result.error || result.message);
    } catch (e) {
        console.error(e);
    }
}

function generarComprobante(id) {
    window.open(`api/comprobantes.php?action=generar&id_pago=${id}`, '_blank');
}
