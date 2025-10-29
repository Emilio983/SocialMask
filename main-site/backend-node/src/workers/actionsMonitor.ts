import { pool } from '../db/index.js';
import { getRelayTaskStatus } from '../services/userOperation.js';
import { logger } from '../utils/logger.js';
import { RowDataPacket } from 'mysql2/promise';

const POLL_INTERVAL_MS = 30000; // 30 segundos

/**
 * Worker que monitorea el estado de las acciones gasless en Gelato
 * Similar a swapMonitor pero para gasless_actions
 */
export async function startActionsMonitor(): Promise<void> {
  logger.info('Actions monitor started');

  // Función para procesar acciones pendientes
  const processPendingActions = async () => {
    try {
      // Buscar acciones pendientes (con task ID de Gelato)
      const [rows] = await pool.execute<RowDataPacket[]>(
        `SELECT id, user_id, relay_task_id, amount_wei, recipient, action_type, created_at
         FROM gasless_actions
         WHERE status = 'pending' AND relay_task_id IS NOT NULL
         ORDER BY created_at ASC
         LIMIT 50`
      );

      if (rows.length === 0) {
        logger.debug('No pending actions to monitor');
        return;
      }

      logger.info({ count: rows.length }, 'Monitoring pending actions');

      for (const row of rows) {
        try {
          const { id, relay_task_id, user_id, amount_wei, recipient, action_type } = row;

          // Consultar estado en Gelato
          const taskStatus = await getRelayTaskStatus(relay_task_id);
          
          if (!taskStatus || !taskStatus.taskState) {
            logger.warn({ actionId: id, taskId: relay_task_id }, 'Task status not available');
            continue;
          }

          if (taskStatus.taskState === 'ExecSuccess') {
            // Acción ejecutada exitosamente
            await pool.execute(
              `UPDATE gasless_actions
               SET status = 'executed', tx_hash = ?, executed_at = NOW(), updated_at = NOW()
               WHERE id = ?`,
              [taskStatus.transactionHash || null, id]
            );

            logger.info(
              {
                actionId: id,
                userId: user_id,
                taskId: relay_task_id,
                txHash: taskStatus.transactionHash,
                actionType: action_type,
                amount: amount_wei,
                recipient,
              },
              'Action executed successfully'
            );
          } else if (
            taskStatus.taskState === 'ExecReverted' ||
            taskStatus.taskState === 'Cancelled'
          ) {
            // Acción fallida o cancelada
            const failReason = taskStatus.lastCheckMessage || taskStatus.taskState;
            const newStatus = taskStatus.taskState === 'Cancelled' ? 'cancelled' : 'failed';

            await pool.execute(
              `UPDATE gasless_actions
               SET status = ?, fail_reason = ?, updated_at = NOW()
               WHERE id = ?`,
              [newStatus, failReason, id]
            );

            logger.warn(
              {
                actionId: id,
                userId: user_id,
                taskId: relay_task_id,
                taskState: taskStatus.taskState,
                failReason,
              },
              'Action failed or cancelled'
            );
          } else {
            // Todavía pendiente (CheckPending, WaitingForConfirmation, etc.)
            logger.debug(
              {
                actionId: id,
                taskId: relay_task_id,
                taskState: taskStatus.taskState,
              },
              'Action still pending'
            );
          }
        } catch (error) {
          logger.error(
            {
              err: error,
              actionId: row.id,
              taskId: row.relay_task_id,
            },
            'Error monitoring action'
          );
        }
      }
    } catch (error) {
      logger.error({ err: error }, 'Error in actions monitor cycle');
    }
  };

  // Procesar inmediatamente y luego cada intervalo
  await processPendingActions();
  setInterval(processPendingActions, POLL_INTERVAL_MS);

  logger.info({ intervalMs: POLL_INTERVAL_MS }, 'Actions monitor polling configured');
}
