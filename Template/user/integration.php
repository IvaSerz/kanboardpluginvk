<?php if (empty($token_available)) : ?>
    <p class="alert alert-info">
        <?= t('VK is not configured in Kanboard settings.') ?><br />
        <?= t('Go to ') ?><a href="<?= $this->url->href('ConfigController', 'integrations') ?>">
            <?= t('Settings > Integrations > VK') ?> </a> <?= t(' and fill the form:') ?> <br />
        <?= t('VK access token: Community access token with the "messages" permission') ?><br />
    </p>
<?php else : ?>
    <?php $random = md5(time() . $this->url->base() . rand()); ?>
    <div class="panel">
        <p>
            <?= t('To get your VK peer id,') ?><br />
            <?= t('1. Send the message %s to', $random) ?> <a href="https://vk.com/im" target="_blank" rel="noreferrer"><?= t('the dialog with the bot/community') ?></a><br />
            <?= t('2. Press') ?><?= $this->modal->medium('none', t('Get peer id'), 'VkController', 'get_user_peer_id', array('plugin' => 'VK', 'private_message' => $random)) ?>
        </p>

        <?= $this->form->label(t('Peer id of private chat with bot'), 'vk_user_cid') ?>
        <?= $this->form->text('vk_user_cid', $values) ?>

        <div class="form-actions">
            <input type="submit" value="<?= t('Save') ?>" class="btn btn-blue" />
        </div>
    </div>
<?php endif ?>

<div class="panel">
    <h4><?= t('Personal VK account (OAuth)') ?></h4>
    <?php if (!empty($vk_connected)) : ?>
        <p class="alert alert-success"><?= t('Your personal VK account is connected. Notifications can be sent with your own long-lived user token.') ?></p>
        <a href="<?= $this->url->href('VkController', 'revoke', array('plugin' => 'VK', 'user_id' => $user['id'], 'csrf_token' => $this->security->getTokenCSRF())) ?>" class="btn btn-red"><?= t('Disconnect VK') ?></a>
    <?php else : ?>
        <p><?= t('You can connect your personal VK account to receive notifications from a community you manage, or use the shared bot token above.') ?></p>
        <a href="<?= $this->url->href('VkController', 'authenticate', array('plugin' => 'VK')) ?>" class="btn btn-blue"><?= t('Connect VK') ?></a>
    <?php endif ?>
</div>
