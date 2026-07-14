async function inicializarUsuarios() {
    await cargarCombosUsuario();
    await cargarUsuarios();
    configurarBuscador('user-search', 'tabla-usuarios');
}

async function cargarCombosUsuario() {
    const respuesta = await cargarDatos('usuarios', 'combo');
    if (!respuesta || !respuesta.roles) return;
    const selRol = document.getElementById('user-rol');
    selRol.innerHTML = '<option value="">Seleccione un rol</option>';
    respuesta.roles.forEach(rol => {
        selRol.innerHTML += `<option value="${rol.id_rol}">${rol.nombre}</option>`;
    });
}

async function cargarUsuarios() {
    const respuesta = await cargarDatos('usuarios', 'obtener');
    if (!respuesta || !respuesta.data) return;
    const usuarios = respuesta.data;
    const tbody = document.querySelector('#tabla-usuarios tbody');
    tbody.innerHTML = '';
    usuarios.forEach(user => {
        const data = JSON.stringify(user).replace(/'/g, '&apos;');
        const estado = user.id_estado_usuario == 1 ? 'activo' : 'inactivo';
        tbody.innerHTML += `
            <tr>
                <td>${user.nombres} ${user.apellidos}</td>
                <td>${user.dni}</td>
                <td>${user.email}</td>
                <td>${user.nombre_rol || 'Sin rol'}</td>
                <td><span class="tag tag-${estado === 'activo' ? 'green' : 'red'}">${estado}</span></td>
                <td style="display:flex; gap:5px;">
                    <button class="btn btn-secondary btn-sm" onclick='editarUsuario(${data})'>Editar</button>
                    <button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#dc2626;border-color:#fca5a5;" onclick="eliminarUsuario(${user.id_usuario})">Eliminar</button>
                </td>
            </tr>`;
    });
}

function editarUsuario(user) {
    document.getElementById('user-id').value = user.id_usuario;
    document.getElementById('user-nombre').value = user.nombres;
    document.getElementById('user-apellido').value = user.apellidos;
    document.getElementById('user-dni').value = user.dni;
    document.getElementById('user-email').value = user.email;
    document.getElementById('user-rol').value = user.id_rol;
    document.getElementById('user-estado').value = user.id_estado_usuario;
    document.getElementById('user-contrasena').value = '';
    document.getElementById('user-contrasena').placeholder = 'Dejar en blanco para no cambiar';
}

async function guardarUsuario() {
    const id = document.getElementById('user-id').value;
    const data = {
        nombre: document.getElementById('user-nombre').value,
        apellido: document.getElementById('user-apellido').value,
        dni: document.getElementById('user-dni').value,
        email: document.getElementById('user-email').value,
        rol: document.getElementById('user-rol').value,
        estado: document.getElementById('user-estado').value,
        contrasena: document.getElementById('user-contrasena').value
    };

    if (!data.nombre || !data.apellido || !data.dni || !data.email || !data.rol) {
        return alert('Los campos nombre, apellido, DNI, email y rol son obligatorios.');
    }

    if (!id && !data.contrasena) {
        return alert('La contraseña es obligatoria para nuevos usuarios.');
    }

    const action = id ? 'actualizar' : 'crear';
    if (id) data.id_usuario = id;

    if (await guardarDatos('usuarios', action, data)) {
        limpiarFormularioUsuario();
        cargarUsuarios();
    }
}

function limpiarFormularioUsuario() {
    document.querySelectorAll('#user-id, #user-nombre, #user-apellido, #user-dni, #user-email, #user-rol, #user-estado, #user-contrasena').forEach(el => el.value = '');
    document.getElementById('user-contrasena').placeholder = 'Contraseña inicial';
}

async function eliminarUsuario(id) {
    if (confirm('¿Está seguro de eliminar este usuario? Esta acción es irreversible y eliminará perfiles asociados (docente, apoderado, etc).')) {
        if (await guardarDatos('usuarios', 'eliminar', { id_usuario: id })) {
            cargarUsuarios();
        }
    }
}