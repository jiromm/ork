<?php

function loadConfig(): array
{
    return (include __DIR__ . '/config.local.php') + (include __DIR__ . '/config.php');
}