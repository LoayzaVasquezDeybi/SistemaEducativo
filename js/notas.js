let notasActuales = [];
let notasReadonly = false;

async function inicializarNotas() {
    if (window.ES_DOCENTE) {
        document.getElementById('card-planilla-notas').style.display = 'block';
        document.getElementById('card-form-nota').style.display = 'none';
        document.getElementById('card-listado-notas').style.display = 'none';
        await inicializarPlanillaNotas();
        return;
    }
    document.getElementById('card-planilla-notas').style.display = 'none';
    await cargarCombosNotas();
    await cargarNotas();
    aplicarPermisosNotas();

    const btnExportarPdf = document.getElementById('btn-exportar-notas-pdf');
    if (btnExportarPdf) {
        btnExportarPdf.addEventListener('click', () => {
            window.open('api/exportar_notas.php', '_blank');
        });
    }
}

let asignacionesPlanilla = [];
let evaluacionesPlanilla = [];

async function inicializarPlanillaNotas() {
    const r = await cargarDatos('notas', 'planilla_asignaciones');
    if (!r || !r.success) return;
    asignacionesPlanilla = r.asignaciones || [];
    evaluacionesPlanilla = r.evaluaciones || [];
    const asignacion = document.getElementById('planilla-asignacion');
    const periodo = document.getElementById('planilla-periodo');
    asignacion.innerHTML = '<option value="">Seleccione curso, grado y sección</option>';
    asignacionesPlanilla.forEach((a,i) => asignacion.innerHTML += `<option value="${i}">${a.curso} · ${a.grado} · Sección ${a.seccion}</option>`);
    periodo.innerHTML = '<option value="">Seleccione periodo</option>';
    (r.periodos || []).forEach(p => periodo.innerHTML += `<option value="${p.id_periodo_evaluacion}">${p.nombre}</option>`);
    if (!asignacionesPlanilla.length) {
        document.querySelector('#tabla-planilla-notas tbody').innerHTML = '<tr><td style="text-align:center;color:var(--muted)">No tiene cursos con grado y sección asignados en el horario.</td></tr>';
    }
}

async function cargarPlanillaNotas() {
    const indice = document.getElementById('planilla-asignacion').value;
    const periodo = document.getElementById('planilla-periodo').value;
    if (indice === '' || !periodo) return alert('Seleccione el curso asignado y el periodo de evaluación.');
    const a = asignacionesPlanilla[Number(indice)];
    const params = new URLSearchParams({action:'planilla_obtener',id_curso_docente:a.id_curso_docente,id_grado:a.id_grado,id_seccion:a.id_seccion,id_periodo_evaluacion:periodo});
    const response = await fetch(`./api/notas.php?${params}`);
    const r = await response.json();
    if (!r.success) return alert('Error: ' + (r.error || r.message));
    document.getElementById('estado-planilla-notas').textContent = 'Planilla cargada. Puede registrar notas nuevas o modificar las existentes.';
    document.getElementById('btn-guardar-planilla').textContent = 'Guardar cambios de la planilla';
    evaluacionesPlanilla = r.evaluaciones;
    const etiquetas = evaluacionesPlanilla.map((_,i) => i<10 ? `E${i+1}` : i<12 ? `PC${i-9}` : 'EF');
    document.querySelector('#tabla-planilla-notas thead').innerHTML = `<tr><th style="min-width:190px">Estudiante</th>${etiquetas.map((e,i)=>`<th title="${evaluacionesPlanilla[i]}">${e}</th>`).join('')}<th>Prom.</th></tr>`;
    const tbody=document.querySelector('#tabla-planilla-notas tbody');
    tbody.innerHTML='';
    r.estudiantes.forEach(e => {
        const notas=r.notas[e.id_estudiante] || {};
        tbody.innerHTML += `<tr data-estudiante="${e.id_estudiante}"><td><strong>${e.apellido}, ${e.nombre}</strong><br><small style="color:var(--muted)">${e.codigo_estudiante} · DNI ${e.dni || 'S/D'}</small></td>${evaluacionesPlanilla.map(nombre=>`<td><input class="nota-planilla" data-evaluacion="${nombre}" type="number" min="0" max="20" step="0.01" value="${notas[nombre] ?? ''}" oninput="actualizarPromedioPlanilla(this)" style="width:58px;padding:6px"></td>`).join('')}<td class="promedio-planilla">—</td></tr>`;
    });
    if (!r.estudiantes.length) tbody.innerHTML='<tr><td colspan="15" style="text-align:center;color:var(--muted)">No hay estudiantes activos en este grado y sección.</td></tr>';
    tbody.querySelectorAll('tr[data-estudiante] .nota-planilla:first-of-type').forEach(input => actualizarPromedioPlanilla(input, false));
}

function actualizarPromedioPlanilla(input, marcarCambio = true) {
    if (marcarCambio) {
        input.style.borderColor = '#f59e0b';
        input.dataset.modificada = 'true';
        document.getElementById('estado-planilla-notas').textContent = 'Hay cambios pendientes de guardar.';
    }
    const fila=input.closest('tr');
    const valores=Array.from(fila.querySelectorAll('.nota-planilla')).map(i=>i.value).filter(v=>v!=='').map(Number);
    const celda=fila.querySelector('.promedio-planilla');
    if (!valores.length) { celda.textContent='—'; celda.className='promedio-planilla'; return; }
    const promedio=valores.reduce((a,b)=>a+b,0)/valores.length;
    celda.textContent=promedio.toFixed(2);
    celda.className='promedio-planilla ' + (promedio>=11?'tag-green':'tag-red');
}

async function guardarPlanillaNotas() {
    const indice=document.getElementById('planilla-asignacion').value;
    const periodo=document.getElementById('planilla-periodo').value;
    if (indice==='' || !periodo) return alert('Seleccione y cargue una planilla.');
    const filas=Array.from(document.querySelectorAll('#tabla-planilla-notas tbody tr[data-estudiante]'));
    if (!filas.length) return alert('Primero cargue la lista de estudiantes.');
    const a=asignacionesPlanilla[Number(indice)];
    const calificaciones=filas.map(fila => ({id_estudiante:fila.dataset.estudiante,notas:Object.fromEntries(Array.from(fila.querySelectorAll('.nota-planilla')).map(i=>[i.dataset.evaluacion,i.value]))}));
    const data={id_curso_docente:a.id_curso_docente,id_grado:a.id_grado,id_seccion:a.id_seccion,id_periodo_evaluacion:periodo,calificaciones};
    if (await guardarDatos('notas','planilla_guardar',data)) {
        document.getElementById('estado-planilla-notas').textContent = 'Las calificaciones se guardaron correctamente.';
        await cargarPlanillaNotas();
    }
}

async function cargarCombosNotas() {
    const result = await cargarDatos('notas', 'combo');
    if (!result || !result.success) return;

    const selEst = document.getElementById('nota-estudiante');
    const selCurso = document.getElementById('nota-curso-docente');
    const selPeriodo = document.getElementById('nota-periodo');

    selEst.innerHTML = '<option value="">Seleccione estudiante</option>';
    result.estudiantes.forEach(e => selEst.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre} (${e.dni || 'S/D'})</option>`);

    const selLibreta = document.getElementById('libreta-estudiante');
    if (selLibreta) {
        selLibreta.innerHTML = '<option value="">Seleccione estudiante</option>';
        result.estudiantes.forEach(e => selLibreta.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre} (${e.dni || 'S/D'})</option>`);
    }

    selCurso.innerHTML = '<option value="">Seleccione curso</option>';
    result.cursos.forEach(c => selCurso.innerHTML += `<option value="${c.id_curso_docente}">${c.curso} - ${c.docente}</option>`);

    selPeriodo.innerHTML = '<option value="">Seleccione periodo</option>';
    result.periodos.forEach(p => selPeriodo.innerHTML += `<option value="${p.id_periodo_evaluacion}">${p.nombre}</option>`);
}

async function cargarNotas() {
    const respuesta = await cargarDatos('notas', 'obtener');
    if (!respuesta) return;

    const notas = respuesta.data || [];
    notasReadonly = !!respuesta.readonly;
    notasActuales = notas;
    const tbody = document.querySelector('#tabla-notas tbody');
    tbody.innerHTML = '';
    notas.forEach(n => {
        const data = JSON.stringify(n).replace(/'/g, '&apos;');
        const estado = Number(n.calificacion) >= 11 ? 'green' : 'red';
        const puedeEliminar = window.USUARIO_ACTUAL && Number(window.USUARIO_ACTUAL.rol) === 1;
        const acciones = notasReadonly
            ? '-'
            : `<button class="btn btn-secondary btn-sm" onclick='editarNota(${data})'>Editar</button>
               ${puedeEliminar ? `<button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarNota(${n.id_nota})">Eliminar</button>` : ''}`;
        tbody.innerHTML += `
            <tr>
                <td>${n.estudiante}</td>
                <td>${n.curso}</td>
                <td>${n.periodo_evaluacion}</td>
                <td>${n.evaluacion}</td>
                <td><span class="tag tag-${estado}">${n.calificacion}</span></td>
                <td>${n.docente}</td>
                <td style="display:flex; gap:5px;">
                    ${acciones}
                </td>
            </tr>`;
    });
    aplicarPermisosNotas();
}

function editarNota(n) {
    document.getElementById('nota-id').value = n.id_nota;
    document.getElementById('nota-estudiante').value = n.id_estudiante;
    document.getElementById('nota-curso-docente').value = n.id_curso_docente;
    document.getElementById('nota-periodo').value = n.id_periodo_evaluacion;
    document.getElementById('nota-evaluacion').value = n.evaluacion;
    document.getElementById('nota-calificacion').value = n.calificacion;
}

async function guardarNota() {
    const data = {
        id_estudiante: document.getElementById('nota-estudiante').value,
        id_curso_docente: document.getElementById('nota-curso-docente').value,
        id_periodo_evaluacion: document.getElementById('nota-periodo').value,
        evaluacion: document.getElementById('nota-evaluacion').value.trim(),
        calificacion: document.getElementById('nota-calificacion').value
    };
    const id = document.getElementById('nota-id').value;
    if (id) data.id_nota = id;
    if (!data.id_estudiante || !data.id_curso_docente || !data.id_periodo_evaluacion || !data.evaluacion || data.calificacion === '') {
        alert('Completa todos los campos de la nota.');
        return;
    }
    if (await guardarDatos('notas', id ? 'actualizar' : 'crear', data)) {
        limpiarFormularioNota();
        cargarNotas();
    }
}

function limpiarFormularioNota() {
    document.querySelectorAll('#nota-id, #nota-estudiante, #nota-curso-docente, #nota-periodo, #nota-evaluacion, #nota-calificacion').forEach(el => el.value = '');
}

async function eliminarNota(id_nota) {
    if (confirm('Eliminar esta nota?') && await guardarDatos('notas', 'eliminar', { id_nota })) cargarNotas();
}

function aplicarPermisosNotas() {
    const form = document.getElementById('card-form-nota');
    if (form && (notasReadonly || (window.USUARIO_ACTUAL && Number(window.USUARIO_ACTUAL.rol) === 3))) {
        form.style.display = 'none';
    }
    const libretas = document.getElementById('card-libretas');
    if (libretas) libretas.style.display = window.ES_DOCENTE ? 'block' : 'none';
}

function descargarLibreta() {
    if (!window.ES_DOCENTE) return alert('Solo los profesores pueden descargar libretas de notas.');
    const idEstudiante = document.getElementById('libreta-estudiante').value;
    if (!idEstudiante) return alert('Seleccione un estudiante.');
    window.open(`api/libreta_notas.php?id_estudiante=${encodeURIComponent(idEstudiante)}`, '_blank', 'noopener');
}
