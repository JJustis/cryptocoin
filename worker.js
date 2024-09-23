importScripts('https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js');

let mining = false;
let currentBlock = null;
let difficulty = 4;

function calculateHash(index, previousHash, timestamp, transactions, nonce) {
    return CryptoJS.SHA256(index + previousHash + timestamp + JSON.stringify(transactions) + nonce).toString();
}

function mineBlock(block) {
    let nonce = block.nonce || 0;
    let hash = calculateHash(block.index, block.previousHash, block.timestamp, block.transactions, nonce);
    const startTime = Date.now();

    while (mining && hash.substring(0, difficulty) !== Array(difficulty + 1).join("0")) {
        nonce++;
        hash = calculateHash(block.index, block.previousHash, block.timestamp, block.transactions, nonce);

        if (nonce % 10000 === 0) {
            const currentTime = Date.now();
            const timeTaken = (currentTime - startTime) / 1000;
            const hashesPerSecond = Math.floor(nonce / timeTaken);
            self.postMessage({ action: 'miningProgress', nonce: nonce, timeTaken: timeTaken, hashesPerSecond: hashesPerSecond });
        }
    }

    if (mining) {
        return { nonce, hash };
    } else {
        return null;
    }
}

self.onmessage = function(e) {
    switch (e.data.action) {
        case 'start':
            mining = true;
            difficulty = e.data.difficulty;
            currentBlock = e.data.block;
            const result = mineBlock(currentBlock);
            if (result) {
                self.postMessage({ action: 'blockMined', ...result });
            }
            break;
        case 'stop':
            mining = false;
            self.postMessage({ action: 'miningStopped', currentBlock });
            break;
        case 'getState':
            self.postMessage({ action: 'workerState', mining, currentBlock });
            break;
    }
};