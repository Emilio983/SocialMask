import { 
  getPendingWithdraws, 
  updateWithdrawStatus, 
  getWithdrawById,
  type WithdrawEvent 
} from '../services/withdrawEvents.js';
import { getRelayTaskStatus, submitSponsoredCall } from '../services/userOperation.js';
import { config } from '../config/index.js';
import { ethers, Interface } from 'ethers';
import { logger } from '../utils/logger.js';
import { pool } from '../db/index.js';

/**
 * Verifica el estado de un retiro (flujo de 2 pasos)
 * Paso 1: Swap SPHE→USDT
 * Paso 2: Transfer USDT→externa
 */
async function checkWithdrawStatus(row: WithdrawEvent) {
  try {
    // Si tiene swap_task_id, verificar primero el swap
    if (row.swap_task_id && row.swap_task_id !== row.transfer_task_id) {
      const swapStatus = await getRelayTaskStatus(row.swap_task_id);
      
      if (!swapStatus) {
        logger.warn({ id: row.id, swapTaskId: row.swap_task_id }, 'No swap status from Gelato');
        return;
      }

      // Si el swap aún está pending, esperar
      if (['ExecPending', 'CheckPending', 'WaitingForConfirmation'].includes(swapStatus.taskState)) {
        return;
      }

      // Si el swap falló, marcar todo como failed
      if (['ExecReverted', 'Cancelled'].includes(swapStatus.taskState)) {
        await updateWithdrawStatus(row.id!, 'failed', null, `Swap ${swapStatus.taskState}`);
        logger.error({ id: row.id, swapTaskId: row.swap_task_id, state: swapStatus.taskState }, 'Swap failed');
        return;
      }

      // Si el swap fue exitoso, ejecutar el transfer
      if (swapStatus.taskState === 'ExecSuccess') {
        logger.info({ id: row.id, swapHash: swapStatus.transactionHash }, 'Swap completed, initiating transfer');
        
        // Ejecutar transfer USDT→externa
        const transferTaskId = await executeUsdtTransfer(row);
        
        // Actualizar el transfer_task_id en la DB
        await pool.execute(
          'UPDATE withdraw_events SET transfer_task_id = ? WHERE id = ?',
          [transferTaskId, row.id]
        );
        
        logger.info({ id: row.id, transferTaskId }, 'USDT transfer submitted');
        return; // En la siguiente iteración verificará el transfer
      }
    }

    // Verificar el transfer_task_id (segunda fase o si no hay swap)
    const taskId = row.transfer_task_id;
    if (!taskId) {
      logger.warn({ id: row.id }, 'Withdraw event without transfer_task_id');
      return;
    }

    const transferStatus = await getRelayTaskStatus(taskId);
    if (!transferStatus) {
      logger.warn({ id: row.id, taskId }, 'No transfer status from Gelato');
      return;
    }

    const state = transferStatus.taskState;

    if (['ExecPending', 'CheckPending', 'WaitingForConfirmation'].includes(state)) {
      return; // Aún procesando
    }

    if (state === 'ExecSuccess') {
      await updateWithdrawStatus(
        row.id!,
        'executed',
        transferStatus.transactionHash ?? null,
        null
      );
      logger.info({ id: row.id, hash: transferStatus.transactionHash }, 'Withdraw completed successfully');
      return;
    }

    if (state === 'Cancelled') {
      await updateWithdrawStatus(row.id!, 'cancelled', null, 'Transfer cancelled');
      logger.warn({ id: row.id }, 'Transfer cancelled');
      return;
    }

    if (state === 'ExecReverted') {
      await updateWithdrawStatus(row.id!, 'failed', null, `Transfer ${state}`);
      logger.error({ id: row.id, state }, 'Transfer failed');
    }
  } catch (error) {
    logger.error({ err: error, withdrawId: row.id }, 'Error checking withdraw status');
  }
}

/**
 * Ejecuta la transferencia de USDT desde smart account→dirección externa
 */
async function executeUsdtTransfer(withdrawEvent: WithdrawEvent): Promise<string> {
  const usdtInterface = new Interface([
    'function transfer(address to, uint256 amount) returns (bool)'
  ]);

  const transferData = usdtInterface.encodeFunctionData('transfer', [
    withdrawEvent.external_address,
    withdrawEvent.amount_usdt_wei.toString()
  ]);

  const result = await submitSponsoredCall({
    target: config.tokens.usdt.address,
    data: transferData,
    value: '0'
  });

  return result.taskId;
}

export async function startWithdrawMonitor(intervalMs = 30_000) {
  logger.info('Starting withdraw monitor loop (2-phase: swap + transfer)');
  setInterval(async () => {
    try {
      const pending = await getPendingWithdraws();
      for (const item of pending) {
        await checkWithdrawStatus(item);
      }
    } catch (error) {
      logger.error({ error }, 'withdrawMonitor iteration failed');
    }
  }, intervalMs);
}

