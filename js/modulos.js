// ========== UTILIDAD: Cargar datos desde API ==========
async function cargarDatos(modulo, accion = 'obtener') {
    try {
        const response = await fetch(`./api/${modulo}.php?action=${accion}`);
        const result = await response.json();
        if (result.success) {
            return result.data || result;
        } else {
            alert('Error: ' + result.message);
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
            alert('Error: ' + result.error);
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
    const btnRegistrar = document.getElementById('btn-registrar-usuario');
    if (btnRegistrar) {
        // Usamos una función anónima para que siempre use la lógica de registrarUsuario
        btnRegistrar.onclick = registrarUsuario; 
        console.log('Evento registrar usuario asignado');
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
                const rolTexto = user.id_rol == 1 ? 'Administrador' : (user.id_rol == 2 ? 'Docente' : 'Auxiliar');
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
            const fila = `
                <tr>
                    <td><span style="font-family:'DM Mono';font-size:12px;">${est.codigo}</span></td>
                    <td>${est.nombre} ${est.apellido}</td>
                    <td>${est.dni}</td>
                    <td>${est.grado} ${est.seccion}</td>
                    <td><span class="tag tag-${est.estado === 'activo' ? 'green' : 'amber'}">${est.estado.charAt(0).toUpperCase() + est.estado.slice(1)}</span></td>
                    <td><button class="btn btn-secondary btn-sm">Editar</button></td>
                </tr>
            `;
            tbody.innerHTML += fila;
        });
    }
}

async function cargarCombos() {
    const result = await cargarDatos('estudiantes', 'combo');
    if (result && result.success) {
        const select = document.querySelector('select');
        select.innerHTML = '<option>Seleccionar...</option>';
        result.grados.forEach(grado => {
            select.innerHTML += `<option value="${grado.id_grado}">${grado.nombre}</option>`;
        });
    }
}

async function registrarEstudiante() {
    const codigo = document.querySelector('input[placeholder="EST-2026-001"]').value;
    const nombre = document.querySelector('input[placeholder="Ana Lucía"]').value;
    const apellido = document.querySelector('input[placeholder="García Ríos"]').value;
    const dni = document.querySelector('input[placeholder="74123456"]').value;
    const fecha_nacimiento = document.querySelector('input[type="date"]').value;
    const id_grado = document.querySelector('select').value;

    if (!codigo || !nombre || !apellido || !dni || !fecha_nacimiento) {
        alert('Completa todos los campos');
        return;
    }

    const success = await guardarDatos('estudiantes', 'crear', {
        codigo, nombre, apellido, dni, fecha_nacimiento, id_grado, id_seccion: 1
    });

    if (success) {
        document.querySelector('input[placeholder="EST-2026-001"]').value = '';
        document.querySelector('input[placeholder="Ana Lucía"]').value = '';
        document.querySelector('input[placeholder="García Ríos"]').value = '';
        document.querySelector('input[placeholder="74123456"]').value = '';
        document.querySelector('input[type="date"]').value = '';
        cargarEstudiantes();
    }
}
