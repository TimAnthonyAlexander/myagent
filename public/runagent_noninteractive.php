<?php

declare(strict_types=1);

namespace PublicScripts;

use TimAlexander\Myagent\Agent\Agent;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['api-key:']);

$apiKey = $options['api-key'] ?? null;

$taskDescription = 'Write a sentence about Duolingo.';

$agent = new Agent($apiKey);
$result = $agent->run($taskDescription, 'Nothing else. Just the sentence.');

print json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
