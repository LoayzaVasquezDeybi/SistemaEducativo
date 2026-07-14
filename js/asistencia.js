async function inicializarAsistencia() {
    if (window.ES_DOCENTE) {
        document.getElementById('card-planilla-asistencia').style.display='block';
        document.getElementById('card-form-asistencia').style.display='none';
        document.getElementById('card-historial-asistencia').style.display='none';
        await inicializarPlanillaAsistencia();
        return;
    }
    document.getElementById('card-planilla-asistencia').style.display='none';
    document.getElementById('asis-fecha').value = new Date().toISOString().split('T')[0];
    await cargarCombosAsistencia();
    await cargarAsistencias();

    const btnExportarAsistenciaPdf = document.getElementById('btn-exportar-asistencia-pdf');
    if (btnExportarAsistenciaPdf) {
        btnExportarAsistenciaPdf.addEventListener('click', () => {
            window.open('api/exportar_asistencia.php', '_blank');
        });
    }

    const btnExportarAsistenciaExcel = document.getElementById('btn-exportar-asistencia-excel');
    if (btnExportarAsistenciaExcel) {
        btnExportarAsistenciaExcel.addEventListener('click', () => {
            window.open('api/exportar_asistencia_excel.php', '_blank');
        });
    }
}

let asignacionesAsistencia=[];

async function inicializarPlanillaAsistencia() {
    const fecha=document.getElementById('asistencia-planilla-fecha');
    fecha.value=new Date().toISOString().split('T')[0];
    fecha.max=fecha.value;
    document.getElementById('reporte-asistencia-desde').value=`${new Date().getFullYear()}-01-01`;
    document.getElementById('reporte-asistencia-hasta').value=fecha.value;
    document.getElementById('reporte-asistencia-hasta').max=fecha.value;
    const r=await cargarDatos('asistencia','planilla_asignaciones');
    if (!r || !r.success) return;
    asignacionesAsistencia=r.asignaciones || [];
    const select=document.getElementById('asistencia-asignacion');
    select.innerHTML='<option value="">Seleccione curso, grado y sección</option>';
    asignacionesAsistencia.forEach((a,i)=>select.innerHTML+=`<option value="${i}">${a.curso} · ${a.grado} · Sección ${a.seccion}</option>`);
    if (!asignacionesAsistencia.length) document.querySelector('#tabla-planilla-asistencia tbody').innerHTML='<tr><td style="text-align:center;color:var(--muted)">No tiene cursos con grado y sección asignados en el horario.</td></tr>';
}

async function cargarPlanillaAsistencia() {
    const indice=document.getElementById('asistencia-asignacion').value;
    const fecha=document.getElementById('asistencia-planilla-fecha').value;
    if (indice==='' || !fecha) return alert('Seleccione el curso asignado y la fecha.');
    const a=asignacionesAsistencia[Number(indice)];
    const params=new URLSearchParams({action:'planilla_obtener',id_curso_docente:a.id_curso_docente,id_grado:a.id_grado,id_seccion:a.id_seccion,fecha});
    const response=await fetch(`./api/asistencia.php?${params}`);
    const r=await response.json();
    if (!r.success) return alert('Error: '+(r.error||r.message));
    document.querySelector('#tabla-planilla-asistencia thead').innerHTML='<tr><th style="min-width:220px">Estudiante</th><th>Estado</th><th style="min-width:260px">Observación</th></tr>';
    const tbody=document.querySelector('#tabla-planilla-asistencia tbody');
    tbody.innerHTML='';
    r.estudiantes.forEach(e=>tbody.innerHTML+=`<tr data-estudiante="${e.id_estudiante}"><td><strong>${e.apellido}, ${e.nombre}</strong><br><small style="color:var(--muted)">${e.codigo_estudiante} · DNI ${e.dni||'S/D'}</small></td><td><select class="estado-asistencia-planilla" onchange="marcarCambioAsistencia(this)"><option ${(!e.estado_asistencia||e.estado_asistencia==='Presente')?'selected':''}>Presente</option><option ${e.estado_asistencia==='Tardanza'?'selected':''}>Tardanza</option><option ${e.estado_asistencia==='Falta'?'selected':''}>Falta</option></select></td><td><input class="observacion-asistencia-planilla" type="text" maxlength="255" value="${escaparAsistencia(e.observacion||'')}" placeholder="Opcional" oninput="marcarCambioAsistencia(this)" style="width:100%"></td></tr>`);
    if (!r.estudiantes.length) tbody.innerHTML='<tr><td colspan="3" style="text-align:center;color:var(--muted)">No hay estudiantes activos en este grado y sección.</td></tr>';
    const estudianteReporte=document.getElementById('reporte-asistencia-estudiante');
    estudianteReporte.innerHTML='<option value="">Todos los estudiantes del curso</option>';
    r.estudiantes.forEach(e=>estudianteReporte.innerHTML+=`<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre}</option>`);
    document.getElementById('estado-planilla-asistencia').textContent='Lista cargada en orden alfabético. Puede registrar o modificar la asistencia.';
}

function escaparAsistencia(valor) { return String(valor).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'})[c]); }
function marcarCambioAsistencia(elemento) { elemento.style.borderColor='#f59e0b'; document.getElementById('estado-planilla-asistencia').textContent='Hay cambios pendientes de guardar.'; }

async function guardarPlanillaAsistencia() {
    const indice=document.getElementById('asistencia-asignacion').value;
    const fecha=document.getElementById('asistencia-planilla-fecha').value;
    if (indice==='' || !fecha) return alert('Seleccione y cargue una lista.');
    const filas=Array.from(document.querySelectorAll('#tabla-planilla-asistencia tbody tr[data-estudiante]'));
    if (!filas.length) return alert('Primero cargue la lista de estudiantes.');
    const a=asignacionesAsistencia[Number(indice)];
    const asistencias=filas.map(f=>({id_estudiante:f.dataset.estudiante,estado_asistencia:f.querySelector('.estado-asistencia-planilla').value,observacion:f.querySelector('.observacion-asistencia-planilla').value.trim()}));
    const data={id_curso_docente:a.id_curso_docente,id_grado:a.id_grado,id_seccion:a.id_seccion,fecha,asistencias};
    if (await guardarDatos('asistencia','planilla_guardar',data)) await cargarPlanillaAsistencia();
}

function exportarReporteAsistenciaPDF() {
    const indice=document.getElementById('asistencia-asignacion').value;
    const desde=document.getElementById('reporte-asistencia-desde').value;
    const hasta=document.getElementById('reporte-asistencia-hasta').value;
    if (indice==='' || !desde || !hasta) return alert('Seleccione el curso y el rango de fechas del reporte.');
    if (desde>hasta) return alert('La fecha inicial no puede ser posterior a la fecha final.');
    const a=asignacionesAsistencia[Number(indice)];
    const params=new URLSearchParams({id_curso_docente:a.id_curso_docente,id_grado:a.id_grado,id_seccion:a.id_seccion,desde,hasta});
    const estudiante=document.getElementById('reporte-asistencia-estudiante').value;
    if (estudiante) params.set('id_estudiante',estudiante);
    window.open(`api/reporte_asistencia_curso.php?${params}`,'_blank','noopener');
}

async function cargarCombosAsistencia() {
    const result = await cargarDatos('asistencia', 'combo');
    if (!result || !result.success) return;
    const selEst = document.getElementById('asis-estudiante');
    const selCurso = document.getElementById('asis-curso-docente');
    selEst.innerHTML = '<option value="">Seleccione estudiante</option>';
    result.estudiantes.forEach(e => selEst.innerHTML += `<option value="${e.id_estudiante}">${e.apellido}, ${e.nombre}</option>`);
    selCurso.innerHTML = '<option value="">Seleccione curso</option>';
    result.cursos.forEach(c => selCurso.innerHTML += `<option value="${c.id_curso_docente}">${c.curso} - ${c.docente}</option>`);
}

async function cargarAsistencias() {
    const respuesta = await cargarDatos('asistencia', 'obtener');
    if (!respuesta || !respuesta.data) return;
    const asistencias = respuesta.data;
    const tbody = document.querySelector('#tabla-asistencia tbody');
    tbody.innerHTML = '';
    asistencias.forEach(a => {
        const data = JSON.stringify(a).replace(/'/g, '&apos;');
        const color = a.estado_asistencia === 'Presente' ? 'green' : (a.estado_asistencia === 'Tardanza' ? 'amber' : 'red');
        tbody.innerHTML += `<tr>
            <td>${a.fecha}</td><td>${a.estudiante}</td><td>${a.curso}</td>
            <td><span class="tag tag-${color}">${a.estado_asistencia}</span></td><td>${a.observacion || ''}</td>
            <td style="display:flex; gap:5px;"><button class="btn btn-secondary btn-sm" onclick='editarAsistencia(${data})'>Editar</button><button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarAsistencia(${a.id_asistencia})">Eliminar</button></td>
        </tr>`;
    });
}

function editarAsistencia(a) {
    document.getElementById('asis-id').value = a.id_asistencia;
    document.getElementById('asis-fecha').value = a.fecha;
    document.getElementById('asis-estudiante').value = a.id_estudiante;
    document.getElementById('asis-curso-docente').value = a.id_curso_docente;
    document.getElementById('asis-estado').value = a.estado_asistencia;
    document.getElementById('asis-observacion').value = a.observacion || '';
}

async function guardarAsistencia() {
    const id = document.getElementById('asis-id').value;
    const data = {
        fecha: document.getElementById('asis-fecha').value,
        id_estudiante: document.getElementById('asis-estudiante').value,
        id_curso_docente: document.getElementById('asis-curso-docente').value,
        estado_asistencia: document.getElementById('asis-estado').value,
        observacion: document.getElementById('asis-observacion').value.trim()
    };
    if (id) data.id_asistencia = id;
    if (!data.fecha || !data.id_estudiante || !data.id_curso_docente || !data.estado_asistencia) return alert('Completa los campos obligatorios.');
    if (await guardarDatos('asistencia', id ? 'actualizar' : 'crear', data)) {
        limpiarFormularioAsistencia();
        cargarAsistencias();
    }
}

function limpiarFormularioAsistencia() {
    document.querySelectorAll('#asis-id, #asis-estudiante, #asis-curso-docente, #asis-observacion').forEach(el => el.value = '');
    document.getElementById('asis-fecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('asis-estado').value = 'Presente';
}

async function eliminarAsistencia(id_asistencia) {
    if (confirm('Eliminar este registro de asistencia?') && await guardarDatos('asistencia', 'eliminar', { id_asistencia })) cargarAsistencias();
}
