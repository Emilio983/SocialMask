import pg from 'pg';
const { Pool } = pg;

const pool = new Pool({
  connectionString: 'postgresql://wallet:wallet_secure_password@localhost:5432/wallet'
});

async function cleanup() {
  try {
    console.log('Cleaning up ALL data (testnet + any old data)...');

    // Clear all tables with RESTART IDENTITY
    await pool.query('TRUNCATE TABLE tx_log RESTART IDENTITY CASCADE');
    console.log('✓ Truncated tx_log');

    await pool.query('TRUNCATE TABLE wallets RESTART IDENTITY CASCADE');
    console.log('✓ Truncated wallets');

    await pool.query('TRUNCATE TABLE device_credentials RESTART IDENTITY CASCADE');
    console.log('✓ Truncated device_credentials');

    await pool.query('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
    console.log('✓ Truncated users');

    console.log('\n✅ All data cleaned');

    // Verify all tables are empty
    const users = await pool.query('SELECT COUNT(*) FROM users');
    const creds = await pool.query('SELECT COUNT(*) FROM device_credentials');
    const wallets = await pool.query('SELECT COUNT(*) FROM wallets');
    const txs = await pool.query('SELECT COUNT(*) FROM tx_log');

    console.log('\n=== Database Status ===');
    console.log(`users: ${users.rows[0].count}`);
    console.log(`device_credentials: ${creds.rows[0].count}`);
    console.log(`wallets: ${wallets.rows[0].count}`);
    console.log(`tx_log: ${txs.rows[0].count}`);
    console.log('======================');

  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  } finally {
    await pool.end();
  }
}

cleanup();
