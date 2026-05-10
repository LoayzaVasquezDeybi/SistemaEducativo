document.getElementById('loginForm').addEventListener('submit', async (e) => {
    // Esto evita que la página "pestañee" o se recargue al dar clic en el botón
    e.preventDefault();
    
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const errorMsg = document.getElementById('errorMsg');
    const btn = document.getElementById('btn-submit');
    
    errorMsg.style.display = 'none';
    btn.textContent = 'Verificando...';
    btn.disabled = true;
    
    try {
        const response = await fetch('./api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const result = await response.json();
        
        if (result.success) {
            // Si el login es correcto, redigir al index principal
            window.location.href = 'index.html'; 
        } else {
            errorMsg.textContent = result.message || 'Error al iniciar sesión';
            errorMsg.style.display = 'block';
            btn.textContent = 'Ingresar al sistema';
            btn.disabled = false;
        }
    } catch (error) {
        errorMsg.textContent = 'Error de conexión con el servidor.';
        errorMsg.style.display = 'block';
        btn.textContent = 'Ingresar al sistema';
        btn.disabled = false;
    }
});