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