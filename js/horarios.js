async function inicializarHorarios() {
    await cargarCombosHorario();
    await cargarCombosGeneradorHorario();
    await cargarHorarios();
    if (window.USUARIO_ACTUAL && Number(window.USUARIO_ACTUAL.rol) !== 1) {
        const gen = document.getElementById('card-generador-horarios');
        if (gen) gen.style.display = 'none';
    }
}

let horarioGeneradoPreview = null;
const DIAS_HORARIO = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];
const REFRIGERIO_HORARIO = {
    es_refrigerio: true,
    dia_semana: '',
    hora_inicio: '11:15:00',
    hora_fin: '11:45:00',
    curso: 'Refrigerio',
    docente: '-',
    aula: '-',
    nombre_aula: '-'
};

async function cargarCombosHorario() {
    const result = await cargarDatos('horarios', 'combo');
    if (!result || !result.success) return;
    const curso = document.getElementById('horario-curso-docente');
    const aula = document.getElementById('horario-aula');
    const grado = document.getElementById('horario-grado');
    const seccion = document.getElementById('horario-seccion');
    curso.innerHTML = '<option value="">Seleccione curso</option>';
    result.cursos.forEach(c => curso.innerHTML += `<option value="${c.id_curso_docente}">${c.curso} - ${c.docente}</option>`);
    aula.innerHTML = '<option value="">Seleccione aula</option>';
    result.aulas.forEach(a => aula.innerHTML += `<option value="${a.id_aula}">${a.nombre_aula}</option>`);
    grado.innerHTML = '<option value="">Seleccione grado</option>';
    result.grados.forEach(g => grado.innerHTML += `<option value="${g.id_grado}">${g.nombre}</option>`);
    seccion.innerHTML = '<option value="">Seleccione seccion</option>';
    result.secciones.forEach(s => seccion.innerHTML += `<option value="${s.id_seccion}">${s.nombre}</option>`);
    grado.onchange = descartarHorarioGenerado;
    seccion.onchange = descartarHorarioGenerado;
}

async function cargarCombosGeneradorHorario() {
    const result = await cargarDatos('horario_generador', 'combo');
    if (!result || !result.success) return;
    const grado = document.getElementById('gen-horario-grado');
    const seccion = document.getElementById('gen-horario-seccion');
    if (!grado || !seccion) return;
    grado.innerHTML = '<option value="">Seleccione grado</option>';
    result.grados.forEach(g => grado.innerHTML += `<option value="${g.id_grado}">${g.nombre}</option>`);
    seccion.innerHTML = '<option value="">Seleccione seccion</option>';
    result.secciones.forEach(s => seccion.innerHTML += `<option value="${s.id_seccion}">${s.nombre}</option>`);
}

async function generarHorarioAutomatico(regenerar = false) {
    const id_grado = document.getElementById('gen-horario-grado').value;
    const id_seccion = document.getElementById('gen-horario-seccion').value;
    if (!id_grado || !id_seccion) return alert('Seleccione grado y seccion.');

    const response = await fetch('./api/horario_generador.php?action=preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_grado, id_seccion, regenerar })
    });
    const result = await response.json();
    if (!result.success) return alert('Error: ' + (result.error || result.message));

    horarioGeneradoPreview = {
        id_grado,
        id_seccion,
        bloques: result.bloques,
        logs: result.logs || [],
        estudiantes_asignados: result.estudiantes_asignados || 0
    };
    renderPreviewHorario(result.bloques, result.logs || [], horarioGeneradoPreview.estudiantes_asignados);
}

function renderPreviewHorario(bloques, logs, estudiantesAsignados = 0) {
    const wrap = document.getElementById('preview-horario-wrap');
    const tbody = document.querySelector('#tabla-preview-horario tbody');
    const logsBox = document.getElementById('horario-generador-logs');
    const btnGuardar = document.getElementById('btn-guardar-horario-generado');
    const btnDescartar = document.getElementById('btn-descartar-horario-generado');
    wrap.style.display = 'block';
    btnGuardar.style.display = 'inline-flex';
    if (btnDescartar) btnDescartar.style.display = 'inline-flex';
    tbody.innerHTML = '';
    incluirRefrigerio(bloques).forEach(b => {
        const estilo = b.es_refrigerio ? ' style="background:#fef3c7;font-weight:600;"' : '';
        tbody.innerHTML += `<tr${estilo}><td>${b.dia_semana}</td><td>${b.hora_inicio} - ${b.hora_fin}</td><td>${b.curso}</td><td>${b.docente}</td><td>${b.aula}</td></tr>`;
    });
    const asignacion = `<div style="color:var(--ok)">Este horario se asignara automaticamente a ${estudiantesAsignados} estudiante(s) activos de la seccion seleccionada.</div>`;
    logsBox.innerHTML = asignacion + (logs.length ? logs.map(l => `<div>${l}</div>`).join('') : '<span style="color:var(--ok)">Vista previa generada sin conflictos criticos.</span>');
}

async function guardarHorarioGenerado() {
    if (!horarioGeneradoPreview || !horarioGeneradoPreview.bloques.length) return alert('Primero genere una vista previa.');
    if (!confirm('Guardar este horario reemplazando el horario actual de la seccion seleccionada?')) return;

    const response = await fetch('./api/horario_generador.php?action=guardar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(horarioGeneradoPreview)
    });
    const result = await response.json();
    if (result.success) {
        alert(result.message);
        descartarHorarioGenerado();
        cargarHorarios();
    } else {
        alert('Error: ' + (result.error || result.message));
    }
}

async function revertirHorarioSeccion() {
    const id_grado = document.getElementById('gen-horario-grado').value;
    const id_seccion = document.getElementById('gen-horario-seccion').value;
    if (!id_grado || !id_seccion) return alert('Seleccione grado y seccion.');
    if (!confirm('Revertir el horario guardado de esta seccion? Si no existe una generacion anterior, se eliminara el horario actual de esa seccion.')) return;

    const response = await fetch('./api/horario_generador.php?action=revertir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_grado, id_seccion })
    });
    const result = await response.json();
    if (result.success) {
        alert(result.message);
        descartarHorarioGenerado();
        cargarHorarios();
    } else {
        alert('Error: ' + (result.error || result.message));
    }
}

function descartarHorarioGenerado() {
    horarioGeneradoPreview = null;
    const wrap = document.getElementById('preview-horario-wrap');
    const tbody = document.querySelector('#tabla-preview-horario tbody');
    const logsBox = document.getElementById('horario-generador-logs');
    const btnGuardar = document.getElementById('btn-guardar-horario-generado');
    const btnDescartar = document.getElementById('btn-descartar-horario-generado');
    if (wrap) wrap.style.display = 'none';
    if (tbody) tbody.innerHTML = '';
    if (logsBox) logsBox.innerHTML = '';
    if (btnGuardar) btnGuardar.style.display = 'none';
    if (btnDescartar) btnDescartar.style.display = 'none';
}

async function cargarHorarios() {
    const respuesta = await cargarDatos('horarios', 'obtener');
    if (!respuesta || !respuesta.data) return;
    const horarios = respuesta.data;
    const tbody = document.querySelector('#tabla-horarios tbody');
    tbody.innerHTML = '';
    incluirRefrigerio(horarios).forEach(h => {
        if (h.es_refrigerio) {
            tbody.innerHTML += `<tr style="background:#fef3c7;font-weight:600;">
                <td>${h.dia_semana}</td><td>${h.hora_inicio} - ${h.hora_fin}</td><td>-</td><td>Refrigerio</td><td>-</td><td>-</td><td>-</td>
            </tr>`;
            return;
        }
        const data = JSON.stringify(h).replace(/'/g, '&apos;');
        tbody.innerHTML += `<tr>
            <td>${h.dia_semana}</td><td>${h.hora_inicio} - ${h.hora_fin}</td><td>${h.grado || '-'} ${h.seccion || ''}</td><td>${h.curso}</td><td>${h.docente}</td><td>${h.nombre_aula}</td>
            <td style="display:flex; gap:5px;"><button class="btn btn-secondary btn-sm" onclick='editarHorario(${data})'>Editar</button><button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarHorario(${h.id_horario})">Eliminar</button></td>
        </tr>`;
    });
}

function incluirRefrigerio(bloques) {
    const salida = [...bloques];
    DIAS_HORARIO.forEach(dia => {
        salida.push({ ...REFRIGERIO_HORARIO, dia_semana: dia });
    });
    return salida.sort((a, b) => {
        const diaA = DIAS_HORARIO.indexOf(a.dia_semana);
        const diaB = DIAS_HORARIO.indexOf(b.dia_semana);
        if (diaA !== diaB) return diaA - diaB;
        return String(a.hora_inicio).localeCompare(String(b.hora_inicio));
    });
}

function editarHorario(h) {
    document.getElementById('horario-id').value = h.id_horario;
    document.getElementById('horario-curso-docente').value = h.id_curso_docente;
    document.getElementById('horario-aula').value = h.id_aula;
    document.getElementById('horario-grado').value = h.id_grado || '';
    document.getElementById('horario-seccion').value = h.id_seccion || '';
    document.getElementById('horario-dia').value = h.dia_semana;
    document.getElementById('horario-inicio').value = h.hora_inicio;
    document.getElementById('horario-fin').value = h.hora_fin;
}

async function guardarHorario() {
    const id = document.getElementById('horario-id').value;
    const data = {
        id_curso_docente: document.getElementById('horario-curso-docente').value,
        id_aula: document.getElementById('horario-aula').value,
        id_grado: document.getElementById('horario-grado').value,
        id_seccion: document.getElementById('horario-seccion').value,
        dia_semana: document.getElementById('horario-dia').value,
        hora_inicio: document.getElementById('horario-inicio').value,
        hora_fin: document.getElementById('horario-fin').value
    };
    if (id) data.id_horario = id;
    if (!data.id_curso_docente || !data.id_aula || !data.id_grado || !data.id_seccion || !data.dia_semana || !data.hora_inicio || !data.hora_fin) return alert('Completa todos los campos.');
    if (await guardarDatos('horarios', id ? 'actualizar' : 'crear', data)) {
        limpiarFormularioHorario();
        cargarHorarios();
    }
}

function limpiarFormularioHorario() {
    document.querySelectorAll('#horario-id, #horario-curso-docente, #horario-aula, #horario-grado, #horario-seccion, #horario-inicio, #horario-fin').forEach(el => el.value = '');
    document.getElementById('horario-dia').value = 'Lunes';
}

async function eliminarHorario(id_horario) {
    if (confirm('Eliminar este horario?') && await guardarDatos('horarios', 'eliminar', { id_horario })) cargarHorarios();
}
