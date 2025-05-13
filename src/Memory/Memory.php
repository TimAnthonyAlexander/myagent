<?php

declare(strict_types=1);

namespace TimAlexander\Myagent\Memory;

use TimAlexander\Myagent\Data\DataModel;
use TimAlexander\Myagent\Task\Task;

final class Memory
{
    private Task $currentTask;
    private array $searchResults = [];
    private array $approaches = [];
    private array $feedback = [];
    private array $scores = [];
    
    public function storeTask(Task $task): void
    {
        $this->currentTask = $task;
    }
    
    public function getCurrentTask(): Task
    {
        return $this->currentTask;
    }
    
    public function storeSearchResults(string $results): void
    {
        $this->searchResults[] = [
            'content' => $results,
            'timestamp' => time(),
        ];
    }
    
    public function storeApproach(string $approach): void
    {
        $this->approaches[] = [
            'content' => $approach,
            'timestamp' => time(),
        ];
    }
    
    public function storeFeedback(string $feedback): void
    {
        $this->feedback[] = [
            'content' => $feedback,
            'timestamp' => time(),
        ];
    }
    
    public function storeScore(int $score): void
    {
        $this->scores[] = [
            'score' => $score,
            'timestamp' => time(),
        ];
    }
    
    public function getRelevantContextSummary(): string
    {
        if (empty($this->searchResults)) {
            return "No previous search results.";
        }
        
        // Return last 3 search results to keep context manageable
        $recentResults = array_slice($this->searchResults, -3);
        $summary = "";
        
        foreach ($recentResults as $index => $result) {
            $summary .= "Search result " . ($index + 1) . ": " . $result['content'] . "\n\n";
        }
        
        return $summary;
    }
    
    public function getSearchResultsSummary(): string
    {
        if (empty($this->searchResults)) {
            return "No search results available.";
        }
        
        // Get the most recent search result
        $latestResult = end($this->searchResults);
        return $latestResult['content'];
    }
    
    public function getLastFeedback(): ?string
    {
        if (empty($this->feedback)) {
            return null;
        }
        
        $lastFeedback = end($this->feedback);
        return $lastFeedback['content'];
    }
    
    public function getBestApproach(): string
    {
        if (empty($this->approaches)) {
            return "No approaches have been generated.";
        }
        
        // If we have scores, return the approach with the highest score
        if (!empty($this->scores)) {
            $highestScore = 0;
            $bestIndex = 0;
            
            foreach ($this->scores as $index => $scoreData) {
                if ($scoreData['score'] > $highestScore) {
                    $highestScore = $scoreData['score'];
                    $bestIndex = $index;
                }
            }
            
            // Make sure we don't exceed array bounds
            $bestIndex = min($bestIndex, count($this->approaches) - 1);
            return $this->approaches[$bestIndex]['content'];
        }
        
        // If no scores, return the most recent approach
        $lastApproach = end($this->approaches);
        return $lastApproach['content'];
    }
    
    public function getAllApproaches(): array
    {
        return $this->approaches;
    }
    
    public function getAllSearchResults(): array
    {
        return $this->searchResults;
    }
    
    public function getAllFeedback(): array
    {
        return $this->feedback;
    }
} 