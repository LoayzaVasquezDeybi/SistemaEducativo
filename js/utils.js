// ========== UTILIDAD: Cargar datos desde API ==========
async function cargarDatos(modulo, accion = 'obtener') {
    try {
        console.log(`Cargando datos de: ./api/${modulo}.php?action=${accion}`);
        const response = await fetch(`./api/${modulo}.php?action=${accion}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Respuesta de cargarDatos:', result);
        
        if (result.success) {
            return result; // Devolver el objeto completo para acceder a 'data', 'estudiantes', 'tipos', etc.
        } else {
            console.error('Error en respuesta de API:', result.error || result.message);
            alert('Error: ' + (result.error || result.message || 'Error desconocido'));
            return null;
        }
    } catch(error) {
        console.error('Error en fetch de cargarDatos:', error);
        alert('Error de conexión: ' + error.message);
        return null;
    }
}

// ========== UTILIDAD: Guardar datos ==========
async function guardarDatos(modulo, accion, datos) {
    try {
        console.log(`Guardando datos en: ./api/${modulo}.php?action=${accion}`, datos);
        
        const response = await fetch(`./api/${modulo}.php?action=${accion}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Respuesta de guardarDatos:', result);
        
        if (result.success) {
            alert('✅ ' + (result.message || 'Operación completada exitosamente'));
            return true;
        } else {
            const errorMsg = result.error || result.message || 'Error desconocido';
            console.error('Error en respuesta de API:', errorMsg);
            alert('❌ Error: ' + errorMsg);
            return false;
        }
    } catch(error) {
        console.error('Error en fetch de guardarDatos:', error);
        alert('❌ Error de conexión: ' + error.message);
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

// ========== UTILIDAD: Buscador en tablas ==========
function configurarBuscador(inputId, tablaId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        const rows = document.querySelectorAll(`#${tablaId} tbody tr`);
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
}