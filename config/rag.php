<?php

return [
    'queue' => env('RAG_QUEUE', 'rag'),

    'chunking' => [
        'target_chars' => (int) env('RAG_CHUNK_TARGET_CHARS', 1600),
        'overlap_chars' => (int) env('RAG_CHUNK_OVERLAP_CHARS', 250),
        'min_chunk_chars' => (int) env('RAG_CHUNK_MIN_CHARS', 300),
    ],

    'parsers' => [
        'csv' => [
            'rows_per_chunk' => (int) env('RAG_CSV_ROWS_PER_CHUNK', 50),
        ],
        'excel' => [
            'rows_per_chunk' => (int) env('RAG_EXCEL_ROWS_PER_CHUNK', 20),
            'max_sheets' => (int) env('RAG_EXCEL_MAX_SHEETS', 20),
            'max_rows_per_sheet' => (int) env('RAG_EXCEL_MAX_ROWS_PER_SHEET', 5000),
            'max_columns_per_sheet' => (int) env('RAG_EXCEL_MAX_COLUMNS_PER_SHEET', 100),
        ],
        'max_chars_per_parsed_unit' => (int) env('RAG_MAX_CHARS_PER_PARSED_UNIT', 12000),
    ],

    'retrieval' => [
        'top_k' => (int) env('RAG_RETRIEVAL_TOP_K', 6),
    ],

    'crawler' => [
        'max_pages' => (int) env('RAG_CRAWLER_MAX_PAGES', 40),
        'request_timeout_seconds' => (int) env('RAG_CRAWLER_REQUEST_TIMEOUT_SECONDS', 10),
        'max_content_chars' => (int) env('RAG_CRAWLER_MAX_CONTENT_CHARS', 120000),
    ],

    'widget' => [
        'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('RAG_WIDGET_ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1')))),
        'session_ttl_seconds' => (int) env('RAG_WIDGET_SESSION_TTL_SECONDS', 86400),
    ],

    'voice' => [
        'queue' => env('VOICE_QUEUE', 'voice'),

        /*
         * Twilio abandons a webhook after 15 seconds. When async answering is enabled the turn
         * webhook dispatches a job and parks the caller in a <Redirect> hold loop, so a slow
         * Ollama generation can never drop a live call.
         */
        'async_answer' => (bool) env('VOICE_ASYNC_ANSWER', true),
        'answer_timeout_seconds' => (int) env('VOICE_ANSWER_TIMEOUT_SECONDS', 45),
        'hold_poll_seconds' => (int) env('VOICE_HOLD_POLL_SECONDS', 2),

        /*
         * Voice runs its own generation budget because a caller is waiting on the line. Prompt
         * *processing* dominates latency far more than token generation, so the retrieval budget
         * below is the single biggest lever: measured on this hardware, llama3 with ~2000 prompt
         * tokens took 284s, while gemma:2b with ~700 took 8.4s.
         */
        'generation_model' => env('VOICE_GENERATION_MODEL', env('OLLAMA_GENERATION_MODEL', 'llama3')),
        'retrieval_top_k' => (int) env('VOICE_RETRIEVAL_TOP_K', 2),
        'max_context_chars_per_chunk' => (int) env('VOICE_MAX_CONTEXT_CHARS_PER_CHUNK', 450),
        'history_turns' => (int) env('VOICE_HISTORY_TURNS', 4),
        'num_predict' => (int) env('VOICE_NUM_PREDICT', 80),

        /*
         * Ollama unloads an idle model after ~5 minutes. On a phone line that means the first
         * caller after a quiet spell waits out a cold load and hears the fallback, so keep the
         * voice models pinned. -1 holds them indefinitely; a duration like "30m" also works.
         *
         * The type matters: Ollama parses a string as a Go duration, so "-1" is rejected with
         * `missing unit in duration` while the number -1 is accepted. Normalise here so callers
         * cannot get it wrong.
         */
        'model_keep_alive' => is_numeric(env('VOICE_MODEL_KEEP_ALIVE', -1))
            ? (int) env('VOICE_MODEL_KEEP_ALIVE', -1)
            : (string) env('VOICE_MODEL_KEEP_ALIVE'),

        /*
         * Must stay BELOW hold_poll budget (answer_timeout_seconds) so a stalled generation fails
         * fast and the caller hears the fallback, instead of the worker blocking past the point
         * where anyone is still listening. Note Windows has no pcntl, so the queue timeout is not
         * enforced there — this HTTP timeout is the real backstop.
         */
        'ollama_timeout_seconds' => (int) env('VOICE_OLLAMA_TIMEOUT_SECONDS', 30),

        'max_answer_chars' => (int) env('VOICE_MAX_ANSWER_CHARS', 1200),
        'max_turns' => (int) env('VOICE_MAX_TURNS', 20),
        'max_consecutive_no_input' => (int) env('VOICE_MAX_CONSECUTIVE_NO_INPUT', 2),
        'min_speech_confidence' => (float) env('VOICE_MIN_SPEECH_CONFIDENCE', 0.3),
        'max_speech_hints' => (int) env('VOICE_MAX_SPEECH_HINTS', 50),

        'default_tts_voice' => env('VOICE_DEFAULT_TTS_VOICE', 'Polly.Joanna'),
        'default_language' => env('VOICE_DEFAULT_LANGUAGE', 'en-US'),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'generation_model' => env('OLLAMA_GENERATION_MODEL', 'llama3'),
        'timeout' => (int) env('OLLAMA_TIMEOUT_SECONDS', 120),
        'proxy_allow_remote' => (bool) env('OLLAMA_PROXY_ALLOW_REMOTE', false),

        /*
         * Shared secret for the OpenAI-compatible proxy at /api/ollama. Setting it lets a
         * client on another host authenticate with a bearer token the way an OpenAI SDK
         * does, and it then replaces the local-only IP check as the access gate. Leave it
         * empty to keep the proxy restricted to local requests.
         */
        'proxy_api_key' => env('OLLAMA_PROXY_API_KEY', ''),

        /*
         * Logs each proxied request and response to the application log. Useful for
         * seeing what an opaque client (an IDE plugin) actually sends. Off by default:
         * the log will contain full prompt contents while it is on.
         */
        'proxy_debug' => (bool) env('OLLAMA_PROXY_DEBUG', false),
    ],

    'confluence' => [
        'request_timeout_seconds' => (int) env('RAG_CONFLUENCE_REQUEST_TIMEOUT_SECONDS', 20),
        'max_spaces_per_request' => (int) env('RAG_CONFLUENCE_MAX_SPACES_PER_REQUEST', 500),
        'max_pages_per_sync' => (int) env('RAG_CONFLUENCE_MAX_PAGES_PER_SYNC', 200),
    ],
];
