<?php

namespace Kanboard\Plugin\VK\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\VK\Model\VkLongAccessTokenModel;

/**
 * VK Controller
 *
 * Handles the VK OAuth authorization-code flow (community/group tokens and
 * personal long-lived user tokens) and the "secret code" pairing of VK
 * dialogs with Kanboard users/projects.
 *
 * @package  vk
 * @author   Kanboard VK Plugin
 */
class VkController extends BaseController
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
     * VK OAuth endpoints
     */
    const OAUTH_AUTHORIZE_URL = 'https://oauth.vk.com/authorize';
    const OAUTH_ACCESS_TOKEN_URL = 'https://oauth.vk.com/access_token';
    const LONG_LIVED_TOKEN_URL = 'https://api.vk.com/method/auth.exchangeLongLivedToken';

    public function index()
    {
        $this->response->redirect($this->helper->url->to('UserViewController', 'integrations'));
    }

    /**
     * Step 1 of the VK OAuth flow: redirect the current Kanboard user to the
     * VK authorization page ("code" grant type). The same flow yields a
     * community token (if the user authorizes a community) and a short-lived
     * user token which is exchanged for a long-lived one in callback().
     *
     * @access public
     */
    public function authenticate()
    {
        $user = $this->getUserSession();
        $appId = trim($this->configModel->get('vk_app_id'));
        $appSecret = trim($this->configModel->get('vk_app_secret'));

        if ($appId === '' || $appSecret === '') {
            $this->flash->failure(t('Please set up your VK App ID and App Secret in settings first.'));
            return $this->response->redirect($this->helper->url->to('UserViewController', 'integrations'));
        }

        $params = array(
            'client_id' => $appId,
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'messages,groups,offline',
            'v' => self::API_VERSION,
            'state' => $user['id'],
        );

        $this->response->redirect(self::OAUTH_AUTHORIZE_URL . '?' . http_build_query($params, '', '&'));
    }

    /**
     * Step 2 of the VK OAuth flow: VK calls us back with ?code=...
     *
     * - The code is exchanged for an access token.
     * - If the response contains "groups", the first community token is saved
     *   into the global config (vk_access_token / vk_group_id).
     * - The user token is exchanged for a long-lived token (valid ~6 months)
     *   and stored in the users table (vk_long_token column).
     *
     * @access public
     */
    public function callback()
    {
        $code = $this->request->getStringParam('code');
        $state = $this->request->getStringParam('state');

        if (empty($code)) {
            $error = urldecode($this->request->getStringParam('error_description', 'Authorization failed'));
            $this->flash->failure(t('VK error: %s', $error));
            return $this->response->redirect($this->helper->url->to('UserViewController', 'integrations'));
        }

        $params = array(
            'client_id' => trim($this->configModel->get('vk_app_id')),
            'client_secret' => trim($this->configModel->get('vk_app_secret')),
            'redirect_uri' => $this->getRedirectUri(),
            'code' => $code,
            'v' => self::API_VERSION,
        );

        $result = $this->httpPostForm(self::OAUTH_ACCESS_TOKEN_URL, $params);

        if (!isset($result['access_token'])) {
            $message = isset($result['error_description']) ? $result['error_description'] : t('Invalid response from VK.');
            $this->flash->failure(t('VK error: %s', $message));
            return $this->response->redirect($this->helper->url->to('UserViewController', 'integrations'));
        }

        $userId = (int) $state;

        // Community (group) token -> store globally so projects can use it
        if (isset($result['groups']) && !empty($result['groups'])) {
            $group = reset($result['groups']);
            $this->configModel->save(array(
                'vk_group_id' => $group['group_id'],
                'vk_access_token' => $result['access_token'],
            ));
            $this->flash->success(t('The community access token was saved successfully. You can now configure projects.'));
        }

        // User token -> exchange the short-lived token for a long-lived one
        if ($userId > 0 && isset($result['user_id'])) {
            $longLived = $this->exchangeLongLivedToken($result['access_token']);

            if ($longLived !== false) {
                $this->vkLongAccessTokenModel->storeToken($userId, $longLived);
                $this->flash->success(t('Your personal VK account has been connected successfully.'));
            } else {
                $this->flash->failure(t('Unable to get a long-lived token from VK. Try again.'));
            }
        }

        if (empty($result['groups']) && ($userId <= 0 || !isset($result['user_id']))) {
            $this->flash->failure(t('Unexpected response from VK.'));
        }

        return $this->response->redirect($this->helper->url->to('UserViewController', 'integrations'));
    }

    /**
     * Remove the stored long-lived user token
     *
     * @access public
     */
    public function revoke()
    {
        $user = $this->getUserSession();
        $this->checkCSRFParam();

        $this->vkLongAccessTokenModel->revokeToken($user['id']);
        $this->flash->success(t('The VK connection has been removed.'));

        return $this->response->redirect($this->helper->url->to('UserViewController', 'integrations'));
    }

    public function get_user_peer_id()
    {
        $user = $this->getUser();

        $token = $this->configModel->get('vk_access_token');
        $secret_code = mb_substr(urldecode($this->request->getStringParam('private_message')), 0, 32);

        list($peer_id, $user_name) = $this->find_peer_id_by_secret_code($token, $secret_code);

        if ($peer_id !== '') {
            //ok
            $this->response->html($this->template->render('vk:user/save_peer_id', array(
                'peer_id' => $peer_id,
                'user_name' => $user_name,
                'private_message' => $secret_code,
                'user' => $user,
            )));
        } else {
            //error
            $this->flash->failure(t('VK error: secret message not found. Please send it to the bot first.'));
            $this->response->redirect($this->helper->url->to('UserViewController', 'integrations', array('user_id' => $user['id'])), true);
        }
    }

    public function get_project_group_id()
    {
        $project = $this->getProject();

        $token = $this->projectMetadataModel->get($project['id'], 'vk_access_token', $this->configModel->get('vk_access_token'));
        $secret_code = mb_substr(urldecode($this->request->getStringParam('private_message')), 0, 32);

        list($peer_id, $group_name) = $this->find_peer_id_by_secret_code($token, $secret_code);

        if ($peer_id !== '') {
            //ok
            $this->response->html($this->template->render('vk:project/save_group_id', array(
                'peer_id' => $peer_id,
                'group_name' => $group_name,
                'private_message' => $secret_code,
                'project' => $project,
            )));
        } else {
            //error
            $this->flash->failure(t('VK error: secret message not found. Please send it to the community first.'));
            $this->response->redirect($this->helper->url->to('ProjectViewController', 'integrations', array('project_id' => $project['id'])), true);
        }
    }

    public function save_user_peer_id()
    {
        $user = $this->getUser();
        $this->checkCSRFParam();

        $peer_id = urldecode($this->request->getStringParam('peer_id'));
        if (is_numeric($peer_id)) {
            $this->userMetadataModel->save($user['id'], array('vk_user_cid' => $peer_id));
            $this->flash->success(t("Peer id was updated to %s", $peer_id));
        } else {
            $this->flash->failure(t('VK error: wrong peer id'));
        }

        return $this->response->redirect($this->helper->url->to('UserViewController', 'integrations', array('user_id' => $user['id'])), true);
    }

    public function save_project_group_id()
    {
        $project = $this->getProject();
        $this->checkCSRFParam();

        $peer_id = urldecode($this->request->getStringParam('peer_id'));
        if (is_numeric($peer_id)) {
            $this->projectMetadataModel->save($project['id'], array('vk_group_cid' => $peer_id));
            $this->flash->success(t("Group peer id was updated to %s", $peer_id));
        } else {
            $this->flash->failure(t('VK error: wrong peer id'));
        }

        return $this->response->redirect($this->helper->url->to('ProjectViewController', 'integrations', array('project_id' => $project['id'])), true);
    }

    /**
     * Look at the last conversations of the bot/community and find the one
     * which received the secret code as an incoming message.
     *
     * @access private
     * @param  string $token       Community access token
     * @param  string $secret_code Secret code sent by the user
     * @return array  [peer_id, name]
     */
    private function find_peer_id_by_secret_code($token, $secret_code)
    {
        $peer_id = '';
        $name = '';

        if (empty($token) || empty($secret_code)) {
            return array($peer_id, $name);
        }

        try {
            $response = $this->apiCall('messages.get', $token, array(
                'count' => 200,
                'preview_length' => 255,
            ));

            if (isset($response['error'])) {
                throw new \Exception(isset($response['error_msg']) ? $response['error_msg'] : 'Unknown VK API error');
            }

            if (isset($response['response']['items'])) {
                foreach ($response['response']['items'] as $conversation) {
                    $message = isset($conversation['last_message']) ? $conversation['last_message'] : null;

                    if ($message === null) {
                        continue;
                    }

                    $is_outgoing = !empty($message['out']);

                    if (!$is_outgoing && isset($message['text']) && mb_strpos($message['text'], $secret_code) !== false) {
                        $peer_id = $conversation['peer']['id'];
                        $name = isset($conversation['peer']['title']) && $conversation['peer']['title'] !== ''
                            ? $conversation['peer']['title']
                            : trim($conversation['peer']['first_name'] . ' ' . $conversation['peer']['last_name']);
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
            $this->flash->failure(t('VK error: ') . $e->getMessage());
        }

        return array($peer_id, $name);
    }

    /**
     * Exchange a short-lived user token for a long-lived one (valid ~6 months).
     *
     * @access protected
     * @param  string  $accessToken
     * @return string|false
     */
    protected function exchangeLongLivedToken($accessToken)
    {
        $result = $this->httpPostForm(self::LONG_LIVED_TOKEN_URL, array(
            'access_token' => $accessToken,
            'v' => self::API_VERSION,
        ));

        if (isset($result['response']['access_token'])) {
            return $result['response']['access_token'];
        }

        return false;
    }

    /**
     * OAuth callback URI of this Kanboard installation
     *
     * @access protected
     * @return string
     */
    protected function getRedirectUri()
    {
        return $this->helper->url->to('VkController', 'callback', array('plugin' => 'VK'), '', true);
    }

    /**
     * Simple form POST helper using cURL
     *
     * @access protected
     * @param  string $url
     * @param  array  $params
     * @return array
     */
    protected function httpPostForm($url, array $params)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return is_array($data) ? $data : array();
    }

    /**
     * Call the VK REST API using POST form data
     *
     * @access private
     * @param  string $method API method name (e.g. messages.get)
     * @param  string $token  Access token
     * @param  array  $params Additional parameters
     * @return array
     */
    private function apiCall($method, $token, array $params = array())
    {
        $params['access_token'] = $token;
        $params['v'] = self::API_VERSION;

        $ch = curl_init(self::API_URL . $method);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return is_array($data) ? $data : array('error' => 1, 'error_msg' => 'Invalid response from VK');
    }
}
