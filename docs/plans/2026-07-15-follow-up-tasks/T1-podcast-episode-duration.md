# T1 — Add episode duration to podcast feed XML

## Problem

Podcast feeds currently write episode enclosures but omit `itunes:duration`, even when the canonical media
item has a known duration.

## Scope

- Add nullable duration seconds to `app/Broadcasts/Podcasts/PodcastEpisode.php`.
- In `app/Broadcasts/Plugins/PodcastBroadcastPlugin.php`, populate it from the media item's
  `durationSeconds` value object. Keep the conversion at this boundary rather than changing the stored type.
- In `app/Broadcasts/Podcasts/PodcastFeedBuilder.php`, write `<itunes:duration>` for a known duration.
  Integer seconds are sufficient; omit the element when duration is unknown.
- Extend the existing podcast feed feature tests, especially `tests/Feature/Phase5CPodcastFeedTest.php`.

## Out of scope

- Probing media files to invent missing duration metadata.
- Changing podcast tokens, routes, artwork, enclosure URLs, or MIME-type behavior.
- Adding duration to unrelated API resources.

## Acceptance criteria

- Audio and video podcast episodes with known duration emit the correct `itunes:duration` value.
- Episodes with unknown duration omit the element rather than emitting zero or an empty value.
- Feed XML remains valid and does not expose internal paths or tokens beyond the existing designed URLs.
- Focused podcast tests, lint, static analysis, and the parallel suite pass.
