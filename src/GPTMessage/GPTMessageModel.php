<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\GPTMessage;

use stdClass;
use TimAlexander\Myagent\Data\DataModel;

class GPTMessageModel extends DataModel
{
    public string $role;
    public string|array $content = '';
    public string $created;

    public static function getRuleText(
        bool $isSearch = false,
    ): GPTMessageModel {
        $file = __DIR__ . '/../../config/general.txt';
        $text = (string) file_get_contents($file);

        $message          = new GPTMessageModel();
        $message->role    = 'system';
        if ($isSearch) {
            $message->content = [(object) [
                'text' => $text,
                'type' => 'text',
            ]];
        } else {
            $message->content = $text;
        }
        $message->created = date('Y-m-d H:i:s');

        return $message;
    }

    public function toArrayFiltered(): array
    {
        return [
            'role'    => $this->role,
            'content' => is_array($this->content)
                ? array_map(
                    fn(stdClass $item) => get_object_vars($item),
                    $this->content,
                )
                : str_replace(["\r\n", "\r", "\n"], '\\n', $this->content),
        ];
    }

    public function toArrayFilteredCommand(): array
    {
        // Instead of role -> system/assistant/user, we use role -> system/CHATBOT/USER
        // Instead of content we use message

        $convertedRole = match ($this->role) {
            'system'    => 'SYSTEM',
            'assistant' => 'CHATBOT',
            'user'      => 'USER',
            default     => 'SYSTEM',
        };

        return [
            'role'    => $convertedRole,
            'message' => $this->content,
        ];
    }
}
