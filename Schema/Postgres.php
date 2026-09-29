<?php

namespace Kanboard\Plugin\VK\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS plugin_vk_user_tokens (
        user_id INTEGER NOT NULL PRIMARY KEY,
        vk_long_token VARCHAR(255) DEFAULT \'\',
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
}
