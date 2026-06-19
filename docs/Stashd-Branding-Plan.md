# Stashd Branding Plan

> **Updated:** 2026-06-16  
> **Status:** Current branding direction for v1

---

# 1. Brand Summary

**Stashd** is a self-hosted media archiver for people who want to keep local, useful copies of online media they care about.

It saves media into a local **Vault**, then rebroadcasts it into the apps people already use:

- Jellyfin
- Plex
- private podcast feeds

Stashd is built for homelabbers, self-hosters, preservationists, and people who have been burned before by disappearing uploads, broken feeds, region locks, or platform churn.

---

# 2. Brand Core

## Name

**Stashd**

Why it works:

- **Stash** is short, understandable, and slightly mischievous
- it hints at keeping a private copy
- it has a subtle “hide it from dad” undercurrent without sounding sketchy
- the trailing **d** evokes a Unix daemon / background service
- it feels at home in a self-hosted stack

Technical users can naturally read it as **Stash Daemon**.

## North-Star Brand Line

> **Because the internet forgets.**

## Supporting Brand Line

> **Your archive. Your rules.**

---

# 3. Brand Sentence

Primary brand sentence:

> **Stashd turns fragile online media into a local archive you control, then rebroadcasts it into the apps you already use: Jellyfin, Plex, and private podcast feeds.**

Short product sentence:

> **Save the online media you care about to a local Vault, then broadcast it to Jellyfin, Plex, or your podcast app.**

Short repository description:

> **Self-hosted media archiving for YouTube, Jellyfin, Plex, and private podcast feeds.**

---

# 4. Homepage Hero

```text
stashd_
Because the internet forgets.

Save the online media you care about to a local Vault, then broadcast it to Jellyfin, Plex, or your podcast app.

Your archive. Your rules.
```

This is the preferred homepage / README hero stack.

---

# 5. Brand Personality

Stashd should feel:

- calm
- technical
- self-hosted
- preservation-minded
- trustworthy
- quietly competent
- slightly cheeky
- a little secretive in a charming way

It should **not** feel like:

- a piracy brand
- a YouTube clone
- a startup/SaaS marketing page
- a “hacker meme” tool
- a loud or chaotic media app

The right vibe is:

> **mischievous preservationist**

not

> **pirate-coded downloader**

---

# 6. Voice & Tone

Stashd should talk like a competent homelab friend.

## Voice traits

- direct
- clear
- calm
- useful
- dryly reassuring
- never salesy

## Examples

Good:

- `This stash is up to date.`
- `Hardlinks are unavailable. Stashd will not duplicate 800 GB of media without asking first.`
- `This feed URL is private. Anyone with the link can listen.`
- `Jellyfin scan failed, but your files were published successfully.`

## Cheeky microcopy bank

Use sparingly:

- `Tucked safely away.`
- `No judgement. It’s in the Vault now.`
- `Your Vault is your business.`
- `Saved before it vanished.`
- `Stashed where only you can find it.`
- `Private feeds for private little goblin habits.`

---

# 7. Visual Direction

## Overall Style

The UI and brand should feel like:

> **Glance crossed with a well-designed system dashboard**

Visual inspiration:

- Glance dashboard warmth and density
- dark, self-hosted dashboard aesthetic
- restrained accenting
- strong information hierarchy
- low visual noise

## Visual Traits

Prefer:

- dark warm-brown/espresso backgrounds
- muted off-white text
- restrained amber accenting
- compact, calm layout
- subtle borders
- minimal geometry
- dense but readable information presentation
- monospace / semi-monospace typography

Avoid:

- bright red YouTube-like styling
- glossy gradients
- loud neon cyberpunk
- giant entertainment thumbnails everywhere
- over-designed “brand mascot” aesthetics

---

# 8. Typography Direction

## Primary Identity

The primary identity is **wordmark-first**.

Preferred wordmark:

```text
stashd_
```

Traits:

- lowercase
- clean rounded monospace or semi-monospace feel
- technical, but softened
- modern, not retro-terminal gimmicky
- the underscore is integral to the mark

## Underscore Rule

The underscore should be treated as a meaningful brand element, not decoration.

Preferred treatment:

- letters in warm off-white or dark espresso, depending on background
- underscore in muted amber

The underscore can also recur in:

- active states
- dividers
- nav indicators
- shell/CLI examples
- tiny accent moments throughout the UI

## Monospace Usage

Monospace typography should be used generously in product/UI contexts:

- paths
- IDs
- logs
- commands
- URLs
- tokens
- filenames
- system diagnostics
- technical tables

A fully monospace UI is acceptable if it remains polished and readable.

---

# 9. Logo System

## Primary Logo

**Wordmark only**

```text
stashd_
```

This is the primary identity.

## Secondary / Light Background Logo

A light-background version is also desirable:

- warm parchment / beige background
- dark espresso wordmark
- amber underscore

## Favicon / App Icon

Preferred small icon concept:

> **A rounded-square / rounded-rectangle tile with only the amber underscore inside it.**

Why it works:

- minimal and distinctive
- clearly derived from the wordmark
- reads well at tiny sizes
- feels calm, technical, and brand-ownable
- avoids inventing a separate mascot or symbol too early

## What We Are *Not* Using

At this time, the preferred direction is **not** the vault/play icon.

The wordmark plus underscore icon system feels cleaner and more distinctive.

---

# 10. Color Direction

## Core Palette Feeling

The palette should be:

- warm
- dark
- slightly brown rather than neutral gray
- dashboard-like
- subtle, not high-contrast cyberpunk

## Preferred Colors

### Background

Warm espresso-charcoal:

- very dark brown / charcoal
- slightly textured or softly vignetted is acceptable
- visually closer to the warm dark tones seen in Glance’s README imagery

### Primary Text

- soft off-white
- warm cream
- never harsh pure white unless necessary

### Accent

Muted amber:

- used for the underscore
- active states
- highlights
- small accent moments

### Supporting UI Colors

- muted gray-browns for panels
- muted green for success
- deeper amber/orange for warnings
- restrained brick red for errors

---

# 11. Positioning

## Category

Stashd is a:

- self-hosted media archiver
- preservation tool
- rebroadcasting tool
- homelab utility

## Not a...

Stashd is **not**:

- a streaming platform
- a media player
- a YouTube frontend
- a social app
- a recommendation engine

## Core Positioning Statement

> **Stashd helps you keep local copies of online media you care about, so disappearing uploads, changing platforms, and broken feeds do not take your library with them.**

---

# 12. Messaging Framework

## Short Taglines

Primary:

- `Because the internet forgets.`

Supporting:

- `Your archive. Your rules.`

## Supporting Message Options

- `Save it before it disappears.`
- `The internet is temporary. Your Vault doesn’t have to be.`
- `Stash online media. Broadcast it anywhere.`
- `A local Vault for the media you actually care about.`

## Emotional Framing

Stashd is for:

- creators you care about
- lectures and talks you want to keep
- documentaries and playlists you return to
- obscure internet artifacts that might vanish tomorrow
- media you would genuinely miss if it disappeared

---

# 13. Product Language

Use the product terminology defined in the engineering spec.

Preferred terms:

- **Stash**
- **Vault**
- **Broadcast**
- **Input**
- **Item**

Avoid older terminology such as:

- Mirror
- Collection
- Destination

These terms no longer represent the preferred product language.

---

# 14. Brand Do / Don’t

## Do

- use lowercase `stashd_`
- keep the aesthetic calm and useful
- lean into self-hosted/system-tool confidence
- use warm dark backgrounds
- use amber carefully and consistently
- keep copy direct and helpful
- let the “private little archive” energy stay subtle

## Don’t

- use obvious YouTube red styling
- lean into torrent/piracy tropes
- use mascots too early
- make it feel like a consumer streaming product
- use loud meme-y hacker visuals
- replace the wordmark with a generic symbol

---

# 15. Current Approved Asset Direction

Approved direction:

- **Primary logo:** wordmark-only `stashd_`
- **Preferred background:** warm espresso-charcoal
- **Preferred accent:** muted amber underscore
- **Favicon/app icon:** rounded square tile with amber underscore only
- **Visual mood:** Glance-inspired warm dark dashboard

---

# 16. Asset Checklist

Useful export targets:

- primary dark-background wordmark PNG
- light-background wordmark PNG
- square favicon/app icon PNG
- compact identity board PNG
- later: SVG versions, monochrome variants, and favicon-size exports

Current generated asset files in `/mnt/data` / generated workspace include:

- `Stashd-Branding-Plan.md`
- `minimalist_tech_logo_design.png`
- `minimalist_stashd_logo_design.png`
- `minimalist_icon_with_amber_accent.png`
- `minimalist_brand_presentation_with_logo_lockup.png`

---

# 17. Final Brand Snapshot

```text
stashd_
Because the internet forgets.

Save the online media you care about to a local Vault, then broadcast it to Jellyfin, Plex, or your podcast app.

Your archive. Your rules.
```

That is the current branding direction for Stashd.
