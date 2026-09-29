<?php

namespace Kanboard\Plugin\VK;

use Kanboard\Core\Translator;
use Kanboard\Core\Plugin\Base;

/**
 * VK Plugin
 *
 * Receive Kanboard notifications on VK (VKontakte) social network.
 *
 * @package  vk
 * @author   Kanboard VK Plugin
 */
class Plugin extends Base
{
    public function initialize()
    {
        // Add the vk_long_token column to the "users" table (needed for
        // personal user tokens obtained through VK OAuth)
        $this->schemaManager->addMissingColumnToTable('users', 'vk_long_token');

        $this->route->addRoute('/auth/vk/', 'VkController', 'authenticate', 'VK');
        $this->route->addRoute('/callback/vk/', 'VkController', 'callback', 'VK');

        $this->template->hook->attach('template:config:integrations', 'vk:config/integration');

        // Pass the global VK token availability to project/user integration templates
        $token = $this->configModel->get('vk_access_token');
        $this->template->hook->attach('template:project:integrations', 'vk:project/integration', array('token_available' => $token));
        $this->template->hook->attach('template:user:integrations', 'vk:user/integration', array('token_available' => $token));

        $this->userNotificationTypeModel->setType('vk', t('VK'), '\Kanboard\Plugin\VK\Notification\VK');
        $this->projectNotificationTypeModel->setType('vk', t('VK'), '\Kanboard\Plugin\VK\Notification\VK');
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    public function getClasses()
    {
        return array(
            'Plugin\VK\Model' => array(
                'VkLongAccessTokenModel',
            ),
            'Plugin\VK\Controller' => array(
                'VkController',
            ),
            'Plugin\VK\Notification\Api' => array(
                'VkApi',
            ),
        );
    }

    public function getPluginName()
    {
        return 'VK';
    }

    public function getPluginDescription()
    {
        return 'Receive notifications on VK (VKontakte)';
    }

    public function getPluginAuthor()
    {
        return 'Kanboard VK Plugin';
    }

    public function getPluginVersion()
    {
        return '1.0.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/manuvarkey/kanboard-plugin-telegram';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.22';
    }
}
