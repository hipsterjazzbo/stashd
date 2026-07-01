---
paths:
  - "app/Auth/**/*.php"
  - "app/Broadcasts/**/*.php"
  - "app/Commands/**/*.php"
  - "app/Http/**/*.php"
  - "app/Jobs/**/*.php"
  - "app/MediaServers/**/*.php"
  - "app/Providers/**/*.php"
  - "tests/**/*.php"
---

# Security, tokens, and secrets

Never expose or log:

- raw broadcast tokens
- raw item tokens
- secret ciphertext
- password hashes
- provider credentials
- media-server tokens
- raw command/job payloads that may contain sensitive data
- internal filesystem paths in public APIs/XML unless explicitly intended and tested

## Podcast tokens

- Tokens live in the URL path, not query strings.
- Invalid tokens must return non-revealing responses.
- Rotated/revoked tokens must not continue working.
- Do not store raw tokens in plaintext DB columns, activity metadata, command results, job payloads, logs, or exceptions.
- Token previews are okay only if intentionally designed as previews.

## Secrets

- Use `SecretsService` for provider/media-server/broadcast secrets.
- Do not pass raw secrets through broad generic metadata arrays.
- Redact secrets before storing raw provider metadata snapshots.

## Tests

Security-sensitive changes need tests that prove both valid and invalid behavior.

Examples:

```text
valid token works
invalid token returns non-revealing 404
old token fails after rotation
raw token absent from command result/activity/job payload/log-like metadata
```
