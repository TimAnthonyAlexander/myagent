<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\Agent;

use stdClass;
use TimAlexander\Myagent\GPT\GPT;
use TimAlexander\Myagent\Memory\Memory;
use TimAlexander\Myagent\Task\Task;
use TimAlexander\Myagent\Evaluator\Evaluator;
use TimAlexander\Myagent\GPTMessage\GPTMessageModel;
use TimAlexander\Myagent\PDF\PDFService;

final class Agent
{
    private GPT $gpt;
    private GPT $searchGpt;
    private GPT $thinkingGpt;
    private Memory $memory;
    private Evaluator $evaluator;
    private PDFService $pdfService;
    private array $config;
    private bool $conversationActive = false;
    private string $apiKey = '';

    public function __construct(?string $apiKey = null, private bool $interactive = false)
    {
        $this->loadConfig();

        $this->gpt = new GPT('default');
        $this->searchGpt = new GPT('search');
        $this->thinkingGpt = new GPT('thinking');
        $this->evaluator = new Evaluator();

        if ($apiKey !== null) {
            $this->setApiKey($apiKey);
        } else {
            $this->loadApiKey();
        }
        $this->memory = new Memory();
        $this->evaluator = new Evaluator();
        $this->pdfService = new PDFService();
    }

    /**
     * Set the OpenAI API key
     *
     * @param string $apiKey
     * @return self
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        $this->gpt->setApiKey($apiKey);
        $this->searchGpt->setApiKey($apiKey);
        $this->thinkingGpt->setApiKey($apiKey);
        $this->evaluator->setApiKey($apiKey);

        return $this;
    }

    /**
     * Load API key from config file
     */
    private function loadApiKey(): void
    {
        $apiKeyFile = __DIR__ . '/../../config/openai.txt';

        if (file_exists($apiKeyFile)) {
            $this->apiKey = trim(file_get_contents($apiKeyFile));

            // Update API key in GPT instances
            $this->gpt->setApiKey($this->apiKey);
            $this->searchGpt->setApiKey($this->apiKey);
            $this->thinkingGpt->setApiKey($this->apiKey);
            $this->evaluator->setApiKey($this->apiKey);
        } else {
            throw new \RuntimeException(
                "OpenAI API key not found. Please set it using setApiKey() or create a config/openai.txt file."
            );
        }
    }

    private function loadConfig(): void
    {
        $configFile = __DIR__ . '/../../config/models.json';

        if (file_exists($configFile)) {
            try {
                $jsonConfig = file_get_contents($configFile);
                $this->config = json_decode($jsonConfig, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                die("Error: Could not parse models.json config file. Please check its format.\n");
            }
        } else {
            die("Error: Configuration file models.json not found. Please create it in the config directory.\n");
        }
    }

    public function run(?string $taskDescription = null, ?string $context = null): stdClass
    {
        if ($taskDescription === null && $this->interactive) {
            echo "Enter task description: ";
            $taskDescription = trim(fgets(STDIN));
        } elseif ($taskDescription === null) {
            die("Error: No task description provided. Please provide a task description.\n");
        }

        $task = new Task($taskDescription);
        if ($this->interactive) {
            echo "Starting task: {$task->getDescription()}\n";
        }

        $this->memory->storeTask($task);

        if ($context === null) {
            if ($this->interactive) {
                $this->gatherInitialTaskContext($task);
            }
        } else {
            $task->addMetadata('initial_context', $context);
        }

        $completionScore = 0;
        $attempts = 0;
        $maxAttempts = $this->config['execution']['max_attempts'];
        $targetScore = $this->config['execution']['target_score'];

        while ($completionScore < $targetScore && $attempts < $maxAttempts) {
            $attempts++;

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
            $completionProgressPercent = ($completionScore / $targetScore) * 100;
            // echo "Current evaluation score: $completionScore/$targetScore\n";

            if ($this->interactive) {
                echo "Current progress: $completionProgressPercent%\n";
            }

            if ($completionScore < $targetScore) {
                if ($this->interactive) {
                    echo "Not yet complete. Continuing...\n";
                }

                $feedback = $this->evaluator->generateFeedback($task, $this->memory);
                $this->memory->storeFeedback($feedback);
            }
        }

        if ($completionScore >= $targetScore) {
            $finalResultObject = $this->generateFinalResult($task);
            $finalResult = $finalResultObject->report;

            if ($this->interactive) {
                echo $finalResult . "\n";
            }
        } else {
            $bestSolution = $this->memory->getBestApproach();

            if ($this->interactive) {
                echo $bestSolution . "\n";
            }
        }

        if ($this->interactive) {
            $this->startFollowUpConversation();
        }

        return (object) [
            'task' => $task,
            'memory' => $this->memory,
            'final_result' => $finalResultObject ?? null,
            'best_solution' => $bestSolution ?? null,
        ];
    }

    private function gatherInitialTaskContext(Task $task): void
    {
        if (!$this->interactive) {
            return;
        }

        echo "\nGenerating preliminary questions to gather more context about your task...\n";

        // Create a prompt for thinkingGpt to generate follow-up questions
        $questionsPrompt = new GPTMessageModel();
        $questionsPrompt->role = 'user';
        $questionsPrompt->content = sprintf(
            "Based on this task description: \"%s\"\n\nGenerate at least 5 specific follow-up questions that would help clarify important details, scope, requirements, or context needed to effectively complete this task. These questions should help gather essential information that might be missing from the initial description.",
            $task->getDescription()
        );

        // Get the follow-up questions
        $this->thinkingGpt->send($questionsPrompt);
        $questions = $this->thinkingGpt->response->content;

        // Display the questions to the user
        echo "\n$questions\n\n";

        // Ask the user to provide answers
        echo "Please provide your answers to these questions (type your response and press Enter):\n";
        $userAnswers = "";

        // Read multiline input until an empty line is entered
        echo "> ";
        while (($line = trim(fgets(STDIN))) !== "") {
            $userAnswers .= $line . "\n";
            echo "> ";
        }

        // Store this additional context in memory
        $contextWithAnswers = "FOLLOW-UP QUESTIONS:\n$questions\n\nUSER ANSWERS:\n$userAnswers";
        $this->memory->storeSearchResults($contextWithAnswers);

        // Update task metadata with this additional context
        $task->addMetadata('initial_context_questions', $questions);
        $task->addMetadata('initial_context_answers', $userAnswers);

        echo "\nThank you for providing additional context. Starting the solution process...\n";
    }

    private function startFollowUpConversation(): void
    {
        $this->conversationActive = true;
        echo "\nYou can ask follow-up questions or type 'exit' to end the conversation.\n";

        while ($this->conversationActive) {
            echo "\nYour question: ";
            $question = trim(fgets(STDIN));

            if (strtolower($question) === 'exit') {
                $this->conversationActive = false;
                echo "Conversation ended.\n";
                break;
            }

            $response = $this->handleFollowUpQuestion($question);
            echo "\nResponse:\n$response\n";
        }
    }

    private function handleFollowUpQuestion(string $question): string
    {
        // Store the question as a related task
        $task = $this->memory->getCurrentTask();
        $followUpTask = new Task($question, ['parent_task' => $task->getDescription()]);

        // Create a prompt that leverages the current memory context
        $followUpPrompt = new GPTMessageModel();
        $followUpPrompt->role = 'user';
        $followUpPrompt->content = sprintf(
            "Original task: %s\n\nFollow-up question: %s\n\nUse the following context to answer the question:\n- Search results: %s\n- Previous approaches: %s\n- Previous feedback: %s",
            $task->getDescription(),
            $question,
            $this->memory->getRelevantContextSummary(),
            $this->memory->getBestApproach(),
            $this->memory->getLastFeedback() ?? "None"
        );

        $this->gpt->send($followUpPrompt);
        $response = $this->gpt->response->content;

        // Store this follow-up interaction in memory for future reference
        $this->memory->storeSearchResults("Follow-up question: $question");
        $this->memory->storeApproach("Follow-up response: $response");

        return $response;
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

    private function generateFinalResult(Task $task): stdClass
    {
        $finalPrompt = new GPTMessageModel();
        $finalPrompt->role = 'user';
        $finalPrompt->content = sprintf(
            "Create a final, comprehensive response and EXTENSIVE report for the task: %s\nWrite it in the language of the original task mentioned. Use all gathered information and approaches. Write it in a professional tone, suitable for an intelligent audience. Use data and numbers from reports to make it logically sound. Write as much as you know.",
            $task->getDescription()
        );

        $this->gpt->send($finalPrompt);
        $finalReport = $this->gpt->response->content;

        // Save the report as a PDF
        $title = "Report: " . $task->getDescription();
        $fileName = "task_report_" . md5($task->getDescription());
        $pdfPath = $this->pdfService->convertAndSave($finalReport, $title, $fileName);
        $fullPath = __DIR__ . "/../../$pdfPath";
        $fullPath = realpath($fullPath);

        if ($this->interactive) {
            echo "Report saved as PDF: $pdfPath\n";
        }

        return (object) [
            'report' => $finalReport,
            'pdf_path' => $fullPath,
        ];
    }
}
