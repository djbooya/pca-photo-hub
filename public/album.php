<?php
/**
 * PCA Photo Hub - Album Detail Page
 * Shows existing photos and upload interface for a specific album
 */

$config = require_once __DIR__ . '/init.php';

use PCAPhotoHub\GoogleDriveManager;
use PCAPhotoHub\GoogleSheetsManager;
use PCAPhotoHub\AlbumManager;
use PCAPhotoHub\SessionManager;

$folderId = $_GET['id'] ?? null;
$albumPasswordKey = "album_password_" . ($folderId ?? '');
$isPasswordVerified = isset($_SESSION[$albumPasswordKey]);

if (!$folderId) {
    http_response_code(404);
    exit("<h1>Album not found</h1>");
}

try {
    $driveManager = new GoogleDriveManager($config);
    $sheetsManager = new GoogleSheetsManager($config);
    $albumManager = new AlbumManager($config, $sheetsManager, $driveManager);
    $sessionManager = new SessionManager($config);

    $album = $albumManager->getAlbumByFolderId($folderId);

    if (!$album) {
        http_response_code(404);
        exit("<h1>Album not found</h1>");
    }

    $albumInfo = $albumManager->getAlbumInfo($album);

    // Handle password verification
    if ($album['password'] && !$isPasswordVerified) {
        if ($_POST['password'] ?? false) {
            if ($albumManager->verifyPassword($album, $_POST['password'])) {
                $_SESSION[$albumPasswordKey] = true;
                $isPasswordVerified = true;
            } else {
                $passwordError = "Invalid password. Please try again.";
            }
        }
    }

    // Get photos if password verified
    $photos = [];
    $userUploadedFiles = [];

    if ($isPasswordVerified || !$album['password']) {
        $photos = $driveManager->listFilesInFolder($folderId);
        $userUploadedFiles = $sessionManager->getUserUploadedFilesInFolder($folderId);
    }
} catch (\Exception $e) {
    $error = "Error loading album: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($albumInfo['name'] ?? 'Album'); ?> - PCA Photo Hub</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">
                <img src="https://diablo-pca.org/wp-content/uploads/2023/01/Porsche-Club-of-America-Diablo-Region-Logo.png"
                     alt="Diablo PCA Logo" onerror="this.style.display='none'">
                <div class="logo-text">
                    <h1><?php echo htmlspecialchars($config['app']['name']); ?></h1>
                    <p>Share your event photos easily</p>
                </div>
            </div>
            <nav>
                <a href="/">← Back to Albums</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Album Header -->
        <div class="album-detail-header">
            <div class="container">
                <h1><?php echo htmlspecialchars($albumInfo['name']); ?></h1>
                <p class="subtitle"><?php echo htmlspecialchars($albumInfo['notes']); ?></p>

                <div class="album-detail-info">
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($albumInfo['status']); ?>
                        </div>
                    </div>
                    <?php if ($albumInfo['upload_start']): ?>
                        <div class="info-item">
                            <div class="info-label">Opens</div>
                            <div class="info-value"><?php echo $albumInfo['upload_start']; ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($albumInfo['upload_end']): ?>
                        <div class="info-item">
                            <div class="info-label">Closes</div>
                            <div class="info-value"><?php echo $albumInfo['upload_end']; ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="info-label">Photos</div>
                        <div class="info-value"><?php echo count($photos); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Error Messages -->
            <?php if (isset($error)): ?>
                <div class="message error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Password Form (if needed) -->
            <?php if ($album['password'] && !$isPasswordVerified): ?>
                <div class="password-section">
                    <h3>Password Required</h3>
                    <p>This album is password protected. Please enter the password provided by the event organizers.</p>

                    <?php if (isset($passwordError)): ?>
                        <div class="message error" style="margin-bottom: 1rem;">
                            <?php echo htmlspecialchars($passwordError); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="password">Album Password:</label>
                            <input type="password" id="password" name="password" required autofocus>
                        </div>
                        <button type="submit" class="btn-primary">Unlock Album</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Disclaimer for uploads -->
                <?php if ($albumInfo['accepting_uploads']): ?>
                    <div class="disclaimer-banner" style="margin-bottom: 2rem;">
                        <strong>📸 Photo Upload Notice:</strong>
                        <p>By uploading photos, you agree that they will be public and may be shared on social media. You can delete your photos for <strong>7 days</strong> after upload.</p>
                    </div>
                <?php endif; ?>

                <!-- Photo Gallery -->
                <?php if (!empty($photos)): ?>
                    <div class="gallery-section">
                        <h3>Uploaded Photos (<?php echo count($photos); ?>)</h3>

                        <div class="photo-grid">
                            <?php foreach ($photos as $photo): ?>
                                <div class="photo-item">
                                    <?php if ($photo->getThumbnailLink()): ?>
                                        <img src="<?php echo htmlspecialchars($photo->getThumbnailLink()); ?>"
                                             alt="<?php echo htmlspecialchars($photo->getName()); ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                            📷
                                        </div>
                                    <?php endif; ?>

                                    <!-- Delete button for user's own files -->
                                    <?php if (isset($userUploadedFiles[$photo->getId()])): ?>
                                        <div class="photo-actions">
                                            <div class="photo-filename">
                                                <?php echo htmlspecialchars(substr($photo->getName(), 0, 50)); ?>
                                            </div>
                                            <button class="btn-delete-photo" data-file-id="<?php echo htmlspecialchars($photo->getId()); ?>">
                                                🗑️ Delete
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="message" style="background-color: #e7f3ff; color: #004085; border-left-color: #0c5460; margin-bottom: 2rem;">
                        No photos uploaded yet. Be the first to share your photos!
                    </div>
                <?php endif; ?>

                <!-- Upload Section -->
                <?php if ($albumInfo['accepting_uploads']): ?>
                    <div class="upload-section">
                        <h3>Upload Your Photos</h3>

                        <div class="drag-drop-area" id="drag-drop-area">
                            <div class="drag-drop-icon">📁</div>
                            <div class="drag-drop-text">Drag & Drop Your Photos Here</div>
                            <div class="drag-drop-subtext">or click to select files</div>
                        </div>

                        <input type="file" id="file-input" multiple accept="image/jpeg,image/png,image/webp">

                        <div class="upload-info">
                            <strong>Requirements:</strong> JPG, PNG, or WebP images up to 25 MB each.
                            You can upload multiple files at once.
                        </div>

                        <div class="upload-progress" id="upload-progress"></div>

                        <div class="upload-messages" id="upload-messages"></div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="https://diablo-pca.org/" target="_blank">Diablo Region Website</a>
                <a href="https://www.pca.org/" target="_blank">PCA National Website</a>
            </div>
            <p class="footer-copyright">
                © 2026 Porsche Club of America - Diablo Region. All rights reserved.
            </p>
            <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); margin-top: 1rem;">
                <strong><?php echo htmlspecialchars($config['app']['name']); ?></strong> v<?php echo $config['app']['version']; ?> |
                <a href="https://github.com/djbooya/pca-photo-hub" target="_blank" style="color: rgba(255, 255, 255, 0.8);">GitHub</a>
            </p>
        </div>
    </footer>

    <script>
        const folderId = <?php echo json_encode($folderId); ?>;
        const albumName = <?php echo json_encode($albumInfo['name']); ?>;
        const acceptingUploads = <?php echo json_encode($albumInfo['accepting_uploads']); ?>;
    </script>
    <script src="js/upload.js"></script>
    <script src="js/gallery.js"></script>
</body>
</html>
