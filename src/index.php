<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/fn.php';

$config = loadConfig();

try {
    // Connect to PROD server
    $ssh = connect($config['prod']['server']);
    pr('Connected to server');

    // Dump DB
    $tempFile = sprintf('%s/%s_%s.sql',
        $config['meta']['temp_dir'],
        $config['prod']['db']['name'],
        date('Y-m-d')
    );
    $command = sprintf(
        HIDE . 'mysqldump -u %s -p\'%s\' %s > %s',
        $config['prod']['db']['user'],
        $config['prod']['db']['pass'],
        $config['prod']['db']['name'],
        $tempFile
    );
    $ssh->exec($command);
    pr('Dump DB executed: ' . $command);

    // Create copy DB
    $copyDBName = $config['prod']['db']['name'] . $config['meta']['db']['copy_sufix'];
    $dbEncoding = '';
    if (!empty($config['meta']['db']['character']) && !empty($config['meta']['db']['collation'])) {
        $dbEncoding = sprintf(' CHARACTER SET %s COLLATE %s',
            $config['meta']['db']['character'],
            $config['meta']['db']['collation']
        );
    }

    $dbCreateSQL = sprintf('CREATE DATABASE %s%s;',
        $copyDBName,
        $dbEncoding
    );

    execMysql($ssh, $config['prod']['db'], $dbCreateSQL);
    pr('Restore DB executed: ' . $command);

    // Restore DB
    // code here

    foreach ($config['hooks']['post_prod'] as $sqlQuery) {
        execMysql($ssh, $config['prod']['db'], $sqlQuery, $copyDBName);
        pr('SQL Executed: ' . $sqlQuery);
    }
} catch (\Throwable $e) {
    echo 'ERROR! ' . $e->getMessage() . PHP_EOL;
}