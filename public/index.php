<?php
/**
 * PCA Photo Hub - Homepage
 * Displays available albums for uploading photos
 */

$config = require_once __DIR__ . '/init.php';

use PCAPhotoHub\GoogleDriveManager;
use PCAPhotoHub\GoogleSheetsManager;
use PCAPhotoHub\AlbumManager;
use PCAPhotoHub\Logger;

try {
    $driveManager = new GoogleDriveManager($config);
    $sheetsManager = new GoogleSheetsManager($config);
    $albumManager = new AlbumManager($config, $sheetsManager, $driveManager);

    $albums = $albumManager->getAllAlbums();
} catch (\Exception $e) {
    Logger::error('index.php: failed to load albums', ['message' => $e->getMessage()]);
    $error = "Error loading albums: " . $e->getMessage();
    $albums = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCA Photo Hub - Share Your Event Photos</title>
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
                <a href="/">Home</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <!-- Disclaimer Banner -->
            <div class="disclaimer-banner">
                <strong>⚠️ Privacy Notice:</strong>
                <p>All photos you upload will be public and may be shared on the Porsche Club of America social media channels and website.</p>
            </div>

            <!-- Error Message (if any) -->
            <?php if (isset($error)): ?>
                <div class="message error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Debug Panel (only rendered when APP_DEBUG=true) -->
            <?php echo Logger::renderHtml(); ?>

            <!-- Page Title -->
            <h1>Event Photo Gallery</h1>
            <p class="subtitle">Select an event below to upload your photos</p>

            <!-- Albums Grid -->
            <?php if (empty($albums)): ?>
                <div class="message warning">
                    <strong>No albums found.</strong> Please check back later or contact the organizers.
                </div>
            <?php else: ?>
                <div class="albums-grid">
                    <?php foreach ($albums as $album): ?>
                        <?php $albumInfo = $albumManager->getAlbumInfo($album); ?>
                        <div class="album-card">
                            <div class="album-header">
                                <h3><?php echo htmlspecialchars($albumInfo['name']); ?></h3>
                                <div class="album-status <?php echo $albumInfo['accepting_uploads'] ? 'accepting' : 'closed'; ?>">
                                    <?php echo htmlspecialchars($albumInfo['status']); ?>
                                </div>
                            </div>

                            <div class="album-body">
                                <?php if (!empty($albumInfo['notes'])): ?>
                                    <p class="album-notes">
                                        <?php echo htmlspecialchars($albumInfo['notes']); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="album-dates">
                                    <?php if ($albumInfo['upload_start']): ?>
                                        <p><strong>Opens:</strong> <?php echo $albumInfo['upload_start']; ?></p>
                                    <?php endif; ?>
                                    <?php if ($albumInfo['upload_end']): ?>
                                        <p><strong>Closes:</strong> <?php echo $albumInfo['upload_end']; ?></p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($albumInfo['accepting_uploads']): ?>
                                    <a href="album.php?id=<?php echo urlencode($albumInfo['folder_id']); ?>" class="btn-album">
                                        Upload Photos
                                    </a>
                                <?php elseif ($albumInfo['status'] === 'Closed'): ?>
                                    <button class="btn-album" disabled>
                                        Uploads Closed
                                    </button>
                                <?php else: ?>
                                    <button class="btn-album" disabled>
                                        Coming Soon
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Additional Information -->
            <div style="margin-top: 3rem; padding: 2rem; background-color: #f8f9fa; border-radius: 8px;">
                <h2>How It Works</h2>
                <ol style="margin-left: 1.5rem; line-height: 1.8;">
                    <li><strong>Select an Album:</strong> Choose an event from the gallery above</li>
                    <li><strong>Enter Password:</strong> Use the password provided by event organizers</li>
                    <li><strong>Upload Photos:</strong> Drag & drop or click to upload your photos</li>
                    <li><strong>See Your Photos:</strong> View all uploaded photos instantly in the gallery</li>
                    <li><strong>Delete If Needed:</strong> You can delete your photos for 7 days after upload</li>
                </ol>
                <p style="margin-top: 1rem; color: #6c757d; font-size: 0.9rem;">
                    Questions? Contact your event organizer for passwords and details.
                </p>
            </div>
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
            <p style="font-size: 1rem; font-weight: 600; color: rgba(255, 255, 255, 0.9); margin-top: 1rem;">
                <?php echo htmlspecialchars($config['app']['name']); ?> &mdash; Version <?php echo htmlspecialchars($config['app']['version']); ?>
            </p>
            <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); margin-top: 0.25rem;">
                <a href="https://github.com/djbooya/pca-photo-hub" target="_blank" style="color: rgba(255, 255, 255, 0.8);">View on GitHub</a>
            </p>
        </div>
    </footer>
</body>
</html>
