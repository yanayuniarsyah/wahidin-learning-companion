<?php
$c = file_get_contents('api.php');
$c = str_replace("'user' => ['role' => \$user['role'], 'username' => \$user['username']]", "'user' => ['role' => \$user['role'], 'username' => \$user['username'], 'email' => \$user['email'] ?? '']", $c);
file_put_contents('api.php', $c);
