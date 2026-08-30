/**
 * PCA Photo Hub - Gallery Handler
 * Manages photo deletion and gallery interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete-photo');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const fileId = this.dataset.fileId;
            const fileName = this.parentElement.querySelector('.photo-filename').textContent;

            if (!confirm(`Are you sure you want to delete "${fileName}"? This action cannot be undone.`)) {
                return;
            }

            deleteFile(fileId, this);
        });
    });

    /**
     * Delete a file
     */
    function deleteFile(fileId, button) {
        const formData = new FormData();
        formData.append('file_id', fileId);

        fetch('delete.php', {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the photo item
                    button.closest('.photo-item').style.opacity = '0.5';
                    setTimeout(() => {
                        button.closest('.photo-item').remove();

                        // Check if gallery is now empty
                        const photogrid = document.querySelector('.photo-grid');
                        if (photogrid && photogrid.children.length === 0) {
                            location.reload();
                        }
                    }, 300);

                    showMessage('success', '✓ Photo deleted successfully');
                } else {
                    showMessage('error', '✗ Error: ' + (data.error || 'Failed to delete photo'));
                }
            })
            .catch(error => {
                showMessage('error', '✗ Network error: ' + error.message);
            });
    }

    /**
     * Show message
     */
    function showMessage(type, text) {
        // Find or create messages container
        let messages = document.getElementById('upload-messages');
        if (!messages) {
            messages = document.createElement('div');
            messages.id = 'upload-messages';
            messages.className = 'upload-messages';
            document.querySelector('main').appendChild(messages);
        }

        const message = document.createElement('div');
        message.className = `message ${type}`;
        message.textContent = text;
        messages.appendChild(message);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            message.remove();
        }, 5000);
    }
});
