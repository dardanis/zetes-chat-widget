# RAG MVP (Laravel + PostgreSQL pgvector + Ollama)

## Architecture snapshot
- Multi-tenant entities: `tenants` -> `projects`.
- Knowledge base per project: `project_documents` -> `document_chunks` (vectorized).
- Chat persistence per project: `chat_sessions` -> `chat_messages` -> `message_citations`.
- Ingestion pipeline is queued:
  1. Upload PDF (`ProjectDocumentController@store`)
  2. `ProcessProjectDocumentJob` extracts pages + chunks text
  3. `EmbedDocumentChunkJob` creates embeddings with `nomic-embed-text`
  4. Vectors are persisted in PostgreSQL `document_chunks.embedding_vector` (`pgvector`).

## Chunking strategy
- Strategy: sentence-aware sliding window.
- Defaults (configurable in `.env`):
  - target chunk: `1600` chars
  - overlap: `250` chars
  - minimum chunk: `300` chars
- Keeps context continuity while reducing semantic drift at boundaries.

## Models used
- Embeddings: `nomic-embed-text`
- Generation + normalization: `llama3`

## API endpoints (MVP)
### Authenticated endpoints
- `GET /api/tenants`
- `POST /api/tenants`
- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{project}/documents`
- `POST /api/projects/{project}/documents`
- `POST /api/projects/{project}/chats`
- `POST /api/projects/{project}/chats/message`
- `GET /api/projects/{project}/chats/{chat}/history`

### Public widget endpoints
- `POST /api/widget/{widgetKey}/chats`
- `POST /api/widget/{widgetKey}/chats/message`

## Widget security controls (MVP hardening)
- Requests are filtered by origin/referer allowlist (`RAG_WIDGET_ALLOWED_ORIGINS`).
- Project-level widget secret is required via `X-Widget-Secret`.
- Session creation returns a `session_token`; message requests must include it.
- Widget session token is validated and expires based on `RAG_WIDGET_SESSION_TTL_SECONDS`.
- Public widget endpoints are rate-limited (`widget-chat-create`, `widget-chat-message`).

## Notes
- Retrieval is always scoped by both `tenant_id` and `project_id`.
- Citations include chunk id, document name, page range, and excerpt.
- If context is weak, the assistant is instructed to say context is insufficient.


