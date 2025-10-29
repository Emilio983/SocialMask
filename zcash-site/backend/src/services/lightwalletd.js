import grpc from '@grpc/grpc-js';
import protoLoader from '@grpc/proto-loader';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import fs from 'fs';
import crypto from 'crypto';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Lightwalletd gRPC service definitions
const PROTO_PATH = join(__dirname, '../proto/service.proto');

let client = null;
let cachedProto = null;

/**
 * Initialize gRPC client for lightwalletd
 */
export async function initLightwalletdClient() {
  if (client) return client;

  try {
    // Check if proto file exists, if not create it
    if (!fs.existsSync(PROTO_PATH)) {
      await createProtoFile();
    }

    const packageDefinition = protoLoader.loadSync(PROTO_PATH, {
      keepCase: true,
      longs: String,
      enums: String,
      defaults: true,
      oneofs: true
    });

    cachedProto = grpc.loadPackageDefinition(packageDefinition);

    const lightwalletdHost = process.env.LIGHTWALLETD_HOST || 'localhost';
    const lightwalletdPort = process.env.LIGHTWALLETD_PORT || '9067';
    const serverAddress = `${lightwalletdHost}:${lightwalletdPort}`;

    // Create insecure channel (lightwalletd in docker network)
    client = new cachedProto.cash.z.wallet.sdk.rpc.CompactTxStreamer(
      serverAddress,
      grpc.credentials.createInsecure()
    );

    console.log(`[Lightwalletd] Connected to ${serverAddress}`);
    return client;
  } catch (error) {
    console.error('[Lightwalletd] Failed to initialize client:', error);
    throw error;
  }
}

/**
 * Create proto file from inline definition
 */
async function createProtoFile() {
  const protoDir = dirname(PROTO_PATH);
  if (!fs.existsSync(protoDir)) {
    fs.mkdirSync(protoDir, { recursive: true });
  }

  // Minimal proto definition for CompactTxStreamer
  const protoContent = `
syntax = "proto3";

package cash.z.wallet.sdk.rpc;

service CompactTxStreamer {
    rpc GetLatestBlock(ChainSpec) returns (BlockID) {}
    rpc GetBlock(BlockID) returns (CompactBlock) {}
    rpc GetBlockRange(BlockRange) returns (stream CompactBlock) {}
    rpc GetTransaction(TxFilter) returns (RawTransaction) {}
    rpc SendTransaction(RawTransaction) returns (SendResponse) {}
    rpc GetTaddressTxids(TransparentAddressBlockFilter) returns (stream RawTransaction) {}
    rpc GetTaddressBalance(AddressList) returns (Balance) {}
    rpc GetTaddressBalanceStream(stream Address) returns (Balance) {}
    rpc GetMempoolTx(Exclude) returns (stream CompactTx) {}
    rpc GetMempoolStream(Empty) returns (stream RawTransaction) {}
    rpc GetTreeState(BlockID) returns (TreeState) {}
    rpc GetAddressUtxos(GetAddressUtxosArg) returns (GetAddressUtxosReplyList) {}
    rpc GetAddressUtxosStream(GetAddressUtxosArg) returns (stream GetAddressUtxosReply) {}
    rpc GetLightdInfo(Empty) returns (LightdInfo) {}
    rpc Ping(Duration) returns (PingResponse) {}
}

message ChainSpec {
}

message BlockID {
    uint64 height = 1;
    bytes hash = 2;
}

message BlockRange {
    BlockID start = 1;
    BlockID end = 2;
}

message TxFilter {
    BlockID block = 1;
    uint64 index = 2;
    bytes hash = 3;
}

message RawTransaction {
    bytes data = 1;
    uint64 height = 2;
}

message SendResponse {
    int32 errorCode = 1;
    string errorMessage = 2;
}

message TransparentAddressBlockFilter {
    Address address = 1;
    BlockRange range = 2;
}

message Address {
    string address = 1;
}

message AddressList {
    repeated string addresses = 1;
}

message Balance {
    int64 valueZat = 1;
}

message Exclude {
    repeated bytes txid = 1;
}

message Empty {
}

message TreeState {
    string network = 1;
    uint64 height = 2;
    string hash = 3;
    uint32 time = 4;
    string saplingTree = 5;
    string orchardTree = 6;
}

message GetAddressUtxosArg {
    repeated string addresses = 1;
    uint64 startHeight = 2;
    uint32 maxEntries = 3;
}

message GetAddressUtxosReply {
    bytes txid = 1;
    int32 index = 2;
    bytes script = 3;
    int64 valueZat = 4;
    uint64 height = 5;
}

message GetAddressUtxosReplyList {
    repeated GetAddressUtxosReply addressUtxos = 1;
}

message LightdInfo {
    string version = 1;
    string vendor = 2;
    bool taddrSupport = 3;
    string chainName = 4;
    uint64 saplingActivationHeight = 5;
    string consensusBranchId = 6;
    uint64 blockHeight = 7;
    string gitCommit = 8;
    string branch = 9;
    string buildDate = 10;
    string buildUser = 11;
    uint64 estimatedHeight = 12;
    string zcashdBuild = 13;
    string zcashdSubversion = 14;
}

message Duration {
}

message PingResponse {
    int64 entry = 1;
    int64 exit = 2;
}

message CompactBlock {
    uint32 protoVersion = 1;
    uint64 height = 2;
    bytes hash = 3;
    bytes prevHash = 4;
    uint32 time = 5;
    bytes header = 6;
    repeated CompactTx vtx = 7;
}

message CompactTx {
    uint64 index = 1;
    bytes hash = 2;
    uint32 fee = 3;
    repeated CompactSaplingSpend spends = 4;
    repeated CompactSaplingOutput outputs = 5;
    repeated CompactOrchardAction actions = 6;
}

message CompactSaplingSpend {
    bytes nf = 1;
}

message CompactSaplingOutput {
    bytes cmu = 1;
    bytes ephemeralKey = 2;
    bytes ciphertext = 3;
}

message CompactOrchardAction {
    bytes nullifier = 1;
    bytes cmx = 2;
    bytes ephemeralKey = 3;
    bytes ciphertext = 4;
}
`;

  fs.writeFileSync(PROTO_PATH, protoContent);
  console.log('[Lightwalletd] Created proto file:', PROTO_PATH);
}

/**
 * Get latest block from lightwalletd
 * @returns {Promise<{height: number, hash: string, time: number}>}
 */
export async function getLatestBlock() {
  const client = await initLightwalletdClient();

  return new Promise((resolve, reject) => {
    // Use GetLightdInfo to get latest block info
    client.GetLightdInfo({}, (error, response) => {
      if (error) {
        console.error('[Lightwalletd] GetLightdInfo error:', error);
        reject(error);
        return;
      }

      resolve({
        height: parseInt(response.blockHeight || response.height || 0),
        hash: response.blockHash ? Buffer.from(response.blockHash).toString('hex') : '0'.repeat(64),
        time: parseInt(response.time || Math.floor(Date.now() / 1000)),
        chainName: response.chainName || 'main',
        saplingActivationHeight: parseInt(response.saplingActivationHeight || 0),
      });
    });
  });
}

/**
 * Send raw transaction to network
 * @param {string} rawTxHex - Raw transaction hex
 * @returns {Promise<{success: boolean, txid: string, error?: string}>}
 */
export async function sendTransaction(rawTxHex) {
  const client = await initLightwalletdClient();

  return new Promise((resolve, reject) => {
    const rawTxData = Buffer.from(rawTxHex, 'hex');

    client.SendTransaction({ data: rawTxData }, (error, response) => {
      if (error) {
        console.error('[Lightwalletd] SendTransaction error:', error);
        reject(error);
        return;
      }

      if (response.errorCode !== 0) {
        resolve({
          success: false,
          error: response.errorMessage || 'Transaction rejected',
        });
        return;
      }

      // Calculate txid from raw tx
      const hash = crypto.createHash('sha256').update(rawTxData).digest();
      const txid = crypto.createHash('sha256').update(hash).digest('hex');

      resolve({
        success: true,
        txid,
      });
    });
  });
}

/**
 * Get transaction by txid
 * @param {string} txid - Transaction ID
 * @returns {Promise<{found: boolean, height?: number, data?: Buffer}>}
 */
export async function getTransaction(txid) {
  const client = await initLightwalletdClient();

  return new Promise((resolve, reject) => {
    const txidBuffer = Buffer.from(txid, 'hex');

    client.GetTransaction({ hash: txidBuffer }, (error, response) => {
      if (error) {
        // Not found is not an error in our case
        if (error.code === grpc.status.NOT_FOUND) {
          resolve({ found: false });
          return;
        }
        console.error('[Lightwalletd] GetTransaction error:', error);
        reject(error);
        return;
      }

      resolve({
        found: true,
        height: parseInt(response.height),
        data: Buffer.from(response.data),
      });
    });
  });
}

/**
 * Get lightwalletd info
 * @returns {Promise<Object>}
 */
export async function getLightdInfo() {
  const client = await initLightwalletdClient();

  return new Promise((resolve, reject) => {
    client.GetLightdInfo({}, (error, response) => {
      if (error) {
        console.error('[Lightwalletd] GetLightdInfo error:', error);
        reject(error);
        return;
      }

      resolve({
        version: response.version,
        vendor: response.vendor,
        chainName: response.chainName,
        blockHeight: parseInt(response.blockHeight),
        saplingActivationHeight: parseInt(response.saplingActivationHeight),
      });
    });
  });
}

/**
 * Get compact block range (for wallet sync)
 * @param {number} startHeight
 * @param {number} endHeight
 * @returns {AsyncGenerator<Object>}
 */
export async function* getBlockRange(startHeight, endHeight) {
  const client = await initLightwalletdClient();

  const stream = client.GetBlockRange({
    start: { height: startHeight },
    end: { height: endHeight },
  });

  for await (const block of stream) {
    yield {
      height: parseInt(block.height),
      hash: Buffer.from(block.hash).toString('hex'),
      time: block.time,
      vtx: block.vtx || [],
    };
  }
}

export default {
  initLightwalletdClient,
  getLatestBlock,
  sendTransaction,
  getTransaction,
  getLightdInfo,
  getBlockRange,
};
