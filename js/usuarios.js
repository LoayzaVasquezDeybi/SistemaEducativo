async function inicializarUsuarios() {
    await cargarCombosUsuario();
    await cargarUsuarios();
    configurarBuscador('buscar-usuario', 'tabla-usuarios');

    const btnRegistrar = document.getElementById('btn-registrar-usuario');
    if (btnRegistrar) {
        btnRegistrar.onclick = guardarUsuario;
    }

    const btnExportarPdf = document.getElementById('btn-exportar-usuarios-pdf');
    if (btnExportarPdf) {
        btnExportarPdf.onclick = exportarUsuariosPdf;
    }

    const btnExportarExcel = document.getElementById('btn-exportar-usuarios-excel');
    if (btnExportarExcel) {
        btnExportarExcel.onclick = exportarUsuariosExcel;
    }
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
    const inputId = document.getElementById('user-id');
    const inputNombre = document.getElementById('user-nombre');
    const inputApellido = document.getElementById('user-apellido');
    const inputDni = document.getElementById('user-dni');
    const inputEmail = document.getElementById('user-email');
    const selectRol = document.getElementById('user-rol');
    const selectEstado = document.getElementById('user-estado');
    const inputPass = document.getElementById('user-pass');

    if (inputId) inputId.value = user.id_usuario;
    if (inputNombre) inputNombre.value = user.nombres;
    if (inputApellido) inputApellido.value = user.apellidos;
    if (inputDni) inputDni.value = user.dni;
    if (inputEmail) inputEmail.value = user.email;
    if (selectRol) selectRol.value = user.id_rol;
    if (selectEstado) selectEstado.value = user.id_estado_usuario;
    if (inputPass) {
        inputPass.value = '';
        inputPass.placeholder = 'Dejar en blanco para no cambiar';
    }
}

async function guardarUsuario() {
    const idInput = document.getElementById('user-id');
    const id = idInput ? idInput.value : '';
    const estadoSeleccionado = document.getElementById('user-estado');
    const data = {
        nombre: document.getElementById('user-nombre').value,
        apellido: document.getElementById('user-apellido').value,
        dni: document.getElementById('user-dni').value,
        email: document.getElementById('user-email').value,
        rol: document.getElementById('user-rol').value,
        estado: estadoSeleccionado ? estadoSeleccionado.value : '1',
        contrasena: document.getElementById('user-pass').value
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
    const campos = ['#user-id', '#user-nombre', '#user-apellido', '#user-dni', '#user-email', '#user-pass'];
    campos.forEach(selector => {
        const el = document.querySelector(selector);
        if (el) el.value = '';
    });

    const rolSelect = document.getElementById('user-rol');
    if (rolSelect) rolSelect.selectedIndex = 0;

    const estadoSelect = document.getElementById('user-estado');
    if (estadoSelect) estadoSelect.value = '1';

    const passInput = document.getElementById('user-pass');
    if (passInput) passInput.placeholder = 'Contraseña inicial';
}

function limpiarFormularioEdicion() {
    limpiarFormularioUsuario();
}

function exportarUsuariosPdf() {
    window.open('./api/exportar_usuarios_pdf.php', '_blank');
}

function exportarUsuariosExcel() {
    window.open('./api/exportar_usuarios_excel.php', '_blank');
}

async function eliminarUsuario(id) {
    if (confirm('¿Está seguro de eliminar este usuario? Esta acción es irreversible y eliminará perfiles asociados (docente, apoderado, etc).')) {
        if (await guardarDatos('usuarios', 'eliminar', { id_usuario: id })) {
            cargarUsuarios();
        }
    }
}