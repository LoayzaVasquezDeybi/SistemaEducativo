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
    configurarBuscador('buscar-usuario', 'tabla-usuarios');
}

async function cargarRolesUsuario() {
    const result = await cargarDatos('usuarios', 'combo');
    if (result && result.success) {
        const select = document.getElementById('user-rol'); 
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
                const rolTexto = user.nombre_rol || 'Desconocido';
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
                        <td style="display: flex; gap: 5px;">
                            <button class="btn btn-secondary btn-sm" onclick='prepararEdicion(${userJSON})'>
                                Editar
                            </button>
                            <button class="btn btn-secondary btn-sm" style="background:#fee2e2; color:#dc2626; border-color:#fca5a5;" onclick='eliminarUsuario(${user.id_usuario})'>
                                Eliminar
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

    document.getElementById('user-nombre').value = user.nombres || '';
    document.getElementById('user-apellido').value = user.apellidos || '';
    document.getElementById('user-dni').value = user.dni || '';
    document.getElementById('user-email').value = user.email || '';
    
    const passInput = document.getElementById('user-pass');
    if(passInput) {
        passInput.value = '';
        passInput.placeholder = "Dejar en blanco para no cambiar";
    }
    document.getElementById('user-rol').value = user.id_rol || '';

    // Cambiar aspecto del botón
    const btnRegistrar = document.getElementById('btn-registrar-usuario');
    btnRegistrar.textContent = "Actualizar datos";
    btnRegistrar.classList.replace('btn-primary', 'btn-amber');
}

async function registrarUsuario() {
    const datos = {
        nombre: document.getElementById('user-nombre').value,
        apellido: document.getElementById('user-apellido').value,
        dni: document.getElementById('user-dni').value,
        email: document.getElementById('user-email').value,
        contrasena: document.getElementById('user-pass') ? document.getElementById('user-pass').value : '',
        rol: document.getElementById('user-rol').value
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
    document.querySelectorAll('#user-nombre, #user-apellido, #user-dni, #user-email, #user-pass').forEach(i => {
        if(i) { i.value = ''; i.placeholder = ''; }
    });
    const select = document.getElementById('user-rol');
    if(select) select.selectedIndex = 0;
}

async function eliminarUsuario(id_usuario) {
    if (confirm('¿Estás seguro de eliminar este Usuario? Si es un docente o apoderado, sus datos enlazados también serán borrados irreversiblemente.')) {
        if (await guardarDatos('usuarios', 'eliminar', { id_usuario })) cargarUsuarios();
    }
}