<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

jsonResponse([
    'tts_engine'       => getSetting('tts_engine',        'webspeech'),
    'minimax_tts_model'=> getSetting('minimax_tts_model', 'speech-02-turbo'),
    'minimax_tts_voice'=> getSetting('minimax_tts_voice', 'Turkish_Trustworthyman'),
]);
