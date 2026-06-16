# Stashd -- Branding & Product Direction

## Vision

**Stashd** is a self-hosted service for building and maintaining a local
archive of online video content.

It is designed for homelab enthusiasts who want to:

-   Mirror YouTube channels and playlists locally
-   Keep their media server automatically updated
-   Expose content as podcast feeds (video or audio)
-   Own their library instead of relying on a third-party platform

The focus is on **speed, reliability, usability, and preservation**.

------------------------------------------------------------------------

# Core Values

-   **Fast discovery** of new uploads
-   **Lightning-fast downloads**
-   **Robust YouTube integration** that minimizes rate limiting and bot
    detection
-   **Excellent UX** with sensible defaults
-   **Self-hosted first**
-   **Built for automation**

------------------------------------------------------------------------

# Target Audience

Homelabbers, self-hosters, media archivists, Plex/Jellyfin users, and
people who believe in digital preservation.

------------------------------------------------------------------------

# Why "Stashd"

The name has several layers:

-   "Stash" = keep a private copy
-   Slightly rebellious ("hide it from dad") without sounding like
    piracy
-   Instantly understandable
-   Memorable and short
-   The trailing **d** naturally evokes a Unix daemon (`sshd`,
    `containerd`, etc.)

For technical users it subtly reads as **Stash Daemon**.

------------------------------------------------------------------------

# Brand Personality

Stashd should feel:

-   Quietly competent
-   Fast
-   Dependable
-   Understated
-   Slightly cheeky

Avoid loud marketing language.

Think:

-   Sonarr
-   Radarr
-   Jellyfin
-   TrueNAS
-   Caddy
-   Restic

------------------------------------------------------------------------

# Messaging

## Primary positioning

> Keep what matters.

## Alternative taglines

-   Because the internet forgets.
-   Your archive. Your rules.
-   Own your subscriptions.
-   Never lose another upload.
-   Mirror. Archive. Enjoy.
-   Take back your subscriptions.
-   Self-host your watch history.

------------------------------------------------------------------------

# Product Language

Instead of generic terminology:

  Generic          Stashd
  ---------------- ------------
  Download         Stash
  Subscription     Mirror
  Archive          Vault
  Playlist         Collection
  Podcast Export   Feed
  Sync Job         Mirror

Example UI:

    Mirrors

    ✓ Technology Connections
    ✓ Linus Tech Tips
    ✓ Kurzgesagt

    Last sync: 34 seconds ago

------------------------------------------------------------------------

# CLI Direction

Examples:

``` bash
stashd serve
stashd sync
stashd doctor
stashd scan
stashd feeds
stashd import
stashd migrate
stashd backup
stashd stats
```

Potential future tools:

``` text
stashd      # background service
stashctl    # administration CLI
stash-agent # optional remote worker
```

------------------------------------------------------------------------

# Initial Features (MVP)

## Content Sync

-   Channel mirroring
-   Playlist mirroring
-   Fast detection of new uploads
-   Reliable downloading
-   Resume interrupted downloads

## Media Server Integration

-   Plex support
-   Jellyfin support

## Podcast Feeds

-   Audio feeds
-   Video feeds
-   Private RSS endpoints

------------------------------------------------------------------------

# Future Ideas

-   Multiple source platforms
-   Twitch VODs
-   PeerTube
-   Nebula
-   Generic yt-dlp sources

Avoid branding that locks the project to YouTube.

------------------------------------------------------------------------

# Branding Ideas

Logo concepts:

-   Archive box
-   Hidden compartment
-   Downward arrow into a box
-   Vault
-   Squirrel storing an acorn
-   Minimal geometric cube

Avoid YouTube-inspired colors or branding.

------------------------------------------------------------------------

# Website Copy

## Hero

**Stashd**

Keep what matters.

Mirror channels. Archive videos. Sync your media server. Publish podcast
feeds.

Your archive. Your rules.

------------------------------------------------------------------------

## Elevator Pitch

Stashd quietly keeps a local copy of the creators you follow.

If a video disappears, becomes region-locked, or the platform changes
tomorrow, your library is still yours.

------------------------------------------------------------------------

# Guiding Philosophy

Stashd is **not** about piracy.

It is about:

-   ownership
-   preservation
-   reliability
-   self-hosting
-   automation

The software should always feel like trustworthy infrastructure that
quietly does its job.

------------------------------------------------------------------------

# North Star

When someone asks:

> "What should I use to archive YouTube for Jellyfin?"

The ideal answer is simply:

> "Use Stashd."
