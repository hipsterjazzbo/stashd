---
paths:
  - "app/Broadcasts/**/*.php"
  - "app/MediaServers/**/*.php"
  - "tests/**/*Broadcast*.php"
  - "docs/broadcasts/**/*.md"
  - "docs/media-servers/**/*.md"
---

# Broadcast rules

Broadcasts are generated views over Vault assets.

## General rules

- Broadcasts do not own canonical media.
- Broadcasts should be idempotent and rebuildable.
- Deleting a broadcast output must not destroy Vault data.
- Trigger failures are separate from broadcast file validity.
- Published paths/URIs must not expose raw secrets or internal-only paths in public contexts.

## Filesystem broadcasts

- Hardlink first.
- No silent copy fallback.
- Symlink/copy/remux/transcode only when explicitly configured and clearly surfaced.
- Verify output and tolerate filesystem drift.
- Jellyfin/Plex layout rules belong in broadcast/media-server features, not provider/download code.

## Podcast broadcasts

Podcast broadcasts generate feed/media URLs. They are not filesystem series layouts.

Use the podcast-specific rule file for feed/token behavior.
