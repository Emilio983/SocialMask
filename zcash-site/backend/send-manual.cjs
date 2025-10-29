const crypto = require('crypto');
const bs58check = require('bs58check').default || require('bs58check');
const bitcoin = require('bitcoinjs-lib');
const ecc = require('tiny-secp256k1');
const { ECPairFactory } = require('ecpair');
const { bech32 } = require('bech32');
const https = require('https');

const ECPair = ECPairFactory(ecc);

// Your data
const userId = 'fe635c47-ab11-4c20-9324-e9fd5317adc6';
const toAddress = 'tex1gdwaqvjd7qu5jxdjc5sttv6ewxhwxqv02kzrd6';
const txid = '420f7e43b75f7a8dd55717d8ef443f6506cb5269343cf5c9361f9b85bef30be3';
const vout = 0;
const inputValue = 33000000; // 0.33 ZEC
const sendAmount = 32000000; // 0.32 ZEC
const fee = 5000;
const change = inputValue - sendAmount - fee;

console.log('Construyendo transacción...');
console.log('Input:', inputValue, 'sats (0.33 ZEC)');
console.log('Enviando:', sendAmount, 'sats (0.32 ZEC)');
console.log('Fee:', fee, 'sats');
console.log('Change:', change, 'sats');

// Derive private key
const privateKey = crypto.createHash('sha256').update(userId).digest();
const keyPair = ECPair.fromPrivateKey(privateKey);

// Get from address
const hash = crypto.createHash('sha256').update(userId).digest();
const payload = hash.slice(0, 20);
const MAINNET_P2PKH_PREFIX = Buffer.from([0x1C, 0xB8]);
const prefixedPayload = Buffer.concat([MAINNET_P2PKH_PREFIX, payload]);
const fromAddress = bs58check.encode(prefixedPayload);

console.log('From:', fromAddress);
console.log('To:', toAddress);

// Decode tex1 address
let toPubKeyHash;
try {
  const decoded = bech32.decode(toAddress);
  const words = bech32.fromWords(decoded.words);
  toPubKeyHash = Buffer.from(words);
  console.log('Decoded tex1 address, pubkey hash length:', toPubKeyHash.length);
} catch (e) {
  console.error('ERROR decoding tex1:', e.message);
  process.exit(1);
}

// Build transaction
const psbt = new bitcoin.Psbt();

// Add input
psbt.addInput({
  hash: txid,
  index: vout,
  sequence: 0xfffffffe
});

// Add output to recipient
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

// Add change output
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

console.log('\nFirmando transacción...');

try {
  psbt.signInput(0, keyPair);
  psbt.finalizeAllInputs();
  const rawTx = psbt.extractTransaction();
  const rawTxHex = rawTx.toHex();
  
  console.log('\n✅ Transacción construida!');
  console.log('Raw TX:', rawTxHex);
  console.log('\nEnviando a la red...');
  
  // Broadcast
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
      console.log('\nRespuesta:', data);
      try {
        const json = JSON.parse(data);
        if (json.txid || json.hash) {
          console.log('\n🎉 ¡ÉXITO! TXID:', json.txid || json.hash);
        } else {
          console.log('\n❌ Error:', json);
        }
      } catch (e) {
        console.log('Response:', data);
      }
    });
  });
  
  req.on('error', (e) => console.error('Error:', e));
  req.write(postData);
  req.end();
  
} catch (e) {
  console.error('\n❌ Error:', e.message);
  process.exit(1);
}
