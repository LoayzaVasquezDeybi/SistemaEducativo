// ========== MÓDULO DOCENTES ==========
let editandoDocenteID = null;

function inicializarDocentes() {
    cargarDocentes();
    const btnRegistrar = document.getElementById('btn-registrar-docente');
    if (btnRegistrar) {
        // Para evitar múltiples escuchas si se navega varias veces, eliminamos y agregamos
        btnRegistrar.removeEventListener('click', registrarDocente);
        btnRegistrar.addEventListener('click', registrarDocente);
    }

    // Configuración del botón de Exportar PDF para Docentes
    const btnExportar = document.getElementById('btn-exportar-docentes-pdf');
    if (btnExportar) {
        btnExportar.addEventListener('click', () => {
            window.open('api/exportar_docentes.php', '_blank');
        });
    }

    configurarBuscador('buscar-docente', 'tabla-docentes');
}

async function cargarDocentes() {
    const docentes = await cargarDatos('docentes', 'obtener');
    if (docentes) {
        const tbody = document.querySelector('#tabla-docentes tbody');
        if (tbody) {
            tbody.innerHTML = '';
            docentes.forEach(doc => {
                const docJSON = JSON.stringify(doc).replace(/'/g, "&apos;");
                const estado = doc.estado ? doc.estado : 'activo';
                const fila = `
                    <tr>
                        <td>${doc.nombre} ${doc.apellido}</td>
                        <td>${doc.dni}</td>
                        <td>${doc.email}</td>
                        <td>${doc.especialidad || 'N/A'}</td>
                        <td><span class="tag tag-${estado === 'activo' ? 'green' : 'amber'}">${estado.charAt(0).toUpperCase() + estado.slice(1)}</span></td>
                        <td style="display: flex; gap: 5px;">
                            <button class="btn btn-secondary btn-sm" onclick='prepararEdicionDocente(${docJSON})'>Editar</button>
                            <button class="btn btn-secondary btn-sm" style="background:#fee2e2; color:#dc2626; border-color:#fca5a5;" onclick='eliminarDocente(${doc.id_docente})'>Eliminar</button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += fila;
            });
        }
    }
}

function prepararEdicionDocente(doc) {
    editandoDocenteID = doc.id_docente;
    const inputs = document.querySelectorAll('.card input');

    // Suponiendo un orden en el formulario: Nombre, Apellido, DNI, Email, Especialidad
    if (inputs.length >= 4) {
        inputs[0].value = doc.nombre || '';
        inputs[1].value = doc.apellido || '';
        inputs[2].value = doc.dni || '';
        inputs[3].value = doc.email || '';
        if (inputs.length >= 5) inputs[4].value = doc.especialidad || '';
    }

    const btnRegistrar = document.getElementById('btn-registrar-docente');
    if (btnRegistrar) {
        btnRegistrar.textContent = "Actualizar datos";
        btnRegistrar.classList.replace('btn-primary', 'btn-amber');
    }
}

async function registrarDocente() {
    const inputs = document.querySelectorAll('.card input');

    const nombre = inputs[0] ? inputs[0].value : '';
    const apellido = inputs[1] ? inputs[1].value : '';
    const dni = inputs[2] ? inputs[2].value : '';
    const email = inputs[3] ? inputs[3].value : '';
    const especialidad = inputs[4] ? inputs[4].value : '';

    if (!nombre || !apellido || !dni || !email) {
        alert('Por favor, completa los campos obligatorios (Nombre, Apellido, DNI y Email).');
        return;
    }

    const datos = { nombre, apellido, dni, email, especialidad };

    let accion = 'crear';
    if (editandoDocenteID) {
        datos.id_docente = editandoDocenteID;
        datos.estado = 'activo'; 
        accion = 'actualizar';
    }

    const success = await guardarDatos('docentes', accion, datos);

    if (success) {
        editandoDocenteID = null;
        const btnRegistrar = document.getElementById('btn-registrar-docente');
        if (btnRegistrar) { btnRegistrar.textContent = "Registrar docente"; btnRegistrar.classList.replace('btn-amber', 'btn-primary'); }
        inputs.forEach(i => i.value = '');
        cargarDocentes();
    }
}

async function eliminarDocente(id_docente) {
    if (confirm('¿Estás seguro de eliminar este Docente? Su usuario asociado también será borrado.')) {
        if (await guardarDatos('docentes', 'eliminar', { id_docente })) cargarDocentes();
    }
}