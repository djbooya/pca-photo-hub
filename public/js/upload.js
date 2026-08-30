/**
 * PCA Photo Hub - Upload Handler
 * Manages drag-and-drop uploads and progress tracking
 */

document.addEventListener('DOMContentLoaded', function() {
    if (!acceptingUploads) return;

    const dragDropArea = document.getElementById('drag-drop-area');
    const fileInput = document.getElementById('file-input');
    const uploadProgress = document.getElementById('upload-progress');
    const uploadMessages = document.getElementById('upload-messages');

    // Drag and drop events
    dragDropArea.addEventListener('click', () => fileInput.click());

    dragDropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dragDropArea.classList.add('dragover');
    });

    dragDropArea.addEventListener('dragleave', () => {
        dragDropArea.classList.remove('dragover');
    });

    dragDropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dragDropArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    /**
     * Handle selected/dropped files
     */
    function handleFiles(files) {
        if (files.length === 0) return;

        uploadMessages.innerHTML = '';
        uploadProgress.innerHTML = '';
        uploadProgress.classList.add('active');

        let uploadedCount = 0;
        let errorCount = 0;

        Array.from(files).forEach((file, index) => {
            uploadFile(file, index, files.length, () => {
                uploadedCount++;
                if (uploadedCount + errorCount === files.length) {
                    showCompletionMessage(uploadedCount, errorCount);
                    setTimeout(() => location.reload(), 2000);
                }
            }, () => {
                errorCount++;
                if (uploadedCount + errorCount === files.length) {
                    showCompletionMessage(uploadedCount, errorCount);
                }
            });
        });
    }

    /**
     * Upload a single file
     */
    function uploadFile(file, fileIndex, totalFiles, onSuccess, onError) {
        // Validate file
        const maxSizeMB = 25;
        const maxSizeBytes = maxSizeMB * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (file.size > maxSizeBytes) {
            addProgressItem(file.name, 0, false, `File too large (max ${maxSizeMB}MB)`);
            onError();
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            addProgressItem(file.name, 0, false, `File type not allowed (JPG, PNG, WebP only)`);
            onError();
            return;
        }

        // Create progress item
        const progressItem = addProgressItem(file.name, 0, true);

        // Prepare form data
        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder_id', folderId);

        // Upload with XMLHttpRequest to track progress
        const xhr = new XMLHttpRequest();

        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const progress = Math.round((e.loaded / e.total) * 100);
                updateProgressItem(progressItem, progress);
            }
        });

        // Handle completion
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    updateProgressItem(progressItem, 100, true);
                    addMessage('success', `✓ ${file.name} uploaded successfully`);
                    onSuccess();
                } else {
                    updateProgressItem(progressItem, 0, false, response.error || 'Upload failed');
                    addMessage('error', `✗ ${file.name}: ${response.error || 'Unknown error'}`);
                    onError();
                }
            } else {
                updateProgressItem(progressItem, 0, false, 'Upload failed');
                addMessage('error', `✗ ${file.name}: Server error`);
                onError();
            }
        });

        // Handle errors
        xhr.addEventListener('error', () => {
            updateProgressItem(progressItem, 0, false, 'Network error');
            addMessage('error', `✗ ${file.name}: Network error`);
            onError();
        });

        xhr.open('POST', 'upload.php');
        xhr.send(formData);
    }

    /**
     * Add a new progress item
     */
    function addProgressItem(fileName, progress = 0, isActive = true, error = null) {
        const item = document.createElement('div');
        item.className = 'progress-item';
        item.innerHTML = `
            <div class="progress-filename">${escapeHtml(fileName)}</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${progress}%"></div>
            </div>
            ${error ? `<div style="font-size: 0.85rem; color: #dc3545; margin-top: 0.25rem;">${error}</div>` : ''}
        `;
        uploadProgress.appendChild(item);
        return item;
    }

    /**
     * Update progress item
     */
    function updateProgressItem(item, progress, success = false, error = null) {
        const progressFill = item.querySelector('.progress-fill');
        progressFill.style.width = progress + '%';

        if (error) {
            const errorDiv = document.createElement('div');
            errorDiv.style.fontSize = '0.85rem';
            errorDiv.style.color = '#dc3545';
            errorDiv.style.marginTop = '0.25rem';
            errorDiv.textContent = error;
            item.appendChild(errorDiv);
        }

        if (success) {
            progressFill.style.backgroundColor = '#28a745';
        }
    }

    /**
     * Add message to upload messages area
     */
    function addMessage(type, text) {
        const message = document.createElement('div');
        message.className = `message ${type}`;
        message.textContent = text;
        uploadMessages.appendChild(message);
    }

    /**
     * Show completion message
     */
    function showCompletionMessage(uploadedCount, errorCount) {
        uploadProgress.classList.remove('active');

        if (errorCount === 0) {
            addMessage('success', `✓ All ${uploadedCount} photo(s) uploaded successfully! Reloading...`);
        } else if (uploadedCount === 0) {
            addMessage('error', `✗ Failed to upload ${errorCount} file(s). Please try again.`);
        } else {
            addMessage('warning', `⚠️ Uploaded ${uploadedCount} photo(s), ${errorCount} failed.`);
        }
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
});
