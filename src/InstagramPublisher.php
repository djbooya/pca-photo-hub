<?php

namespace PCAPhotoHub;

/**
 * InstagramPublisher - Publishes selected photos as an Instagram feed post.
 *
 * One photo becomes a single image post; several become a swipeable
 * carousel. Publishing is the standard two-step Graph flow: build a media
 * container, then publish that container.
 *
 * NOTE ON STORIES: the API can publish a Story (media_type=STORIES) but it
 * cannot attach any text, caption, sticker or tag to one -- Meta simply
 * does not expose that. Since the point here is captioned, tagged event
 * photos, this publishes feed posts, where the caption carries both the
 * text and any hashtags.
 */
class InstagramPublisher
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
        return !empty($this->config['meta']['instagram']['business_account_id'])
            && !empty($this->config['meta']['instagram']['access_token']);
    }

    public function maxItems()
    {
        return (int) ($this->config['meta']['instagram']['max_carousel_items'] ?? 10);
    }

    /**
     * Publish a feed post.
     *
     * @param array  $photoUrls Signed, publicly fetchable image URLs
     * @param string $caption   Caption text; hashtags belong in here too
     * @return array {media_id, count, permalink}
     */
    public function publish(array $photoUrls, $caption)
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Instagram is not configured. Set INSTAGRAM_BUSINESS_ACCOUNT_ID and INSTAGRAM_ACCESS_TOKEN in .env.');
        }

        if (empty($photoUrls)) {
            throw new \Exception('No photos selected.');
        }

        $max = $this->maxItems();
        if (count($photoUrls) > $max) {
            throw new \Exception('Instagram allows at most ' . $max . ' photos in one post. Please select ' . $max . ' or fewer.');
        }

        $igId = $this->config['meta']['instagram']['business_account_id'];
        $token = $this->config['meta']['instagram']['access_token'];
        $caption = (string) $caption;

        Logger::info('InstagramPublisher: publishing', ['photos' => count($photoUrls)]);

        if (count($photoUrls) === 1) {
            $containerId = $this->createContainer($igId, $token, [
                'image_url' => $photoUrls[0],
                'caption' => $caption,
            ]);
        } else {
            $containerId = $this->createCarousel($igId, $token, $photoUrls, $caption);
        }

        $published = $this->graph->post($igId . '/media_publish', [
            'creation_id' => $containerId,
            'access_token' => $token,
        ]);

        if (empty($published['id'])) {
            throw new \Exception('Instagram did not return a media id after publishing.');
        }

        Logger::info('InstagramPublisher: published', ['media_id' => $published['id'], 'count' => count($photoUrls)]);

        return [
            'media_id' => $published['id'],
            'count' => count($photoUrls),
            'permalink' => $this->fetchPermalink($published['id'], $token),
        ];
    }

    /**
     * A carousel needs one child container per image, then a parent
     * container listing those children. Only the parent carries the caption.
     */
    private function createCarousel($igId, $token, array $photoUrls, $caption)
    {
        $childIds = [];
        foreach ($photoUrls as $url) {
            $childIds[] = $this->createContainer($igId, $token, [
                'image_url' => $url,
                'is_carousel_item' => 'true',
            ]);
        }

        return $this->createContainer($igId, $token, [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
            'caption' => $caption,
        ]);
    }

    private function createContainer($igId, $token, array $params)
    {
        $params['access_token'] = $token;
        $response = $this->graph->post($igId . '/media', $params);

        if (empty($response['id'])) {
            throw new \Exception('Instagram did not return a media container id.');
        }

        return $response['id'];
    }

    /**
     * Best-effort permalink for the confirmation message. A failure here
     * must not make a successful publish look like it failed.
     */
    private function fetchPermalink($mediaId, $token)
    {
        try {
            $ch = curl_init(MetaGraph::BASE . trim($this->config['meta']['graph_version'] ?? 'v21.0', '/')
                . '/' . $mediaId . '?' . http_build_query(['fields' => 'permalink', 'access_token' => $token]));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
            $body = curl_exec($ch);
            curl_close($ch);

            $decoded = json_decode((string) $body, true);
            return $decoded['permalink'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
