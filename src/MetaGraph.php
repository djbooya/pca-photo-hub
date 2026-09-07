<?php

namespace PCAPhotoHub;

/**
 * MetaGraph - Thin POST client for the Meta Graph API.
 *
 * Shared by FacebookPublisher and InstagramPublisher. Uses cURL directly
 * (already a hard requirement for the Google client) rather than pulling
 * in another HTTP abstraction.
 *
 * Meta reports failures as HTTP 4xx/5xx with a JSON body shaped like
 * {"error": {"message": ..., "type": ..., "code": ...}}; this turns that
 * into a readable exception instead of leaking a raw blob to the screen.
 */
class MetaGraph
{
    const BASE = 'https://graph.facebook.com/';

    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * POST to a Graph endpoint, e.g. post('12345/albums', [...]).
     * Returns the decoded response array.
     */
    public function post($path, array $params)
    {
        $version = $this->config['meta']['graph_version'] ?? 'v21.0';
        $url = self::BASE . trim($version, '/') . '/' . ltrim($path, '/');

        // Never log access tokens.
        $loggable = $params;
        unset($loggable['access_token']);
        Logger::debug('MetaGraph: POST', ['endpoint' => $path, 'params' => array_keys($loggable)]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            Logger::error('MetaGraph: network failure', ['endpoint' => $path, 'curl_error' => $curlError]);
            throw new \Exception('Could not reach the Meta API: ' . $curlError);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            Logger::error('MetaGraph: non-JSON response', ['endpoint' => $path, 'status' => $status, 'body' => substr($body, 0, 1000)]);
            throw new \Exception('The Meta API returned an unexpected response (HTTP ' . $status . ').');
        }

        if (isset($decoded['error'])) {
            $err = $decoded['error'];
            Logger::error('MetaGraph: API error', [
                'endpoint' => $path,
                'status' => $status,
                'code' => $err['code'] ?? null,
                'type' => $err['type'] ?? null,
                'message' => $err['message'] ?? null,
            ]);

            $message = $err['message'] ?? 'Unknown Meta API error';
            if (!empty($err['error_user_msg'])) {
                $message = $err['error_user_msg'];
            }
            throw new \Exception($message);
        }

        if ($status < 200 || $status >= 300) {
            Logger::error('MetaGraph: unexpected HTTP status', ['endpoint' => $path, 'status' => $status]);
            throw new \Exception('The Meta API returned HTTP ' . $status . '.');
        }

        return $decoded;
    }
}
