    </div><!-- end content -->
</div><!-- end main -->

<script>
// Tab system
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        const parent = btn.closest('[data-tabs]') || document.body;
        parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        parent.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const content = document.getElementById(target);
        if(content) content.classList.add('active');
    });
});

// Modal
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if(e.target === m) closeModal(m.id); });
});

// Confirm delete
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this record?');
}

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(a => {
    setTimeout(() => { a.style.opacity='0'; a.style.height='0'; a.style.margin='0'; a.style.padding='0'; }, 5000);
});

// Mobile sidebar
const sidebar = document.getElementById('sidebar');
const mobileBtn = document.getElementById('mobileToggle');
if(mobileBtn) mobileBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
