<?php

namespace Kanboard\Plugin\VK\Notification;

use Kanboard\Core\Base;
use Kanboard\Core\Notification\NotificationInterface;
use Kanboard\Model\TaskModel;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\CommentModel;
use Kanboard\Model\TaskFileModel;
use Kanboard\Model\UserModel;
use Kanboard\Plugin\VK\Notification\Api\VkApi;

/**
 * VK Notification
 *
 * Send Kanboard notifications to VK (VKontakte) personal dialogs and
 * community chats.
 *
 * @package  notification
 * @author   Kanboard VK Plugin
 */
class VK extends Base implements NotificationInterface
{
    /**
     * Send notification to a user
     *
     * @access public
     * @param  array     $user
     * @param  string    $eventName
     * @param  array     $eventData
     */
    public function notifyUser(array $user, $eventName, array $eventData)
    {
        // Prefer the community token of the bot, fallback to the personal
        // long-lived token of the user (OAuth connection)
        $token = $this->configModel->get('vk_access_token');
        if (empty($token)) {
            $token = $this->vkLongAccessTokenModel->getToken($user['id']);
        }

        $peer_id = $this->userMetadataModel->get($user['id'], 'vk_user_cid');
        $forward_attachments = $this->userMetadataModel->get($user['id'], 'vk_forward_attachments', $this->configModel->get('vk_forward_attachments'));

        if (!empty($token) && !empty($peer_id)) {
            if ($eventName === TaskModel::EVENT_OVERDUE) {
                foreach ($eventData['tasks'] as $task) {
                    $project = $this->projectModel->getById($task['project_id']);
                    $eventData['task'] = $task;
                    $this->sendMessage($token, $peer_id, $forward_attachments, $project, $eventName, $eventData);
                }
            } else {
                $project = $this->projectModel->getById($eventData['task']['project_id']);
                $this->sendMessage($token, $peer_id, $forward_attachments, $project, $eventName, $eventData);
            }
        }
    }

    /**
     * Send notification to a project
     *
     * @access public
     * @param  array     $project
     * @param  string    $eventName
     * @param  array     $eventData
     */
    public function notifyProject(array $project, $eventName, array $eventData)
    {
        $token = $this->projectMetadataModel->get($project['id'], 'vk_access_token', $this->configModel->get('vk_access_token'));
        $peer_id = $this->projectMetadataModel->get($project['id'], 'vk_group_cid');
        $forward_attachments = $this->projectMetadataModel->get($project['id'], 'vk_forward_attachments', $this->configModel->get('vk_forward_attachments'));

        if (!empty($token) && !empty($peer_id)) {
            $this->sendMessage($token, $peer_id, $forward_attachments, $project, $eventName, $eventData);
        }
    }

    /**
     * Send message to VK
     *
     * @access protected
     * @param  string    $token               Access token
     * @param  int       $peer_id             VK peer id (dialog or -2000000000-style chat id)
     * @param  bool      $forward_attachments Whether to upload & send task files
     * @param  array     $project
     * @param  string    $eventName
     * @param  array     $eventData
     */
    protected function sendMessage($token, $peer_id, $forward_attachments, array $project, $eventName, array $eventData)
    {
        // Get required data

        if ($this->userSession->isLogged()) {
            $author = $this->helper->user->getFullname();
            $title = $this->notificationModel->getTitleWithAuthor($author, $eventName, $eventData);
        } else {
            $title = $this->notificationModel->getTitleWithoutAuthor($eventName, $eventData);
        }

        $proj_name = isset($eventData['project_name']) ? $eventData['project_name'] : $eventData['task']['project_name'];
        $task_title = $eventData['task']['title'];
        $task_url = $this->helper->url->to('TaskViewController', 'show', array('task_id' => $eventData['task']['id'], 'project_id' => $project['id']), '', true);

        $attachment_path = '';
        $attachment_name = '';

        // Build message
        // Note: VK does not support HTML in messages, plain text is used instead

        $message = '[' . $proj_name . "]\n";
        $message .= $this->vkContent($title, $project) . "\n";

        if ($this->configModel->get('application_url') !== '') {
            $message .= $task_title . ' - ' . $task_url;
        } else {
            $message .= $task_title;
        }

        // Add additional informations

        $description_events = array(TaskModel::EVENT_CREATE, TaskModel::EVENT_UPDATE, TaskModel::EVENT_USER_MENTION);
        $subtask_events = array(SubtaskModel::EVENT_CREATE, SubtaskModel::EVENT_UPDATE, SubtaskModel::EVENT_DELETE);
        $comment_events = array(CommentModel::EVENT_UPDATE, CommentModel::EVENT_CREATE, CommentModel::EVENT_DELETE, CommentModel::EVENT_USER_MENTION);

        if (in_array($eventName, $subtask_events)) { // For subtask events
            $subtask_status = $eventData['subtask']['status'];
            $subtask_symbol = '';

            if ($subtask_status == SubtaskModel::STATUS_DONE) {
                $subtask_symbol = '❌ ';
            } elseif ($subtask_status == SubtaskModel::STATUS_TODO) {
                $subtask_symbol = '';
            } elseif ($subtask_status == SubtaskModel::STATUS_INPROGRESS) {
                $subtask_symbol = '🕘 ';
            }

            $message .= "\n  ↳ " . $subtask_symbol . '"' . $eventData['subtask']['title'] . '"';
        } elseif (in_array($eventName, $description_events)) { // If description available
            if ($eventData['task']['description'] != '') {
                $message .= "\n✏️ \"" . $this->vkContent($eventData['task']['description'], $project) . '"';
            }
        } elseif (in_array($eventName, $comment_events)) { // If comment available
            $message .= "\n💬 \"" . $this->vkContent($eventData['comment']['comment'], $project) . '"';
        } elseif ($eventName === TaskFileModel::EVENT_CREATE && $forward_attachments) { // If attachment available
            $attachment_path = getcwd() . '/data/files/' . $eventData['file']['path'];
            $attachment_name = $eventData['file']['name'];
        }

        // Send Message

        try {
            $vk = new VkApi($token);

            $result = $vk->sendMessage($peer_id, $message);

            if (isset($result['error'])) {
                throw new \Exception(isset($result['error_msg']) ? $result['error_msg'] : 'Unknown VK API error');
            }

            // Send any attachment if exists
            if ($attachment_path !== '' && file_exists($attachment_path)) {
                $result_att = $vk->sendDocument($peer_id, $attachment_path, $attachment_name);

                if (isset($result_att['error'])) {
                    error_log('Kanboard VK plugin: ' . (isset($result_att['error_msg']) ? $result_att['error_msg'] : 'attachment upload failed'));
                }
            }
        } catch (\Exception $e) {
            // log vk errors
            error_log($e->getMessage());
        }
    }

    /**
     * Converts user mentions and task IDs in the content to corresponding
     * links for VK (@usernames are highlighted by VK automatically, task ids
     * become absolute Kanboard urls).
     *
     * @param string $content The content string to be processed
     * @param array  $project The project context
     * @return string The modified content string
     */
    public function vkContent($content, $project)
    {
        $taskPattern = '!#(\d+)!i';

        $taskReplacement = function ($matches) use ($project) {
            return $this->inlineTaskLink($matches[1], $project);
        };

        return preg_replace_callback($taskPattern, $taskReplacement, $content);
    }

    /**
     * Generates an inline link for a task ID in Kanboard.
     *
     * @param int   $task_id The task ID to be linked
     * @param array $project The project context
     * @return string The generated link for the task ID
     */
    public function inlineTaskLink($task_id, $project)
    {
        $task_url = $this->helper->url->to('TaskViewController', 'show', array('task_id' => $task_id, 'project_id' => $project['id']), '', true);
        return '#' . $task_id . ' (' . $task_url . ')';
    }
}
