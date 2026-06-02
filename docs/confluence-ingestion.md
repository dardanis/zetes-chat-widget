# Confluence Ingestion

This app now supports selecting Confluence spaces per local project and syncing those pages into RAG documents/chunks.

## API Flow

1. Create tenant connection:
   - `POST /api/tenants/{tenant}/confluence/connections`
2. List accessible spaces:
   - `GET /api/tenants/{tenant}/confluence/connections/{connection}/spaces`
3. Save selected spaces for a project:
   - `PUT /api/projects/{project}/confluence/spaces`
4. Queue sync jobs:
   - `POST /api/projects/{project}/confluence/sync`

## Required payloads

### Create connection

```json
{
  "base_url": "https://your-site.atlassian.net",
  "email": "user@example.com",
  "api_token": "your-atlassian-api-token"
}
```

### Select spaces for project

```json
{
  "connection_id": 1,
  "spaces": [
    {"id": "1001", "key": "ENG", "name": "Engineering", "type": "global"}
  ]
}
```

## Notes

- Sync runs through queued jobs on the `rag` queue by default.
- Documents are stored with `ingestion_type = confluence`.
- Each sync is tenant/project scoped to preserve isolation.

