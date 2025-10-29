import { describe, it, before, after } from 'node:test';
import assert from 'node:assert';
import http from 'node:http';

const BASE_URL = 'http://localhost:8080';

// Helper to make HTTP requests
const request = (method, path, body = null) => {
  return new Promise((resolve, reject) => {
    const url = new URL(path, BASE_URL);
    const options = {
      method,
      headers: {
        'Content-Type': 'application/json',
      },
    };

    const req = http.request(url, options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve({
            status: res.statusCode,
            headers: res.headers,
            body: data ? JSON.parse(data) : null,
          });
        } catch (e) {
          resolve({
            status: res.statusCode,
            headers: res.headers,
            body: data,
          });
        }
      });
    });

    req.on('error', reject);

    if (body) {
      req.write(JSON.stringify(body));
    }

    req.end();
  });
};

describe('Backend API Tests', () => {
  describe('Health Check', () => {
    it('should return healthy status', async () => {
      const res = await request('GET', '/health');
      assert.strictEqual(res.status, 200);
      assert.strictEqual(res.body.status, 'healthy');
      assert.ok(res.body.timestamp);
    });
  });

  describe('Blocks API', () => {
    it('should return current block head', async () => {
      const res = await request('GET', '/api/blocks/head');
      assert.strictEqual(res.status, 200);
      assert.ok(res.body.height);
      assert.ok(res.body.hash);
      assert.ok(res.body.timestamp);
    });
  });

  describe('Users API', () => {
    let userId;

    it('should create a new user', async () => {
      const res = await request('POST', '/api/users');
      assert.strictEqual(res.status, 201);
      assert.ok(res.body.id);
      assert.ok(res.body.created_at);
      userId = res.body.id;
    });

    it('should create a wallet for user', async () => {
      const res = await request('POST', '/api/wallets', {
        userId,
        ua: 'u1test123456789',
      });
      assert.strictEqual(res.status, 200);
      assert.strictEqual(res.body.ua, 'u1test123456789');
    });

    it('should retrieve wallet by user ID', async () => {
      const res = await request('GET', `/api/wallets/${userId}`);
      assert.strictEqual(res.status, 200);
      assert.strictEqual(res.body.user_id, userId);
      assert.strictEqual(res.body.ua, 'u1test123456789');
    });
  });

  describe('Transaction API', () => {
    let userId;
    let txid;

    before(async () => {
      // Create a test user
      const res = await request('POST', '/api/users');
      userId = res.body.id;
    });

    it('should reject transaction submission without required fields', async () => {
      const res = await request('POST', '/api/tx/submit', {});
      assert.strictEqual(res.status, 400);
    });

    it('should reject invalid hex format', async () => {
      const res = await request('POST', '/api/tx/submit', {
        rawTxHex: 'invalid-hex',
        userId,
      });
      assert.strictEqual(res.status, 400);
    });

    it('should submit a valid transaction', async () => {
      const res = await request('POST', '/api/tx/submit', {
        rawTxHex: 'deadbeef',
        userId,
        metadata: {
          direction: 'outgoing',
          amount_zats: 100000,
          to_addr: 'u1test123',
        },
      });
      assert.strictEqual(res.status, 200);
      assert.ok(res.body.txid);
      assert.strictEqual(res.body.status, 'pending');
      txid = res.body.txid;
    });

    it('should retrieve transaction by txid', async () => {
      const res = await request('GET', `/api/tx/${txid}`);
      assert.strictEqual(res.status, 200);
      assert.strictEqual(res.body.txid, txid);
      assert.strictEqual(res.body.status, 'pending');
    });

    it('should retrieve user transaction history', async () => {
      const res = await request('GET', `/api/tx/user/${userId}`);
      assert.strictEqual(res.status, 200);
      assert.ok(Array.isArray(res.body.transactions));
      assert.ok(res.body.transactions.length > 0);
    });
  });

  describe('Credentials API', () => {
    let userId;

    before(async () => {
      const res = await request('POST', '/api/users');
      userId = res.body.id;
    });

    it('should register a device credential', async () => {
      const res = await request('POST', '/api/credentials', {
        userId,
        credentialId: 'test-credential-123',
        publicKey: 'test-public-key',
        deviceName: 'Test Device',
      });
      assert.strictEqual(res.status, 201);
      assert.ok(res.body.id);
    });

    it('should retrieve credential by ID', async () => {
      const res = await request('GET', '/api/credentials/test-credential-123');
      assert.strictEqual(res.status, 200);
      assert.strictEqual(res.body.credential_id, 'test-credential-123');
      assert.strictEqual(res.body.device_name, 'Test Device');
    });

    it('should reject duplicate credential registration', async () => {
      const res = await request('POST', '/api/credentials', {
        userId,
        credentialId: 'test-credential-123',
        publicKey: 'test-public-key',
      });
      assert.strictEqual(res.status, 409);
    });
  });
});
