import { generateTAddress, validateTAddress, generateWalletAddresses } from './src/services/address-generator.js';
import bs58check from 'bs58check';

console.log('=== Testing Address Generation ===\n');

// Test 1: Generate address
const testUserId = 'test-user-12345';
const addr = generateTAddress(testUserId);
console.log('Generated address:', addr);

// Test 2: Decode address
try {
  const decoded = bs58check.decode(addr);
  console.log('Decoded hex:', decoded.toString('hex'));
  console.log('Decoded length:', decoded.length);
  console.log('Prefix hex:', decoded.slice(0, 2).toString('hex'));
} catch (e) {
  console.log('Decode error:', e.message);
}

// Test 3: Validate
console.log('Is valid:', validateTAddress(addr));

// Test 4: Generate wallet addresses
console.log('\n=== Wallet Addresses ===');
const wallet = generateWalletAddresses(testUserId);
console.log('t-addr:', wallet.tAddr);
console.log('UA:', wallet.ua);
console.log('Valid:', wallet.isValid);

// Test 5: Try with known valid Zcash address
console.log('\n=== Testing Known Address ===');
const knownAddr = 't1Hsc1LR8yKnbbe3twRp88p6vFfC5t7DLbs';
console.log('Known address:', knownAddr);
console.log('Is valid:', validateTAddress(knownAddr));
try {
  const decoded = bs58check.decode(knownAddr);
  console.log('Decoded hex:', decoded.toString('hex'));
  console.log('Prefix hex:', decoded.slice(0, 2).toString('hex'));
} catch (e) {
  console.log('Error:', e.message);
}
