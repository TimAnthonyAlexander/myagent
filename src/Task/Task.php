<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\Task;

use TimAlexander\Myagent\Data\DataModel;

final class Task extends DataModel
{
    private string $description;
    private string $createdAt;
    private ?string $completedAt = null;
    private array $metadata = [];

    public function __construct(string $description, array $metadata = [])
    {
        $this->description = $description;
        $this->createdAt = date('Y-m-d H:i:s');
        $this->metadata = $metadata;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function markAsCompleted(): void
    {
        $this->completedAt = date('Y-m-d H:i:s');
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Converts task to a prompt-friendly format
     */
    public function toPrompt(): string
    {
        $prompt = "TASK: " . $this->description . "\n";

        if (!empty($this->metadata)) {
            $prompt .= "ADDITIONAL INFORMATION:\n";
            foreach ($this->metadata as $key => $value) {
                if (is_scalar($value)) {
                    $prompt .= "- $key: $value\n";
                }
            }
        }

        return $prompt;
    }
}

