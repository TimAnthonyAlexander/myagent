# MyAgent: Intelligent Task-Solving Agent

![MyAgent Banner](https://img.shields.io/badge/MyAgent-Intelligent%20PHP%20Agent-blue)
![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-purple)
![License](https://img.shields.io/badge/License-MIT-green)

MyAgent is a sophisticated PHP-based autonomous agent that uses recursive intelligence and iterative refinement to solve complex tasks. Powered by OpenAI's GPT models, it combines search, thinking, evaluation, and memory to produce high-quality solutions.

## ✨ Features

- **Iterative Refinement**: Repeatedly improves solutions until achieving high confidence scores
- **Multi-Model Intelligence**: Uses specialized GPT models for different cognitive functions
- **Memory Management**: Maintains context across iterations with intelligent memory system
- **Self-Evaluation**: Objectively scores its own solutions on a 0-10 scale
- **Feedback Loop**: Generates constructive feedback to guide improvement
- **Persistence**: Continues refining until reaching a perfect score or maximum attempts
- **Configurable**: Easily customize models and settings through configuration file

## 🚀 Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/TimAnthonyAlexander/myagent.git
   cd myagent
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure your OpenAI API key:
   ```bash
   mkdir -p config
   echo "your-openai-api-key" > config/openai.txt
   ```

4. Configure models (optional):
   ```bash
   # The models.json file will be created automatically with defaults
   # You can edit it to customize which models are used
   ```

## 💻 Usage

Run the agent with your task description:

```bash
php public/runagent.php "Generate a comprehensive marketing plan for a new mobile app"
```

You can also run without arguments to be prompted for input:

```bash
php public/runagent.php
Enter task description: Create a Python script to analyze Twitter sentiment
```

## ⚙️ Configuration

MyAgent uses a configuration file (`config/models.json`) to customize its behavior:

```json
{
  "models": {
    "default": "gpt-4.1-mini",     // Main processing model
    "evaluation": "gpt-4.1",       // Solution evaluation model
    "search": "gpt-4o-mini-search-preview", // Information gathering model
    "thinking": "o4-mini",         // Solution development model
    "thinking_advanced": "o1"      // Advanced solution development
  },
  "api": {
    "endpoint": "https://api.openai.com/v1/chat/completions",
    "timeout_ms": 120000
  },
  "generation": {
    "max_tokens": 1200,            // Max tokens for standard responses
    "max_completion_tokens": 10000, // Max tokens for thinking models
    "default_temperature": 0.2     // Lower = more deterministic responses
  },
  "execution": {
    "max_attempts": 10,            // Maximum solution refinement attempts
    "target_score": 10             // Target score (0-10) to consider task complete
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
```

## 🛠️ Requirements

- PHP 8.1 or higher
- Valid OpenAI API key
- Composer

## 📄 License

MIT License

---

Developed with ❤️ by Tim Anthony Alexander
