<?php

declare(strict_types=1);

namespace public;

use TimAlexander\Myagent\Agent\Agent;

require_once __DIR__ . '/../vendor/autoload.php';

// Check if a task was provided as command line argument
$taskDescription = null;
if (isset($argv[1])) {
    $taskDescription = $argv[1];
    // If multiple arguments are provided, combine them as a single task description
    if (count($argv) > 2) {
        $args = array_slice($argv, 1);
        $taskDescription = implode(' ', $args);
    }
}

$agent = new Agent();
$agent->run($taskDescription);
