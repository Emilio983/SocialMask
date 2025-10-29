const API = 'https://zcash.socialmask.org/api';
let userId, walletAddress, utxos;

function checkLogin() {
    userId = localStorage.getItem('userId');
    const email = localStorage.getItem('email');
    walletAddress = localStorage.getItem('walletAddress');

    if (!userId) {
        window.location.href = 'login.html';
        return false;
    }

    document.getElementById('userInfo').innerHTML = `
        <strong>Email:</strong> ${email}<br>
        <strong>User ID:</strong> ${userId}
    `;
    document.getElementById('myAddress').value = walletAddress;
    return true;
}

function logout() {
    localStorage.clear();
    window.location.href = 'login.html';
}

async function fetchUTXOs() {
    const txid = document.getElementById('txid').value.trim();
    if (!txid) {
        alert('Pega el TXID de Binance');
        return;
    }

    document.getElementById('result').innerHTML = '<div class="info">Buscando...</div>';

    try {
        const res = await fetch(`${API}/wallet/get-utxos-from-txid/${txid}/${walletAddress}`);
        const data = await res.json();

        if (data.success && data.utxos && data.utxos.length > 0) {
            utxos = data.utxos;
            const total = utxos.reduce((sum, u) => sum + u.value, 0) / 100000000;
            document.getElementById('utxoResult').innerHTML = 
                `<div class="success">✅ Encontrados ${total.toFixed(8)} ZEC</div>`;
            document.getElementById('sendForm').style.display = 'block';
            document.getElementById('result').innerHTML = '';
        } else {
            document.getElementById('result').innerHTML = 
                '<div class="error">No se encontraron fondos. Verifica que la transacción esté confirmada.</div>';
        }
    } catch (err) {
        document.getElementById('result').innerHTML = 
            `<div class="error">Error: ${err.message}</div>`;
    }
}

async function sendTransaction() {
    const toAddress = document.getElementById('toAddress').value.trim();
    const amount = document.getElementById('amount').value;

    if (!toAddress || !amount || !utxos) {
        alert('Completa todos los campos');
        return;
    }

    document.getElementById('result').innerHTML = '<div class="info">Enviando...</div>';

    try {
        const res = await fetch(`${API}/wallet/send-manual`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userId: userId,
                toAddress: toAddress,
                amountZEC: amount,
                utxos: utxos
            })
        });

        const data = await res.json();

        if (data.success) {
            document.getElementById('result').innerHTML = 
                `<div class="success">
                    ✅ ¡Transacción enviada!<br>
                    <strong>TXID:</strong> ${data.txid}<br>
                    <a href="https://explorer.zcha.in/transactions/${data.txid}" target="_blank">Ver en explorador</a>
                </div>`;
            document.getElementById('sendForm').style.display = 'none';
            document.getElementById('txid').value = '';
            document.getElementById('toAddress').value = '';
            document.getElementById('amount').value = '';
            utxos = null;
        } else {
            document.getElementById('result').innerHTML = 
                `<div class="error">Error: ${data.error}</div>`;
        }
    } catch (err) {
        document.getElementById('result').innerHTML = 
            `<div class="error">Error: ${err.message}</div>`;
    }
}

// Initialize
if (checkLogin()) {
    document.getElementById('fetchBtn').addEventListener('click', fetchUTXOs);
    document.getElementById('sendBtn').addEventListener('click', sendTransaction);
}

window.logout = logout;
