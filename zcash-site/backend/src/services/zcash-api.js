// Simple REST API fallback for Zcash blockchain info
import https from 'https';

export async function getLatestBlockREST() {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: 'api.blockchair.com',
      path: '/zcash/stats',
      method: 'GET',
      timeout: 5000
    };
    
    const req = https.request(options, (res) => {
      let data = '';
      
      res.on('data', (chunk) => {
        data += chunk;
      });
      
      res.on('end', () => {
        try {
          const parsed = JSON.parse(data);
          if (parsed.data && parsed.data.blocks) {
            resolve({
              height: parsed.data.blocks,
              hash: '0'.repeat(64),
              time: Math.floor(Date.now() / 1000),
            });
          } else {
            throw new Error('Invalid response format');
          }
        } catch (error) {
          console.error('[Zcash API] REST fallback error:', error.message);
          resolve(getEstimatedBlock());
        }
      });
    });
    
    req.on('error', (error) => {
      console.error('[Zcash API] REST request error:', error.message);
      resolve(getEstimatedBlock());
    });
    
    req.on('timeout', () => {
      console.error('[Zcash API] REST request timeout');
      req.destroy();
      resolve(getEstimatedBlock());
    });
    
    req.end();
  });
}

function getEstimatedBlock() {
  // Zcash mainnet launched Oct 28, 2016, ~75 sec block time
  const LAUNCH_HEIGHT = 1;
  const LAUNCH_TIME = new Date('2016-10-28').getTime() / 1000;
  const BLOCK_TIME = 75; // seconds
  const currentTime = Math.floor(Date.now() / 1000);
  const estimatedHeight = LAUNCH_HEIGHT + Math.floor((currentTime - LAUNCH_TIME) / BLOCK_TIME);
  
  return {
    height: estimatedHeight,
    hash: '0'.repeat(64),
    time: currentTime,
    _estimated: true,
  };
}
