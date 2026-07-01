---
paths:
  - "app/Broadcasts/Podcasts/**/*.php"
  - "app/Broadcasts/Formats/*Podcast*.php"
  - "tests/**/*Podcast*.php"
  - "docs/broadcasts/**/*.md"
---

# Podcast broadcast rules

Podcast support is private-feed infrastructure over Vault assets.

## Core behavior

- Audio/video podcast broadcasts write deterministic `feed.xml` as a disposable sidecar/artifact.
- Episode enclosure URLs use path tokens:

```text
/b/{broadcastToken}/items/{itemToken}/episode.{ext}
```

- Feed URL uses a path token:

```text
/b/{broadcastToken}/feed.xml
```

- Do not use query tokens.
- Do not store raw tokens in DB URL/path fields or lifecycle metadata.
- Invalid/revoked tokens should return non-revealing responses.
- Non-podcast broadcasts must not expose podcast feed fields.

## Feed rules

Feeds should include stable GUIDs, pub dates, channel metadata, item metadata, enclosure URL, length, and MIME type.

Funding links are v1 behavior when available. Preserve creator support metadata where practical.

## Asset rules

- Audio podcast requires ready audio assets.
- Video podcast requires conservative ready video assets.
- Missing required assets should fail with clear, stable error codes.
- Podcast broadcasts should not hardlink/copy media merely to publish the feed.

## Tests

Add or update tests for:

```text
feed XML generation
path-token URLs
token validation failure
revoked token failure
episode GUID stability
funding tag inclusion when available
raw token non-leakage
```
