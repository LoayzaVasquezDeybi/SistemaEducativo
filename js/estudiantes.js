// ========== MÓDULO ESTUDIANTES ==========
let editandoEstudianteID = null; // Rastrear si estamos editando un estudiante

function inicializarEstudiantes() {
    cargarEstudiantes();
    cargarCombos();
    document.getElementById('btn-registrar-estudiante').addEventListener('click', registrarEstudiante);
}

async function cargarEstudiantes() {
    const estudiantes = await cargarDatos('estudiantes', 'obtener');
    if (estudiantes) {
        const tbody = document.querySelector('#tabla-estudiantes tbody');
        tbody.innerHTML = '';
        estudiantes.forEach(est => {
            const estJSON = JSON.stringify(est).replace(/'/g, "&apos;");
            const fila = `
                <tr>
                    <td><span style="font-family:'DM Mono';font-size:12px;">${est.codigo}</span></td>
                    <td>${est.nombre} ${est.apellido}</td>
                    <td>${est.dni}</td>
                    <td>${est.grado} ${est.seccion}</td>
                    <td><span class="tag tag-${est.estado === 'activo' ? 'green' : 'amber'}">${est.estado.charAt(0).toUpperCase() + est.estado.slice(1)}</span></td>
                    <td><button class="btn btn-secondary btn-sm" onclick='prepararEdicionEstudiante(${estJSON})'>Editar</button></td>
                </tr>
            `;
            tbody.innerHTML += fila;
        });
    }
}

async function cargarCombos() {
    const result = await cargarDatos('estudiantes', 'combo');
    if (result && result.success) {
        const selects = document.querySelectorAll('.card select');
        
        if (selects.length > 0) {
            selects[0].innerHTML = '<option value="">Seleccionar Grado...</option>';
            result.grados.forEach(grado => {
                selects[0].innerHTML += `<option value="${grado.id_grado}">${grado.nombre}</option>`;
            });
        }
        if (selects.length > 1) {
            selects[1].innerHTML = '<option value="">Seleccionar Sección...</option>';
            result.secciones.forEach(seccion => {
                selects[1].innerHTML += `<option value="${seccion.id_seccion}">${seccion.nombre}</option>`;
            });
        }
    }
}

function prepararEdicionEstudiante(est) {
    editandoEstudianteID = est.id_estudiante;
    const inputs = document.querySelectorAll('.card input');
    const selects = document.querySelectorAll('.card select');

    if (inputs.length >= 5) {
        inputs[0].value = est.codigo || '';
        inputs[1].value = est.nombre || '';
        inputs[2].value = est.apellido || '';
        inputs[3].value = est.dni || '';
        inputs[4].value = est.fecha_nacimiento || '';
    }
    if (selects.length > 0) selects[0].value = est.id_grado || '';
    if (selects.length > 1) selects[1].value = est.id_seccion || '';

    const btnRegistrar = document.getElementById('btn-registrar-estudiante');
    if (btnRegistrar) {
        btnRegistrar.textContent = "Actualizar datos";
        btnRegistrar.classList.replace('btn-primary', 'btn-amber');
    }
}

async function registrarEstudiante() {
    // Buscamos los campos dentro de la tarjeta para evitar errores por placeholders distintos
    const inputs = document.querySelectorAll('.card input');
    const selects = document.querySelectorAll('.card select');

    if (inputs.length < 5) {
        alert('Error: No se encontraron los campos necesarios en el formulario HTML.');
        return;
    }

    const codigo = inputs[0].value;
    const nombre = inputs[1].value;
    const apellido = inputs[2].value;
    const dni = inputs[3].value;
    const fecha_nacimiento = inputs[4].value;
    const id_grado = selects.length > 0 ? selects[0].value : '';
    const id_seccion = selects.length > 1 ? selects[1].value : '';

    if (!codigo || !nombre || !apellido || !dni || !fecha_nacimiento || !id_grado || !id_seccion) {
        alert('Por favor, completa todos los campos, incluyendo seleccionar el Grado y la Sección.');
        return;
    }

    const datos = {
        codigo, nombre, apellido, dni, fecha_nacimiento, id_grado, id_seccion
    };

    let accion = 'crear';
    if (editandoEstudianteID) {
        datos.id_estudiante = editandoEstudianteID;
        datos.estado = 'activo';
        accion = 'actualizar';
    }

    const success = await guardarDatos('estudiantes', accion, datos);

    if (success) {
        limpiarFormularioEdicionEstudiante();
        cargarEstudiantes();
    }
}

function limpiarFormularioEdicionEstudiante() {
    editandoEstudianteID = null;
    const btnRegistrar = document.getElementById('btn-registrar-estudiante');
    if(btnRegistrar) {
        btnRegistrar.textContent = "Registrar estudiante";
        btnRegistrar.classList.remove('btn-amber');
        btnRegistrar.classList.add('btn-primary');
    }
    
    const inputs = document.querySelectorAll('.card input');
    inputs.forEach(i => i.value = '');
    const selects = document.querySelectorAll('.card select');
    selects.forEach(s => s.selectedIndex = 0);
}