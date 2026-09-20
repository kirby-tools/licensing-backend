<?php

error_reporting(E_ALL);

ini_set('memory_limit', '512M');
ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

require_once __DIR__ . '/../vendor/autoload.php';

// Whoops registers error handlers on every `App` that a test never removes,
// which PHPUnit reports as risky.
Kirby\Cms\App::$enableWhoops = false;

require_once __DIR__ . '/LicenseTestCase.php';
