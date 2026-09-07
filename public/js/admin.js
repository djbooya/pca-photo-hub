/**
 * PCA Photo Hub - Admin
 * Photo selection, deletion, and publishing to Facebook / Instagram.
 */

document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(document.querySelectorAll('.photo-select'));
    if (checkboxes.length === 0) return;

    const countEl = document.getElementById('selection-count');
    const messages = document.getElementById('admin-messages');
    const btnDelete = document.getElementById('btn-delete');
    const btnFacebook = document.getElementById('btn-facebook');
    const btnInstagram = document.getElementById('btn-instagram');

    const fbModal = document.getElementById('fb-modal');
    const igModal = document.getElementById('ig-modal');

    function selected() {
        return checkboxes.filter(c => c.checked);
    }

    function selectedIds() {
        return selected().map(c => c.value);
    }

    function refresh() {
        const n = selected().length;
        countEl.textContent = n + ' selected';

        checkboxes.forEach(c => c.closest('.admin-photo').classList.toggle('is-selected', c.checked));

        btnDelete.disabled = n === 0;
        // Publish buttons additionally require the relevant .env config.
        btnFacebook.disabled = n === 0 || !ADMIN.facebookReady;
        btnInstagram.disabled = n === 0 || !ADMIN.instagramReady;
    }

    checkboxes.forEach(c => c.addEventListener('change', refresh));

    document.getElementById('select-all').addEventListener('click', () => {
        checkboxes.forEach(c => { c.checked = true; });
        refresh();
    });

    document.getElementById('select-none').addEventListener('click', () => {
        checkboxes.forEach(c => { c.checked = false; });
        refresh();
    });

    function showMessage(type, text, link) {
        const el = document.createElement('div');
        el.className = 'message ' + type;
        el.textContent = text;
        if (link) {
            el.appendChild(document.createTextNode(' '));
            const a = document.createElement('a');
            a.href = link;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = 'View post';
            el.appendChild(a);
        }
        messages.prepend(el);
        messages.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function openModal(modal) { modal.classList.remove('hidden'); }
    function closeModal(modal) { modal.classList.add('hidden'); }

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.closest('.admin-modal')));
    });

    /**
     * POST an admin action. Buttons are disabled while it is in flight so a
     * double-click cannot publish or delete twice.
     */
    function submitAction(action, extraFields, button, onDone) {
        const data = new FormData();
        data.append('action', action);
        data.append('csrf_token', ADMIN.csrfToken);
        data.append('folder_id', ADMIN.folderId);
        selectedIds().forEach(id => data.append('file_ids[]', id));
        Object.keys(extraFields || {}).forEach(k => data.append(k, extraFields[k]));

        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Working...';

        fetch('admin_action.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showMessage('success', res.message || 'Done.', res.link);
                    if (res.errors && res.errors.length) {
                        showMessage('warning', res.errors.length + ' item(s) had problems: ' + res.errors.join('; '));
                    }
                    if (onDone) onDone();
                } else {
                    showMessage('error', res.error || 'The action failed.');
                }
            })
            .catch(err => showMessage('error', 'Network error: ' + err.message))
            .finally(() => {
                button.disabled = false;
                button.textContent = original;
                refresh();
            });
    }

    // ---- Delete ----------------------------------------------------------
    btnDelete.addEventListener('click', () => {
        const n = selected().length;
        if (!confirm('Permanently delete ' + n + ' photo(s)? This cannot be undone.')) return;

        submitAction('delete', {}, btnDelete, () => {
            // Drop the deleted tiles rather than forcing a full reload.
            selected().forEach(c => c.closest('.admin-photo').remove());
            const remaining = Array.from(document.querySelectorAll('.photo-select'));
            checkboxes.length = 0;
            checkboxes.push(...remaining);
        });
    });

    // ---- Facebook --------------------------------------------------------
    btnFacebook.addEventListener('click', () => {
        const cover = document.getElementById('fb-cover');
        cover.innerHTML = '';
        selected().forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.value;
            opt.textContent = c.dataset.name;
            cover.appendChild(opt);
        });

        document.getElementById('fb-album-name').value = ADMIN.albumName;
        openModal(fbModal);
    });

    document.getElementById('fb-confirm').addEventListener('click', function () {
        const name = document.getElementById('fb-album-name').value.trim();
        if (!name) { alert('Please enter an album name.'); return; }

        submitAction('publish_facebook', {
            album_name: name,
            description: document.getElementById('fb-description').value.trim(),
            cover_file_id: document.getElementById('fb-cover').value
        }, this, () => closeModal(fbModal));
    });

    // ---- Instagram -------------------------------------------------------
    btnInstagram.addEventListener('click', () => {
        if (selected().length > ADMIN.instagramMax) {
            showMessage('error', 'Instagram allows at most ' + ADMIN.instagramMax
                + ' photos per post. Please select ' + ADMIN.instagramMax + ' or fewer.');
            return;
        }
        openModal(igModal);
    });

    document.getElementById('ig-confirm').addEventListener('click', function () {
        const caption = document.getElementById('ig-caption').value.trim();
        if (!caption) { alert('Please enter a caption.'); return; }

        submitAction('publish_instagram', {
            caption: caption,
            tags: document.getElementById('ig-tags').value.trim()
        }, this, () => closeModal(igModal));
    });

    refresh();
});
