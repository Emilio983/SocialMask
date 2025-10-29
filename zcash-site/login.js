const API = 'https://zcash.socialmask.org/api';

async function login() {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    if (!email || !password) {
        alert('Completa todos los campos');
        return;
    }

    document.getElementById('result').innerHTML = '<div class="info">Iniciando sesión...</div>';

    try {
        const res = await fetch(`${API}/auth/email-login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await res.json();

        if (data.success) {
            // Save to localStorage
            localStorage.setItem('userId', data.userId);
            localStorage.setItem('email', email);
            localStorage.setItem('walletAddress', data.walletAddress);
            
            document.getElementById('result').innerHTML = 
                `<div class="success">✅ Sesión iniciada! Redirigiendo...</div>`;
            
            // Redirect to dashboard
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1000);
        } else {
            document.getElementById('result').innerHTML = 
                `<div class="error">Error: ${data.error || 'Credenciales incorrectas'}</div>`;
        }
    } catch (err) {
        document.getElementById('result').innerHTML = 
            `<div class="error">Error: ${err.message}</div>`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('loginBtn').addEventListener('click', login);
    document.getElementById('password').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') login();
    });
});
