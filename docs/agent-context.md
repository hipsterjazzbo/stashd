# docs/agent-context.md

## Purpose

This document gives coding agents extra Stashd context without bloating `AGENTS.md`.

Read this when a task touches product behaviour, architecture, naming, UI/UX, storage, providers, API design, downloads, broadcasts, auth, or docs.

## One-sentence product model

Stashd turns fragile online media into a local archive the user controls, then rebroadcasts it into apps they already use: Jellyfin, Plex, and private podcast feeds.

## Product promise

```text
Stash a channel.
Broadcast it to Jellyfin.

Stash a playlist.
Broadcast it as a podcast.

No broadcast?
No problem. It stays in your Vault.
```

## North Star

Stashd should be the fastest, most reliable way to keep a permanent local copy of online video libraries while staying invisible until needed.

The app succeeds when it becomes boring infrastructure.

## Product non-goals

Stashd is not:

* a YouTube frontend
* a media player
* a recommendation engine
* a torrent client
* a social platform
* a video editor
* a growth product
* a public media catalog

Integrate with existing tools instead of replacing them.

## Core user

Think:

* homelab users
* self-hosters
* media archivists
* Plex users
* Jellyfin users
* podcast-feed power users
* people who want local ownership and automation

Assume users value clarity, reliability, and control more than glossy entertainment UI.

## Core concepts

### Stash

A user-facing thing the user wants Stashd to keep.

Examples:

```text
Oculus Imperia
Critical Role Campaign 2
French Documentaries
A single important video
A playlist grouped as a season
```

A stash may contain one or more technical inputs.

### Input

The upstream source inside a stash.

Examples:

```text
YouTube channel
YouTube playlist
URL list
Future Twitch broadcaster
Future PeerTube channel
```

Most users should not need to think about inputs unless configuring advanced organisation.

### MediaItem

The canonical provider media item.

A single item may belong to multiple stashes and appear in multiple broadcasts.

Do not duplicate canonical items just because they were discovered through different stashes.

### Vault

The canonical local archive.

Every preserved media item goes to the Vault first.

The Vault should remain useful even if Stashd is not running.

### Broadcast

A generated view of a stash.

Examples:

```text
Jellyfin Series
Plex Series
Audio Podcast
Video Podcast
```

Broadcasts generate presentation assets, hardlinks, feeds, artwork, NFO files, and output-specific files from the Vault.

Broadcasts are rebuildable and disposable. They do not own canonical media.

## Important architecture lessons

### Media-item-first

Stashes organise media items. They do not own them.

This enables deduplication when the same video appears in multiple channels, playlists, or grouped stashes.

### Database expected state, filesystem verified reality

The database records what Stashd expects.

The filesystem is what exists right now.

Do not trust either one blindly.

If the filesystem drifts, Stashd should notice, explain, and recover instead of crashing or deleting important state.

### State-machine core, event-driven edges

Core workflow should be state-machine-driven.

Events are useful for activity, UI updates, notifications, future hooks, and diagnostics.

Events are not the source of truth.

### Broadcasts are generated views

Broadcasts should be:

```text
idempotent
rebuildable
verifiable
prunable
disposable
```

Deleting generated broadcast files should not delete Vault assets.

### Providers are capability bundles

A provider is not just a downloader.

Providers may support:

```text
discovery
metadata
download
availability
authentication
sessions
```

Strategies declare purpose, cost, availability, auth requirements, and suitability.

## Provider strategy model

Strategy purposes include:

```text
discovery
metadata
download
availability
```

Strategy costs include:

```text
low
medium
high
last_resort
```

Job intent should drive strategy selection.

Examples:

```text
routine_discovery
initial_backfill
metadata_capture
metadata_refresh
download
repair
broadcast
enrich
storage_check
```

Choose the cheapest reliable strategy for the intent.

## YouTube strategy notes

YouTube is v1’s first provider, not the whole architecture.

Preferred approach:

```text
Routine discovery: YouTube RSS where practical
Initial backfill: YouTube Data API when configured
Metadata capture: YouTube Data API when configured
Download: yt-dlp through hazel/ytdlphp
Heavy inspection: yt-dlp fallback, used sparingly
```

Avoid making future Twitch, PeerTube, Nebula, Vimeo, or Internet Archive support awkward.

## URI lesson learned

A prior review called out vague naming around URI handling.

Keep URI classes and methods explicit.

Use `StashdUri` or more specific value objects inside app/provider logic.

Raw strings are boundary details, not domain language.

Do not add methods with unclear names like:

```php
stashd()
```

Prefer names that explain the transformation or query:

```php
parse()
fromProviderInput()
canonicalizeYouTubeVideo()
isYouTubeWatchUrl()
toCanonicalUri()
withQuery()
providerIdentity()
```

## Naming lesson learned

Agents should be suspicious of vague names.

Avoid:

```text
Manager
Helper
Util
Processor
Data
Info
Thing
Stuff
Service
Handler
```

`Handler` is acceptable when it matches an existing command-handler convention, but the class name must still explain what command is handled.

Better names expose the domain boundary:

```text
CreateStashFromDiscovery
StashPreflightCommandHandler
ProviderStrategySelector
DownloadPolicyEvaluator
AssetVerifyCommandHandler
```

## Docblock lesson learned

Class docblocks should explain what the class is and why it exists.

Good:

```php
/**
 * Coordinates creation of a Stash from a completed preflight command.
 *
 * Preflight owns provider resolution and discovery; this class commits the
 * reviewed result into durable Stash, StashInput, MediaItem, and StashItem
 * records without rediscovering provider state.
 */
```

Bad:

```php
/**
 * CreateStashFromDiscovery class.
 */
```

Do not add noise.

## API philosophy

Everything the UI can do should be possible through the API.

The browser extension, future CLI, future automation, and future mobile clients should not need private routes.

Prefer command submission for long-running work.

Example command types:

```text
stash.preflight
stash.create
stash.sync
stash.backfill
item.refresh_metadata
item.download
broadcast.rebuild
broadcast.rotate_token
system.prune_temp
storage.verify
```

Use stable error codes and actionable messages.

## Browser extension boundary

The extension should make “stash this” feel instant from the browser, but it should not become another app.

The extension sends:

```text
current URL
current title if useful
origin = browser_extension
```

Stashd decides:

```text
provider
input type
download policy
broadcast type
quality profile
storage strategy
filters
review flow
```

## Runtime assumptions

Default deployment should be one Docker container.

Default flow:

```text
copy compose file
docker compose up
open UI
create admin account
create first stash
```

RoadRunner handles HTTP and worker runtime.

SQLite is the v1 database.

Avoid introducing required external services in v1:

```text
no required PostgreSQL
no required Redis
no required RabbitMQ
no required external queue
no required Nginx/PHP-FPM
```

## Storage layout assumptions

Default roots:

```text
/data
/media
```

Likely conceptual layout:

```text
/data/stashd.sqlite
/data/secrets or app key material
/data/logs
/media/vault
/media/broadcasts
/media/temp
/media/cache
```

Critical backup guidance:

```text
Must back up: /data and /media/vault
Can regenerate: broadcasts, temp, cache
```

Make this obvious in UI and docs.

## UI philosophy

Stashd should feel like:

```text
Glance crossed with a well-designed system dashboard
```

Traits:

```text
calm
dense
fast
clear
dark-mode-first
keyboard-friendly
searchable
responsive
low-animation
debuggable
trustworthy
```

Avoid:

```text
YouTube clone
giant entertainment cards everywhere
social feed
mystery spinners
heavy gradients
excessive animation
SaaS marketing polish
```

## Brand direction

Primary wordmark:

```text
stashd_
```

The trailing underscore is part of the identity.

It evokes:

```text
terminal cursor
daemon process
command prompt
quiet background service
```

Preferred visual traits:

```text
warm espresso-charcoal backgrounds
muted graphite-brown panels
soft off-white / warm cream text
muted amber accent
subtle borders
monospace / semi-monospace typography
compact dashboard density
minimal visual noise
```

The preferred favicon/app icon is a rounded tile with only the amber underscore.

Avoid YouTube-inspired colours or branding.

## Product language

Prefer Stashd language over generic product language.

```text
Generic       Stashd
--------      ------
Download      Stash
Subscription  Mirror
Archive       Vault
Playlist      Collection
Podcast Export Feed
Sync Job      Mirror
```

Current engineering architecture also uses **Broadcast** for generated views. Do not replace Broadcast casually; treat “Mirror” as product/brand language where appropriate and Broadcast as the technical model unless the repo decides otherwise.

## Microcopy tone

Trustworthy, preservation-focused, lightly cheeky.

Good:

```text
Keep what matters.
Because the internet forgets.
Your archive. Your rules.
Tucked safely away.
It’s in the Vault now.
Saved before it vanished.
```

Avoid:

```text
Steal from YouTube
Bypass creators
Pirate anything
Hide your crimes
Never pay again
```

Stashd is not about piracy. It is about ownership, preservation, reliability, self-hosting, and automation.

## UI states that matter

For each user-facing flow, consider:

* empty state
* loading state
* queued state
* progress state
* partial success
* recoverable failure
* destructive confirmation
* unavailable storage
* missing asset
* provider auth failure
* provider rate limit
* invalid input
* disabled feature
* no real downloads in local/test mode

Bad errors:

```text
Job failed.
Something went wrong.
```

Good errors:

```text
Jellyfin scan failed.
Stashd published the files successfully, but Jellyfin rejected the scan request.
Reason: Invalid API key.
Fix: Update the Jellyfin API key in Settings → Media Servers.
```

## Testing strategy reminders

Useful unit-test targets:

```text
URI parsing
YouTube URL classification
provider strategy selection
state transitions
download policy evaluation
storage estimates
filename/path sanitisation
hardlink fallback policy
feed generation
funding-link detection
activity grouping
error-code mapping
```

Useful feature/integration-test targets:

```text
preflight command
create stash from preflight
API auth/token behaviour
job creation
asset verification
broadcast rebuild planning
storage root unavailable
Docker smoke test
```

Use fixtures for provider data.

Do not rely on live YouTube in normal tests.

## Review checklist for agents

Before returning work, check:

* Did I follow existing local patterns?
* Did I keep Stash → Vault → Broadcasts intact?
* Did I avoid raw URL strings beyond boundaries?
* Did I preserve media-item-first modelling?
* Did I avoid making broadcasts canonical?
* Did I avoid direct yt-dlp shelling?
* Did I avoid introducing external services?
* Did I keep secrets out of logs/errors/activity?
* Did I add/update tests?
* Did I run the relevant checks?
* Did I avoid broad unrelated refactors?
* Did I leave the UI calmer and clearer?
