<?php

/*
| AI providers. Most providers speak the OpenAI-compatible Chat Completions API
| ("openai" driver); Claude uses the official Anthropic SDK ("anthropic" driver).
| Keys and models are managed from the admin panel (AI tab); the env variables
| below are used when no key is stored there. Model names change often — the
| admin panel can fetch the live model list from each provider.
*/
return [
    'timeout' => (int) env('AI_TIMEOUT', 60),

    'providers' => [
        'gemini' => [
            'label' => 'Google Gemini', 'driver' => 'openai', 'free' => true,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model' => 'gemini-2.5-flash', 'env' => 'GEMINI_API_KEY',
            'key_url' => 'https://aistudio.google.com/apikey',
        ],
        'groq' => [
            'label' => 'Groq', 'driver' => 'openai', 'free' => true,
            'base_url' => 'https://api.groq.com/openai/v1',
            'model' => 'llama-3.3-70b-versatile', 'env' => 'GROQ_API_KEY',
            'key_url' => 'https://console.groq.com/keys',
        ],
        'openrouter' => [
            'label' => 'OpenRouter', 'driver' => 'openai', 'free' => true,
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'meta-llama/llama-3.3-70b-instruct:free', 'env' => 'OPENROUTER_API_KEY',
            'key_url' => 'https://openrouter.ai/keys',
        ],
        'cerebras' => [
            'label' => 'Cerebras', 'driver' => 'openai', 'free' => true,
            'base_url' => 'https://api.cerebras.ai/v1',
            'model' => 'llama-3.3-70b', 'env' => 'CEREBRAS_API_KEY',
            'key_url' => 'https://cloud.cerebras.ai',
        ],
        'mistral' => [
            'label' => 'Mistral AI', 'driver' => 'openai', 'free' => true,
            'base_url' => 'https://api.mistral.ai/v1',
            'model' => 'mistral-small-latest', 'env' => 'MISTRAL_API_KEY',
            'key_url' => 'https://console.mistral.ai/api-keys',
        ],
        'github' => [
            'label' => 'GitHub Models', 'driver' => 'openai', 'free' => true,
            'base_url' => 'https://models.github.ai/inference',
            'model' => 'openai/gpt-4.1-mini', 'env' => 'GITHUB_MODELS_TOKEN',
            'key_url' => 'https://github.com/settings/personal-access-tokens',
        ],
        'huggingface' => [
            'label' => 'Hugging Face', 'driver' => 'openai', 'free' => true,
            'base_url' => 'https://router.huggingface.co/v1',
            'model' => 'meta-llama/Llama-3.3-70B-Instruct', 'env' => 'HF_TOKEN',
            'key_url' => 'https://huggingface.co/settings/tokens',
        ],
        'ollama' => [
            'label' => 'Ollama (local)', 'driver' => 'openai', 'free' => true, 'keyless' => true,
            'base_url' => 'http://localhost:11434/v1',
            'model' => 'llama3.2', 'env' => 'OLLAMA_API_KEY',
            'key_url' => 'https://ollama.com/download',
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude', 'driver' => 'anthropic', 'free' => false,
            'base_url' => null,
            'model' => 'claude-opus-5-5', 'env' => 'ANTHROPIC_API_KEY',
            'key_url' => 'https://platform.claude.com/settings/keys',
        ],
        'openai' => [
            'label' => 'OpenAI', 'driver' => 'openai', 'free' => false,
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4.1-mini', 'env' => 'OPENAI_API_KEY',
            'key_url' => 'https://platform.openai.com/api-keys',
        ],
        'deepseek' => [
            'label' => 'DeepSeek', 'driver' => 'openai', 'free' => false,
            'base_url' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-chat', 'env' => 'DEEPSEEK_API_KEY',
            'key_url' => 'https://platform.deepseek.com/api_keys',
        ],
        'xai' => [
            'label' => 'xAI Grok', 'driver' => 'openai', 'free' => false,
            'base_url' => 'https://api.x.ai/v1',
            'model' => 'grok-3-mini', 'env' => 'XAI_API_KEY',
            'key_url' => 'https://console.x.ai',
        ],
        'custom' => [
            'label' => 'OpenAI-compatible (custom)', 'driver' => 'openai', 'free' => null, 'custom' => true,
            'base_url' => '', 'model' => '', 'env' => 'CUSTOM_AI_API_KEY',
            'key_url' => null,
        ],
    ],
];
