<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Suppress deprecation warnings at PHP level
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);