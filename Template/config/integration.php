<h3>VK (VKontakte)</h3>
<div class="panel">
    <?= $this->form->label(t('VK App ID'), 'vk_app_id') ?>
    <?= $this->form->text('vk_app_id', $values, array()) ?>

    <?= $this->form->label(t('VK App Secret'), 'vk_app_secret') ?>
    <?= $this->form->text('vk_app_secret', $values, array()) ?>

    <p class="form-help"><a href="https://dev.vk.com/ru" target="_blank"><?= t('Help on how to create a VK app') ?></a></p>

    <?= $this->form->label(t('VK access token (community or bot)'), 'vk_access_token') ?>
    <?= $this->form->text('vk_access_token', $values, array()) ?>
    <p class="form-help"><?= t('Community access token with the "messages" permission. It can also be obtained automatically via "Connect VK" on your profile integrations page.') ?></p>

    <?= $this->form->hidden('vk_forward_attachments', array('vk_forward_attachments' => 0)) ?>
    <?= $this->form->checkbox('vk_forward_attachments', t('Send attachments along with notification'), 1, isset($values['vk_forward_attachments']) && $values['vk_forward_attachments'] == 1) ?>

    <div class="form-actions">
        <input type="submit" value="<?= t('Save') ?>" class="btn btn-blue"/>
    </div>
</div>
