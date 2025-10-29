/**
 * ============================================
 * WEBSOCKET SIGNALING SERVICE
 * ============================================
 * WebSocket server for WebRTC signaling
 * Replaces Gun.js for real-time communication
 */

import { WebSocketServer, WebSocket } from 'ws';
import { IncomingMessage } from 'http';

interface Client {
  ws: WebSocket;
  userId: string;
  lastSeen: number;
}

export class WebSocketSignalingService {
  private wss: WebSocketServer;
  private clients: Map<string, Client> = new Map();

  constructor(server: any) {
    this.wss = new WebSocketServer({ 
      server,
      path: '/ws/signaling'
    });

    this.wss.on('connection', (ws: WebSocket, req: IncomingMessage) => {
      console.log('New WebSocket connection');

      ws.on('message', (data: Buffer) => {
        try {
          const message = JSON.parse(data.toString());
          this.handleMessage(ws, message);
        } catch (error) {
          console.error('Error parsing WebSocket message:', error);
        }
      });

      ws.on('close', () => {
        this.handleDisconnect(ws);
      });

      ws.on('error', (error) => {
        console.error('WebSocket error:', error);
      });
    });

    // Cleanup inactive clients every 30 seconds
    setInterval(() => this.cleanupInactive(), 30000);

    console.log('✅ WebSocket signaling server started on /ws/signaling');
  }

  /**
   * Handle incoming WebSocket message
   */
  private handleMessage(ws: WebSocket, message: any) {
    switch (message.type) {
      case 'register':
        this.registerClient(ws, message.userId);
        break;

      case 'offer':
      case 'answer':
      case 'ice-candidate':
      case 'end-call':
        this.relaySignal(message);
        break;

      case 'ping':
        this.handlePing(ws, message.userId);
        break;

      default:
        console.warn('Unknown message type:', message.type);
    }
  }

  /**
   * Register a client
   */
  private registerClient(ws: WebSocket, userId: string) {
    // Remove old connection if exists
    const existing = this.clients.get(userId);
    if (existing && existing.ws !== ws) {
      existing.ws.close();
    }

    this.clients.set(userId, {
      ws,
      userId,
      lastSeen: Date.now()
    });

    ws.send(JSON.stringify({
      type: 'registered',
      userId,
      timestamp: Date.now()
    }));

    console.log(`Client registered: ${userId} (total: ${this.clients.size})`);
  }

  /**
   * Relay signaling message to recipient
   */
  private relaySignal(message: any) {
    const { to, from, type, data } = message;

    const recipient = this.clients.get(to);
    if (recipient && recipient.ws.readyState === WebSocket.OPEN) {
      recipient.ws.send(JSON.stringify({
        type,
        from,
        data,
        timestamp: Date.now()
      }));

      console.log(`Relayed ${type} from ${from} to ${to}`);
    } else {
      console.warn(`Recipient ${to} not connected`);
      
      // Send error back to sender
      const sender = this.clients.get(from);
      if (sender && sender.ws.readyState === WebSocket.OPEN) {
        sender.ws.send(JSON.stringify({
          type: 'error',
          message: 'Recipient not online',
          originalType: type
        }));
      }
    }
  }

  /**
   * Handle ping (keep-alive)
   */
  private handlePing(ws: WebSocket, userId: string) {
    const client = this.clients.get(userId);
    if (client) {
      client.lastSeen = Date.now();
    }

    ws.send(JSON.stringify({
      type: 'pong',
      timestamp: Date.now()
    }));
  }

  /**
   * Handle client disconnect
   */
  private handleDisconnect(ws: WebSocket) {
    for (const [userId, client] of this.clients.entries()) {
      if (client.ws === ws) {
        this.clients.delete(userId);
        console.log(`Client disconnected: ${userId} (remaining: ${this.clients.size})`);
        break;
      }
    }
  }

  /**
   * Cleanup inactive clients
   */
  private cleanupInactive() {
    const now = Date.now();
    const timeout = 5 * 60 * 1000; // 5 minutes

    for (const [userId, client] of this.clients.entries()) {
      if (now - client.lastSeen > timeout) {
        console.log(`Removing inactive client: ${userId}`);
        client.ws.close();
        this.clients.delete(userId);
      }
    }
  }

  /**
   * Get connected clients count
   */
  getClientsCount(): number {
    return this.clients.size;
  }

  /**
   * Check if user is online
   */
  isUserOnline(userId: string): boolean {
    return this.clients.has(userId);
  }

  /**
   * Broadcast message to all clients
   */
  broadcast(message: any) {
    const data = JSON.stringify(message);
    
    for (const client of this.clients.values()) {
      if (client.ws.readyState === WebSocket.OPEN) {
        client.ws.send(data);
      }
    }
  }

  /**
   * Close server
   */
  close() {
    this.wss.close();
  }
}
