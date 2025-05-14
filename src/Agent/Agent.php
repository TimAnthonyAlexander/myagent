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
                $this->gatherInitialTaskContextInteractive($task);
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
            $completionProgressPercent = round(min(100, ($completionScore / $targetScore) * 100));

            if ($this->interactive) {
                echo "$completionScore/$targetScore ($completionProgressPercent%)\n";
            }

            if ($completionScore < $targetScore && $attempts < $maxAttempts) {
                $feedback = $this->evaluator->generateFeedback($task, $this->memory);
                $this->memory->storeFeedback($feedback);
            }
        }

        $finalResultObject = $this->generateFinalResult($task);
        $finalResult = $finalResultObject->report;

        if ($this->interactive) {
            echo $finalResult . "\n";
        }

        if ($this->interactive) {
            $this->startFollowUpConversation();
        }

        return (object) [
            'task' => $task,
            'memory' => $this->memory,
            'final_result' => $finalResultObject,
            'best_score' => $completionScore,
            'max_attempts_reached' => $attempts >= $maxAttempts,
        ];
    }

    public function gatherFollowUpQuestions(Task $task): string
    {
        $prompt = new GPTMessageModel();
        $prompt->role = 'user';
        $prompt->content = sprintf(
            "Based on this task description: \"%s\"\n\nGenerate at least 5 specific follow-up questions that would help clarify important details, scope, requirements, or context needed to effectively complete this task. These questions should help gather essential information that might be missing from the initial description.",
            $task->getDescription()
        );
        $this->thinkingGpt->send($prompt);
        return $this->thinkingGpt->response->content;
    }

    public function gatherInitialTaskContextInteractive(Task $task): void
    {
        if (! $this->interactive) {
            return;
        }

        echo "\nGenerating preliminary questions to gather more context about your task...\n";
        $questions = $this->gatherFollowUpQuestions($task);
        echo "\n{$questions}\n\n";
        echo "Please provide your answers to these questions (send an empty message once done):\n";

        $userAnswers = '';
        echo '> ';
        while (($line = trim(fgets(STDIN))) !== '') {
            $userAnswers .= $line . "\n";
            echo '> ';
        }

        $context = "FOLLOW-UP QUESTIONS:\n{$questions}\n\nUSER ANSWERS:\n{$userAnswers}";
        $this->memory->storeSearchResults($context);
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
        $searchResults = $this->memory->getRelevantContextSummary();
        $allApproaches = $this->memory->getAllApproaches();
        $approachSummary = "APPROACHES DEVELOPED:\n";

        // Include at least the last 3 approaches, or all if less than 3
        $relevantApproaches = array_slice($allApproaches, -min(3, count($allApproaches)));
        foreach ($relevantApproaches as $index => $approach) {
            $approachSummary .= "Approach " . ($index + 1) . ":\n" . $approach['content'] . "\n\n";
        }

        // Include feedback history
        $allFeedback = $this->memory->getAllFeedback();
        $feedbackSummary = "";
        if (!empty($allFeedback)) {
            $feedbackSummary = "FEEDBACK HISTORY:\n";
            foreach ($allFeedback as $index => $feedback) {
                $feedbackSummary .= "Feedback " . ($index + 1) . ":\n" . $feedback['content'] . "\n\n";
            }
        }

        $finalPrompt = new GPTMessageModel();
        $finalPrompt->role = 'user';
        $finalPrompt->content = sprintf(
            "Create a final, comprehensive response and EXTENSIVE report for the task: %s

SEARCH FINDINGS:
%s

%s

%s

Write a complete and comprehensive report addressing all aspects of the task. Use all gathered information and approaches described above. 
Write it in the language of the original task mentioned. Use a professional tone, suitable for an intelligent audience. 
Include specific findings, data points, and insights from the approaches. Ensure the report is thorough, well-organized, and addresses all requirements of the task.",
            $task->getDescription(),
            $searchResults,
            $approachSummary,
            $feedbackSummary
        );

        $this->gpt->send($finalPrompt, 'You are a professional report writer, a PHP Deep Research agent. You have searched for information, generated approaches, and received feedback. Now, you need to create a final report that summarizes everything and provides a comprehensive solution to the task. Your report should be well-structured, clear, and informative. Use the information provided in the prompt to create a detailed and professional report. Write it in the language of the original formulated task.');
        $finalReport = $this->gpt->response->content;

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
