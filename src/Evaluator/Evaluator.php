<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\Evaluator;

use TimAlexander\Myagent\GPT\GPT;
use TimAlexander\Myagent\GPTMessage\GPTMessageModel;
use TimAlexander\Myagent\Task\Task;
use TimAlexander\Myagent\Memory\Memory;

final class Evaluator
{
    private GPT $evaluatorGpt;

    public function __construct()
    {
        // Use the evaluation model for high-quality assessment
        $this->evaluatorGpt = new GPT('evaluation');
    }

    public function setApiKey(string $apiKey): void
    {
        $this->evaluatorGpt->setApiKey($apiKey);
    }

    /**
     * Evaluates the current task completion status and returns a score from 0-10
     */
    public function evaluateTaskCompletion(Task $task, Memory $memory): int
    {
        $evaluationPrompt = $this->createEvaluationPrompt($task, $memory);
        $this->evaluatorGpt->send($evaluationPrompt, null, 'json_object');

        try {
            $response = json_decode($this->evaluatorGpt->response->content, true, 512, JSON_THROW_ON_ERROR);

            if (isset($response['score']) && is_numeric($response['score'])) {
                $score = (int) $response['score'];
                // Ensure score is within 0-10 range
                $score = max(0, min(10, $score));

                // Store the score in memory
                $memory->storeScore($score);

                return $score;
            }
        } catch (\JsonException) {
            // If JSON parsing fails, default to a middle score to continue the process
            echo "Warning: Failed to parse evaluation response. Using default score.\n";
        }

        return 5; // Default middle score if parsing fails
    }

    /**
     * Generates feedback for the current approach
     */
    public function generateFeedback(Task $task, Memory $memory): string
    {
        $feedbackPrompt = $this->createFeedbackPrompt($task, $memory);
        $this->evaluatorGpt->send($feedbackPrompt);

        return $this->evaluatorGpt->response->content;
    }

    private function createEvaluationPrompt(Task $task, Memory $memory): GPTMessageModel
    {
        $latestApproaches = $memory->getAllApproaches();
        $latestApproach = empty($latestApproaches) ? "No approach yet." : end($latestApproaches)['content'];

        $message = new GPTMessageModel();
        $message->role = 'user';
        $message->content = <<<EOT
You are an objective evaluator tasked with rating the completeness of a solution.

TASK:
{$task->getDescription()}

LATEST APPROACH:
$latestApproach

Using a scale from 0-10 (where 0 is not started and 10 is fully completed):
1. Evaluate how well the approach addresses the task
2. Consider accuracy, comprehensiveness, and practicality
3. Return ONLY a JSON object with the structure: {"score": X, "rationale": "brief explanation"}

Ensure your assessment is fair, objective, and based solely on how well the approach fulfills the original task requirements.
EOT;

        return $message;
    }

    private function createFeedbackPrompt(Task $task, Memory $memory): GPTMessageModel
    {
        $latestApproaches = $memory->getAllApproaches();
        $latestApproach = empty($latestApproaches) ? "No approach yet." : end($latestApproaches)['content'];

        $message = new GPTMessageModel();
        $message->role = 'user';
        $message->content = <<<EOT
You are providing constructive feedback to improve a solution to this task.

TASK:
{$task->getDescription()}

CURRENT APPROACH:
$latestApproach

Provide specific, actionable feedback on how to improve this approach. Focus on:
1. What aspects are missing or incomplete
2. What could be explored further
3. Specific directions to pursue in the next iteration

Your feedback should be constructive and helpful for creating an improved solution.
EOT;

        return $message;
    }
}
