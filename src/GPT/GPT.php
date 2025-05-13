<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\GPT;

use JsonException;
use TimAlexander\Myagent\GPTMessage\GPTMessageModel;

ini_set('max_execution_time', 0);
set_time_limit(0);

final class GPT
{
    public const MODEL               = 'gpt-4.1-mini';
    public const SUPERMODEL          = 'gpt-4.1';
    public const SEARCHMODEL         = 'gpt-4o-mini-search-preview';
    public const THINKING            = 'o4-mini';
    public const THINKING_SUPERMODEL = 'o1';
    public const API                 = 'https://api.openai.com/v1/chat/completions';

    public GPTMessageModel $response;
    public string $md5;

    public float $inCost  = 0;
    public float $outCost = 0;

    public function __construct(
        public string $model = self::MODEL,
        public bool $useOpenRouter = false,
    ) {}

    public function send(
        GPTMessageModel|array $messages,
        ?string $instructions = null,
        string $type = 'text',
        float $temperature = 0.2,
    ): void {
        if ($instructions !== null) {
            $ruleText          = new GPTMessageModel();
            $ruleText->role    = 'system';
            if (str_contains($this->model, 'search')) {
                $ruleText->content = [(object) [
                    'text' => $instructions,
                    'type' => 'text',
                ]];
            } else {
                $ruleText->content = $instructions;
            }
        } else {
            $ruleText = GPTMessageModel::getRuleText(str_contains($this->model, 'search'));
        }

        if ($messages instanceof GPTMessageModel) {
            $newHistory = [$ruleText, $messages];
        } else {
            $newHistory = array_merge([$ruleText], $messages);
        }

        // If the model contains o1, replace all system messages with user messages
        if (str_contains($this->model, 'o1') || str_contains($this->model, 'o3') || str_contains($this->model, 'o4')) {
            foreach ($newHistory as $key => $message) {
                if ($message->role === 'system') {
                    $newHistory[$key]->role = 'user';
                }
            }
        }

        $messages    = array_map(fn(GPTMessageModel $message) => $message->toArrayFiltered(), $newHistory);

        $data = [
            'model'    => $this->model,
            'messages' => $messages,
        ];

        if (str_contains($this->model, 'o1') || str_contains($this->model, 'reasoner') || str_contains($this->model, 'o3') || str_contains($this->model, 'o4')) {
            $data['max_completion_tokens'] = 10000;
        } else {
            $data['max_tokens']        = 1200;
            $data['response_format']   = [
                'type' => $type,
            ];
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        $curl = curl_init();

        $file = match (true) {
            default                                => __DIR__ . '/../../config/openai.txt',
        };
        $api = self::API;

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL            => $api,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . trim((string) file_get_contents($file)),
                ],
                CURLOPT_CONNECTTIMEOUT_MS => 0,
            ]
        );

        $maxRetries     = 5;
        $retries        = 0;
        $sizeDownloaded = 0;
        $httpStatus     = 102;
        $response       = null;

        $microtime = microtime(true);
        while (($httpStatus !== 200 || (int) $sizeDownloaded === 0) && $retries < $maxRetries) {
            try {
                $response       = curl_exec($curl);
                $httpStatus     = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $sizeDownloaded = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);
                $retries++;

                if ($sizeDownloaded === 0 || $sizeDownloaded <= 10) {
                    print "Got a 0 size, retrying...\n";
                    usleep(2 * 1000 * 1000);
                }

                if ($httpStatus !== 200) {
                    print $response;
                    print "Got a $httpStatus, retrying...\n";
                    usleep(3 * 1000 * 1000);
                }
            } catch (\Throwable) {
                $httpStatus = 102;
                print "Got a throwable, retrying...\n";
            }
        }
        $microtime = microtime(true) - $microtime;

        curl_close($curl);

        $response = trim((string) $response);

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE);
        } catch (JsonException) {
            $decoded = [];
        }

        if (!isset($decoded['choices'][0]['message']['content'])) {
            print "Error: " . $decoded['error']['message'] . "\n";
            die();
        } else {
            $response = $decoded['choices'][0]['message']['content'];
        }

        $inputTokens  = $decoded['usage']['prompt_tokens'];
        $outputTokens = $decoded['usage']['completion_tokens'];

        $this->response          = new GPTMessageModel();
        $this->response->role    = 'assistant';
        $this->response->content = $response;
    }
}
