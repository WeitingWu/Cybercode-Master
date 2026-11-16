<?php

return [
    'ollama_host'     => env('OLLAMA_HOST', 'http://127.0.0.1:11434'),
    'ollama_model'    => env('OLLAMA_MODEL', 'qwen2.5-coder:14b'),
    'ollama_timeout'  => (int) env('OLLAMA_TIMEOUT', 300),
    'ollama_num_ctx'  => (int) env('OLLAMA_NUM_CTX', 8192),
    'max_code_chars'  => 12000,
    'history_enabled' => env('HISTORY_ENABLED', true),
];
