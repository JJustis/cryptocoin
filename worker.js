importScripts('https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js');

let mining = false;
let currentBlock = null;
let difficulty = 4;

const gpuMiner = {
    gl: null,
    program: null,
    vao: null,

    init() {
        const canvas = new OffscreenCanvas(1024, 1024);
        this.gl = canvas.getContext('webgl2');
        if (!this.gl) return false;

        const vertexShader = `#version 300 es
            void main() {
                float x = float(gl_VertexID % 1024) / 1024.0;
                float y = float(gl_VertexID / 1024) / 1024.0;
                gl_Position = vec4(x * 2.0 - 1.0, y * 2.0 - 1.0, 0.0, 1.0);
                gl_PointSize = 1.0;
            }`;

        const fragmentShader = `#version 300 es
            precision highp float;
            precision highp int;
            
            uniform vec4 uBlockData;
            uniform int uDifficulty;
            out vec4 fragColor;

            uint ROTR(uint x, int n) {
                return (x >> uint(n)) | (x << uint(32 - n));
            }

            uint Ch(uint x, uint y, uint z) {
                return (x & y) ^ (~x & z);
            }

            uint Maj(uint x, uint y, uint z) {
                return (x & y) ^ (x & z) ^ (y & z);
            }

            uint Sigma0(uint x) {
                return ROTR(x, 2) ^ ROTR(x, 13) ^ ROTR(x, 22);
            }

            uint Sigma1(uint x) {
                return ROTR(x, 6) ^ ROTR(x, 11) ^ ROTR(x, 25);
            }

            uint sigma0(uint x) {
                return ROTR(x, 7) ^ ROTR(x, 18) ^ (x >> 3u);
            }

            uint sigma1(uint x) {
                return ROTR(x, 17) ^ ROTR(x, 19) ^ (x >> 10u);
            }

            uvec8 sha256(vec4 blockData, uint nonce) {
                uint w[64];
                uint K[64] = uint[64](
                    0x428a2f98u, 0x71374491u, 0xb5c0fbcfu, 0xe9b5dba5u,
                    0x3956c25bu, 0x59f111f1u, 0x923f82a4u, 0xab1c5ed5u,
                    0xd807aa98u, 0x12835b01u, 0x243185beu, 0x550c7dc3u,
                    0x72be5d74u, 0x80deb1feu, 0x9bdc06a7u, 0xc19bf174u,
                    0xe49b69c1u, 0xefbe4786u, 0x0fc19dc6u, 0x240ca1ccu,
                    0x2de92c6fu, 0x4a7484aau, 0x5cb0a9dcu, 0x76f988dau,
                    0x983e5152u, 0xa831c66du, 0xb00327c8u, 0xbf597fc7u,
                    0xc6e00bf3u, 0xd5a79147u, 0x06ca6351u, 0x14292967u,
                    0x27b70a85u, 0x2e1b2138u, 0x4d2c6dfcu, 0x53380d13u,
                    0x650a7354u, 0x766a0abbu, 0x81c2c92eu, 0x92722c85u,
                    0xa2bfe8a1u, 0xa81a664bu, 0xc24b8b70u, 0xc76c51a3u,
                    0xd192e819u, 0xd6990624u, 0xf40e3585u, 0x106aa070u,
                    0x19a4c116u, 0x1e376c08u, 0x2748774cu, 0x34b0bcb5u,
                    0x391c0cb3u, 0x4ed8aa4au, 0x5b9cca4fu, 0x682e6ff3u,
                    0x748f82eeu, 0x78a5636fu, 0x84c87814u, 0x8cc70208u,
                    0x90befffau, 0xa4506cebu, 0xbef9a3f7u, 0xc67178f2u
                );
                
                w[0] = floatBitsToUint(blockData.x);
                w[1] = floatBitsToUint(blockData.y);
                w[2] = floatBitsToUint(blockData.z);
                w[3] = nonce;
                
                for(int i = 4; i < 16; i++) {
                    w[i] = 0u;
                }
                
                for(int i = 16; i < 64; i++) {
                    w[i] = w[i-16] + sigma0(w[i-15]) + w[i-7] + sigma1(w[i-2]);
                }

                uint h[8] = uint[8](
                    0x6a09e667u, 0xbb67ae85u, 0x3c6ef372u, 0xa54ff53au,
                    0x510e527fu, 0x9b05688cu, 0x1f83d9abu, 0x5be0cd19u
                );

                uint a = h[0];
                uint b = h[1];
                uint c = h[2];
                uint d = h[3];
                uint e = h[4];
                uint f = h[5];
                uint g = h[6];
                uint h_ = h[7];

                for(int i = 0; i < 64; i++) {
                    uint t1 = h_ + Sigma1(e) + Ch(e, f, g) + K[i] + w[i];
                    uint t2 = Sigma0(a) + Maj(a, b, c);
                    h_ = g;
                    g = f;
                    f = e;
                    e = d + t1;
                    d = c;
                    c = b;
                    b = a;
                    a = t1 + t2;
                }

                return uvec8(
                    a + h[0],
                    b + h[1],
                    c + h[2],
                    d + h[3],
                    e + h[4],
                    f + h[5],
                    g + h[6],
                    h_ + h[7]
                );
            }

            bool checkDifficulty(uvec8 hash, int difficulty) {
                uint mask = uint(0xFFFFFFFF) << uint(32 - difficulty);
                return (hash.x & mask) == 0u;
            }

            void main() {
                uint nonce = uint(gl_FragCoord.x) + uint(gl_FragCoord.y) * 1024u;
                uvec8 hash = sha256(uBlockData, nonce);
                
                if (checkDifficulty(hash, uDifficulty)) {
                    fragColor = vec4(1.0, float(nonce) / 4294967295.0, 0.0, 1.0);
                } else {
                    fragColor = vec4(0.0, 0.0, 0.0, 1.0);
                }
            }`;

        this.program = this.createProgram(vertexShader, fragmentShader);
        if (!this.program) return false;

        // Create and bind VAO
        this.vao = this.gl.createVertexArray();
        this.gl.bindVertexArray(this.vao);
        
        return true;
    },

    createProgram(vsSource, fsSource) {
        const vs = this.compileShader(vsSource, this.gl.VERTEX_SHADER);
        const fs = this.compileShader(fsSource, this.gl.FRAGMENT_SHADER);
        if (!vs || !fs) return null;
        
        const program = this.gl.createProgram();
        this.gl.attachShader(program, vs);
        this.gl.attachShader(program, fs);
        this.gl.linkProgram(program);

        if (!this.gl.getProgramParameter(program, this.gl.LINK_STATUS)) {
            console.error('Program linking failed:', this.gl.getProgramInfoLog(program));
            return null;
        }

        return program;
    },

    compileShader(source, type) {
        const shader = this.gl.createShader(type);
        this.gl.shaderSource(shader, source);
        this.gl.compileShader(shader);

        if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
            console.error(`Shader compilation failed:`, this.gl.getShaderInfoLog(shader));
            return null;
        }

        return shader;
    },

    mine(block, difficulty) {
        this.gl.useProgram(this.program);
        this.gl.bindVertexArray(this.vao);
        
        const blockData = new Float32Array([
            block.index,
            block.timestamp,
            parseInt(block.previousHash.slice(0, 8), 16),
            0.0
        ]);
        
        const blockDataLoc = this.gl.getUniformLocation(this.program, 'uBlockData');
        const difficultyLoc = this.gl.getUniformLocation(this.program, 'uDifficulty');
        
        this.gl.uniform4fv(blockDataLoc, blockData);
        this.gl.uniform1i(difficultyLoc, difficulty);

        this.gl.drawArrays(this.gl.POINTS, 0, 1024 * 1024);

        const pixels = new Uint8Array(4);
        this.gl.readPixels(0, 0, 1, 1, this.gl.RGBA, this.gl.UNSIGNED_BYTE, pixels);

        return {
            found: pixels[0] > 0,
            nonce: pixels[1] | (pixels[2] << 8) | (pixels[3] << 16)
        };
    }
};

function calculateHash(index, previousHash, timestamp, transactions, nonce) {
    const data = index + previousHash + timestamp + JSON.stringify(transactions) + nonce;
    return CryptoJS.SHA256(data).toString();
}

function mineBlock(block) {
    if (!gpuMiner.gl) {
        if (!gpuMiner.init()) {
            console.error('GPU initialization failed');
            return null;
        }
    }

    const startTime = Date.now();
    let totalHashes = 0;

    while (mining) {
        const result = gpuMiner.mine(block, difficulty);
        totalHashes += 1024 * 1024;

        if (result.found) {
            const hash = calculateHash(
                block.index,
                block.previousHash,
                block.timestamp,
                block.transactions,
                result.nonce
            );
            return { nonce: result.nonce, hash };
        }

        if (totalHashes % (1024 * 1024 * 10) === 0) {
            const timeTaken = (Date.now() - startTime) / 1000;
            self.postMessage({ 
                action: 'miningProgress', 
                nonce: totalHashes, 
                timeTaken, 
                hashesPerSecond: Math.floor(totalHashes / timeTaken)
            });
        }
    }

    return null;
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