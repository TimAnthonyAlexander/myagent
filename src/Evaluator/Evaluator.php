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
                
                // Get previous scores to enforce progression limits
                $previousScores = $memory->getAllScores();
                $attemptCount = count($previousScores);

                // Apply progressive score constraints
                if (!empty($previousScores)) {
                    // For first few attempts (1-2), limit maximum score to prevent early completion
                    if ($attemptCount < 3) {
                        // First two attempts: Cap score at 6 to ensure multiple iterations
                        $score = min($score, 6);
                    } elseif ($attemptCount < 4) {
                        // Third attempt: Cap score at 8 to ensure at least 4 iterations for complex tasks
                        $score = min($score, 8);
                    }
                    
                    // Ensure score increases gradually (max +3 per iteration)
                    $lastScore = end($previousScores)['score'];
                    $maxAllowedIncrease = 3;
                    $score = min($score, $lastScore + $maxAllowedIncrease);
                }

                // Store the score in memory
                $memory->storeScore($score);
                
                // Store rationale if available
                if (isset($response['rationale'])) {
                    $task->addMetadata('last_evaluation_rationale', $response['rationale']);
                }

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
        
        // Get previous scores to track progress
        $previousScores = $memory->getAllScores();
        $attemptCount = count($previousScores);
        $scoreHistory = empty($previousScores) ? "No previous scores." : implode(", ", array_map(fn($s) => $s['score'], $previousScores));
        
        // Get search results to provide context
        $searchResults = $memory->getSearchResultsSummary();
        
        // Get previous feedback to consider improvements
        $lastFeedback = $memory->getLastFeedback() ?? "No previous feedback.";

        $message = new GPTMessageModel();
        $message->role = 'user';
        $message->content = <<<EOT
You are an objective evaluator tasked with rating the completeness of a solution.

TASK:
{$task->getDescription()}

CONTEXT:
Previous search findings: $searchResults

CURRENT APPROACH:
$latestApproach

PROGRESS TRACKING:
Attempt number: $attemptCount
Previous scores: $scoreHistory
Last feedback: $lastFeedback

STRICT EVALUATION GUIDELINES:
1. Score from 0-10 based on how well the approach addresses ALL aspects of the task
2. Consider:
   - Completeness: Does it address all requirements?
   - Accuracy: Is the information correct and well-supported?
   - Practicality: Is the solution feasible and implementable?
   - Improvement: Has it addressed previous feedback?
   
3. SCORING CONSTRAINTS - FOLLOW THESE EXACTLY:
   - 0-3: Very incomplete, missing major elements
   - 4-6: Partially complete, but still missing important aspects
   - 7-8: Mostly complete with minor issues or omissions
   - 9-10: ONLY for solutions that are essentially perfect and truly complete

4. For early iterations (attempt 1-3), be EXTREMELY strict with scoring
   - First attempts should almost never score above 6
   - Scores of 9-10 should be rare and only for exceptional solutions

5. Return ONLY a JSON object with the structure: {"score": X, "rationale": "brief explanation including what's still missing"}

Ensure your assessment is objective and identifies remaining gaps or areas for improvement.
EOT;

        return $message;
    }

    private function createFeedbackPrompt(Task $task, Memory $memory): GPTMessageModel
    {
        $latestApproaches = $memory->getAllApproaches();
        $latestApproach = empty($latestApproaches) ? "No approach yet." : end($latestApproaches)['content'];
        
        // Get information about the current state
        $previousScores = $memory->getAllScores();
        $attemptCount = count($previousScores);
        $currentScore = empty($previousScores) ? 0 : end($previousScores)['score'];
        $targetScore = 9; // Aligned with the config target score
        
        // Get the evaluation rationale if available
        $evaluationRationale = $task->getMetadata('last_evaluation_rationale') ?? "No evaluation rationale available.";

        $message = new GPTMessageModel();
        $message->role = 'user';
        $message->content = <<<EOT
You are providing constructive feedback to improve a solution to this task.

TASK:
{$task->getDescription()}

CURRENT APPROACH:
$latestApproach

ASSESSMENT:
Current attempt: $attemptCount
Current score: $currentScore out of 10
Target score needed: $targetScore
Gaps identified in evaluation: $evaluationRationale

FEEDBACK INSTRUCTIONS:
Provide specific, actionable feedback on how to improve this approach to reach the target score. Focus on:
1. What aspects are missing or incomplete from the solution
2. What specific improvements would address the identified gaps
3. Detailed, concrete suggestions for the next iteration

Your feedback should be comprehensive, specific, and directly address the weaknesses identified in the evaluation.
If this is an early attempt (1-2), be particularly thorough as the solution likely needs significant improvement.
EOT;

        return $message;
    }
}
