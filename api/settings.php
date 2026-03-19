<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

jsonResponse([
    'tts_engine' => getSetting('tts_engine', 'webspeech'),
]);
