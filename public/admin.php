<?php
/**
 * PCA Photo Hub - Admin
 *
 * Password-protected screening and publishing area. The password comes
 * from column F of the config spreadsheet.
 */

$config = require_once __DIR__ . '/init.php';

use PCAPhotoHub\AdminAuth;
use PCAPhotoHub\AlbumManager;
use PCAPhotoHub\FacebookPublisher;
use PCAPhotoHub\GoogleDriveManager;
use PCAPhotoHub\GoogleSheetsManager;
use PCAPhotoHub\InstagramPublisher;
use PCAPhotoHub\Logger;
use PCAPhotoHub\MediaLink;
use PCAPhotoHub\MetaGraph;

$loginError = null;
$fatalError = null;
$albums = [];
$selectedAlbum = null;
$photos = [];
$isLoggedIn = false;
$adminConfigured = false;
$fbReady = false;
$igReady = false;
$mediaReady = false;
$igMax = 10;

try {
    $driveManager = new GoogleDriveManager($config);
    $sheetsManager = new GoogleSheetsManager($config);
    $auth = new AdminAuth($config, $sheetsManager->getAdminPassword());
    $adminConfigured = $auth->isConfigured();

    if (($_POST['admin_action'] ?? '') === 'logout') {
        $auth->logout();
        header('Location: admin.php');
        exit;
    }

    if (($_POST['admin_action'] ?? '') === 'login') {
        if ($auth->isLockedOut()) {
            $loginError = 'Too many failed attempts. Try again in '
                . ceil($auth->lockoutSecondsRemaining() / 60) . ' minute(s).';
        } elseif (!$auth->attemptLogin($_POST['admin_password'] ?? '')) {
            $loginError = $auth->isLockedOut()
                ? 'Too many failed attempts. Try again in 15 minutes.'
                : 'Incorrect admin password.';
        } else {
            // Redirect after successful login so a refresh does not repost.
            header('Location: admin.php');
            exit;
        }
    }

    $isLoggedIn = $auth->isLoggedIn();

    if ($isLoggedIn) {
        $albumManager = new AlbumManager($config, $sheetsManager, $driveManager);
        $albums = $albumManager->getAllAlbums();

        $mediaReady = (new MediaLink($config))->isConfigured();
        $graph = new MetaGraph($config);
        $fbReady = (new FacebookPublisher($config, $graph))->isConfigured();
        $igPublisher = new InstagramPublisher($config, $graph);
        $igReady = $igPublisher->isConfigured();
        $igMax = $igPublisher->maxItems();

        $folderId = $_GET['id'] ?? '';
        if ($folderId !== '') {
            $selectedAlbum = $albumManager->getAlbumByFolderId($folderId);
            if ($selectedAlbum) {
                $photos = $driveManager->listFilesInFolder($folderId);
            }
        }
    }
} catch (\Throwable $e) {
    Logger::error('admin.php: failed', ['message' => $e->getMessage()]);
    $fatalError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo htmlspecialchars($config['app']['name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <a href="https://diablo-pca.org/" target="_blank" rel="noopener">
                    <img src="<?php echo htmlspecialchars($config['app']['logo_url']); ?>"
                         alt="Porsche Club of America - Diablo Region">
                </a>
                <div class="logo-text">
                    <h1><?php echo htmlspecialchars($config['app']['name']); ?></h1>
                    <p>Admin</p>
                </div>
            </div>
            <nav>
                <a href="index.php">Public Site</a>
                <?php if ($isLoggedIn): ?>
                    <a href="admin.php">Albums</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if ($fatalError): ?>
                <div class="message error"><strong>Error:</strong> <?php echo htmlspecialchars($fatalError); ?></div>
            <?php endif; ?>

            <?php echo Logger::renderHtml(); ?>

            <?php if (!$isLoggedIn): ?>
                <!-- ---------- Login ---------- -->
                <h1>Admin Sign In</h1>

                <?php if (!$adminConfigured && !$fatalError): ?>
                    <div class="message warning">
                        <strong>Admin is not set up yet.</strong>
                        Add an <strong>Admin Password</strong> column (column F) to the config
                        spreadsheet and put a password in it, then make sure
                        <code>GOOGLE_SHEETS_CONFIG_RANGE</code> in <code>.env</code> covers
                        column F (for example <code>Config!A:F</code>).
                    </div>
                <?php else: ?>
                    <p class="subtitle">Screening and publishing tools for club officers.</p>

                    <div class="password-section" style="max-width: 460px;">
                        <?php if ($loginError): ?>
                            <div class="message error" style="margin-bottom: 1rem;">
                                <?php echo htmlspecialchars($loginError); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="admin_action" value="login">
                            <div class="form-group">
                                <label for="admin_password">Admin Password</label>
                                <input type="password" id="admin_password" name="admin_password" required autofocus>
                            </div>
                            <button type="submit" class="btn-primary">Sign In</button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php elseif (!$selectedAlbum): ?>
                <!-- ---------- Album picker ---------- -->
                <div class="admin-bar">
                    <h1 style="margin:0;">Admin</h1>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="admin_action" value="logout">
                        <button type="submit" class="btn-secondary">Sign Out</button>
                    </form>
                </div>

                <p class="subtitle">Choose an album to screen photos or publish them.</p>

                <?php if (!$mediaReady): ?>
                    <div class="message warning">
                        <strong>Publishing is unavailable.</strong>
                        <code>MEDIA_LINK_SECRET</code> is not set in <code>.env</code>. Meta fetches
                        photos by URL, so publishing needs it. Deleting still works.
                    </div>
                <?php endif; ?>

                <?php if (empty($albums)): ?>
                    <div class="message warning">No albums found in the config spreadsheet.</div>
                <?php else: ?>
                    <div class="albums-grid">
                        <?php foreach ($albums as $album): ?>
                            <?php $info = $albumManager->getAlbumInfo($album); ?>
                            <a class="album-card-link" href="admin.php?id=<?php echo urlencode($info['folder_id']); ?>">
                                <div class="album-card">
                                    <div class="album-header">
                                        <h3><?php echo htmlspecialchars($info['name']); ?></h3>
                                        <div class="album-status <?php echo $info['accepting_uploads'] ? 'accepting' : 'closed'; ?>">
                                            <?php echo htmlspecialchars($info['status']); ?>
                                        </div>
                                    </div>
                                    <div class="album-body">
                                        <?php if (!empty($info['notes'])): ?>
                                            <p class="album-notes"><?php echo htmlspecialchars($info['notes']); ?></p>
                                        <?php endif; ?>
                                        <span class="btn-album">Manage Photos</span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- ---------- Album management ---------- -->
                <?php $albumInfo = $albumManager->getAlbumInfo($selectedAlbum); ?>
                <div class="admin-bar">
                    <div>
                        <h1 style="margin:0;"><?php echo htmlspecialchars($albumInfo['name']); ?></h1>
                        <p class="text-muted" style="margin:0;"><?php echo count($photos); ?> photo(s)</p>
                    </div>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="admin_action" value="logout">
                        <button type="submit" class="btn-secondary">Sign Out</button>
                    </form>
                </div>

                <p><a href="admin.php">&larr; All albums</a></p>

                <?php if (empty($photos)): ?>
                    <div class="message warning">This album has no photos yet.</div>
                <?php else: ?>
                    <div class="admin-toolbar">
                        <div class="admin-toolbar-left">
                            <button type="button" class="btn-secondary" id="select-all">Select All</button>
                            <button type="button" class="btn-secondary" id="select-none">Clear</button>
                            <span id="selection-count" class="text-muted">0 selected</span>
                        </div>
                        <div class="admin-toolbar-right">
                            <button type="button" class="btn-danger" id="btn-delete" disabled>Delete Selected</button>
                            <button type="button" class="btn-primary" id="btn-facebook" disabled
                                    <?php echo (!$fbReady || !$mediaReady) ? 'title="Facebook is not configured in .env"' : ''; ?>>
                                Publish to Facebook
                            </button>
                            <button type="button" class="btn-primary" id="btn-instagram" disabled
                                    <?php echo (!$igReady || !$mediaReady) ? 'title="Instagram is not configured in .env"' : ''; ?>>
                                Publish to Instagram
                            </button>
                        </div>
                    </div>

                    <div id="admin-messages"></div>

                    <div class="photo-grid admin-photo-grid">
                        <?php foreach ($photos as $photo): ?>
                            <label class="photo-item admin-photo" data-file-id="<?php echo htmlspecialchars($photo->getId()); ?>">
                                <input type="checkbox" class="photo-select" value="<?php echo htmlspecialchars($photo->getId()); ?>"
                                       data-name="<?php echo htmlspecialchars($photo->getName()); ?>">
                                <?php if ($photo->getThumbnailLink()): ?>
                                    <img src="<?php echo htmlspecialchars($photo->getThumbnailLink()); ?>"
                                         alt="<?php echo htmlspecialchars($photo->getName()); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="photo-placeholder">&#128247;</div>
                                <?php endif; ?>
                                <span class="admin-photo-check" aria-hidden="true"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Facebook publish dialog -->
                <div class="admin-modal hidden" id="fb-modal">
                    <div class="admin-modal-box">
                        <h3>Publish to Facebook Page Album</h3>
                        <p class="text-muted">
                            Creates a new album on the club Facebook Page. Facebook Groups cannot be
                            posted to by any app -- Meta removed that API in 2024.
                        </p>
                        <div class="form-group">
                            <label for="fb-album-name">Album name</label>
                            <input type="text" id="fb-album-name" value="<?php echo htmlspecialchars($albumInfo['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="fb-description">Description (optional)</label>
                            <textarea id="fb-description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="fb-cover">Cover image</label>
                            <select id="fb-cover"></select>
                        </div>
                        <div class="admin-modal-actions">
                            <button type="button" class="btn-secondary" data-close-modal>Cancel</button>
                            <button type="button" class="btn-primary" id="fb-confirm">Publish</button>
                        </div>
                    </div>
                </div>

                <!-- Instagram publish dialog -->
                <div class="admin-modal hidden" id="ig-modal">
                    <div class="admin-modal-box">
                        <h3>Publish to Instagram</h3>
                        <p class="text-muted">
                            Posts to the feed -- one photo, or a swipeable carousel for several
                            (max <?php echo (int) $igMax; ?>). Instagram Stories cannot carry text
                            or tags through the API, so this publishes a captioned feed post.
                        </p>
                        <div class="form-group">
                            <label for="ig-caption">Caption</label>
                            <textarea id="ig-caption" rows="4" placeholder="Great turnout at this event..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="ig-tags">Hashtags (optional)</label>
                            <input type="text" id="ig-tags" placeholder="porsche, diablopca, autocross">
                        </div>
                        <div class="admin-modal-actions">
                            <button type="button" class="btn-secondary" data-close-modal>Cancel</button>
                            <button type="button" class="btn-primary" id="ig-confirm">Publish</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <p class="footer-copyright">&copy; 2026 Porsche Club of America - Diablo Region.</p>
            <p style="font-size: 1rem; font-weight: 600; color: rgba(255,255,255,0.9); margin-top: 1rem;">
                <?php echo htmlspecialchars($config['app']['name']); ?> &mdash; Version <?php echo htmlspecialchars($config['app']['version']); ?>
            </p>
        </div>
    </footer>

    <?php if ($isLoggedIn && $selectedAlbum): ?>
    <script>
        const ADMIN = {
            csrfToken: <?php echo json_encode($auth->getCsrfToken()); ?>,
            folderId: <?php echo json_encode($selectedAlbum['folder_id']); ?>,
            albumName: <?php echo json_encode($albumInfo['name']); ?>,
            facebookReady: <?php echo json_encode($fbReady && $mediaReady); ?>,
            instagramReady: <?php echo json_encode($igReady && $mediaReady); ?>,
            instagramMax: <?php echo (int) $igMax; ?>
        };
    </script>
    <script src="js/admin.js"></script>
    <?php endif; ?>
</body>
</html>
