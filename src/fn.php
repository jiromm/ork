<?php

use \phpseclib\Net\SSH2;

define('HIDE', ' ');

function loadConfig(): array
{
    $main = include __DIR__ . '/config.php';
    if (!($local = include __DIR__ . '/config.local.php')) {
        $local = [];
    }
    return $local + $main;
}

function connect(array $config): SSH2
{
    $ssh = new SSH2($config['host']);

    if ($config['prod']['key']) {
        $key = new RSA();
        $key->loadKey(file_get_contents($config['prod']['key']));
    } else {
        $key = $config['pass'];
    }

    if (!$ssh->login($config['user'], $key)) {
        throw new DomainException('Authentication failed');
    }

    return $ssh;
}

function pr(string $message)
{
    echo $message . PHP_EOL;
}

function execMysql($ssh, $dbConfig, $query, $dbName = null)
{
    $command = sprintf(HIDE . 'mysql -u %s -p\'%s\' %s -e "%s"',
        $dbConfig['user'],
        $dbConfig['pass'],
        (string)$dbName,
        $query
    );
    return $ssh->exec($command);
}