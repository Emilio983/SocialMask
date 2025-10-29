const API = 'https://zcash.socialmask.org/api';
const userId = 'fe635c47-ab11-4c20-9324-e9fd5317adc6';
const myAddress = 't1Y3qrYxrrTqBzMuC7gg3K27yVQPC9HuFae';
let utxos = null;

async function fetchUTXOs() {
    const txid = document.getElementById('txid').value.trim();
    if (!txid) {
        alert('Pega el TXID de Binance');
        return;
    }

    document.getElementById('result').innerHTML = '<div class="info">Buscando...</div>';

    try {
        const res = await fetch(`${API}/wallet/get-utxos-from-txid/${txid}/${myAddress}`);
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

    document.getElementById('result').innerHTML = '<div class="info">Enviando transacción...</div>';

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
        } else {
            document.getElementById('result').innerHTML = 
                `<div class="error">Error: ${data.error}</div>`;
        }
    } catch (err) {
        document.getElementById('result').innerHTML = 
            `<div class="error">Error: ${err.message}</div>`;
    }
}

// Add event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('fetchBtn').addEventListener('click', fetchUTXOs);
    document.getElementById('sendBtn').addEventListener('click', sendTransaction);
});
