<?php

namespace Kanboard\Plugin\VK\Notification\Api;

/**
 * Minimal VK REST API client (no external dependencies).
 *
 * Wraps the https://api.vk.com/method/ JSON endpoints used by this plugin:
 *  - messages.send
 *  - docs.getMessagesUploadServer / docs.save (attachment forwarding)
 *
 * @package  vk
 * @author   Kanboard VK Plugin
 */
class VkApi
{
    /**
     * Base URL of the VK REST API
     */
    const API_URL = 'https://api.vk.com/method/';

    /**
     * VK API version
     */
    const API_VERSION = '5.199';

    /**
     * @var string Access token (community or long-lived user token)
     */
    private $accessToken;

    /**
     * @var int|null HTTP timeout in seconds
     */
    private $timeout;

    public function __construct($accessToken, $timeout = 20)
    {
        $this->accessToken = $accessToken;
        $this->timeout = $timeout;
    }

    /**
     * Send a text message to a peer id
     *
     * @param  int    $peerId
     * @param  string $text
     * @param  string $attachments Comma separated attachment strings (docXXX, photoXXX)
     * @return array  API response
     */
    public function sendMessage($peerId, $text, $attachments = '')
    {
        $params = array(
            'peer_id' => $peerId,
            'message' => $text,
            'v' => self::API_VERSION,
            'access_token' => $this->accessToken,
            'random_id' => mt_rand(),
        );

        if ($attachments !== '') {
            $params['attachment'] = $attachments;
        }

        return $this->call('messages.send', $params);
    }

    /**
     * Upload a local file and send it as document attachment
     *
     * @param  int    $peerId
     * @param  string $filePath Absolute path of the local file
     * @param  string $fileName Original file name
     * @return array  API response of messages.send
     */
    public function sendDocument($peerId, $filePath, $fileName)
    {
        $upload = $this->call('docs.getMessagesUploadServer', array(
            'peer_id' => $peerId,
            'type' => 'doc',
            'v' => self::API_VERSION,
            'access_token' => $this->accessToken,
        ));

        if (!isset($upload['response']['upload_url'])) {
            return $upload;
        }

        $fileInfo = $this->uploadFile($upload['response']['upload_url'], $filePath, $fileName);

        if (!isset($fileInfo['file'])) {
            return array('error' => 1, 'error_msg' => 'Failed to upload file to VK');
        }

        $saved = $this->call('docs.save', array(
            'file' => $fileInfo['file'],
            'title' => $fileName,
            'v' => self::API_VERSION,
            'access_token' => $this->accessToken,
        ));

        if (!isset($saved['response'])) {
            return $saved;
        }

        $attachment = $this->formatAttachment($saved['response']);

        return $this->sendMessage($peerId, '', $attachment);
    }

    /**
     * Build the attachment string ("doc123_456" / "photo123_456") from a
     * docs.save response
     *
     * @param  array $saveResponse
     * @return string
     */
    public function formatAttachment(array $saveResponse)
    {
        foreach (array('doc', 'photo') as $type) {
            if (isset($saveResponse[$type]['id']) && isset($saveResponse[$type]['owner_id'])) {
                return $type . $saveResponse[$type]['owner_id'] . '_' . $saveResponse[$type]['id'];
            }
        }

        return '';
    }

    /**
     * Check whether the API response means "token is invalid/expired"
     *
     * @param  array $response
     * @return bool
     */
    public static function isTokenError(array $response)
    {
        return isset($response['error_code']) && in_array((int) $response['error_code'], array(5, 6, 9, 17), true);
    }

    /**
     * Perform a POST call to the VK API
     *
     * @param  string $method
     * @param  array  $params
     * @return array
     */
    private function call($method, array $params)
    {
        $ch = curl_init(self::API_URL . $method);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return is_array($data) ? $data : array('error' => 1, 'error_msg' => 'Invalid response from VK');
    }

    /**
     * Upload a file (multipart/form-data) to the temporary VK upload server
     *
     * @param  string $uploadUrl
     * @param  string $filePath
     * @param  string $fileName
     * @return array
     */
    private function uploadFile($uploadUrl, $filePath, $fileName)
    {
        if (!file_exists($filePath)) {
            return array();
        }

        $postFields = array(
            'file' => new \CURLFile($filePath, 'application/octet-stream', $fileName),
        );

        $ch = curl_init($uploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return is_array($data) ? $data : array();
    }
}
