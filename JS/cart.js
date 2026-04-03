function toggleCart(pid, btn) {
    fetch('add_to_cart.php?id=' + pid, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.classList.toggle('added');
        }
    })
    .catch(err => console.error(err));
}
