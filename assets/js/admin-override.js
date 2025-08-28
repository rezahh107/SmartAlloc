(function () {
    const btn   = document.getElementById('smartalloc-override-btn');
    const modal = document.getElementById('smartalloc-override-modal');
    if (!btn || !modal || typeof wp === 'undefined' || !wp.apiFetch) {
        return;
    }

    const mentorField = modal.querySelector('#smartalloc-override-mentor');
    const notesField  = modal.querySelector('#smartalloc-override-notes');
    const submitBtn   = modal.querySelector('#smartalloc-override-submit');

    btn.addEventListener('click', function () {
        modal.style.display = 'block';
    });

    submitBtn.addEventListener('click', function () {
        const mentor = mentorField.value;
        if (!mentor) {
            return;
        }
        wp.apiFetch({
            path: SmartAllocOverride.api,
            method: 'POST',
            headers: { 'X-WP-Nonce': SmartAllocOverride.nonce },
            data: { mentor_id: mentor, notes: notesField.value }
        }).then(function () {
            window.location.reload();
        });
    });
})();
