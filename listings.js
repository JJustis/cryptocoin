// Fetch and display all listings
function fetchAndDisplayListings() {
    fetch('get_listings.php')
        .then(response => response.json())
        .then(listings => {
            const allListingsDisplay = document.getElementById('allListingsDisplay');
            allListingsDisplay.innerHTML = '';
            listings.forEach(listing => {
                const listingElement = document.createElement('div');
                listingElement.className = 'listing-item';
                listingElement.innerHTML = `
                    <div>Amount: ${listing.amount} coins</div>
                    <div>Price: $${listing.price}</div>
                    <button onclick="buyListing(${listing.id})">Buy</button>
                `;
                allListingsDisplay.appendChild(listingElement);
            });
        });
}

// Create a new listing
function createListing() {
    const amount = document.getElementById('listingAmountInput').value;
    const price = document.getElementById('listingPriceInput').value;
    const paypalEmail = document.getElementById('listingPaypalEmailInput').value;
    
    fetch('create_listing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `amount=${amount}&price=${price}&paypal_email=${encodeURIComponent(paypalEmail)}`
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Listing created successfully');
            fetchAndDisplayListings();
        } else {
            alert('Failed to create listing: ' + result.error);
        }
    });
}

// Buy a listing
function buyListing(listingId) {
    fetch('initiate_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `listing_id=${listingId}`
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.location.href = result.paypal_url;
        } else {
            alert('Failed to initiate payment: ' + result.error);
        }
    });
}

// Event listeners
document.getElementById('createListingButton').addEventListener('click', createListing);

// Initial fetch of listings
fetchAndDisplayListings();