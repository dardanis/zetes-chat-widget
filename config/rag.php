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

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        'generation_model' => env('OLLAMA_GENERATION_MODEL', 'llama3'),
        'timeout' => (int) env('OLLAMA_TIMEOUT_SECONDS', 120),
    ],

    'confluence' => [
        'request_timeout_seconds' => (int) env('RAG_CONFLUENCE_REQUEST_TIMEOUT_SECONDS', 20),
        'max_spaces_per_request' => (int) env('RAG_CONFLUENCE_MAX_SPACES_PER_REQUEST', 500),
        'max_pages_per_sync' => (int) env('RAG_CONFLUENCE_MAX_PAGES_PER_SYNC', 200),
    ],
];
