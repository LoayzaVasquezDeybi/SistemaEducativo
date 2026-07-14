// ========== MÓDULO ROLES ==========
let editandoRolID = null;

function inicializarRoles() {
    cargarRoles();
    const btnRegistrar = document.getElementById('btn-registrar-rol');
    if (btnRegistrar) {
        btnRegistrar.onclick = registrarRol;
    }
    configurarBuscador('buscar-rol', 'tabla-roles');
}

async function cargarRoles() {
    const respuesta = await cargarDatos('roles', 'obtener');
    if (respuesta && respuesta.data) {
        const roles = respuesta.data;
        const tbody = document.querySelector('#tabla-roles tbody');
        if (tbody) {
            tbody.innerHTML = '';
            roles.forEach(rol => {
                const rolJSON = JSON.stringify(rol).replace(/'/g, "&apos;");
                
                // Roles del sistema (1,2,3) no deberían poder ser eliminados para no dañar el acceso principal
                const esDelSistema = (rol.id_rol == 1 || rol.id_rol == 2 || rol.id_rol == 3);
                const btnEliminar = esDelSistema 
                    ? `<button class="btn btn-secondary btn-sm" disabled title="Roles básicos del sistema no se pueden borrar">Eliminar</button>` 
                    : `<button class="btn btn-secondary btn-sm" style="background:#fee2e2; color:#dc2626; border-color:#fca5a5;" onclick='eliminarRol(${rol.id_rol})'>Eliminar</button>`;

                const fila = `
                    <tr>
                        <td>${rol.id_rol}</td>
                        <td><span class="tag tag-blue">${rol.nombre}</span></td>
                        <td>${rol.descripcion || '<span style="color:var(--muted)">Sin descripción</span>'}</td>
                        <td style="display: flex; gap: 5px;">
                            <button class="btn btn-secondary btn-sm" onclick='prepararEdicionRol(${rolJSON})'>Editar</button>
                            ${btnEliminar}
                        </td>
                    </tr>
                `;
                tbody.innerHTML += fila;
            });
        }
    }
}

function prepararEdicionRol(rol) {
    editandoRolID = rol.id_rol;
    document.getElementById('rol-nombre').value = rol.nombre || '';
    document.getElementById('rol-descripcion').value = rol.descripcion || '';

    const btnRegistrar = document.getElementById('btn-registrar-rol');
    if (btnRegistrar) { btnRegistrar.textContent = "Actualizar rol"; btnRegistrar.classList.replace('btn-primary', 'btn-amber'); }
}

async function registrarRol() {
    const nombre = document.getElementById('rol-nombre').value;
    const descripcion = document.getElementById('rol-descripcion').value;

    if (!nombre) return alert('Por favor, ingresa el nombre del rol.');

    const datos = { nombre, descripcion };
    let accion = editandoRolID ? 'actualizar' : 'crear';
    if (editandoRolID) datos.id_rol = editandoRolID;

    if (await guardarDatos('roles', accion, datos)) {
        limpiarFormularioEdicionRol();
        cargarRoles();
    }
}

async function eliminarRol(id_rol) {
    if (confirm('¿Estás totalmente seguro de eliminar este Rol? Esta acción es irreversible.')) {
        if (await guardarDatos('roles', 'eliminar', { id_rol })) cargarRoles();
    }
}

function limpiarFormularioEdicionRol() {
    editandoRolID = null;
    const btnRegistrar = document.getElementById('btn-registrar-rol');
    if (btnRegistrar) { btnRegistrar.textContent = "Registrar rol"; btnRegistrar.classList.replace('btn-amber', 'btn-primary'); }
    document.querySelectorAll('#rol-nombre, #rol-descripcion').forEach(el => el.value = '');
}