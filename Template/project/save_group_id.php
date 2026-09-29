<div class="page-header">
    <h2><?= t('Save Peer ID') ?></h2>
</div>

<?php if (empty($peer_id)) : ?>
    <div class="confirm">
        <p class="alert alert-info"><?= t('Message %s not found!', $private_message) ?></p>
        <p class="info"><?= t('Please send message %s ', $private_message) ?><br />
            to <a href="https://vk.com/im" target="_blank" rel="noreferrer"><?= t('the dialog with the bot/community') ?></a></p>
        <br />
        <p><?= t('If you wish to connect a community chat, please ensure that the community has the "messages" permission enabled!') ?></p>
    </div>
<?php else : ?>
    <div class="confirm">
        <p class="alert alert-info">
            <?= t('Save peer id="%s" from "%s"?', $this->text->e($peer_id), $this->text->e($group_name)) ?>
        </p>

        <?= $this->modal->confirmButtons(
            'VkController',
            'save_project_group_id',
            array('plugin' => 'VK', 'project_id' => $project['id'], 'peer_id' => $peer_id)
        ) ?>
    </div>
<?php endif ?>
