import cron from 'node-cron';
import { pool } from '../models/db.js';
import { getTransaction, getLatestBlock } from './bridge-client.js';

// Update transaction statuses (check for confirmations)
const updateTransactionStatuses = async () => {
  try {
    console.log('[CRON] Checking transaction statuses...');

    // Get all pending transactions older than 1 minute
    const result = await pool.query(
      `SELECT id, txid, user_id, created_at FROM tx_log
       WHERE status = 'pending'
       AND created_at < NOW() - INTERVAL '1 minute'`
    );

    const pendingTxs = result.rows;
    console.log(`[CRON] Found ${pendingTxs.length} pending transactions`);

    if (pendingTxs.length === 0) {
      return;
    }

    // Get current block height
    const latestBlock = await getLatestBlock();
    const currentHeight = latestBlock.height;

    // Check each transaction
    for (const tx of pendingTxs) {
      try {
        // Try to get transaction info from bridge
        const txInfo = await getTransaction(tx.txid);

        if (txInfo && txInfo.height) {
          // Transaction found and confirmed
          const confirmations = currentHeight - txInfo.height + 1;

          await pool.query(
            `UPDATE tx_log
             SET status = 'confirmed',
                 block_height = $1,
                 confirmations = $2,
                 updated_at = NOW()
             WHERE id = $3`,
            [txInfo.height, confirmations, tx.id]
          );

          console.log(`[CRON] ✅ Transaction ${tx.txid.substring(0, 8)}... confirmed at height ${txInfo.height} (${confirmations} confirmations)`);
        } else {
          // Transaction not found yet - keep as pending
          console.log(`[CRON] ⏳ Transaction ${tx.txid.substring(0, 8)}... still pending`);
        }
      } catch (error) {
        // If transaction is older than 1 hour and still failing, mark as failed
        const ageMinutes = (Date.now() - new Date(tx.created_at).getTime()) / 1000 / 60;

        if (ageMinutes > 60) {
          await pool.query(
            `UPDATE tx_log SET status = 'failed', updated_at = NOW() WHERE id = $1`,
            [tx.id]
          );
          console.log(`[CRON] ❌ Transaction ${tx.txid.substring(0, 8)}... marked as failed (${ageMinutes.toFixed(0)}min old)`);
        } else {
          console.log(`[CRON] ⏳ Transaction ${tx.txid.substring(0, 8)}... error checking status: ${error.message}`);
        }
      }
    }

    console.log('[CRON] Transaction status update complete');
  } catch (error) {
    console.error('[CRON] Error updating transaction statuses:', error);
  }
};

// Start all cron jobs
export const startCronJobs = () => {
  // Run every 5 minutes
  cron.schedule('*/5 * * * *', updateTransactionStatuses);

  console.log('[CRON] Background jobs started');
};

export default {
  startCronJobs,
  updateTransactionStatuses,
};
