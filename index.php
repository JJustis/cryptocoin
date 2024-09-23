<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoCoin Miner</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 {
            color: #1a73e8;
        }
        h1 {
            text-align: center;
        }
        .panel {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        button {
            background-color: #1a73e8;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #1557b0;
        }
        input[type="text"], input[type="password"], input[type="number"], textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .block {
            background-color: #e8f0fe;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }
        .block:hover {
            transform: translateY(-5px);
        }
        .block-header {
            font-weight: bold;
            margin-bottom: 10px;
            color: #1a73e8;
        }
        .block-content {
            font-size: 0.9em;
        }
        #hashingAnimation {
            width: 100%;
            height: 200px;
        }
        .transaction-item {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        #difficultyDisplay, #remainingBlocksDisplay {
            font-weight: bold;
            margin-top: 10px;
        }
        
        /* New styles for the wallet section */
        .wallet-panel {
            background: linear-gradient(135deg, #6e8efb, #a777e3);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .wallet-panel h2 {
            color: white;
            margin-bottom: 20px;
            position: relative;
        }
        .wallet-info {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            backdrop-filter: blur(5px);
        }
        #walletAddressDisplay, #walletBalanceDisplay {
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        #logoutButton {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            transition: all 0.3s ease;
        }
        #logoutButton:hover {
            background-color: white;
            color: #6e8efb;
        }
		        /* Updated styles for tracking messages and purchase details */
        #trackingMessagesDisplay {
            margin-top: 15px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            font-size: 0.9em;
            height: 220px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.5) rgba(255, 255, 255, 0.1);
        }
        #trackingMessagesDisplay::-webkit-scrollbar {
            width: 6px;
        }
        #trackingMessagesDisplay::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        #trackingMessagesDisplay::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 3px;
        }
        #trackingMessagesDisplay h4 {
            color: #ffffff;
            margin-top: 0;
            margin-bottom: 10px;
            position: sticky;
            top: 0;
            background-color: rgba(110, 142, 251, 0.9);
            padding: 5px 0;
            z-index: 1;
        }
        .purchase-item {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .purchase-item h5 {
            margin: 0 0 5px 0;
            color: #ffffff;
        }
        .purchase-item p {
            margin: 0 0 5px 0;
        }
        .tracking-message {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            padding: 5px;
            margin-top: 5px;
            font-style: italic;
        }
		 .shop-panel {
            background: linear-gradient(135deg, #43cea2, #185a9d);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CryptoCoin Miner</h1>
        
        <div id="loginPanel" class="panel">
            <h2>Login / Register</h2>
            <input type="text" id="usernameInput" placeholder="Username">
            <input type="password" id="pinInput" placeholder="Password">
            <button id="loginButton">Login</button>
            <button id="registerButton">Register</button>
        </div>

        <div id="mainContent" style="display:none; word-break: break-all;">
            <div class="grid">
                <div class="wallet-panel">
                    <h2>Wallet</h2>
                    <div class="wallet-info">
                        <div id="walletAddressDisplay"></div>
                        <div id="walletBalanceDisplay"></div>
						<div id="trackingMessagesDisplay"></div>
                    </div>
                    <button id="logoutButton">Logout</button>
                </div>

                <div class="panel">
                    <h2>Mining Control</h2>
                    <button id="mineButton">Start Mining</button>
                    <div id="statusDisplay"></div>
                    <div id="difficultyDisplay"></div>
                    <div id="remainingBlocksDisplay"></div>
                    <canvas id="hashingAnimation"></canvas>
                </div>
            </div>

            <div class="panel" id="transactionPanel">
                <h2>Transactions</h2>
                <div class="grid">
                    <div class="transaction-form">
                        <h3>Send CryptoCoins</h3>
                        <input type="text" id="recipientAddressInput" placeholder="Recipient Address">
                        <input type="number" id="amountInput" placeholder="Amount">
                        <textarea id="transactionMessageInput" placeholder="Optional message"></textarea>
                        <button id="sendTransactionButton">Send CryptoCoins</button>
                        <div id="transactionStatusDisplay"></div>
                    </div>
					    <div class="wallet-panel panel" id="listingsPanel">
    <h2>Coin Listings</h2>
    <div class="grid ">
        <div class="create-listing-form">
            <h3>Create New Listing</h3>
            <input type="number" id="listingAmountInput" placeholder="Amount of coins">
            <input type="number" id="listingPriceInput" placeholder="Price in USD">
            <input type="email" id="listingPaypalEmailInput" placeholder="PayPal Email">
            <button id="createListingButton">Create Listing</button>
        </div>
    </div>
    <div class="all-listings">
        <h3>All Available Listings</h3>
        <div class="" id="allListingsDisplay"></div>
    </div>
</div>
                    <div class="transaction-history">
                        <h3>Transaction History</h3>
                        <div id="transactionHistoryDisplay"></div>
                    </div>
                </div>
                <div class="pending-transactions">
                    <h3>Pending Transactions</h3>
                    <div id="pendingTransactionsDisplay"></div>
                </div>
            </div>
             <div class="shop-panel">
                <h2>Shop</h2>
                <button id="shopButton">Visit Shop</button>
            </div>
            <div class="panel">
                <h2>Recent Blocks</h2>
                <div id="recentBlocksDisplay"></div>
            </div>
        </div>
    </div>
    <script src="main.js"></script>
	    <script>
        // Add event listener for the Shop button
        document.getElementById('shopButton').addEventListener('click', function() {
            window.location.href = 'shop.php';
        });
function fetchAndDisplayPurchaseDetails() {
    fetch('main.php?action=get_tracking_messages')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const trackingMessagesDisplay = document.getElementById('trackingMessagesDisplay');
                trackingMessagesDisplay.innerHTML = '';
                if (data.data.purchases.length > 0) {
                    data.data.purchases.forEach(purchase => {
                        const purchaseElement = document.createElement('div');
                        purchaseElement.className = 'purchase-item';
                        purchaseElement.innerHTML = `
                            <h5>${purchase.product.name}</h5>
                            <p>Purchase Date: ${new Date(purchase.date).toLocaleString()}</p>
                            <p>Price: ${purchase.product.price} CryptoCoins</p>
                            <p>Type: ${purchase.product.is_virtual ? 'Virtual' : 'Physical'}</p>
                            <p>Description: ${purchase.product.description}</p>
                            <p>Email: ${purchase.email}</p>
                            <p>Transaction Hash: ${purchase.transaction_hash}</p>
                            ${purchase.product.is_virtual ? `
                                <p>Download Code: ${purchase.download_code || 'N/A'}</p>
                                <p>Downloads: ${purchase.download_count || 0}</p>
                                <p>Last Download: ${purchase.last_download_date ? new Date(purchase.last_download_date).toLocaleString() : 'Never'}</p>
                            ` : ''}
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
                console.error('Failed to fetch purchase details:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching purchase details:', error);
        });
}

    </script>
</body>
</html>