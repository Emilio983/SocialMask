import mysql, { PoolConnection } from 'mysql2/promise';
import { config } from '../config/index.js';
import { logger } from '../utils/logger.js';

export const pool = mysql.createPool({
  host: config.database.host,
  port: config.database.port,
  user: config.database.user,
  password: config.database.password,
  database: config.database.name,
  waitForConnections: true,
  connectionLimit: 10,
  charset: 'utf8mb4',
});

export async function withTransaction<T>(handler: (conn: PoolConnection) => Promise<T>): Promise<T> {
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();
    const result = await handler(connection);
    await connection.commit();
    return result;
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}

pool.on('connection', () => {
  logger.debug('MySQL connection established');
});
