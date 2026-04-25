// Wait for the page to load
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the cart page
    if (document.getElementById('cartitems')) {
        displayCart();
    }
});

// Display cart items
function displayCart() {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    let cartContainer = document.getElementById('cartitems');
    
    if (!cartContainer) return;
    
    if (cart.length === 0) {
        cartContainer.innerHTML = '<p style="text-align:center; padding:20px;">Your cart is empty</p>';
        return;
    }
    
    let total = 0;
    let html = '<div class="cart-items-container">';
    
    cart.forEach((item, index) => {
        // Ensure price is a number
        let price = parseFloat(item.price) || 0;
        let quantity = parseInt(item.quantity) || 1;
        let subtotal = price * quantity;
        total += subtotal;
        
        html += `
            <div class="cart-item" style="border:1px solid #ddd; padding:15px; margin:10px; border-radius:5px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3>${item.name} (${item.color})</h3>
                    <p>Price: £${price.toFixed(2)}</p>
                    <p>Quantity: ${quantity}</p>
                    <p>Subtotal: £${subtotal.toFixed(2)}</p>
                </div>
                <button onclick="removeFromCart(${index})" style="background:#BE1622; color:white; border:none; padding:8px 15px; border-radius:3px; cursor:pointer;">
                    Remove
                </button>
            </div>
        `;
    });
    
    html += `
        <div style="text-align:right; padding:20px; font-size:1.2em; font-weight:bold;">
            Total: £${total.toFixed(2)}
        </div>
        <div style="text-align:center; padding:20px;">
            <button onclick="emptyCart()" style="background:#666; color:white; border:none; padding:10px 20px; border-radius:3px; cursor:pointer;">
                Empty Cart
            </button>
            <button onclick="checkout()" style="background:#339933; color:white; border:none; padding:10px 20px; border-radius:3px; cursor:pointer; margin-left:10px;">
                Checkout
            </button>
        </div>
    `;
    
    cartContainer.innerHTML = html;
}

// Add to cart function (for product pages)
function addToCart(productId) {
    // You need to have the 'products' array available
    if (typeof products === 'undefined') {
        console.error('Products array not found');
        return;
    }
    
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    let product = products.find(p => p.id == productId);
    
    if (!product) {
        console.error('Product not found:', productId);
        return;
    }
    
    // Ensure price is a number
    let price = parseFloat(product.price);
    if (isNaN(price)) {
        price = 0;
    }
    
    // Check if product already in cart
    let existingItem = cart.find(item => item.id == productId);
    
    if (existingItem) {
        existingItem.quantity = (parseInt(existingItem.quantity) || 1) + 1;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            color: product.color,
            price: price,
            quantity: 1
        });
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    alert('Added to cart!');
    
    // If we're on the cart page, update the display
    if (document.getElementById('cartitems')) {
        displayCart();
    }
}

// Remove item from cart
function removeFromCart(index) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart.splice(index, 1);
    localStorage.setItem('cart', JSON.stringify(cart));
    displayCart(); // Refresh the display
}

// Empty the entire cart
function emptyCart() {
    if (confirm('Are you sure you want to empty your cart?')) {
        localStorage.removeItem('cart');
        displayCart();
    }
}

// Checkout function
function checkout() {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    if (cart.length === 0) {
        alert('Your cart is empty!');
        return;
    }
    
    // Calculate total
    let total = cart.reduce((sum, item) => {
        let price = parseFloat(item.price) || 0;
        let quantity = parseInt(item.quantity) || 1;
        return sum + (price * quantity);
    }, 0);
    
    alert(`Proceeding to checkout. Total: £${total.toFixed(2)}`);
    // Here you would redirect to a checkout page
    // window.location.href = 'checkout.php';
}


if (document.getElementById('cartitems')) {
    displayCart();
}