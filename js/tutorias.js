let tutoriaActual = null;

async function inicializarTutorias() {
    const admin = window.USUARIO_ACTUAL && Number(window.USUARIO_ACTUAL.rol) === 1;
    document.getElementById('tutoria-admin').style.display = admin ? 'block' : 'none';
    if (admin) await cargarCombosTutorias();
    await cargarTutorias();
}

async function cargarCombosTutorias() {
    const r = await cargarDatos('tutorias', 'combos');
    if (!r || !r.success) return;
    llenarComboTutor('tutor-docente', r.docentes, 'id_docente', 'nombre', 'Seleccione docente');
    llenarComboTutor('tutor-grado', r.grados, 'id_grado', 'nombre', 'Seleccione grado');
    llenarComboTutor('tutor-seccion', r.secciones, 'id_seccion', 'nombre', 'Seleccione sección');
}

function llenarComboTutor(id, datos, value, label, placeholder) {
    const select=document.getElementById(id);
    select.innerHTML=`<option value="">${placeholder}</option>`;
    datos.forEach(item => select.innerHTML += `<option value="${item[value]}">${item[label]}</option>`);
}

async function cargarTutorias() {
    const r=await cargarDatos('tutorias','obtener');
    if (!r || !r.success) return;
    const thead=document.querySelector('#tabla-tutorias thead');
    const tbody=document.querySelector('#tabla-tutorias tbody');
    const mensaje=document.getElementById('tutoria-mensaje');
    tbody.innerHTML='';
    if (r.es_admin) {
        mensaje.textContent='Cada sección admite un tutor y cada docente puede asumir una sección por periodo.';
        thead.innerHTML='<tr><th>Grado</th><th>Sección</th><th>Tutor</th><th>Alumnos</th><th>Acciones</th></tr>';
        r.asignaciones.forEach(a => tbody.innerHTML += `<tr><td>${a.grado}</td><td>${a.seccion}</td><td>${a.tutor}</td><td>${a.alumnos}</td><td><button class="btn btn-danger btn-sm" onclick="eliminarTutor(${a.id_tutor_seccion})">Retirar</button></td></tr>`);
        if (!r.asignaciones.length) tbody.innerHTML='<tr><td colspan="5" style="text-align:center;color:var(--muted)">Todavía no hay tutores asignados.</td></tr>';
        return;
    }
    tutoriaActual=r.asignacion;
    thead.innerHTML='<tr><th>Código</th><th>Estudiante</th><th>DNI</th><th>Libreta</th></tr>';
    if (!r.asignacion) {
        mensaje.textContent='No tiene una sección asignada como tutor. Solicite la asignación al administrador.';
        tbody.innerHTML='<tr><td colspan="4" style="text-align:center;color:var(--muted)">Sin sección asignada.</td></tr>';
        return;
    }
    mensaje.innerHTML=`Tutor de <strong>${r.asignacion.grado} - Sección ${r.asignacion.seccion}</strong> · ${r.alumnos.length} estudiante(s) <button class="btn btn-primary btn-sm" style="margin-left:12px" onclick="descargarTodasLibretas()">Descargar todas las libretas</button>`;
    r.alumnos.forEach(e => tbody.innerHTML += `<tr><td>${e.codigo_estudiante}</td><td>${e.apellido}, ${e.nombre}</td><td>${e.dni || 'S/D'}</td><td><button class="btn btn-secondary btn-sm" onclick="descargarLibretaTutor(${e.id_estudiante})">Descargar</button></td></tr>`);
    if (!r.alumnos.length) tbody.innerHTML='<tr><td colspan="4" style="text-align:center;color:var(--muted)">La sección no tiene estudiantes activos.</td></tr>';
}

async function guardarTutor() {
    const data={id_docente:document.getElementById('tutor-docente').value,id_grado:document.getElementById('tutor-grado').value,id_seccion:document.getElementById('tutor-seccion').value};
    if (!data.id_docente || !data.id_grado || !data.id_seccion) return alert('Complete docente, grado y sección.');
    if (await guardarDatos('tutorias','guardar',data)) cargarTutorias();
}

async function eliminarTutor(id_tutor_seccion) {
    if (confirm('¿Retirar al tutor de esta sección?') && await guardarDatos('tutorias','eliminar',{id_tutor_seccion})) cargarTutorias();
}

function descargarLibretaTutor(id) { window.open(`api/libreta_notas.php?id_estudiante=${encodeURIComponent(id)}`,'_blank','noopener'); }
function descargarTodasLibretas() { window.open('api/libretas_seccion.php','_blank','noopener'); }
