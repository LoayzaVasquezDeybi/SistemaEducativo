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