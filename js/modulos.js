// ========== UTILIDAD: Cargar datos desde API ==========
async function cargarDatos(modulo, accion = 'obtener') {
    try {
        const response = await fetch(`./api/${modulo}.php?action=${accion}`);
        const result = await response.json();
        if (result.success) {
            return result.data || result;
        } else {
            alert('Error: ' + (result.error || result.message || 'Error desconocido'));
            return null;
        }
    } catch(error) {
        console.error('Error en fetch:', error);
        return null;
    }
}

// ========== UTILIDAD: Guardar datos ==========
async function guardarDatos(modulo, accion, datos) {
    try {
        const response = await fetch(`./api/${modulo}.php?action=${accion}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            return true;
        } else {
            alert('Error: ' + (result.error || result.message || 'Error desconocido'));
            return false;
        }
    } catch(error) {
        console.error('Error:', error);
        return false;
    }
}

// ========== UTILIDAD: Limpiar formulario ==========
function limpiarFormulario(button) {
    const form = button.closest('.card');
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'email' || input.type === 'password' || input.type === 'date') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
    });
}

// ========== MÓDULO USUARIOS ==========
let editandoID = null; // Variable global para rastrear si estamos editando

function inicializarUsuarios() {
    console.log('Inicializando módulo Usuarios...');
    cargarUsuarios();
    cargarRolesUsuario(); // Cargar roles en el <select>
    const btnRegistrar = document.getElementById('btn-registrar-usuario');
    if (btnRegistrar) {
        // Usamos una función anónima para que siempre use la lógica de registrarUsuario
        btnRegistrar.onclick = registrarUsuario; 
        console.log('Evento registrar usuario asignado');
    }
}

async function cargarRolesUsuario() {
    const result = await cargarDatos('usuarios', 'combo');
    if (result && result.success) {
        // Busca el primer <select> dentro de la tarjeta de registro/edición
        const select = document.querySelector('.card form select, .card select'); 
        if (select) {
            select.innerHTML = '<option value="">Seleccionar Rol...</option>';
            result.roles.forEach(rol => {
                select.innerHTML += `<option value="${rol.id_rol}">${rol.nombre}</option>`;
            });
        }
    }
}

async function cargarUsuarios() {
    console.log('Cargando usuarios...');
    const usuarios = await cargarDatos('usuarios', 'obtener');
    
    if (usuarios) {
        const tbody = document.querySelector('#tabla-usuarios tbody');
        if (tbody) {
            tbody.innerHTML = '';
            usuarios.forEach(user => {
                const rolTexto = user.id_rol == 1 ? 'Administrador' : (user.id_rol == 2 ? 'Docente' : 'Alumno');
                const estadoTexto = user.id_estado_usuario == 1 ? 'Activo' : 'Inactivo';
                const tagColor = user.id_estado_usuario == 1 ? 'green' : 'red';

                // Importante: Convertimos el objeto user a string para pasarlo a la función
                const userJSON = JSON.stringify(user).replace(/'/g, "&apos;");

                const fila = `
                    <tr>
                        <td>${user.nombres} ${user.apellidos}</td>
                        <td>${user.dni || 'S/D'}</td> 
                        <td>${user.email}</td>
                        <td><span class="tag tag-blue">${rolTexto}</span></td>
                        <td><span class="tag tag-${tagColor}">${estadoTexto}</span></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick='prepararEdicion(${userJSON})'>
                                Editar
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += fila;
            });
        }
    }
}

// Función para cargar los datos en el formulario
function prepararEdicion(user) {
    console.log('Preparando edición de:', user.nombres);
    editandoID = user.id_usuario;

    const card = document.querySelector('.card');
    const inputs = card.querySelectorAll('input');
    const select = card.querySelector('select');

    // Llenar campos con los nombres de columna de tu DB
    inputs[0].value = user.nombres;
    inputs[1].value = user.apellidos;
    inputs[2].value = user.dni || '';
    inputs[3].value = user.email;
    inputs[4].value = ''; // Contraseña vacía por seguridad
    inputs[4].placeholder = "Dejar en blanco para no cambiar";
    select.value = user.id_rol;

    // Cambiar aspecto del botón
    const btnRegistrar = document.getElementById('btn-registrar-usuario');
    btnRegistrar.textContent = "Actualizar datos";
    btnRegistrar.classList.replace('btn-primary', 'btn-amber');
}

async function registrarUsuario() {
    const card = document.querySelector('.card');
    const inputs = card.querySelectorAll('input');
    const select = card.querySelector('select');

    const datos = {
        nombre: inputs[0].value,
        apellido: inputs[1].value,
        dni: inputs[2].value,
        email: inputs[3].value,
        contrasena: inputs[4].value,
        rol: select.value
    };

    let accion = 'crear';
    if (editandoID) {
        datos.id_usuario = editandoID;
        datos.estado = 1; // Activo por defecto
        accion = 'actualizar';
    }

    if (!datos.nombre || !datos.email) {
        alert('Nombre y Email son obligatorios');
        return;
    }

    const success = await guardarDatos('usuarios', accion, datos);

    if (success) {
        limpiarFormularioEdicion();
        cargarUsuarios();
    }
}

function limpiarFormularioEdicion() {
    editandoID = null;
    const btnRegistrar = document.getElementById('btn-registrar-usuario');
    if(btnRegistrar) {
        btnRegistrar.textContent = "Registrar usuario";
        btnRegistrar.classList.remove('btn-amber');
        btnRegistrar.classList.add('btn-primary');
    }
    
    // Limpiamos los campos
    const card = document.querySelector('.card');
    card.querySelectorAll('input').forEach(i => {
        i.value = '';
        i.placeholder = '';
    });
    card.querySelector('select').selectedIndex = 0;
}

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
