<?php

namespace Kanboard\Plugin\VK\Model;

use Kanboard\Core\Base;

/**
 * VK Long-Lived User Access Token Model
 *
 * Stores long-lived user access tokens for each Kanboard user in the
 * "user_has_permissions" table (column: vk_long_token).
 *
 * VK user service tokens expire after ~12 hours, so they must be exchanged
 * for long-lived tokens (valid for 6-12 months) via the "secure.vk.com" endpoint.
 *
 * @package  vk
 * @author   Kanboard VK Plugin
 */
class VkLongAccessTokenModel extends Base
{
    const SERVICE = 'vk';

    /**
     * Save the long lived access token of the user
     *
     * @param  int    $userId
     * @param  string $token
     * @return bool
     */
    public function storeToken($userId, $token)
    {
        return $this->db
            ->table(self::TABLE_USERS)
            ->eq('id', $userId)
            ->eq('role', self::ROLE_MANAGER)
            ->save(array('vk_long_token' => $token));
    }

    /**
     * Get the long lived access token of the user
     *
     * @param  int    $userId
     * @return string|null
     */
    public function getToken($userId)
    {
        return $this->db
            ->table(self::TABLE_USERS)
            ->eq('id', $userId)
            ->eq('role', self::ROLE_MANAGER)
            ->eq('is_active', 1)
            ->pluck('vk_long_token');
    }

    /**
     * Check whether a long lived access token is saved for this user
     *
     * @param  int    $userId
     * @return bool
     */
    public function hasToken($userId)
    {
        $token = $this->getToken($userId);
        return !empty($token);
    }

    /**
     * Remove the long lived access token of the user
     *
     * @param  int    $userId
     * @return bool
     */
    public function revokeToken($userId)
    {
        return $this->db
            ->table(self::TABLE_USERS)
            ->eq('id', $userId)
            ->eq('role', self::ROLE_MANAGER)
            ->save(array('vk_long_token' => ''));
    }
}
