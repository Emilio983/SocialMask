import { RowDataPacket } from 'mysql2/promise';
import { fetchPendingSweeps, retryRotatingSweep, updateSweepStatus } from '../services/rotatingSweep.js';
import { getRelayTaskStatus } from '../services/userOperation.js';
import { logger } from '../utils/logger.js';

async function checkSweep(row: RowDataPacket) {
  const taskId = row.task_id as string;
  if (!taskId) {
    logger.warn({ id: row.id }, 'Rotating sweep without taskId');
    return;
  }

  try {
    const status = await getRelayTaskStatus(taskId);
    if (!status) {
      logger.warn({ id: row.id, taskId }, 'No status returned for rotating sweep');
      return;
    }

    const state = status.taskState;

    if (state === 'ExecPending' || state === 'CheckPending' || state === 'WaitingForConfirmation') {
      return;
    }

    if (state === 'ExecSuccess') {
      await updateSweepStatus({ taskId, status: 'executed' });
      logger.info({ taskId }, 'Rotating sweep executed');
      return;
    }

    if (state === 'Cancelled' || state === 'ExecReverted') {
      const newStatus = state === 'Cancelled' ? 'cancelled' : 'failed';
      await updateSweepStatus({ taskId, status: newStatus, failReason: state });
      logger.error({ taskId, state }, 'Rotating sweep did not complete');

      if (row.rotating_address_id) {
        try {
          const retryTask = await retryRotatingSweep(Number(row.rotating_address_id));
          if (retryTask) {
            logger.info({ previousTaskId: taskId, retryTask }, 'Rotating sweep retry scheduled');
          }
        } catch (error) {
          logger.error({ error, taskId, rotatingAddressId: row.rotating_address_id }, 'Failed to resubmit rotating sweep');
        }
      }
      return;
    }
  } catch (error) {
    logger.error({ error, taskId }, 'Error checking rotating sweep status');
  }
}

export function startRotatingSweepMonitor(intervalMs = 30_000) {
  logger.info('Starting rotating sweep monitor');
  setInterval(async () => {
    try {
      const pending = await fetchPendingSweeps();
      for (const item of pending) {
        await checkSweep(item);
      }
    } catch (error) {
      logger.error({ error }, 'rotatingSweepMonitor iteration failed');
    }
  }, intervalMs);
}
