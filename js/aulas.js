/**
 * Función inicializadora llamada por funcion.js
 */
function inicializarAulas() {
    console.log("Módulo de aulas cargado");
    
    // Verificar que los elementos existan
    const form = document.getElementById('form-aula');
    const tabla = document.getElementById('tabla-aulas');
    const buscar = document.getElementById('buscar-aula');
    
    if (!form) {
        console.error('No se encontró el formulario form-aula');
        return;
    }
    
    if (!tabla) {
        console.error('No se encontró la tabla tabla-aulas');
        return;
    }
    
    // Cargar datos
    listarAulas();
    
    // Configurar buscador
    if (buscar) {
        configurarBuscador('buscar-aula', 'tabla-aulas');
    }

    // Asignar evento submit al formulario
    form.onsubmit = async (e) => {
        e.preventDefault();
        console.log('Formulario enviado');
        await guardarAula();
    };
}

async function listarAulas() {
    const data = await cargarDatos('aulas', 'obtener');
    const tbody = document.querySelector('#tabla-aulas tbody');
    tbody.innerHTML = '';

    if (data && data.length > 0) {
        data.forEach(aula => {
            const estado = aula.estado ? aula.estado.toLowerCase() : '';
            const tagClass = estado === 'activo' ? 'tag tag-green' : (estado === 'inactivo' ? 'tag tag-red' : 'tag tag-amber');
            // Escapamos el objeto para evitar errores de sintaxis en el HTML
            const aulaJSON = JSON.stringify(aula).replace(/'/g, "&apos;");
            
            tbody.innerHTML += `
                <tr>
                    <td><strong>${aula.nombre_aula}</strong></td>
                    <td>${aula.capacidad} est.</td>
                    <td><small class="text-muted">${aula.ubicacion}</small></td>
                    <td><span class="${tagClass}">${aula.estado}</span></td>
                    <td style="display: flex; gap: 5px;">
                        <button class="btn btn-secondary btn-sm" onclick='editarAula(${aulaJSON})'>
                            Editar
                        </button>
                        <button class="btn btn-secondary btn-sm" style="background:#fee2e2; color:#dc2626; border-color:#fca5a5;" onclick="eliminarAula(${aula.id_aula})">
                            Eliminar
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No hay aulas registradas</td></tr>';
    }
}

async function guardarAula() {
    try {
        const id = document.getElementById('id_aula').value || '';
        const nombre_aula = document.getElementById('nombre_aula').value.trim();
        const capacidad = document.getElementById('capacidad').value.trim();
        const ubicacion = document.getElementById('ubicacion').value.trim();
        const estado = document.getElementById('estado').value;
        
        // Validar campos obligatorios
        if (!nombre_aula) {
            alert('❌ El nombre del aula es obligatorio');
            return;
        }
        
        if (!capacidad || capacidad <= 0) {
            alert('❌ La capacidad debe ser mayor a 0');
            return;
        }
        
        if (!estado) {
            alert('❌ El estado es obligatorio');
            return;
        }
        
        const datos = {
            id_aula: id,
            nombre_aula: nombre_aula,
            capacidad: parseInt(capacidad),
            ubicacion: ubicacion,
            estado: estado
        };
        
        console.log('Guardando aula:', datos);
        
        const accion = id ? 'actualizar' : 'crear';
        const exito = await guardarDatos('aulas', accion, datos);

        if (exito) {
            console.log('Aula guardada exitosamente');
            limpiarFormularioAula();
            await listarAulas();
        } else {
            console.error('Error al guardar aula');
        }
    } catch (error) {
        console.error('Error en guardarAula:', error);
        alert('Error: ' + error.message);
    }
}

function editarAula(aula) {
    document.getElementById('form-aula-title').textContent = 'Editar Aula';
    document.getElementById('btn-guardar-aula').textContent = 'Actualizar Cambios';
    
    document.getElementById('id_aula').value = aula.id_aula;
    document.getElementById('nombre_aula').value = aula.nombre_aula;
    document.getElementById('capacidad').value = aula.capacidad;
    document.getElementById('ubicacion').value = aula.ubicacion;
    document.getElementById('estado').value = aula.estado;
}

async function eliminarAula(id) {
    if (confirm('¿Está seguro de eliminar esta aula? Esta acción no se puede deshacer.')) {
        const exito = await guardarDatos('aulas', 'eliminar', { id_aula: id });
        if (exito) listarAulas();
    }
}

function limpiarFormularioAula() {
    document.getElementById('form-aula').reset();
    document.getElementById('id_aula').value = '';
    document.getElementById('form-aula-title').textContent = 'Registrar Nueva Aula';
    document.getElementById('btn-guardar-aula').textContent = 'Guardar Aula';
}

// Hacer las funciones accesibles globalmente si es necesario
window.editarAula = editarAula;
window.eliminarAula = eliminarAula;