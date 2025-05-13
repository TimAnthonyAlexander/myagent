<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\Agent;

use TimAlexander\Myagent\GPT\GPT;
use TimAlexander\Myagent\Memory\Memory;
use TimAlexander\Myagent\Task\Task;
use TimAlexander\Myagent\Evaluator\Evaluator;
use TimAlexander\Myagent\GPTMessage\GPTMessageModel;

final class Agent
{
    private GPT $gpt;
    private GPT $searchGpt;
    private GPT $thinkingGpt;
    private Memory $memory;
    private Evaluator $evaluator;

    public function __construct()
    {
        $this->gpt = new GPT();
        $this->searchGpt = new GPT(GPT::SEARCHMODEL);
        $this->thinkingGpt = new GPT(GPT::THINKING);
        $this->memory = new Memory();
        $this->evaluator = new Evaluator();
    }

    public function run(?string $taskDescription = null): void
    {
        if ($taskDescription === null) {
            // Read from stdin if no task provided
            echo "Enter task description: ";
            $taskDescription = trim(fgets(STDIN));
        }

        $task = new Task($taskDescription);
        echo "Starting task: {$task->getDescription()}\n";

        $this->memory->storeTask($task);
        $completionScore = 0;
        $attempts = 0;
        $maxAttempts = 10; // Safety limit

        while ($completionScore < 10 && $attempts < $maxAttempts) {
            $attempts++;
            echo "\nAttempt $attempts\n";

            // Search for relevant information
            $searchPrompt = $this->createSearchPrompt($task, $this->memory);
            $this->searchGpt->send($searchPrompt);
            $searchResults = $this->searchGpt->response;

            // Store search results in memory
            $this->memory->storeSearchResults($searchResults->content);

            // Generate solution approach
            $thinkingPrompt = $this->createThinkingPrompt($task, $this->memory);
            $this->thinkingGpt->send($thinkingPrompt);
            $approach = $this->thinkingGpt->response;

            // Store approach in memory
            $this->memory->storeApproach($approach->content);

            // Evaluate current solution
            $completionScore = $this->evaluator->evaluateTaskCompletion($task, $this->memory);
            echo "Current evaluation score: $completionScore/10\n";

            if ($completionScore < 10) {
                echo "Task not yet complete. Refining approach...\n";
                // Create feedback for next iteration
                $feedback = $this->evaluator->generateFeedback($task, $this->memory);
                $this->memory->storeFeedback($feedback);
            }
        }

        if ($completionScore >= 10) {
            echo "\nTask completed successfully!\n";
            $finalResult = $this->generateFinalResult($task);
            echo $finalResult . "\n";
        } else {
            echo "\nReached maximum attempts. Best solution so far:\n";
            $bestSolution = $this->memory->getBestApproach();
            echo $bestSolution . "\n";
        }
    }

    private function createSearchPrompt(Task $task, Memory $memory): GPTMessageModel
    {
        $message = new GPTMessageModel();
        $message->role = 'user';
        $message->content = [(object) [
            'text' => sprintf(
                "Task: %s\nFind relevant information to solve this task.\nPrevious findings: %s",
                $task->getDescription(),
                $memory->getRelevantContextSummary(),
            ),
            'type' => 'text',
        ]];

        return $message;
    }

    private function createThinkingPrompt(Task $task, Memory $memory): GPTMessageModel
    {
        $message = new GPTMessageModel();
        $message->role = 'user';
        $message->content = sprintf(
            "Task: %s\nUse the following information to generate a solution approach:\n%s\nPrevious feedback: %s",
            $task->getDescription(),
            $memory->getSearchResultsSummary(),
            $memory->getLastFeedback() ?? "None"
        );

        return $message;
    }

    private function generateFinalResult(Task $task): string
    {
        $finalPrompt = new GPTMessageModel();
        $finalPrompt->role = 'user';
        $finalPrompt->content = sprintf(
            "Create a final, comprehensive response for the task: %s\nUse all gathered information and approaches.",
            $task->getDescription()
        );

        $this->gpt->send($finalPrompt);
        return $this->gpt->response->content;
    }
}
