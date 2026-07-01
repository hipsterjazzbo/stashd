# Broadcast Plugin Architecture — Implementation Plan

## Goal

Extract the broadcast format layer from core into separate Composer packages. Core becomes a thin orchestration layer with no knowledge of podcast, Jellyfin, Plex, or media-server internals.

## Current State

- `BroadcastType` enum: `FilesystemSeries`, `JellyfinSeries`, `PlexSeries`, `Podcast`
- `BroadcastTypeRegistry` — holds instances of format classes, resolves by enum value
- `BroadcastFormat` interface — `key()`, `plan()`, `publish()`, `verify()`, `prune()`
- `AbstractSeriesBroadcastType` — shared base for series formats (NFO, hardlinks, sidecars)
- `PodcastBroadcastFormat` — RSS feed generation, tokenized URLs, asset selection
- `BroadcastController` — hardcodes `BroadcastType` enum, `PodcastTokenService`, `PodcastEpisodeUrlBuilder`
- `BroadcastLifecycleService` — uses `BroadcastTypeRegistry` to resolve handlers

## Target State

- `BroadcastPlugin` interface (replaces `BroadcastFormat`)
- `#[StashdBroadcast]` attribute for discovery
- `BroadcastPluginDiscoverer` — scans Tempest discovery at boot
- `stashd/plugin-podcast` — Composer package
- `stashd/plugin-media-server` — Composer package
- Core has zero knowledge of plugin internals

## Core Interfaces (new, in core)

### `BroadcastPlugin` (interface)

```php
interface BroadcastPlugin
{
    public function broadcastKeys(): array;
    public function supportedFileKinds(): array;
    public function uiControls(): array;
    public function plan(BroadcastContext $context, array $userInput = []): BroadcastPlan;
    public function publish(BroadcastContext $context, BroadcastPlan $plan): BroadcastPublishResult;
    public function verify(BroadcastContext $context): BroadcastVerifyResult;
    public function prune(BroadcastContext $context): BroadcastPruneResult;
}
```

Differences from `BroadcastFormat`:

- `broadcastKeys()` — returns array of string keys (replaces single `key()`)
- `supportedFileKinds()` — tells core what stash content is compatible (new)
- `uiControls()` — declares UI controls for broadcast creation (new)
- `plan()` takes `$userInput` for user-provided settings (new)

### `StashdBroadcast` (attribute)

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StashdBroadcast
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description = '',
    ) {}
}
```

Applied as: `#[StashdBroadcast('podcast', 'Podcast Feed', 'Generate an RSS podcast feed')]`

### `DiscoveredPlugin` (wrapper)

```php
final readonly class DiscoveredPlugin
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public BroadcastPlugin $plugin,
    ) {}
}
```

### `BroadcastPluginDiscoverer` (service)

Scans Tempest discovery registrations at boot, finds all `#[StashdBroadcast]` attributes, instantiates plugins, validates no duplicate keys, returns `DiscoveredPlugin[]`.

### `FileKind` (enum)

```php
enum FileKind: string { case Video = 'video'; case Audio = 'audio'; }
```

### `UiControl` (value object)

```php
final readonly class UiControl
{
    public function __construct(
        public string $name,
        public string $label,
        public string $type,    // 'select', 'text', 'checkbox', 'toggle'
        public string $category, // groups controls in UI
        public array $options = [], // for select: ['label' => '...', 'value' => '...']
        public bool $required = false,
    ) {}
}
```

## File Layout

### Core (`app/Broadcasts/`)

```
app/Broadcasts/
├── BroadcastPlugin.php          ← NEW interface
├── BroadcastPlan.php            ← existing, unchanged
├── BroadcastPublishResult.php   ← existing, unchanged
├── BroadcastVerifyResult.php    ← existing, unchanged
├── BroadcastPruneResult.php     ← existing, unchanged
├── BroadcastContext.php         ← existing, unchanged
├── BroadcastContextFactory.php  ← existing, unchanged
├── BroadcastController.php      ← REFACTOR: use discoverer, remove podcast deps
├── BroadcastLifecycleService.php ← REFACTOR: use discoverer, remove podcast deps
├── BroadcastStateTransitionService.php ← unchanged
├── BroadcastTriggerService.php  ← unchanged
├── BroadcastCommandHandler.php  ← unchanged
├── BroadcastRepository.php      ← unchanged
├── BroadcastItemRepository.php  ← unchanged
├── BroadcastRecord.php          ← CHANGE: type from BroadcastType enum to string
├── BroadcastItemRecord.php      ← unchanged
├── BroadcastState.php           ← unchanged
├── BroadcastItemState.php       ← unchanged
├── BroadcastPathBuilder.php     ← unchanged
├── BroadcastFilenameBuilder.php ← unchanged
├── BroadcastHardlinkPublisher.php ← unchanged
├── BroadcastSidecarWriter.php   ← unchanged
├── BroadcastNfoBuilder.php      ← unchanged
├── BroadcastException.php       ← unchanged
├── BroadcastPluginDiscoverer.php ← NEW
├── FileKind.php                 ← NEW
├── UiControl.php                ← NEW
├── DiscoveredPlugin.php         ← NEW
├── StashdBroadcast.php          ← NEW attribute
├── SeasonMapping.php            ← keep (shared)
├── SeasonNameMapper.php         ← keep (shared)
├── Formats/                     ← DELETED
├── Podcasts/                    ← DELETED
└── Podcast/                     ← DELETED (moved to plugin)
```

### `stashd/plugin-podcast`

```
stashd/plugin-podcast/
├── composer.json
├── src/
│   ├── PodcastPlugin.php          ← extracted, implements BroadcastPlugin
│   ├── PodcastAssetSelector.php
│   ├── PodcastFeedBuilder.php
│   ├── PodcastEpisodeUrlBuilder.php
│   ├── PodcastMimeType.php
│   ├── PodcastTranscodeFallback.php
│   ├── PodcastTokenService.php
│   ├── PodcastEpisode.php
│   ├── PodcastEpisodeByteRange.php
│   ├── PodcastFeedMetadata.php
│   ├── PodcastMediaKind.php
│   ├── PodcastTranscodingAsset.php
│   └── PodcastAssetSelection.php
└── bootstrap.php                   ← registers Tempest discovery
```

### `stashd/plugin-media-server`

```
stashd/plugin-media-server/
├── composer.json
├── src/
│   ├── JellyfinSeriesPlugin.php   ← extracted, implements BroadcastPlugin
│   ├── PlexSeriesPlugin.php       ← extracted, implements BroadcastPlugin
│   ├── FilesystemSeriesPlugin.php ← extracted, implements BroadcastPlugin
│   ├── AbstractSeriesPlugin.php   ← shared base
│   └── SeriesFormatOptions.php
└── bootstrap.php
```

## Implementation Tasks

### Phase 1: Core interfaces and types

1. Create `BroadcastPlugin` interface
2. Create `StashdBroadcast` attribute
3. Create `DiscoveredPlugin` wrapper
4. Create `FileKind` enum
5. Create `UiControl` value object
6. Create `BroadcastPluginDiscoverer` service
7. Wire discoverer into Tempest discovery at boot

### Phase 2: Extract podcast plugin

1. Create `stashd/plugin-podcast` package skeleton (composer.json, directory structure)
2. Move `PodcastBroadcastFormat` → plugin (rename to `PodcastPlugin`, implement `BroadcastPlugin`)
3. Move all podcast support classes → plugin
4. Add `#[StashdBroadcast]` to plugin class
5. Create plugin `bootstrap.php` that registers Tempest discovery
6. Update core to depend on plugin via Composer (dev-only during migration)
7. Update `BroadcastController` and `BroadcastLifecycleService` to use discovered plugins
8. Update `BroadcastType` enum — remove podcast, jellyfin, plex, filesystem values
9. Delete `app/Broadcasts/Formats/` directory
10. Delete `app/Broadcasts/Podcasts/` directory (moved to plugin)
11. Delete `BroadcastTypeRegistry`
12. Delete `AbstractSeriesBroadcastType` (plugin-internal)
13. Delete `BroadcastFormat` interface (replaced by `BroadcastPlugin`)

### Phase 3: Extract media-server plugin

1. Create `stashd/plugin-media-server` package skeleton
2. Move `JellyfinSeriesBroadcastType`, `PlexSeriesBroadcastType`, `FilesystemSeriesBroadcastType` → plugin
3. Move `SeriesFormatOptions`, `AbstractSeriesBroadcastType` → plugin
4. Add `#[StashdBroadcast]` attributes to each broadcast type in plugin
5. Create plugin `bootstrap.php`
6. Update core to depend on media-server plugin via Composer

### Phase 4: Wire and clean up

1. Update `BroadcastController` to use `BroadcastPluginDiscoverer`
2. Update `BroadcastLifecycleService` to call plugin methods directly
3. Update `BroadcastContextFactory` if needed
4. Update `BroadcastState` enum if any type-dependent states need removal
5. Update tests:
    - Update existing broadcast tests to load plugin discovery
    - Create plugin-level tests in each package
    - Update Docker smoke tests if needed
6. Update `composer.json` to add plugin dependencies
7. Update `docs/TODO.md` to reflect completion
8. Delete any remaining dead code

### Phase 5: Documentation

1. Update `docs/TODO.md` with plugin architecture docs
2. Write plugin development guide (how to create a new plugin)
3. Update `AGENTS.md` or architecture docs with new structure

## Key Migration Details

### `BroadcastRecord.type` — enum to string

`BroadcastRecord.type` changes from `BroadcastType` enum to string (the plugin key).

```sql
ALTER TABLE broadcasts DROP CONSTRAINT broadcasts_type_check;
ALTER COLUMN type TYPE VARCHAR(255);
```

Or keep the enum but remove the non-core values (backward-compatible if no broadcasts use them yet).

### UI flow

1. User creates broadcast → core queries `DiscoveredPlugin[]` → renders `uiControls()` for each plugin → user selects type and fills controls
2. Core passes selected key + controls to plugin's `plan()` → plugin returns `BroadcastPlan`
3. Core calls `publish()` → plugin creates its output (RSS feed, Jellyfin layout, etc.)
4. Plugin returns `BroadcastPublishResult` with publication details

### Plugin bootstrap

Each plugin ships a `bootstrap.php`:

```php
// Registers the plugin's Tempest discovery with the core
Tempest\Discovery::add(PodcastPlugin::class);
```

Core's `composer.json` lists plugins as `dev` dependencies during development. Production deployment uses separate Composer workspaces.

## Risks

- Existing broadcasts with `BroadcastType::JellyfinSeries` values need migration to string
- Tests that hardcode `BroadcastType` values need updating
- Plugin discovery must handle missing plugins gracefully (log warning, skip)
- Docker build needs plugin Composer packages available
- `BroadcastController` hardcodes `PodcastTokenService` and `PodcastEpisodeUrlBuilder` — these need to move to the podcast plugin and be injected conditionally
