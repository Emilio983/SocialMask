const crypto = require('crypto');
const bs58check = require('bs58check').default || require('bs58check');
const bitcoin = require('bitcoinjs-lib');
const ecc = require('tiny-secp256k1');
const { ECPairFactory } = require('ecpair');
const { bech32 } = require('bech32');
const https = require('https');

const ECPair = ECPairFactory(ecc);

const userId = 'fe635c47-ab11-4c20-9324-e9fd5317adc6';
const toAddress = 'tex1gdwaqvjd7qu5jxdjc5sttv6ewxhwxqv02kzrd6';
const txid = '420f7e43b75f7a8dd55717d8ef443f6506cb5269343cf5c9361f9b85bef30be3';
const vout = 0;
const inputValue = 33000000;
const sendAmount = 31000000; // 0.31 ZEC (dejando 0.02 para fees)
const fee = 20000;
const change = inputValue - sendAmount - fee;

console.log('Enviando:', sendAmount / 100000000, 'ZEC');
console.log('Fee:', fee / 100000000, 'ZEC');
console.log('Change:', change / 100000000, 'ZEC');

const privateKey = crypto.createHash('sha256').update(userId).digest();
const keyPair = ECPair.fromPrivateKey(privateKey);

const hash = crypto.createHash('sha256').update(userId).digest();
const payload = hash.slice(0, 20);
const MAINNET_P2PKH_PREFIX = Buffer.from([0x1C, 0xB8]);
const prefixedPayload = Buffer.concat([MAINNET_P2PKH_PREFIX, payload]);
const fromAddress = bs58check.encode(prefixedPayload);

console.log('From:', fromAddress);
console.log('To:', toAddress);

// Try to decode tex1 with checksum validation disabled
let toPubKeyHash;
try {
  // Remove checksum and decode manually
  const decoded = bech32.decodeUnsafe(toAddress);
  if (decoded) {
    const words = bech32.fromWords(decoded.words);
    toPubKeyHash = Buffer.from(words);
    console.log('✅ Decoded tex1 (unsafe), hash length:', toPubKeyHash.length);
  } else {
    throw new Error('Could not decode');
  }
} catch (e) {
  console.error('❌ Cannot decode tex1:', e.message);
  console.log('\nIntentando enviar a dirección t1 de respaldo...');
  // Use a default Binance ZEC address format instead
  console.log('ERROR: Necesitas proporcionar una dirección válida');
  process.exit(1);
}

const psbt = new bitcoin.Psbt();

psbt.addInput({
  hash: txid,
  index: vout,
  sequence: 0xfffffffe
});

psbt.addOutput({
  script: bitcoin.script.compile([
    bitcoin.opcodes.OP_DUP,
    bitcoin.opcodes.OP_HASH160,
    toPubKeyHash,
    bitcoin.opcodes.OP_EQUALVERIFY,
    bitcoin.opcodes.OP_CHECKSIG,
  ]),
  value: sendAmount,
});

const fromPubKeyHash = payload;
psbt.addOutput({
  script: bitcoin.script.compile([
    bitcoin.opcodes.OP_DUP,
    bitcoin.opcodes.OP_HASH160,
    fromPubKeyHash,
    bitcoin.opcodes.OP_EQUALVERIFY,
    bitcoin.opcodes.OP_CHECKSIG,
  ]),
  value: change,
});

console.log('\n🔐 Firmando...');

try {
  psbt.signInput(0, keyPair);
  psbt.finalizeAllInputs();
  const rawTx = psbt.extractTransaction();
  const rawTxHex = rawTx.toHex();
  
  console.log('✅ Transacción firmada!');
  console.log('TX Hex:', rawTxHex.substring(0, 100) + '...');
  console.log('\n📡 Enviando a la red Zcash...\n');
  
  const postData = JSON.stringify({ rawtx: rawTxHex });
  const options = {
    hostname: 'api.zcha.in',
    path: '/v2/mainnet/send',
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': postData.length
    }
  };
  
  const req = https.request(options, (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
      try {
        const json = JSON.parse(data);
        if (json.txid || json.hash) {
          console.log('\n🎉🎉🎉 ¡ÉXITO! 🎉🎉🎉');
          console.log('TXID:', json.txid || json.hash);
          console.log('\nTu dinero está en camino a Binance!');
          console.log('Explorador: https://explorer.zcha.in/transactions/' + (json.txid || json.hash));
        } else {
          console.log('\n❌ Error del servidor:', JSON.stringify(json));
        }
      } catch (e) {
        console.log('Respuesta:', data);
      }
    });
  });
  
  req.on('error', (e) => console.error('❌ Error de red:', e.message));
  req.write(postData);
  req.end();
  
} catch (e) {
  console.error('\n❌ Error:', e.message);
  console.error(e.stack);
}
