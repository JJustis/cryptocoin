// shop.js - Complete shop functionality for CryptoCoin

// Debugging
const DEBUG = true;
const log = (...args) => DEBUG && console.log('SHOP:', ...args);

// DOM Elements
const elements = {
    productList: document.getElementById('productList'),
    walletBalance: document.getElementById('walletBalance'),
    errorContainer: document.getElementById('errorContainer')
};

// API Functions
const API = {
    baseUrl: '/cybercoin/main.php',
    
    async request(action, data = {}, method = 'POST') {
        const url = new URL(this.baseUrl, window.location.origin);
        url.searchParams.append('action', action);
        
        const options = {
            method,
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        };

        if (method === 'POST') {
            options.body = new URLSearchParams({...data, action});
        } else if (method === 'GET') {
            Object.entries(data).forEach(([key, value]) => url.searchParams.append(key, value));
        }

        try {
            const response = await fetch(url, options);
            const text = await response.text();
            log(`API Response (${action}):`, text);
            
            const result = JSON.parse(text);
            if (!result.success) {
                throw new Error(result.message || 'Unknown error occurred');
            }
            return result.data;
        } catch (error) {
            log(`API Error (${action}):`, error);
            throw error;
        }
    },

    getProducts: () => API.request('get_products', {}, 'GET'),
    getBalance: () => API.request('get_balance', {}, 'GET'),
    getProductDetails: (productId) => API.request('get_product_details', { product_id: productId }, 'GET'),
    buyProduct: (productId, email, ign) => API.request('buy_product', { product_id: productId, email, ign }),
};

// Shop Functions
const Shop = {
    async loadProducts() {
        try {
            const data = await API.getProducts();
            const products = Array.isArray(data) ? data : (data.products || []);
            
            if (products.length === 0) {
                elements.productList.innerHTML = '<p>No products available at the moment.</p>';
                return;
            }

            elements.productList.innerHTML = products.map(product => `
                <div class="product-item">
                    <h3>${escapeHtml(product.name)}</h3>
                    ${product.image ? `<img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" class="product-image">` : ''}
                    <p>${escapeHtml(product.description)}</p>
                    <p>Price: ${product.price} CryptoCoins</p>
                    <p>In Stock: ${product.stock}</p>
                    <button onclick="Shop.buyProduct(${product.id})">Buy Now</button>
                </div>
            `).join('');
        } catch (error) {
            this.showError('Failed to load products. Please try again later.');
        }
    },

    async updateBalance() {
        try {
            const data = await API.getBalance();
            const balance = typeof data === 'object' ? data.balance : data;
            elements.walletBalance.textContent = balance;
        } catch (error) {
            elements.walletBalance.textContent = 'Error loading balance';
        }
    },

    async getProductDetails(productId) {
        try {
            return await API.getProductDetails(productId);
        } catch (error) {
            this.showError(`Failed to get product details: ${error.message}`);
            throw error;
        }
    },

    async buyProduct(productId) {
        try {
            const product = await this.getProductDetails(productId);
            const email = prompt("Please enter your email for purchase confirmation:");
            if (!email) return;

            let ign = '';
            if (product.minecraft_command) {
                ign = prompt("Please enter your Minecraft In-Game Name (IGN):");
                if (!ign) return;
            }

            const result = await API.buyProduct(productId, email, ign);
if (result.script_url) {
   alert(`Purchase successful! You bought ${result.product}. Click OK to run the script.`);
   window.location.href = result.script_url;
} else if (result.redemption_url) {
   alert(`Purchase successful! You bought ${result.product}. The command will be executed for player: ${ign}`);
   window.location.href = result.redemption_url;
} else if (result.download_url) {
   alert(`Purchase successful! You bought ${result.product}. Click OK to download.`);
   window.location.href = result.download_url;
} else {
   alert(`Purchase successful! You bought ${result.product}. Transaction hash: ${result.transaction_hash}`);
}
await this.updateBalance();
await this.loadProducts(); // Refresh product list
        } catch (error) {
            this.showError(`Purchase failed: ${error.message}`);
        }
    },

    showError(message) {
        log('Error:', message);
        if (elements.errorContainer) {
            elements.errorContainer.textContent = message;
            elements.errorContainer.style.display = 'block';
        } else {
            alert(message);
        }
    },

    init() {
        log('Initializing shop...');
        this.loadProducts();
        this.updateBalance();
        log('Shop initialized');
    }
};

// Utility Functions
function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => Shop.init());

// Global error handler
window.onerror = function(message, source, lineno, colno, error) {
    log(`Global error: ${message} at ${source}:${lineno}:${colno}`);
    Shop.showError('An unexpected error occurred. Please try again later.');
    return false;
};

// Expose necessary functions to global scope
window.Shop = {
    buyProduct: (productId) => Shop.buyProduct(productId)
};

log('shop.js loaded successfully');