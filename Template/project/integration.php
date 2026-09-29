<h3>VK (VKontakte)</h3>
<?php if (empty($token_available)) : ?>
    <p class="alert alert-info">
        <?= t('VK is not configured in Kanboard settings.') ?><br />
        <?= t('Go to ') ?><a href="<?= $this->url->href('ConfigController', 'integrations') ?>">
            <?= t('Settings > Integrations > VK') ?> </a> <?= t(' and fill the form:') ?> <br />
        <?= t('VK access token: Community access token with the "messages" permission') ?><br />
    </p>
<?php else : ?>
    <?php $random = md5(time() . $this->url->base() . rand()); ?>
    <p>
        <?= t('To get your VK peer id,') ?><br />
        <?= t('1. Send the message %s to', $random) ?> <a href="https://vk.com/im" target="_blank" rel="noreferrer"><?= t('the dialog with the bot/community') ?></a><br />
        <?= t('2. Press') ?><?= $this->modal->medium('none', t('Get peer id'), 'VkController', 'get_project_group_id', array('plugin' => 'VK', 'private_message' => $random, 'project_id' => $project['id'])) ?>
    </p>

    <div class="panel">
        <?= $this->form->label(t('Peer id of the community chat/dialog'), 'vk_group_cid') ?>
        <?= $this->form->text('vk_group_cid', $values, array()) ?>

        <?= $this->form->label(t('Project specific VK access token (optional)'), 'vk_access_token') ?>
        <?= $this->form->text('vk_access_token', $values, array()) ?>

        <div class="form-actions">
            <input type="submit" value="<?= t('Save') ?>" class="btn btn-blue" />
        </div>
    </div>
<?php endif ?>
