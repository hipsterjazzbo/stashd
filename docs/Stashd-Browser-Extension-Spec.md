# Stashd Browser Extension — Mini Engineering Specification

> **Status:** Draft companion-app spec  
> **Purpose:** Define a small browser extension that lets a user add the current supported page to Stashd.  
> **Suggested path:** `docs/browser-extension-spec.md`

---

# 1. Product Summary

The Stashd browser extension is a lightweight companion tool for sending the current browser page to a Stashd instance.

Its job is not to be a full Stashd UI.

Its job is:

```text
current supported page
  ↓
extension action
  ↓
Stashd preflight/create command
  ↓
open Stashd for review or confirm success
```

The extension should make “stash this” feel instant from the browser.

---

# 2. Core User Story

As a user browsing YouTube or another supported provider, I want to click the Stashd extension button and add the current page to my Stashd instance without manually copying/pasting the URL.

Examples:

```text
I am on a YouTube channel page → click Stashd → create/review channel stash.
I am on a YouTube playlist page → click Stashd → create/review playlist stash.
I am on a YouTube video page → click Stashd → add single item or create stash from item.
I am on an unsupported page → click Stashd → explain that this page is not supported yet.
```

---

# 3. Design Principles

## Small Companion, Not Second UI

The extension should stay small.

The full Stashd web app remains the primary UI for:

```text
stash settings
download policies
broadcast configuration
title regex filters
storage impact review
job progress
errors and recovery
```

The extension may show a compact popup, but it should hand off complex decisions to Stashd.

## API-First

The extension talks only to Stashd’s public API.

No private endpoints.

No database access.

No filesystem access.

No provider scraping beyond current page URL/title metadata.

## Explicit User Action

The extension should only inspect or submit the current page after explicit user action:

```text
toolbar button click
context-menu click
keyboard shortcut
```

This supports a low-permission model and avoids creepy background browsing behavior.

## Privacy-Respecting

The extension should not track browsing history.

It should not send arbitrary page URLs to Stashd automatically.

It should send only the current URL after the user asks it to.

---

# 4. Supported Browsers

## v1 Target

```text
Chrome / Chromium-compatible browsers
Firefox
```

Use the WebExtensions model where practical.

Prefer Manifest V3 for new development.

Browser compatibility differences should be isolated behind a small browser API adapter.

---

# 5. Extension Architecture

## Components

```text
manifest.json
background service worker
popup UI
options/settings page
content script only if needed
browser API adapter
Stashd API client
```

## Recommended Structure

```text
extension/
  manifest.json
  src/
    background/
      service-worker.ts
    popup/
      popup.html
      popup.ts
      popup.css
    options/
      options.html
      options.ts
      options.css
    shared/
      browser.ts
      stashd-api.ts
      page-detection.ts
      storage.ts
      types.ts
  assets/
    icon-16.png
    icon-32.png
    icon-48.png
    icon-128.png
```

## Technology

Use a small TypeScript build.

Avoid heavy frontend frameworks for v1.

Preferred:

```text
TypeScript
plain HTML/CSS
small bundler if needed
browser.storage.local
fetch
```

Avoid:

```text
React/Vue/Svelte unless clearly useful
background analytics
large dependency chains
remote scripts
```

---

# 6. Permissions

The extension should request the minimum practical permissions.

Suggested v1 permissions:

```json
{
  "permissions": [
    "activeTab",
    "storage",
    "contextMenus"
  ],
  "host_permissions": []
}
```

Optional if using script injection:

```json
{
  "permissions": [
    "activeTab",
    "storage",
    "contextMenus",
    "scripting"
  ]
}
```

The extension should not request broad host permissions like:

```text
<all_urls>
```

unless a later feature truly requires it.

## Stashd Host Access

The user configures the Stashd base URL manually:

```text
http://localhost:8474
https://stashd.example.com
```

The extension sends API requests only to the configured Stashd URL.

If browser extension host permissions are needed for API calls to arbitrary user-configured origins, request them in the narrowest supported way and explain why.

---

# 7. Configuration

The options page stores:

```text
Stashd base URL
API token
default action
open Stashd after submit: yes/no
```

Default action options:

```text
preflight only
create stash draft
send to Stashd review screen
```

Recommended v1 default:

```text
send to Stashd review screen
```

That keeps complex policy decisions inside Stashd.

## API Token

The extension uses a Stashd API token.

The token should be created in the Stashd UI with a limited scope:

```text
browser_extension
stash:create
stash:preflight
commands:create
```

The extension stores the token in browser local extension storage.

The extension should never log the token.

---

# 8. Stashd API Requirements

The Stashd API should expose a browser-companion-friendly command.

## Preferred Command

```http
POST /api/v1/commands
```

Example:

```json
{
  "type": "stash.preflight",
  "options": {
    "source_uri": "https://www.youtube.com/@ExampleChannel",
    "source_title": "Example Channel",
    "origin": "browser_extension"
  }
}
```

Response:

```json
{
  "id": "cmd_01JZ...",
  "state": "accepted",
  "jobs": [
    "job_01JZ..."
  ],
  "links": {
    "review": "https://stashd.example.com/stashes/new?command=cmd_01JZ..."
  }
}
```

## Optional Create Draft Command

```json
{
  "type": "stash.create_draft",
  "options": {
    "source_uri": "https://www.youtube.com/playlist?list=...",
    "source_title": "Some Playlist",
    "origin": "browser_extension"
  }
}
```

This creates a draft/new-stash review flow in Stashd rather than immediately creating a fully configured stash.

## API Principle

The extension should not decide:

```text
download policy
broadcast type
quality profile
storage strategy
title filters
```

It sends the URL to Stashd. Stashd performs provider resolution and preflight.

---

# 9. Page Detection

The extension should detect whether the current page looks supported before enabling the main action.

## v1 Supported URL Types

YouTube:

```text
channel page
handle page
playlist page
video page
```

Examples:

```text
https://www.youtube.com/@channel
https://www.youtube.com/channel/UC...
https://www.youtube.com/c/SomeChannel
https://www.youtube.com/user/SomeUser
https://www.youtube.com/playlist?list=...
https://www.youtube.com/watch?v=...
```

Future:

```text
Twitch VOD/channel
PeerTube channel/video
Vimeo page
Internet Archive item
Nebula page
generic yt-dlp-supported URL
```

## Detection Strategy

v1 detection should be URL-based.

Do not scrape page internals unless needed.

Use content scripts only if URL/title are insufficient.

For supported pages, popup shows:

```text
Ready to stash
YouTube playlist detected
[Send to Stashd]
```

For unsupported pages:

```text
Unsupported page
Stashd does not know how to stash this page yet.
```

---

# 10. Popup UX

## Supported Page

Popup should show:

```text
stashd_
Because the internet forgets.

Detected:
YouTube playlist

Current page:
<page title>

[Send to Stashd]
[Open Stashd]
```

After submit:

```text
Sent to Stashd.
Review stash setup in Stashd.
[Open Review]
```

## Unsupported Page

```text
stashd_
This page is not supported yet.

[Open Stashd]
```

## Not Configured

```text
Connect to Stashd

Base URL:
[ http://localhost:8474 ]

API token:
[ paste token ]

[Test connection]
[Save]
```

---

# 11. Context Menu

Add a right-click context menu:

```text
Stash this page
```

Optionally:

```text
Stash this link
```

“Stash this link” allows users to right-click a YouTube link without opening it.

The extension should send the selected link URL to Stashd using the same command flow.

---

# 12. Keyboard Shortcut

## Preferred Shortcut

The preferred brand-aligned keyboard shortcut is:

```text
Meta+_
```

Display examples:

```text
macOS: ⌘ + _
Windows/Linux: Meta + _
```

Action:

```text
Send current page to Stashd
```

This is intentionally tied to the brand mark:

```text
stashd_
      ↑
  shortcut key
```

## Browser Compatibility Caveat

Browser extension shortcut registration can be restrictive, and `_` is commonly produced as:

```text
Shift + -
```

So the extension may need to register the physical combination as:

```text
Meta+Shift+Minus
```

while displaying it in the UI as:

```text
Meta+_
```

or:

```text
⌘ + _
```

## Fallback Shortcut

If `Meta+_` cannot be registered reliably in a target browser, use:

```text
Alt+Shift+S
```

Fallback behavior should be graceful.

The preferred brand-facing shortcut remains:

```text
Meta+_
```

---

# 13. State Handling

The extension should remain mostly stateless.

Stored locally:

```text
base URL
API token
user preferences
last connection test result
```

Not stored:

```text
full browsing history
submitted URL history
provider metadata snapshots
download state
job state history
```

If the user wants job status, open Stashd.

---

# 14. Error Handling

Errors should be actionable.

## Cannot Connect

```text
Could not reach Stashd at http://localhost:8474.

Check that Stashd is running and that the URL is correct.
```

## Auth Failed

```text
Stashd rejected this API token.

Create a new browser extension token in Stashd Settings → API Tokens.
```

## Unsupported URL

```text
Stashd does not support this page yet.
```

## Provider Not Configured

```text
Stashd recognized this page, but the provider needs setup first.

Open Stashd to configure YouTube support.
```

## Command Accepted But Preflight Failed

```text
Stashd received the page, but preflight failed.

Open Stashd to see the error.
```

---

# 15. Security

## Token Scope

Use a dedicated limited-scope API token.

Suggested scopes:

```text
system:health:read
commands:create
stash:preflight
stash:create_draft
```

Do not use a full admin token.

## No Secret Logging

Never log:

```text
API token
private feed URLs
provider credentials
authorization headers
```

## Origin Safety

Only send requests to the configured Stashd base URL.

Validate base URL:

```text
http://localhost allowed
http://127.0.0.1 allowed
https:// allowed
plain http to non-localhost should show a warning
```

Warning example:

```text
This Stashd URL is not HTTPS. Your API token may be exposed on the network.
```

## CSP

Do not load remote scripts.

Do not execute dynamic code.

Keep extension pages simple and bundled.

---

# 16. Stashd Server Support

Stashd should expose extension-friendly API behavior.

## Health

```http
GET /health
```

Used for basic connectivity.

## Authenticated API Health

```http
GET /api/v1/system/health
```

Used to confirm token works once auth exists.

## Preflight Command

```http
POST /api/v1/commands
{
  "type": "stash.preflight",
  "options": {
    "source_uri": "...",
    "source_title": "...",
    "origin": "browser_extension"
  }
}
```

## Review Link

Command responses should include a review URL where practical:

```json
{
  "links": {
    "review": "https://stashd.example.com/stashes/new?command=cmd_..."
  }
}
```

This lets the extension stay simple.

---

# 17. Branding

Use the current Stashd brand direction.

```text
stashd_
Because the internet forgets.
```

Visual traits:

```text
warm espresso-charcoal background
soft off-white text
muted amber underscore
monospace / semi-monospace typography
compact calm dashboard feel
```

The browser icon should use the approved small-mark direction:

```text
rounded-square tile with amber underscore
```

---

# 18. Testing

## Unit Tests

Test:

```text
URL detection
provider type detection
base URL normalization
API request building
keyboard shortcut fallback logic
error mapping
storage read/write
```

## Integration Tests

Use a fake Stashd API server.

Test:

```text
connection success
connection failure
auth failure
preflight command accepted
unsupported URL response
review URL handling
```

## Manual Browser Tests

Test:

```text
Chrome
Firefox
Chromium/Brave if convenient
```

Scenarios:

```text
fresh install
configure Stashd URL/token
click extension on YouTube channel
click extension on YouTube playlist
click extension on YouTube video
right-click link → stash this link
keyboard shortcut on supported page
keyboard shortcut on unsupported page
unsupported page
Stashd offline
bad API token
```

---

# 19. v1 Scope

## Must Have

```text
Manifest V3 extension
Chrome/Chromium support
Firefox support if practical
options page for Stashd URL and API token
connection test
toolbar popup
current-page URL detection
YouTube channel/playlist/video URL detection
send current page to Stashd preflight command
open Stashd review URL
context menu: Stash this page
keyboard shortcut: Meta+_ where supported
shortcut fallback: Alt+Shift+S
minimal error handling
underscore favicon/app icon
```

## Should Have

```text
context menu: Stash this link
plain-http warning for non-local URLs
recent success toast/message
browser-specific shortcut display
```

## Not v1

```text
full stash management UI
job progress UI
download progress UI
provider credential setup
broadcast configuration
local page scraping
automatic background page detection
history of submitted URLs
multi-Stashd instance switching
```

---

# 20. Release Strategy

Start unsigned/local developer builds.

Then publish when stable:

```text
Chrome Web Store
Firefox Add-ons
GitHub releases with source package
```

The extension should be versioned independently from Stashd but declare compatible Stashd API versions.

Example:

```json
{
  "minimum_stashd_api_version": "v1"
}
```

---

# 21. Product Boundary

The extension exists to reduce friction.

It should not become another Stashd frontend.

The ideal v1 experience:

```text
user is on a supported page
clicks stashd_
extension sends URL to Stashd
Stashd opens review flow
user chooses policy/broadcasts there
```

That is enough.
