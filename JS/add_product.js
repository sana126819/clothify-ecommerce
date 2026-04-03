document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('addProductForm');

    form.addEventListener('submit', (e) => {
        const price = parseFloat(document.getElementById('price').value.trim());
        const stock = parseInt(document.getElementById('stock_quantity').value.trim());

        if (isNaN(price) || price < 0) {
            alert('Price cannot be negative or empty.');
            e.preventDefault();
            return false;
        }

        if (isNaN(stock) || stock < 0) {
            alert('Stock quantity cannot be negative or empty.');
            e.preventDefault();
            return false;
        }

        return true; // Form is valid
    });
});
