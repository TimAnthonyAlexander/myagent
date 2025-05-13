# MyAgent: Intelligent Task-Solving Agent

![MyAgent Banner](https://img.shields.io/badge/MyAgent-Intelligent%20PHP%20Agent-blue)
![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-purple)
![License](https://img.shields.io/badge/License-MIT-green)
[![Packagist](https://img.shields.io/badge/Packagist-tim.alexander%2Fmyagent-blue)](https://packagist.org/packages/tim.alexander/myagent)

MyAgent is a sophisticated PHP-based autonomous agent that uses recursive intelligence and iterative refinement to solve complex tasks. Powered by OpenAI's GPT models, it combines search, thinking, evaluation, and memory to produce high-quality solutions.

## ✨ Features

- **Iterative Refinement**: Repeatedly improves solutions until achieving high confidence scores
- **Multi-Model Intelligence**: Uses specialized GPT models for different cognitive functions
- **Memory Management**: Maintains context across iterations with intelligent memory system
- **Self-Evaluation**: Objectively scores its own solutions on a 0-10 scale
- **Feedback Loop**: Generates constructive feedback to guide improvement
- **Persistence**: Continues refining until reaching a perfect score or maximum attempts
- **PDF Reports**: Automatically generates and saves final reports as PDF files
- **Configurable**: Easily customize models and settings through configuration file

## 🚀 Installation

### As a Composer Package

Install via [Packagist](https://packagist.org/packages/tim.alexander/myagent):

```bash
composer require tim.alexander/myagent
```

### From Source

1. Clone the repository:
   ```bash
   git clone https://github.com/TimAnthonyAlexander/myagent.git
   cd myagent
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure your OpenAI API key (one of these methods):
   
   a. Create a config file:
   ```bash
   mkdir -p config
   echo "your-openai-api-key" > config/openai.txt
   ```
   
   b. Pass it as a command line argument:
   ```bash
   php public/runagent.php --api-key=your-openai-api-key "Your task"
   ```
   
   c. Set it programmatically:
   ```php
   $agent = new Agent();
   $agent->setApiKey('your-openai-api-key');
   ```

4. Configure models (optional):
   ```bash
   # The models.json file will be created automatically with defaults
   # You can edit it to customize which models are used
   ```

## 💻 Usage

### Basic Usage

```php
<?php

require 'vendor/autoload.php';

use TimAlexander\Myagent\Agent\Agent;

// Create agent with API key
$agent = new Agent('your-openai-api-key');
// Or set it later
// $agent->setApiKey('your-openai-api-key');

// Run a task with automatic context gathering (interactive mode)
$agent = new Agent('your-openai-api-key', interactive: true);
$agent->run("Generate a comprehensive marketing plan for a new mobile app");

// Or provide context directly (non-interactive mode)
$agent = new Agent('your-openai-api-key', interactive: false);
$context = "The app is a fitness tracker targeting young professionals. Budget is $50k for marketing. Need to focus on social media and influencer marketing.";
$result = $agent->run("Generate a comprehensive marketing plan for a new mobile app", $context);

// Access the results
echo $result->final_result->report; // The generated report in Markdown
echo $result->final_result->pdf_path; // Path to the generated PDF
```

The `run()` method returns a `stdClass` object with the following structure:
```php
{
    "task": Task,           // The Task object containing the original task and metadata
    "memory": Memory,       // The Memory object containing all gathered information
    "final_result": {       // Present if task completed successfully
        "report": string,   // The final report in Markdown format
        "pdf_path": string  // Full path to the generated PDF file
    },
    "best_solution": string // Present if task didn't reach target score
}
```

When you run a task, the agent will:

1. If no context is provided:
   - In interactive mode: Ask you clarifying questions to gather more context
   - In non-interactive mode: Skip the questions phase
2. If context is provided:
   - Skip the interactive questions phase
   - Use the provided context directly
3. The agent will then process your task through multiple iterations:
   - Search for relevant information
   - Develop solution approaches
   - Evaluate and refine the solution
   - Generate a final comprehensive report
4. The final report will be saved as a PDF in the `reports` directory

### Command Line

The command-line interface always uses interactive mode:

```bash
# Using API key from config file
php public/runagent.php "Generate a comprehensive marketing plan for a new mobile app"

# Or specify API key directly
php public/runagent.php --api-key=your-openai-api-key "Generate a comprehensive marketing plan for a new mobile app"
```

You can also run without arguments to be prompted for input:

```bash
php public/runagent.php
Enter task description: Create a Python script to analyze Twitter sentiment
```

After entering your task, the agent will:
1. Ask you clarifying questions about your requirements
2. Wait for your answers (type your response and press Enter twice when done)
3. Process the task and generate a solution
4. Save the final report as a PDF in the `reports` folder

### Follow-up Questions

After the task is completed in interactive mode, you can ask follow-up questions about the solution. The agent maintains context from the original task and can provide additional insights or clarifications. Type 'exit' to end the conversation.

## ⚙️ Configuration

MyAgent uses a configuration file (`config/models.json`) to customize its behavior:

```json
{
  "models": {
    "default": "gpt-4.1",              // Main processing model
    "evaluation": "gpt-4.1-mini",      // Solution evaluation model
    "search": "gpt-4o-mini-search-preview", // Information gathering model
    "thinking": "o4-mini",             // Solution development model
    "thinking_advanced": "o4-mini"     // Advanced solution development
  },
  "api": {
    "endpoint": "https://api.openai.com/v1/chat/completions",
    "timeout_ms": 120000
  },
  "generation": {
    "max_tokens": 2200,                // Max tokens for standard responses
    "max_completion_tokens": 20000,    // Max tokens for thinking models
    "default_temperature": 0.2         // Lower = more deterministic responses
  },
  "execution": {
    "max_attempts": 5,                 // Maximum solution refinement attempts
    "target_score": 9                  // Target score (0-10) to consider task complete
  }
}
```

You can adjust these settings to customize:
- Which models are used for each cognitive function
- API endpoint and timeout settings
- Token limits and temperature
- Maximum attempts and target score

## 🧠 How It Works

1. **Task Input**: The system accepts a natural language task description
2. **Search Phase**: Gathers relevant information for solving the task
3. **Thinking Phase**: Develops a solution approach based on gathered information
4. **Evaluation**: Assesses the solution quality on a 0-10 scale
5. **Feedback**: Generates specific improvement suggestions if needed
6. **Iteration**: Repeats the process, refining the solution until reaching a perfect score or maximum attempts
7. **Final Output**: Delivers the highest quality solution
8. **PDF Generation**: Saves the final report as a PDF in the reports folder

## 📋 Example Tasks

- "Research the impact of AI on healthcare and summarize key findings"
- "Create a Python script to analyze Twitter sentiment"
- "Design a database schema for an e-commerce platform"
- "Write a detailed business plan for a SaaS startup"
- "Develop an algorithm to solve the traveling salesman problem"

## ⚙️ Architecture

```
┌────────────┐     ┌────────────┐     ┌────────────┐
│   Search   │────▶│  Thinking  │────▶│ Evaluation │
│    GPT     │     │    GPT     │     │    GPT     │
└────────────┘     └────────────┘     └────────────┘
       │                  │                  │
       │                  │                  │
       ▼                  ▼                  ▼
┌─────────────────────────────────────────────────┐
│                     Memory                       │
└─────────────────────────────────────────────────┘
                         │
                         │
                         ▼
┌─────────────────────────────────────────────────┐
│                 Final Response                   │
└─────────────────────────────────────────────────┘
                         │
                         │
                         ▼
┌─────────────────────────────────────────────────┐
│                   PDF Report                     │
└─────────────────────────────────────────────────┘
```

## 🛠️ Requirements

- PHP 8.1 or higher
- Valid OpenAI API key
- Composer

## 📄 License

MIT License

---

Developed with ❤️ by Tim Anthony Alexander
