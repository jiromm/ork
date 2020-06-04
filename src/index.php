<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/fn.php';

// TODO use --defaults-extra-file for mysql and mysqldump commands
// TODO use colors for message types

$config = loadConfig();

try {
    // Configure
    $copyDBName = $config['prod']['db']['name'] . $config['meta']['db']['copy_sufix'];
    $tempFileName = sprintf('%s_%s.sql',
        $config['prod']['db']['name'],
        date('Y-m-d')
    );
    $tempFile = sprintf('%s/%s',
        $config['meta']['temp_dir'],
        $tempFileName
    );
    $tempFileLocal = sprintf('~/%s', $tempFileName);
    $tarGzProdFile = sprintf('~/%s_%s.sql.tar.gz',
        $config['prod']['db']['name'],
        date('Y-m-d')
    );
    $tarGzStageFile = sprintf('~/%s_%s.sql.tar.gz',
        $config['stage']['db']['name'],
        date('Y-m-d')
    );
    $tarGzLocalFile = sprintf('~/%s_%s.sql.tar.gz',
        $config['prod']['db']['name'],
        date('Y-m-d')
    );
    $removeTarFileLocal = sprintf('rm %s', $tarGzLocalFile);
    $removeSQLFileLocal = sprintf('rm %s', $tempFileLocal);

    $dbDumpCommand = sprintf(
        HIDE . 'mysqldump -u %s -p\'%s\' %s > %s',
        $config['prod']['db']['user'],
        $config['prod']['db']['pass'],
        $config['prod']['db']['name'],
        $tempFile
    );
    $restoreDbCommand = sprintf(
        HIDE . 'mysql -u %s -p\'%s\' %s < %s',
        $config['prod']['db']['user'],
        $config['prod']['db']['pass'],
        $copyDBName,
        $tempFile
    );
    $restoreStageDbCommand = sprintf(
        HIDE . 'mysql -u %s -p\'%s\' %s < %s',
        $config['stage']['db']['user'],
        $config['stage']['db']['pass'],
        $config['stage']['db']['name'],
        $tempFileLocal
    );
    $removeSQLFileProd = sprintf('rm %s', $tempFile);

    $dbEncoding = '';
    if (!empty($config['meta']['db']['character']) && !empty($config['meta']['db']['collation'])) {
        $dbEncoding = sprintf(' CHARACTER SET %s COLLATE %s',
            $config['meta']['db']['character'],
            $config['meta']['db']['collation']
        );
    }
    $dbDropSQL = sprintf('DROP DATABASE IF EXISTS %s;', $config['stage']['db']['name']);
    $dbDropCopySQL = sprintf('DROP DATABASE IF EXISTS %s;', $copyDBName);
    $dbCreateSQL = sprintf('CREATE DATABASE %s%s;',
        $config['stage']['db']['name'],
        $dbEncoding
    );
    $dbCreateCopySQL = sprintf('CREATE DATABASE %s%s;',
        $copyDBName,
        $dbEncoding
    );
    $dbDumpCopyCommand = sprintf(
        HIDE . 'mysqldump -u %s -p\'%s\' %s > %s',
        $config['prod']['db']['user'],
        $config['prod']['db']['pass'],
        $copyDBName,
        $tempFile
    );
    $tarCommand = sprintf('tar -czvf %s --directory=%s %s',
        $tarGzProdFile,
        $config['meta']['temp_dir'],
        $tempFileName
    );
    $scpProdToLocalCommand = sprintf('scp -i %s %s@%s:%s %s',
        $config['meta']['key'],
        $config['prod']['server']['user'],
        $config['prod']['server']['host'],
        $tarGzProdFile,
        $tarGzLocalFile
    );
    $scpLocalToStageCommand = sprintf('scp -i %s %s %s@%s:%s',
        $config['meta']['key'],
        $tarGzLocalFile,
        $config['stage']['server']['user'],
        $config['stage']['server']['host'],
        $tarGzStageFile
    );
    $extractTar = sprintf('tar -xzvf %s', $tarGzStageFile);
    $removeDumpFileProd = sprintf('rm %s', $tarGzProdFile);

    // Connect to PROD server
    $sshProd = connect($config['prod']['server']);
    pr('Connected to PROD server');

    // Dump DB
    echo $sshProd->exec($dbDumpCommand);
    pr('Dump DB', $dbDumpCommand);

    // Drop copy DB if exist
    echo execMysql($sshProd, $config['prod']['db'], $dbDropCopySQL);
    pr('Drop copy DB', $dbDropCopySQL);

    // Create copy DB
    echo execMysql($sshProd, $config['prod']['db'], $dbCreateCopySQL);
    pr('Create copy DB', $dbCreateCopySQL);

    // Restore DB
    echo $sshProd->exec($restoreDbCommand);
    pr('Restore DB', $restoreDbCommand);

    // Run SQL migration queries
    foreach ($config['hooks']['post_prod'] as $sqlQuery) {
        echo execMysql($sshProd, $config['prod']['db'], $sqlQuery, $copyDBName);
        pr('SQL', $sqlQuery);
    }

    // Dump copy DB
    echo $sshProd->exec($dbDumpCopyCommand);
    pr('Dump copy DB',  $dbDumpCopyCommand);

    // Compress dump file
    echo $sshProd->exec($tarCommand);
    pr('Dump file compressed',  $tarCommand);

    // Connect to STAGE server
    $sshStage = connect($config['stage']['server']);
    pr('Connected to STAGE server');

    // Import dump file from PROD to LOCAL
    echo exec($scpProdToLocalCommand);
    pr('Dump file moved from PROD to LOCAL', $scpProdToLocalCommand);

    // Export dump file from LOCAL to STAGE
    echo exec($scpLocalToStageCommand);
    pr('Dump file moved from PROD to LOCAL', $scpLocalToStageCommand);

    // Extract dump file
    echo $sshStage->exec($extractTar);
    pr('Extract dump file', $extractTar);

    // Drop DB
    echo execMysql($sshStage, $config['stage']['db'], $dbDropSQL);
    pr('Drop DB', $dbDropSQL);

    // Create DB
    echo execMysql($sshStage, $config['stage']['db'], $dbCreateSQL);
    pr('Create DB', $dbCreateSQL);

    // Restore DB
    echo $sshProd->exec($restoreStageDbCommand);
    pr('Restore DB', $restoreStageDbCommand);

    // Remove LOCAL dump file
    echo exec($removeTarFileLocal);
    pr('LOCAL dump file removed', $removeTarFileLocal);

    // Remove LOCAL SQL file
    echo exec($removeSQLFileLocal);
    pr('LOCAL SQL file removed', $removeSQLFileLocal);

    // Remove PROD dump file
    echo exec($removeSQLFileProd);
    pr('PROD SQL file removed', $removeSQLFileProd);

    // Remove PROD dump file
    echo exec($removeDumpFileProd);
    pr('PROD SQL file removed', $removeDumpFileProd);
} catch (\Throwable $e) {
    echo 'ERROR! ' . $e->getMessage() . PHP_EOL;
}
