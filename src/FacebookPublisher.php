<?php

namespace PCAPhotoHub;

/**
 * FacebookPublisher - Creates a photo album on the club Facebook Page and
 * uploads selected photos into it.
 *
 * NOTE ON GROUPS: this deliberately targets a Page, not a Group. Meta
 * removed the Groups API entirely on 2024-04-22 (the publish_to_groups
 * permission and every Groups publishing endpoint went with it), so no
 * application can create albums or post photos into a Facebook Group any
 * more. Pages remain fully supported and are the only viable target.
 */
class FacebookPublisher
{
    private $config;
    private $graph;

    public function __construct($config, MetaGraph $graph)
    {
        $this->config = $config;
        $this->graph = $graph;
    }

    public function isConfigured()
    {
        return !empty($this->config['meta']['facebook']['page_id'])
            && !empty($this->config['meta']['facebook']['access_token']);
    }

    /**
     * Create an album and upload photos into it.
     *
     * @param string      $albumName   Album title on Facebook
     * @param string      $description Optional album description
     * @param array       $photoUrls   Signed, publicly fetchable image URLs
     * @param string|null $coverUrl    Which of those should be the cover
     * @return array {album_id, uploaded, failed, link}
     */
    public function publishAlbum($albumName, $description, array $photoUrls, $coverUrl = null)
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Facebook is not configured. Set FACEBOOK_PAGE_ID and FACEBOOK_PAGE_ACCESS_TOKEN in .env.');
        }

        if (empty($photoUrls)) {
            throw new \Exception('No photos selected.');
        }

        $pageId = $this->config['meta']['facebook']['page_id'];
        $token = $this->config['meta']['facebook']['access_token'];

        $albumParams = [
            'name' => $albumName,
            'access_token' => $token,
        ];
        if (trim((string) $description) !== '') {
            $albumParams['message'] = $description;
        }

        Logger::info('FacebookPublisher: creating album', ['name' => $albumName, 'photos' => count($photoUrls)]);
        $album = $this->graph->post($pageId . '/albums', $albumParams);

        if (empty($album['id'])) {
            throw new \Exception('Facebook did not return an album id.');
        }
        $albumId = $album['id'];

        // Facebook uses the first photo uploaded into an album as its cover,
        // and the album cover_photo field is not directly settable here, so
        // the chosen cover is simply uploaded first.
        $ordered = $this->coverFirst($photoUrls, $coverUrl);

        $uploaded = [];
        $failed = [];

        foreach ($ordered as $url) {
            try {
                $photo = $this->graph->post($albumId . '/photos', [
                    'url' => $url,
                    'access_token' => $token,
                ]);
                $uploaded[] = $photo['id'] ?? null;
            } catch (\Throwable $e) {
                // One bad photo should not abandon the whole album.
                Logger::error('FacebookPublisher: photo upload failed', ['error' => $e->getMessage()]);
                $failed[] = $e->getMessage();
            }
        }

        Logger::info('FacebookPublisher: album published', [
            'album_id' => $albumId,
            'uploaded' => count($uploaded),
            'failed' => count($failed),
        ]);

        return [
            'album_id' => $albumId,
            'uploaded' => count($uploaded),
            'failed' => $failed,
            'link' => 'https://www.facebook.com/media/set/?set=a.' . $albumId,
        ];
    }

    /**
     * Move the chosen cover URL to the front, preserving the rest of the order.
     */
    private function coverFirst(array $photoUrls, $coverUrl)
    {
        if ($coverUrl === null || $coverUrl === '') {
            return $photoUrls;
        }

        $index = array_search($coverUrl, $photoUrls, true);
        if ($index === false) {
            return $photoUrls;
        }

        unset($photoUrls[$index]);
        array_unshift($photoUrls, $coverUrl);

        return array_values($photoUrls);
    }
}
