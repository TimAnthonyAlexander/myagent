<?php

declare(strict_types=1);

namespace public;

use TimAlexander\Myagent\Agent\Agent;

require_once __DIR__ . '/../vendor/autoload.php';

// Parse command line arguments
$options = getopt('', ['api-key:']);

// Check if API key was provided
$apiKey = $options['api-key'] ?? null;

// Check if a task was provided as command line argument
$taskDescription = null;
if (isset($argv[1]) && !str_starts_with($argv[1], '--api-key=')) {
    $taskDescription = $argv[1];
    // If multiple arguments are provided, combine them as a single task description
    if (count($argv) > 2) {
        $args = array_slice($argv, 1);
        $taskDescription = implode(' ', $args);
    }
}

$agent = new Agent($apiKey);
$agent->run($taskDescription);
