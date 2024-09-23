// Debug logging function
const debugLog = message => console.log(`DEBUG: ${message}`);

// Global variables
let currentUser = null;
let walletAddress = null;
let mining = false;
let hashingChart = null;
let currentDifficulty = 1;
let hashingRate = 0;
let lastMiningTime = 0;
let hashRateHistory = [];

// DOM Elements
const elements = {};

// Helper Functions
const getElement = id => {
    const element = document.getElementById(id);
    if (!element) debugLog(`Element '${id}' is missing`);
    return element;
};

function fetchTrackingMessages() {
    fetch('main.php?action=get_tracking_messages')
        .then(response => response.json())
        .then(result => {
            console.log('Server response:', result); // Keep this for debugging
            if (result.success) {
                const trackingMessagesDisplay = document.getElementById('trackingMessagesDisplay');
                trackingMessagesDisplay.innerHTML = '<h4>Purchase History & Tracking</h4>';
                
                if (result.data.purchases && result.data.purchases.length > 0) {
                    result.data.purchases.forEach(purchase => {
                        const purchaseElement = document.createElement('div');
                        purchaseElement.className = 'purchase-item';
                        purchaseElement.innerHTML = `
                            <h5>${purchase.product.name}</h5>
                            <p>Purchase Date: ${new Date(purchase.date).toLocaleString()}</p>
                            <p>Price: ${purchase.product.price} CryptoCoins</p>
                            <p>Type: ${purchase.product.is_virtual ? 'Virtual' : 'Physical'}</p>
                            <p>Description: ${purchase.product.description}</p>
                            <p>Stock at time of purchase: ${purchase.product.stock}</p>
                            <p>Product Created: ${new Date(purchase.product.created_at).toLocaleString()}</p>
                            <p>Product Last Updated: ${new Date(purchase.product.updated_at).toLocaleString()}</p>
                            <p>Email: ${purchase.email}</p>
                            <p>Transaction Hash: ${purchase.transaction_hash}</p>
                            ${purchase.product.is_virtual && purchase.product.download_file ? `<p>Download: <a href="${purchase.product.download_file}" target="_blank">Download File</a></p>` : ''}
                            ${purchase.tracking_message ? `<div class="tracking-message">Tracking: ${purchase.tracking_message}</div>` : '<p>No tracking information available.</p>'}
                        `;
                        if (purchase.product.image) {
                            const img = document.createElement('img');
                            img.src = purchase.product.image;
                            img.alt = purchase.product.name;
                            img.style.maxWidth = '100px';
                            img.style.maxHeight = '100px';
                            purchaseElement.insertBefore(img, purchaseElement.firstChild);
                        }
                        trackingMessagesDisplay.appendChild(purchaseElement);
                    });
                } else {
                    trackingMessagesDisplay.innerHTML += '<p>No purchase history available.</p>';
                }
            } else {
                console.error('Failed to fetch purchase details:', result.message);
            }
        })
        .catch(error => {
            console.error('Error fetching purchase details:', error);
        });
}
// Call fetchTrackingMessages after successful login
// API Functions

const apiRequest = async (action, data = {}, method = 'POST') => {

    debugLog(`API Request: ${method} ${action}`);

    let url = 'main.php';

    const options = {

        method: method,

        headers: {'Content-Type': 'application/x-www-form-urlencoded'},

    };



    if (method === 'POST') {

        const formData = new URLSearchParams();

        formData.append('action', action);

        for (const [key, value] of Object.entries(data)) {

            formData.append(key, value);

        }

        options.body = formData;

    } else if (method === 'GET') {

        const params = new URLSearchParams({action, ...data});

        url += '?' + params.toString();

    }



    try {

        const response = await fetch(url, options);

        const rawText = await response.text();

        debugLog(`Raw API Response for ${action}:`, rawText);

        

        let result;

        try {

            result = JSON.parse(rawText);

        } catch (parseError) {

            throw new Error(`Failed to parse JSON: ${parseError.message}. Raw response: ${rawText}`);

        }



        if (!result.success) {

            throw new Error(result.message || `Request failed with status: ${response.status}`);

        }

        return result.data;

    } catch (error) {

        debugLog(`API Request error for ${action}: ${error.message}`);

        throw error;

    }

};



// User Authentication Functions

const login = async () => {

    const username = elements.usernameInput.value;

    const password = elements.pinInput.value;

    try {

        const data = await apiRequest('login', { username, password });

        currentUser = data.username;

        walletAddress = data.walletAddress;

        elements.loginPanel.style.display = 'none';

        elements.mainContent.style.display = 'block';

        await updateUI();

        debugLog('Login successful');
		fetchTrackingMessages();

    } catch (error) {

        debugLog(`Login failed: ${error.message}`);

        alert('Login failed: ' + error.message);

    }

};



const register = async () => {

    const username = elements.usernameInput.value;

    const password = elements.pinInput.value;

    try {

        await apiRequest('register', { username, password });

        alert('Registration successful. You can now login.');

        debugLog('Registration successful');

    } catch (error) {

        debugLog(`Registration failed: ${error.message}`);

        alert('Registration failed: ' + error.message);

    }

};



const logout = async () => {

    try {

        await apiRequest('logout');

        currentUser = null;

        walletAddress = null;

        elements.loginPanel.style.display = 'block';

        elements.mainContent.style.display = 'none';

        debugLog('Logout successful');

    } catch (error) {

        debugLog(`Logout failed: ${error.message}`);

        alert('Logout failed: ' + error.message);

    }

};



// Blockchain and Wallet Functions

const getBlockchain = async () => {

    try {

        return await apiRequest('get_blockchain', {}, 'GET');

    } catch (error) {

        debugLog(`Failed to get blockchain: ${error.message}`);

        return { blocks: [], currentDifficulty: 0, remainingBlocks: 0 };

    }

};



const getBalance = async () => {

    try {

        const data = await apiRequest('get_balance', {}, 'GET');

        return data.balance;

    } catch (error) {

        debugLog(`Failed to get balance: ${error.message}`);

        return 0;

    }

};



// UI Update Functions

const updateUI = async () => {

    const blockchainData = await getBlockchain();

    const balance = await getBalance();

    

    if (elements.walletAddressDisplay) {

        elements.walletAddressDisplay.textContent = `Address: ${walletAddress}`;

    }

    if (elements.walletBalanceDisplay) {

        elements.walletBalanceDisplay.textContent = `Balance: ${balance} CryptoCoins`;

    }

    

    updateBlockchainDisplay(blockchainData.blocks);

    updateTransactionHistory(blockchainData.blocks);

    updateDifficultyDisplay(blockchainData.currentDifficulty);

    updateRemainingBlocksDisplay(blockchainData.remainingBlocks);

    await updateListingsDisplay();

};



const updateBlockchainDisplay = (blocks) => {

    if (elements.recentBlocksDisplay) {

        elements.recentBlocksDisplay.innerHTML = blocks.slice(-5).reverse().map(block => `

            <div class="block">

                <div class="block-header">Block #${block.index}</div>

                <div class="block-content">

                    <strong>Hash:</strong> ${block.hash.substring(0, 20)}...<br>

                    <strong>Previous Hash:</strong> ${block.previousHash.substring(0, 20)}...<br>

                    <strong>Transactions:</strong> ${block.transactions.length}<br>

                    <strong>Timestamp:</strong> ${new Date(block.timestamp * 1000).toLocaleString()}<br>

                    <strong>Nonce:</strong> ${block.nonce}<br>

                    <strong>Difficulty:</strong> ${Number(block.difficulty).toFixed(2)}

                </div>

            </div>

        `).join('');

    }

};



const updateTransactionHistory = (blocks) => {

    if (elements.transactionHistoryDisplay) {

        const transactions = blocks.flatMap(block => 

            block.transactions.filter(trans => 

                trans.fromAddress === walletAddress || trans.toAddress === walletAddress

            )

        );

        elements.transactionHistoryDisplay.innerHTML = transactions.map(trans => `

            <div class="transaction-item">

                ${trans.fromAddress === walletAddress ? 'Sent to: ' : 'Received from: '}

                ${trans.fromAddress === walletAddress ? trans.toAddress : (trans.fromAddress || 'Mining Reward')}

                Amount: ${trans.amount} CryptoCoins

                ${trans.message ? `<br>Message: ${trans.message}` : ''}

            </div>

        `).join('');

    }

};



const updateDifficultyDisplay = (difficulty) => {

    if (elements.difficultyDisplay) {

        currentDifficulty = Number(difficulty);

        elements.difficultyDisplay.textContent = `Current Difficulty: ${currentDifficulty.toFixed(2)}`;

    }

};



const updateRemainingBlocksDisplay = (remainingBlocks) => {

    if (elements.remainingBlocksDisplay) {

        elements.remainingBlocksDisplay.textContent = `Remaining Blocks: ${Number(remainingBlocks)}`;

    }

};



// Mining Functions

// Mining Functions
const toggleMining = () => {
    if (!mining) {
        mining = true;
        elements.mineButton.textContent = 'Stop Mining';
        elements.statusDisplay.textContent = 'Mining in progress...';
        lastMiningTime = Date.now();
        hashRateHistory = [];
        continuousMining();
        startHashingSimulation();
    } else {
        mining = false;
        elements.mineButton.textContent = 'Start Mining';
        elements.statusDisplay.textContent = 'Mining stopped';
        stopHashingSimulation();
    }
};



const continuousMining = async () => {
    if (mining) {
        try {
            const startTime = Date.now();
            const result = await apiRequest('mine');
            const endTime = Date.now();
            const miningDuration = (endTime - startTime) / 1000; // in seconds

            // Calculate hashing rate based on difficulty and time taken
            const hashesPerformed = 2 ** currentDifficulty;
            hashingRate = hashesPerformed / miningDuration;

            await updateUI();
            debugLog(`Block mined successfully. New balance: ${result.newBalance}`);
            updateDifficultyDisplay(result.currentDifficulty);
            updateRemainingBlocksDisplay(result.remainingBlocks);

            lastMiningTime = Date.now();
        } catch (error) {
            debugLog(`Mining failed: ${error.message}`);
            alert('Mining failed: ' + error.message);
            mining = false;
            elements.mineButton.textContent = 'Start Mining';
            elements.statusDisplay.textContent = 'Mining stopped due to an error';
            stopHashingSimulation();
        }
        if (mining) {
            setTimeout(continuousMining, 100); // Reduced delay for more frequent updates
        }
    }
};



// Transaction Functions

const sendTransaction = async () => {

    const toAddress = elements.recipientAddressInput.value;

    const amount = parseFloat(elements.amountInput.value);

    const message = elements.transactionMessageInput.value;



    try {

        const result = await apiRequest('create_transaction', { toAddress, amount, message });

        elements.transactionStatusDisplay.textContent = 'Transaction processed successfully.';

        elements.recipientAddressInput.value = '';

        elements.amountInput.value = '';

        elements.transactionMessageInput.value = '';

        

        if (elements.walletBalanceDisplay) {

            elements.walletBalanceDisplay.textContent = `Balance: ${result.newBalance} CryptoCoins`;

        }

        

        await updateUI();

        debugLog('Transaction sent and processed successfully');

    } catch (error) {

        elements.transactionStatusDisplay.textContent = 'Error: ' + error.message;

        debugLog(`Transaction error: ${error.message}`);

    }

};



// Hashing Animation Functions

// Hashing Animation Functions
let hashingSimulationInterval;

const startHashingSimulation = () => {
    hashingSimulationInterval = setInterval(() => {
        if (mining) {
            const elapsedTime = (Date.now() - lastMiningTime) / 1000; // in seconds
            const estimatedHashes = Math.floor(hashingRate * elapsedTime);
            updateHashingAnimation(hashingRate);
        }
    }, 1000); // Update every second
};



const stopHashingSimulation = () => {

    clearInterval(hashingSimulationInterval);

};



const initHashingAnimation = () => {
    debugLog('Initializing hashing animation');
    const ctx = elements.hashingAnimation.getContext('2d');
    hashingChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Hashes per Second',
                data: [],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hashes per Second'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Time'
                    }
                }
            }
        }
    });
};


const updateHashingAnimation = (hashesPerSecond) => {
    if (!hashingChart) return;
    const now = new Date();
    hashingChart.data.labels.push(now.toLocaleTimeString());
    hashingChart.data.datasets[0].data.push(hashesPerSecond);
    
    if (hashingChart.data.labels.length > 20) {
        hashingChart.data.labels.shift();
        hashingChart.data.datasets[0].data.shift();
    }
    
    hashingChart.update();
    debugLog(`Updated hashing animation: ${hashesPerSecond.toFixed(2)} H/s`);
};



// Listing Functions

const updateListingsDisplay = async () => {

    if (elements.allListingsDisplay) {

        try {

            debugLog('Fetching listings...');

            const response = await apiRequest('get_listings', {}, 'GET');

            debugLog(`Received listings response:`, response);



            let listings = [];

            if (Array.isArray(response)) {

                listings = response;

            } else if (response && typeof response === 'object' && Array.isArray(response.listings)) {

                listings = response.listings;

            } else {

                throw new Error('Unexpected response format from get_listings');

            }



            debugLog(`Processing ${listings.length} listings`);

            

            if (listings.length === 0) {

                elements.allListingsDisplay.innerHTML = '<p>No listings available at the moment.</p>';

            } else {

                elements.allListingsDisplay.innerHTML = listings.map(listing => `

                    <div class="listing-item">

                        <div>Amount: ${listing.amount} coins</div>

                        <div>Price: $${listing.price}</div>

                        <div>Seller: ${listing.seller_username || 'Unknown'}</div>

                        <button onclick="safeBuyListing(${listing.id})">Buy</button>

                    </div>

                `).join('');

            }

        } catch (error) {

            debugLog(`Failed to fetch or process listings: ${error.message}`);

            elements.allListingsDisplay.innerHTML = '<p>Failed to load listings. Please try again later.</p>';

        }

    } else {

        debugLog('allListingsDisplay element not found');

    }

};



const createListing = async () => {

    const amount = elements.listingAmountInput.value;

    const price = elements.listingPriceInput.value;

    const paypalEmail = elements.listingPaypalEmailInput.value;

    

    if (!amount || !price || !paypalEmail) {

        alert('Please fill in all fields for the listing.');

        return;

    }



    try {

        debugLog('Creating new listing...');

        await apiRequest('create_listing', { amount, price, paypalEmail });

        alert('Listing created successfully');

        await updateListingsDisplay();

        elements.listingAmountInput.value = '';

        elements.listingPriceInput.value = '';

        elements.listingPaypalEmailInput.value = '';

        debugLog('Listing created successfully');

    } catch (error) {

        debugLog(`Failed to create listing: ${error.message}`);

        alert('Failed to create listing: ' + error.message);

    }

};





const buyListing = async (listingId) => {

    try {

        debugLog(`Initiating payment for listing ${listingId}`);

        const result = await apiRequest('initiate_payment', { listing_id: listingId });

        debugLog('Payment initiation response:', result);



        if (result && result.paypal_form) {

            // Create a temporary div to hold the form

            const tempDiv = document.createElement('div');

            tempDiv.innerHTML = result.paypal_form;

            document.body.appendChild(tempDiv);



            // Log the form content for debugging

            debugLog('PayPal form content:', tempDiv.innerHTML);



            // Find the form element

            const form = tempDiv.querySelector('form');

            if (form) {

                debugLog('PayPal form found, submitting...');

                form.submit();

            } else {

                throw new Error('PayPal form not found in the response');

            }

        } else {

            debugLog('Unexpected response structure:', result);

            throw new Error('Invalid response from server: PayPal form not found in response');

        }

    } catch (error) {

        debugLog(`Failed to initiate payment: ${error.message}`);

        debugLog('Error details:', error);

        alert('Failed to initiate payment. Please try again later. Error: ' + error.message);

    }

};



// Initialization Function

const initializeApp = () => {

    debugLog('Initializing app');

    

    // Assign DOM elements to variables

    ['walletAddressDisplay', 'walletBalanceDisplay', 'transactionHistoryDisplay', 'recentBlocksDisplay', 

     'usernameInput', 'pinInput', 'loginPanel', 'mainContent', 'mineButton', 

     'statusDisplay', 'recipientAddressInput', 'amountInput', 'transactionMessageInput', 'sendTransactionButton',

     'transactionStatusDisplay', 'hashingAnimation', 'difficultyDisplay', 'remainingBlocksDisplay',

     'listingAmountInput', 'listingPriceInput', 'listingPaypalEmailInput', 'createListingButton', 'allListingsDisplay']

    .forEach(id => elements[id] = getElement(id));



    // Attach event listeners

    getElement('loginButton').addEventListener('click', login);

    getElement('registerButton').addEventListener('click', register);

    getElement('logoutButton').addEventListener('click', logout);

    getElement('mineButton').addEventListener('click', toggleMining);

    getElement('sendTransactionButton').addEventListener('click', safeSendTransaction);

    getElement('createListingButton').addEventListener('click', safeCreateListing);



    initHashingAnimation();

    updateListingsDisplay(); // Initial update of listings

    debugLog('App initialized successfully');

};



// Error handling wrapper

const errorHandler = (func) => {

    return async (...args) => {

        try {

            await func(...args);

        } catch (error) {

            debugLog(`Error in ${func.name}: ${error.message}`);

            alert(`An error occurred: ${error.message}`);

        }

    };

};



// Wrap key functions with error handler

const safeCreateListing = errorHandler(createListing);

const safeBuyListing = errorHandler(buyListing);

const safeSendTransaction = errorHandler(sendTransaction);



// Global error handler

window.onerror = function(message, source, lineno, colno, error) {

    debugLog(`Global error: ${message} at ${source}:${lineno}:${colno}`);

    return false;

};



// Initialize the application when the DOM is fully loaded

document.addEventListener('DOMContentLoaded', initializeApp);



// Simulated mining process

setInterval(() => {

    if (mining) {

        const elapsedTime = (Date.now() - lastMiningTime) / 1000; // in seconds

        const estimatedHashes = Math.floor(hashingRate * elapsedTime);

        updateHashingAnimation(estimatedHashes);

    }

}, 1000);



debugLog('main.js fully loaded and initialized');