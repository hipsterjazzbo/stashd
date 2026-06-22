import './main.entrypoint.css'
import Alpine from 'alpinejs'

declare global {
	interface Window {
		Alpine: typeof Alpine
	}
}

/**
 * Marks the current section in the top nav with the amber underscore.
 * Nav links carry `data-nav="/stashes"` etc.; the longest matching prefix wins
 * so `/stashes/{id}` still highlights Stashes.
 */
function markActiveNav(): void {
	const path = window.location.pathname
	const links = Array.from(
		document.querySelectorAll<HTMLElement>('[data-nav]'),
	)

	let best: HTMLElement | null = null
	for (const link of links) {
		const target = link.dataset.nav ?? ''
		const matches = target === '/' ? path === '/' : path.startsWith(target)
		if (matches && (!best || target.length > (best.dataset.nav?.length ?? 0))) {
			best = link
		}
	}

	for (const link of links) {
		link.classList.toggle('is-active', link === best)
	}
}

/**
 * Wires the header "log out" button: revokes the session server-side (which
 * clears the HttpOnly cookie) then returns to the login page.
 */
function wireLogout(): void {
	const button = document.querySelector<HTMLElement>('[data-logout]')
	if (!button) return

	button.addEventListener('click', async () => {
		try {
			await fetch('/api/v1/auth/logout', {
				method: 'POST',
				credentials: 'same-origin',
			})
		} finally {
			window.location.assign('/login')
		}
	})
}

/** Thrown by apiFetch on 401/403, after it has already redirected to /login. */
class UnauthenticatedError extends Error {}

/**
 * Shared fetch wrapper for every /api/v1 call made from the dashboard shell.
 * Sends the session cookie, and treats 401/403 as "bounce to /login" rather
 * than something each call site needs to check for itself.
 */
async function apiFetch(path: string, init: RequestInit = {}): Promise<Response> {
	const response = await fetch(path, { ...init, credentials: 'same-origin' })

	if (response.status === 401 || response.status === 403) {
		window.location.assign('/login')
		throw new UnauthenticatedError(path)
	}

	return response
}

/**
 * Client auth gate for the dashboard shell. The HTML pages are public, so when
 * the session cookie is missing or invalid every /api/v1 call returns 401 — we
 * detect that up front and bounce to /login. Pages opt in via the layout's
 * `data-requires-auth` body attribute; the login page does not.
 */
async function enforceAuth(): Promise<void> {
	if (!document.body.hasAttribute('data-requires-auth')) return

	try {
		await apiFetch('/api/v1/auth/me')
	} catch {
		// apiFetch already redirected on 401/403; a network error here is left
		// in place rather than trapping a redirect loop.
	}
}

function formatBytes(bytes: number | null | undefined): string {
	if (bytes === null || bytes === undefined || Number.isNaN(bytes)) return '—'

	const units = ['B', 'KB', 'MB', 'GB', 'TB']
	let value = bytes
	let unit = 0
	while (value >= 1024 && unit < units.length - 1) {
		value /= 1024
		unit++
	}

	return `${unit === 0 ? value : value.toFixed(1)} ${units[unit]}`
}

function formatDuration(seconds: number | null | undefined): string {
	if (seconds === null || seconds === undefined || Number.isNaN(seconds)) return '—'

	const total = Math.max(0, Math.round(seconds))
	if (total < 60) return `${total}s`

	const minutes = Math.floor(total / 60)
	if (minutes < 60) return `${minutes}m ${total % 60}s`

	const hours = Math.floor(minutes / 60)
	return `${hours}h ${minutes % 60}m`
}

const ALL_DOWNLOAD_POLICIES = ['video', 'audio_only', 'metadata_only', 'manual_download']

/**
 * Mirrors App\Broadcasts\BroadcastType::isSatisfiedByDownloadPolicy() — kept
 * here only for instant reactive feedback as the user picks a broadcast type;
 * the server-side check in BroadcastController::create() is authoritative.
 */
function downloadPolicySatisfiesBroadcastType(policy: string, broadcastType: string): boolean {
	if (policy === 'metadata_only') return false
	if (policy === 'audio_only') return broadcastType !== 'video_podcast'
	return true
}

function compatibleDownloadPolicies(broadcastType: string): string[] {
	return ALL_DOWNLOAD_POLICIES.filter((policy) => downloadPolicySatisfiesBroadcastType(policy, broadcastType))
}

const SERIES_BROADCAST_TYPES = ['filesystem_series', 'jellyfin_series', 'plex_series']

/** Mirrors App\Broadcasts\BroadcastType::isSeries(). */
function isSeriesBroadcastType(type: string): boolean {
	return SERIES_BROADCAST_TYPES.includes(type)
}

function formatRelativeTime(iso: string | null | undefined): string {
	if (!iso) return '—'

	const then = new Date(iso).getTime()
	if (Number.isNaN(then)) return '—'

	const diffSeconds = Math.round((then - Date.now()) / 1000)
	const abs = Math.abs(diffSeconds)
	const suffix = diffSeconds <= 0 ? 'ago' : 'from now'

	if (abs < 5) return 'just now'
	if (abs < 60) return `${abs}s ${suffix}`
	if (abs < 3600) return `${Math.round(abs / 60)}m ${suffix}`
	if (abs < 86400) return `${Math.round(abs / 3600)}h ${suffix}`
	return `${Math.round(abs / 86400)}d ${suffix}`
}

interface Badge {
	label: string
	dot: string
	text: string
	/** Whether the dot should pulse (T14: only genuinely "happening right now" states). */
	pulse?: boolean
}

// Tailwind's scanner needs every class name to appear as a literal string
// somewhere in the source — it can't see through `bg-${color}`-style
// interpolation — so state -> badge is a static lookup table, not a template.
const STATE_BADGES: Record<string, Badge> = {
	pending: { label: 'pending', dot: 'bg-muted', text: 'text-muted' },
	processing: { label: 'processing', dot: 'bg-amber', text: 'text-amber' },
	ready: { label: 'ready', dot: 'bg-success', text: 'text-success' },
	completed: { label: 'completed', dot: 'bg-success', text: 'text-success' },
	stale: { label: 'stale', dot: 'bg-warn', text: 'text-warn' },
	failed: { label: 'failed', dot: 'bg-error', text: 'text-error' },
	missing: { label: 'missing', dot: 'bg-error', text: 'text-error' },
	disabled: { label: 'disabled', dot: 'bg-muted', text: 'text-muted' },
	ignored: { label: 'ignored', dot: 'bg-muted', text: 'text-muted' },
	cancelled: { label: 'cancelled', dot: 'bg-muted', text: 'text-muted' },
	active: { label: 'active', dot: 'bg-success', text: 'text-success' },
	removed: { label: 'removed', dot: 'bg-muted', text: 'text-muted' },
	hidden: { label: 'hidden', dot: 'bg-muted', text: 'text-muted' },
	discovered: { label: 'discovered', dot: 'bg-muted', text: 'text-muted' },
	metadata_ready: { label: 'metadata ready', dot: 'bg-amber', text: 'text-amber' },
	download_pending: { label: 'download pending', dot: 'bg-amber', text: 'text-amber' },
	downloading: { label: 'downloading', dot: 'bg-amber', text: 'text-amber' },
}

// States that mean "actively in progress right now", as opposed to queued
// (pending, download_pending), settled (ready, failed, ignored…), or merely
// descriptive (active = not-removed, not a live process). Only these pulse.
const ACTIVELY_HAPPENING_STATES = new Set(['processing', 'downloading'])

function statusBadge(state: string | null | undefined): Badge {
	if (!state) return { label: '—', dot: 'bg-muted', text: 'text-muted' }
	const badge = STATE_BADGES[state] ?? { label: state, dot: 'bg-muted', text: 'text-muted' }
	return { ...badge, pulse: ACTIVELY_HAPPENING_STATES.has(state) }
}

const ACTIVITY_LEVEL_BADGES: Record<string, Badge> = {
	info: { label: 'info', dot: 'bg-muted', text: 'text-muted' },
	success: { label: 'success', dot: 'bg-success', text: 'text-success' },
	warning: { label: 'warning', dot: 'bg-warn', text: 'text-warn' },
	error: { label: 'error', dot: 'bg-error', text: 'text-error' },
}

const JOB_EVENT_BADGES: Record<string, Badge> = {
	'job.created': { label: 'job created', dot: 'bg-muted', text: 'text-muted' },
	'job.progress': { label: 'job progress', dot: 'bg-amber', text: 'text-amber' },
	'job.completed': { label: 'job completed', dot: 'bg-success', text: 'text-success' },
	'job.failed': { label: 'job failed', dot: 'bg-error', text: 'text-error' },
}

interface ActivityEvent {
	id: string
	event: string
	payload: Record<string, unknown>
	created_at: string
}

function eventBadge(event: ActivityEvent): Badge {
	if (event.event === 'activity.created') {
		const level = String(event.payload.level ?? 'info')
		return ACTIVITY_LEVEL_BADGES[level] ?? ACTIVITY_LEVEL_BADGES.info
	}

	return JOB_EVENT_BADGES[event.event] ?? { label: event.event, dot: 'bg-muted', text: 'text-muted' }
}

function summarizeEvent(event: ActivityEvent): string {
	const payload = event.payload

	switch (event.event) {
		case 'activity.created':
			return String(payload.message ?? '')
		case 'job.created':
			return `${String(payload.intent ?? 'job')} job created`
		case 'job.progress': {
			const label = String(payload.progress_label ?? payload.intent ?? 'job')
			return payload.progress_percent === null || payload.progress_percent === undefined
				? label
				: `${label} — ${payload.progress_percent}%`
		}
		case 'job.completed':
			return `${String(payload.intent ?? 'job')} completed`
		case 'job.failed':
			return payload.last_error
				? `${String(payload.intent ?? 'job')} failed: ${payload.last_error}`
				: `${String(payload.intent ?? 'job')} failed`
		default:
			return event.event
	}
}

interface ActivityLogEntry {
	id: string
	level: string
	type: string
	message: string
	entity_type: string | null
	entity_id: string | null
	stash_id: string | null
	media_item_id: string | null
	broadcast_id: string | null
	job_id: string | null
	command_id: string | null
	created_at: string
}

interface ActivitySummaryGroup {
	type: string
	level: string
	stash_id: string | null
	message: string
	count: number
	created_at: string
}

/**
 * Collapses consecutive (already-recency-ordered) log entries that share a
 * type and stash into one summary line with a count — spec §29's grouping
 * ("event type, stash, command/job, short time window"), approximated by
 * "adjacent in an already-time-ordered recent list" rather than parsing
 * timestamps. Shows the most recent message in the group; older ones in the
 * same group are summarized as "+N more" rather than re-stated.
 */
function summarizeRecentActivity(events: ActivityLogEntry[], limit = 8): ActivitySummaryGroup[] {
	const groups: ActivitySummaryGroup[] = []

	for (const event of events) {
		const last = groups[groups.length - 1]
		if (last && last.type === event.type && last.stash_id === event.stash_id) {
			last.count++
		} else {
			groups.push({
				type: event.type,
				level: event.level,
				stash_id: event.stash_id,
				message: event.message,
				count: 1,
				created_at: event.created_at,
			})
		}
	}

	return groups.slice(0, limit)
}

// The full, fixed set of named SSE events EventsController ever emits
// (app/System/Event/EventPublisher.php) — EventSource has no "any event"
// listener, so every name has to be wired up individually.
const EVENT_TYPES = ['job.created', 'job.progress', 'job.completed', 'job.failed', 'activity.created'] as const

/**
 * Opens one EventSource, re-running `checkTerminal` on every relevant SSE
 * event (and once immediately, in case the thing it's waiting on already
 * finished before this connection opened), and closes the connection as soon
 * as it returns true.
 *
 * Only for short, one-shot waits (preflight/add-input steps) — never held
 * open for a page's whole lifetime like Dashboard/Activity/Stash detail.
 * EventsController holds a RoadRunner worker for its full poll-loop duration
 * regardless of how soon the client closes (see docs/TODO.md's SSE note), so
 * this is used once or twice per input added, not perpetually.
 */
function awaitSseTerminal(checkTerminal: () => Promise<boolean>): void {
	if (!('EventSource' in window)) {
		void checkTerminal()
		return
	}

	const source = new EventSource('/api/v1/events')
	let closed = false

	const tick = async () => {
		if (closed) return
		if (await checkTerminal()) {
			closed = true
			source.close()
		}
	}

	for (const type of EVENT_TYPES) {
		source.addEventListener(type, () => void tick())
	}

	void tick()
}

interface StorageLocation {
	key: string
	path: string
	state: string
	readable: boolean
	writable: boolean
	supports_hardlinks: boolean
	last_error: string | null
	free_bytes: number | null
	total_bytes: number | null
}

interface HealthReport {
	status: string
	version: string
	database: { writable: boolean }
	storage: {
		ready: boolean
		vault_broadcast_hardlink: boolean
		message: string | null
		locations: StorageLocation[]
	}
}

interface JobSummary {
	id: string
	command_id: string | null
	intent: string
	state: string
	progress_current: number | null
	progress_total: number | null
	progress_percent: number | null
	progress_label: string | null
	last_error: string | null
	started_at: string | null
	finished_at: string | null
	heartbeat_at: string | null
	created_at: string
	updated_at: string
}

interface ResolvedInputSummary {
	provider_key: string
	input_type: string
	source_uri: string
	provider_input_id: string
	title: string | null
	source_title: string | null
	source_avatar_uri: string | null
	estimated_item_count: number | null
}

interface DiscoveredItemSummary {
	provider_item_id: string
	canonical_uri: string
	title: string
	description: string | null
	duration_seconds: number | null
	published_at: string | null
	thumbnail_uri: string | null
}

interface UniversalFilterDeclaration {
	key: string
	label: string
	type: string
}

interface InputOptionDeclaration {
	key: string
	label: string
	type: 'bool' | 'enum'
	default: boolean | string
	choices: string[] | null
	applicable_input_types: string[]
}

interface PreflightReview {
	command_id: string
	state: string
	review_url: string | null
	preflight: {
		source_uri: string
		source_title: string | null
		origin: string
		resolved_input: ResolvedInputSummary | null
		discovery: {
			strategy_key: string
			estimated_item_count: number
			estimated_total_duration_seconds: number
			discovered_items: DiscoveredItemSummary[]
			sample_items: DiscoveredItemSummary[]
		} | null
		universal_filters: UniversalFilterDeclaration[]
		input_options: InputOptionDeclaration[]
	} | null
	ui_note: string
}

interface CommandSummary {
	id: string
	type: string
	state: string
	target_type: string | null
	target_id: string | null
	options: Record<string, unknown> | null
	result: Record<string, unknown> | null
	created_by_user_id: string | null
	created_at: string
	updated_at: string
}

interface CommandShowResponse {
	command: CommandSummary
	jobs: JobSummary[]
}

interface StashSummary {
	id: string
	name: string
	slug: string
	description: string | null
	sync_mode: string
	download_policy: string
	organization_mode: string
	state: string
	icon_uri: string | null
	created_at: string
	updated_at: string
}

interface StashDeleteImpactItem {
	media_item_id: string
	title: string
}

interface StashDeleteImpactSharedItem extends StashDeleteImpactItem {
	shared_with_stashes: { id: string; name: string }[]
}

interface StashDeleteImpactSummary {
	shared_items: StashDeleteImpactSharedItem[]
	orphaned_items: StashDeleteImpactItem[]
}

interface StashEditForm {
	name: string
	description: string
	syncMode: string
	downloadPolicy: string
	organizationMode: string
}

interface StashItemSummary {
	id: string
	stash_id: string
	media_item_id: string
	stash_input_id: string | null
	state: string
	position: number | null
	season_number: number | null
	episode_number: number | null
	season_title: string | null
	display_title: string | null
	display_description: string | null
	first_seen_at: string | null
	last_seen_at: string | null
	removed_at: string | null
	removed_reason: string | null
	ignored_reason: string | null
	created_at: string
	updated_at: string
}

interface StashInputSummary {
	id: string
	stash_id: string
	provider_key: string
	input_type: string
	source_uri: string
	provider_input_id: string
	state: string
	consecutive_failures: number
	title: string | null
	sync_mode: string | null
	last_checked_at: string | null
	next_check_at: string | null
	last_success_at: string | null
	last_failure_at: string | null
	created_at: string
	updated_at: string
}

interface BroadcastSummary {
	id: string
	stash_id: string
	type: string
	name: string
	slug: string
	state: string
	settings: Record<string, unknown> | null
	last_planned_at: string | null
	last_built_at: string | null
	last_verified_at: string | null
	last_error: string | null
	created_at: string
	updated_at: string
	feed_url?: string
	token_preview?: string
}

interface MediaItemSummary {
	id: string
	provider_key: string
	provider_item_id: string
	canonical_uri: string
	title: string
	description: string | null
	state: string
	upstream_state: string
	content_type: string | null
	creator_name: string | null
	duration_seconds: number | null
	published_at: string | null
	thumbnail_uri: string | null
	last_seen_upstream_at: string | null
	created_at: string
	updated_at: string
}

interface AssetSummary {
	id: string
	media_item_id: string
	role: string
	kind: string
	state: string
	derived_from_asset_id: string | null
	path: string | null
	relative_path: string | null
	mime_type: string | null
	container: string | null
	size_bytes: number | null
	checksum: string | null
	duration_seconds: number | null
	last_verified_at: string | null
	missing_at: string | null
	missing_reason: string | null
	created_at: string
	updated_at: string
}

interface ApiTokenSummary {
	id: string
	name: string
	token_preview: string
	scopes: string[]
	last_used_at: string | null
	expires_at: string | null
	created_at: string
}

interface YoutubeApiKeyStatus {
	configured: boolean
}

interface MediaServerSummary {
	id: string
	type: string
	name: string
	base_uri: string
	state: string
	settings: Record<string, unknown> | null
	last_checked_at: string | null
	last_error: string | null
	created_at: string
	updated_at: string
}

interface LibrarySummary {
	id: string
	name: string
	type: string | null
}

function dashboardComponent() {
	return {
		loading: true,
		error: null as string | null,
		health: null as HealthReport | null,
		stashCount: null as number | null,
		vaultCount: null as number | null,
		activitySummary: [] as ActivitySummaryGroup[],
		formatRelativeTime,
		formatBytes,
		statusBadge,

		totalFreeBytes(): number | null {
			const locations = this.health?.storage?.locations ?? []
			return locations.length === 0 ? null : locations.reduce((sum, location) => sum + (location.free_bytes ?? 0), 0)
		},

		totalDiskBytes(): number | null {
			const locations = this.health?.storage?.locations ?? []
			return locations.length === 0 ? null : locations.reduce((sum, location) => sum + (location.total_bytes ?? 0), 0)
		},

		async init() {
			await this.refresh()
			this.loading = false

			if ('EventSource' in window) {
				const source = new EventSource('/api/v1/events')
				for (const type of EVENT_TYPES) {
					source.addEventListener(type, () => void this.refresh())
				}
			} else {
				setInterval(() => void this.refresh(), 5000)
			}
		},

		async refresh() {
			try {
				const [healthResponse, stashesResponse, itemsResponse, activityResponse] = await Promise.all([
					apiFetch('/api/v1/system/health'),
					apiFetch('/api/v1/stashes'),
					apiFetch('/api/v1/items'),
					apiFetch('/api/v1/activity'),
				])
				this.health = await healthResponse.json()
				this.stashCount = ((await stashesResponse.json()).stashes as unknown[]).length
				this.vaultCount = ((await itemsResponse.json()).items as unknown[]).length
				this.activitySummary = summarizeRecentActivity((await activityResponse.json()).events as ActivityLogEntry[])
				this.error = null
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			}
		},
	}
}

function activityComponent() {
	return {
		events: [] as ActivityEvent[],
		connected: false,
		// SSE replaces `events` wholesale on every notification; tracking
		// open-state here (rather than relying on <details>'s own DOM state)
		// is what survives that, since x-bind:open re-derives it from this
		// set every render instead of trusting whatever the browser kept.
		expandedEventIds: new Set<string>(),
		formatRelativeTime,
		eventBadge,
		summarize: summarizeEvent,

		toggleEventDisclosure(id: string, open: boolean) {
			if (open) {
				this.expandedEventIds.add(id)
			} else {
				this.expandedEventIds.delete(id)
			}
		},

		init() {
			if (!('EventSource' in window)) return

			const source = new EventSource('/api/v1/events')
			source.onopen = () => {
				this.connected = true
			}
			source.onerror = () => {
				this.connected = false
			}

			for (const type of EVENT_TYPES) {
				source.addEventListener(type, (raw) => {
					const parsed = JSON.parse((raw as MessageEvent<string>).data) as ActivityEvent
					this.events = [parsed, ...this.events.filter((event) => event.id !== parsed.id)].slice(0, 200)
				})
			}
		},
	}
}

function stashesComponent() {
	return {
		loading: true,
		error: null as string | null,
		stashes: [] as StashSummary[],
		statusBadge,

		editingStash: null as StashSummary | null,
		editForm: { name: '', description: '', syncMode: 'automatic', downloadPolicy: 'video', organizationMode: 'flat' } as StashEditForm,
		savingEdit: false,

		deletingStash: null as StashSummary | null,
		deleteImpact: null as StashDeleteImpactSummary | null,
		loadingDeleteImpact: false,
		deletingBusy: false,

		creatingStash: false,
		newStashForm: { title: '', link: '' },
		newStashError: null as string | null,
		creatingBusy: false,

		async init() {
			await this.refresh()
			this.loading = false
		},

		async refresh() {
			try {
				const response = await apiFetch('/api/v1/stashes')
				this.stashes = (await response.json()).stashes
				this.error = null
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			}
		},

		startEdit(stash: StashSummary) {
			this.editingStash = stash
			this.editForm = {
				name: stash.name,
				description: stash.description ?? '',
				syncMode: stash.sync_mode,
				downloadPolicy: stash.download_policy,
				organizationMode: stash.organization_mode,
			}
		},

		cancelEdit() {
			this.editingStash = null
		},

		async saveEdit() {
			if (!this.editingStash || this.editForm.name.trim() === '') return
			this.savingEdit = true
			try {
				const response = await apiFetch(`/api/v1/stashes/${this.editingStash.id}`, {
					method: 'PATCH',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						name: this.editForm.name.trim(),
						description: this.editForm.description.trim(),
						sync_mode: this.editForm.syncMode,
						download_policy: this.editForm.downloadPolicy,
						organization_mode: this.editForm.organizationMode,
					}),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not update that stash.'
					return
				}
				this.editingStash = null
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.savingEdit = false
			}
		},

		async startDelete(stash: StashSummary) {
			this.deletingStash = stash
			this.deleteImpact = null
			this.loadingDeleteImpact = true
			try {
				const response = await apiFetch(`/api/v1/stashes/${stash.id}/delete-impact`)
				this.deleteImpact = (await response.json()).delete_impact
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not load delete impact.'
			} finally {
				this.loadingDeleteImpact = false
			}
		},

		cancelDelete() {
			this.deletingStash = null
			this.deleteImpact = null
		},

		async confirmDelete() {
			if (!this.deletingStash) return
			this.deletingBusy = true
			try {
				await apiFetch(`/api/v1/stashes/${this.deletingStash.id}`, { method: 'DELETE' })
				this.deletingStash = null
				this.deleteImpact = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not delete that stash.'
			} finally {
				this.deletingBusy = false
			}
		},

		startCreate() {
			this.newStashForm = { title: '', link: '' }
			this.newStashError = null
			this.creatingStash = true
		},

		cancelCreate() {
			this.creatingStash = false
		},

		async submitCreateStash() {
			this.creatingBusy = true
			try {
				const response = await apiFetch('/api/v1/stashes', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						name: this.newStashForm.title.trim() || undefined,
					}),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.newStashError = body.error?.message ?? 'Could not create the stash.'
					return
				}
				const body = (await response.json()) as { stash: StashSummary }
				const link = this.newStashForm.link.trim()
				this.creatingStash = false
				window.location.assign(link ? `/stashes/${body.stash.id}?link=${encodeURIComponent(link)}` : `/stashes/${body.stash.id}`)
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.newStashError = 'Could not reach the server.'
			} finally {
				this.creatingBusy = false
			}
		},
	}
}

function stashDetailComponent(stashId: string) {
	return {
		loading: true,
		error: null as string | null,
		stash: null as StashSummary | null,
		items: [] as StashItemSummary[],
		inputs: [] as StashInputSummary[],
		broadcasts: [] as BroadcastSummary[],
		actionPending: null as string | null,
		newBroadcastType: 'filesystem_series',
		newBroadcastName: '',
		creatingBroadcast: false,
		compatibleDownloadPolicyChoice: 'video',
		updatingDownloadPolicy: false,
		seasonMappingDrafts: {} as Record<string, Record<string, string>>,
		savingSeasonMapping: null as string | null,
		isSeriesBroadcastType,
		statusBadge,
		formatRelativeTime,
		formatDuration,

		editingOpen: false,
		editForm: { name: '', description: '', syncMode: 'automatic', downloadPolicy: 'video', organizationMode: 'flat' } as StashEditForm,
		savingEdit: false,

		deletingOpen: false,
		deleteImpact: null as StashDeleteImpactSummary | null,
		loadingDeleteImpact: false,
		deletingBusy: false,

		addInputOpen: false,
		addInputStep: 'paste' as 'paste' | 'reviewing' | 'review' | 'committing' | 'failed',
		addInputSourceUri: '',
		addInputSubmitting: false,
		addInputError: null as string | null,
		addInputPreflightCommandId: null as string | null,
		addInputCommitCommandId: null as string | null,
		addInputResolved: null as ResolvedInputSummary | null,
		addInputEstimatedItemCount: null as number | null,
		addInputEstimatedTotalDurationSeconds: null as number | null,
		addInputSampleItems: [] as DiscoveredItemSummary[],
		addInputFailureMessage: null as string | null,
		addInputUnsupported: false,
		addInputUniversalFilters: [] as UniversalFilterDeclaration[],
		addInputInputOptions: [] as InputOptionDeclaration[],
		addInputTitleRegexInclude: '',
		addInputTitleRegexExclude: '',
		addInputProviderOptions: {} as Record<string, boolean | string>,

		async init() {
			await this.refresh()
			this.loading = false

			this.onBroadcastTypeChanged()

			const link = new URLSearchParams(window.location.search).get('link')
			if (link) {
				window.history.replaceState({}, '', window.location.pathname)
				this.openAddInput()
				this.addInputSourceUri = link
				void this.submitAddInputPreflight()
			}

			if ('EventSource' in window) {
				const source = new EventSource('/api/v1/events')
				for (const type of EVENT_TYPES) {
					source.addEventListener(type, () => void this.refresh())
				}
			}
		},

		async refresh() {
			try {
				const [stashResponse, itemsResponse, inputsResponse, broadcastsResponse] = await Promise.all([
					apiFetch(`/api/v1/stashes/${stashId}`),
					apiFetch(`/api/v1/stashes/${stashId}/items`),
					apiFetch(`/api/v1/stashes/${stashId}/inputs`),
					apiFetch(`/api/v1/stashes/${stashId}/broadcasts`),
				])
				this.stash = (await stashResponse.json()).stash
				this.items = (await itemsResponse.json()).items
				this.inputs = (await inputsResponse.json()).inputs
				this.broadcasts = (await broadcastsResponse.json()).broadcasts
				this.error = null
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			}
		},

		async runBroadcastAction(broadcastId: string, action: 'rebuild' | 'verify' | 'prune' | 'rotate_token') {
			this.actionPending = `${broadcastId}:${action}`
			try {
				await apiFetch('/api/v1/commands', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						type: `broadcast.${action}`,
						options: { broadcast_id: broadcastId },
					}),
				})
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not run that action.'
			} finally {
				this.actionPending = null
			}
		},

		async copyFeedUrl(feedUrl: string) {
			await navigator.clipboard.writeText(feedUrl)
		},

		startEdit() {
			if (!this.stash) return
			this.editForm = {
				name: this.stash.name,
				description: this.stash.description ?? '',
				syncMode: this.stash.sync_mode,
				downloadPolicy: this.stash.download_policy,
				organizationMode: this.stash.organization_mode,
			}
			this.editingOpen = true
		},

		cancelEdit() {
			this.editingOpen = false
		},

		async saveEdit() {
			if (this.editForm.name.trim() === '') return
			this.savingEdit = true
			try {
				const response = await apiFetch(`/api/v1/stashes/${stashId}`, {
					method: 'PATCH',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						name: this.editForm.name.trim(),
						description: this.editForm.description.trim(),
						sync_mode: this.editForm.syncMode,
						download_policy: this.editForm.downloadPolicy,
						organization_mode: this.editForm.organizationMode,
					}),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not update that stash.'
					return
				}
				this.editingOpen = false
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.savingEdit = false
			}
		},

		async startDelete() {
			this.deletingOpen = true
			this.deleteImpact = null
			this.loadingDeleteImpact = true
			try {
				const response = await apiFetch(`/api/v1/stashes/${stashId}/delete-impact`)
				this.deleteImpact = (await response.json()).delete_impact
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not load delete impact.'
			} finally {
				this.loadingDeleteImpact = false
			}
		},

		cancelDelete() {
			this.deletingOpen = false
			this.deleteImpact = null
		},

		async confirmDelete() {
			this.deletingBusy = true
			try {
				await apiFetch(`/api/v1/stashes/${stashId}`, { method: 'DELETE' })
				window.location.assign('/stashes')
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not delete that stash.'
				this.deletingBusy = false
			}
		},

		broadcastPolicyMismatchMessage(): string | null {
			if (!this.stash || downloadPolicySatisfiesBroadcastType(this.stash.download_policy, this.newBroadcastType)) {
				return null
			}

			const policy = this.stash.download_policy.replace(/_/g, ' ')
			const type = this.newBroadcastType.replace(/_/g, ' ')
			return `This stash's "${policy}" download policy won't produce media for a "${type}" broadcast.`
		},

		compatibleDownloadPolicies(): string[] {
			return compatibleDownloadPolicies(this.newBroadcastType)
		},

		onBroadcastTypeChanged() {
			const compatible = compatibleDownloadPolicies(this.newBroadcastType)
			if (!compatible.includes(this.compatibleDownloadPolicyChoice)) {
				this.compatibleDownloadPolicyChoice = compatible[0] ?? 'video'
			}
		},

		async updateStashDownloadPolicyTo(policy: string) {
			this.updatingDownloadPolicy = true
			try {
				const response = await apiFetch(`/api/v1/stashes/${stashId}`, {
					method: 'PATCH',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ download_policy: policy }),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not update the download policy.'
					return
				}
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.updatingDownloadPolicy = false
			}
		},

		async createBroadcast() {
			if (this.newBroadcastName.trim() === '') return
			this.creatingBroadcast = true
			try {
				const response = await apiFetch(`/api/v1/stashes/${stashId}/broadcasts`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						type: this.newBroadcastType,
						name: this.newBroadcastName.trim(),
					}),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not create that broadcast.'
					return
				}
				this.newBroadcastName = ''
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.creatingBroadcast = false
			}
		},

		// Initializes a broadcast's season-mapping draft from its current
		// settings the first time it's rendered — never on refresh, so an
		// in-progress edit isn't clobbered by a background SSE-triggered refresh.
		ensureSeasonMappingDraft(broadcast: BroadcastSummary) {
			if (!this.isSeriesBroadcastType(broadcast.type)) return

			const existing = (broadcast.settings?.season_mapping ?? {}) as Record<string, number>
			const draft = this.seasonMappingDrafts[broadcast.id] ?? {}

			for (const input of this.inputs) {
				if (!(input.id in draft)) {
					draft[input.id] = existing[input.id] !== undefined ? String(existing[input.id]) : ''
				}
			}

			this.seasonMappingDrafts[broadcast.id] = draft
		},

		async saveSeasonMapping(broadcastId: string) {
			this.savingSeasonMapping = broadcastId
			try {
				const draft = this.seasonMappingDrafts[broadcastId] ?? {}
				const mapping: Record<string, number> = {}

				for (const [inputId, value] of Object.entries(draft)) {
					const trimmed = value.trim()
					if (trimmed === '') continue
					const season = Number.parseInt(trimmed, 10)
					if (Number.isFinite(season) && season >= 1) {
						mapping[inputId] = season
					}
				}

				const response = await apiFetch(`/api/v1/broadcasts/${broadcastId}/season-mapping`, {
					method: 'PATCH',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ mapping }),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not update the season mapping.'
					return
				}
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.savingSeasonMapping = null
			}
		},

		openAddInput() {
			this.addInputOpen = true
			this.addInputStep = 'paste'
			this.addInputSourceUri = ''
			this.addInputError = null
			this.addInputResolved = null
			this.addInputEstimatedItemCount = null
			this.addInputEstimatedTotalDurationSeconds = null
			this.addInputSampleItems = []
			this.addInputFailureMessage = null
			this.addInputUnsupported = false
			this.addInputUniversalFilters = []
			this.addInputInputOptions = []
			this.addInputTitleRegexInclude = ''
			this.addInputTitleRegexExclude = ''
			this.addInputProviderOptions = {}
		},

		cancelAddInput() {
			this.addInputOpen = false
			this.addInputStep = 'paste'
			this.addInputPreflightCommandId = null
			this.addInputCommitCommandId = null
		},

		async submitAddInputPreflight() {
			if (this.addInputSourceUri.trim() === '') return
			this.addInputSubmitting = true
			try {
				const response = await apiFetch('/api/v1/stashes/preflight', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						source_uri: this.addInputSourceUri.trim(),
						origin: 'web_ui',
					}),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.addInputError = body.error?.message ?? 'Could not start discovery.'
					return
				}
				const body = (await response.json()) as { command_id: string }
				this.addInputPreflightCommandId = body.command_id
				this.addInputError = null
				this.addInputStep = 'reviewing'
				awaitSseTerminal(() => this.checkAddInputPreflightTerminal())
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.addInputError = 'Could not reach the server.'
			} finally {
				this.addInputSubmitting = false
			}
		},

		async checkAddInputPreflightTerminal(): Promise<boolean> {
			try {
				const response = await apiFetch(`/api/v1/commands/${this.addInputPreflightCommandId}`)
				const body = (await response.json()) as CommandShowResponse

				if (body.command.state === 'completed') {
					const review = await apiFetch(`/api/v1/stashes/preflight/${this.addInputPreflightCommandId}/review`)
					const reviewBody = (await review.json()) as PreflightReview
					const discovery = reviewBody.preflight?.discovery ?? null
					this.addInputResolved = reviewBody.preflight?.resolved_input ?? null
					this.addInputEstimatedItemCount = discovery?.estimated_item_count ?? null
					this.addInputEstimatedTotalDurationSeconds = discovery?.estimated_total_duration_seconds ?? null
					this.addInputSampleItems = discovery?.sample_items ?? []
					this.addInputUniversalFilters = reviewBody.preflight?.universal_filters ?? []
					this.addInputInputOptions = reviewBody.preflight?.input_options ?? []
					this.addInputProviderOptions = Object.fromEntries(
						this.addInputInputOptions.map((option) => [option.key, option.default]),
					)
					this.addInputStep = 'review'
					return true
				}

				if (body.command.state === 'failed' || body.command.state === 'rejected') {
					const lastError = body.jobs[0]?.last_error ?? 'Discovery failed.'
					this.addInputUnsupported = lastError.startsWith('unsupported_provider_url:')
					this.addInputFailureMessage = lastError
					this.addInputStep = 'failed'
					return true
				}

				return false
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return true
				this.addInputFailureMessage = 'Could not reach the server.'
				this.addInputStep = 'failed'
				return true
			}
		},

		async confirmAddInput() {
			if (!this.addInputPreflightCommandId) return
			this.addInputSubmitting = true
			try {
				const response = await apiFetch(`/api/v1/stashes/${stashId}/inputs`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						preflight_command_id: this.addInputPreflightCommandId,
						options: {
							title_regex_include: this.addInputTitleRegexInclude.trim() || undefined,
							title_regex_exclude: this.addInputTitleRegexExclude.trim() || undefined,
							provider: this.addInputProviderOptions,
						},
					}),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.addInputError = body.error?.message ?? 'Could not add that input.'
					return
				}
				const body = (await response.json()) as { command_id: string }
				this.addInputCommitCommandId = body.command_id
				this.addInputError = null
				this.addInputStep = 'committing'
				awaitSseTerminal(() => this.checkAddInputCommitTerminal())
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.addInputError = 'Could not reach the server.'
			} finally {
				this.addInputSubmitting = false
			}
		},

		async checkAddInputCommitTerminal(): Promise<boolean> {
			try {
				const response = await apiFetch(`/api/v1/commands/${this.addInputCommitCommandId}`)
				const body = (await response.json()) as CommandShowResponse

				if (body.command.state === 'completed') {
					this.addInputOpen = false
					this.addInputStep = 'paste'
					this.addInputPreflightCommandId = null
					this.addInputCommitCommandId = null
					await this.refresh()
					return true
				}

				if (body.command.state === 'failed' || body.command.state === 'rejected') {
					this.addInputFailureMessage = body.jobs[0]?.last_error ?? 'Adding the input failed.'
					this.addInputStep = 'failed'
					return true
				}

				return false
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return true
				this.addInputFailureMessage = 'Could not reach the server.'
				this.addInputStep = 'failed'
				return true
			}
		},
	}
}

function vaultComponent() {
	return {
		loading: true,
		error: null as string | null,
		items: [] as MediaItemSummary[],
		statusBadge,
		formatDuration,
		formatRelativeTime,

		async init() {
			await this.refresh()
			this.loading = false
		},

		async refresh() {
			try {
				const response = await apiFetch('/api/v1/items')
				this.items = (await response.json()).items
				this.error = null
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			}
		},
	}
}

function vaultDetailComponent(itemId: string) {
	return {
		loading: true,
		error: null as string | null,
		item: null as MediaItemSummary | null,
		assets: [] as AssetSummary[],
		stashes: [] as StashSummary[],
		broadcasts: [] as BroadcastSummary[],
		statusBadge,
		formatBytes,
		formatDuration,
		formatRelativeTime,

		async init() {
			await this.refresh()
			this.loading = false

			if ('EventSource' in window) {
				const source = new EventSource('/api/v1/events')
				for (const type of EVENT_TYPES) {
					source.addEventListener(type, () => void this.refresh())
				}
			}
		},

		async refresh() {
			try {
				const [itemResponse, assetsResponse, stashesResponse, broadcastsResponse] = await Promise.all([
					apiFetch(`/api/v1/items/${itemId}`),
					apiFetch(`/api/v1/items/${itemId}/assets`),
					apiFetch(`/api/v1/items/${itemId}/stashes`),
					apiFetch(`/api/v1/items/${itemId}/broadcasts`),
				])
				this.item = (await itemResponse.json()).item
				this.assets = (await assetsResponse.json()).assets
				this.stashes = (await stashesResponse.json()).stashes
				this.broadcasts = (await broadcastsResponse.json()).broadcasts
				this.error = null
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			}
		},
	}
}

function settingsComponent() {
	return {
		loading: true,
		error: null as string | null,
		me: null as { id: string; email: string; username: string; role: string } | null,
		health: null as HealthReport | null,
		tokens: [] as ApiTokenSummary[],
		mediaServers: [] as MediaServerSummary[],

		newTokenName: '',
		creatingToken: false,
		justCreatedToken: null as string | null,

		newMediaServerType: 'jellyfin',
		newMediaServerName: '',
		newMediaServerBaseUri: '',
		newMediaServerToken: '',
		creatingMediaServer: false,

		youtubeApiKey: { configured: false } as YoutubeApiKeyStatus,
		editingYoutubeApiKey: false,
		newYoutubeApiKey: '',
		savingYoutubeApiKey: false,

		testingId: null as string | null,
		testResults: {} as Record<string, { ok: boolean; message: string; server_name?: string; version?: string }>,

		statusBadge,
		formatRelativeTime,

		async init() {
			await this.refresh()
			this.loading = false
		},

		async refresh() {
			try {
				const [meResponse, healthResponse, tokensResponse, mediaServersResponse, youtubeApiKeyResponse] = await Promise.all([
					apiFetch('/api/v1/auth/me'),
					apiFetch('/api/v1/system/health'),
					apiFetch('/api/v1/auth/tokens'),
					apiFetch('/api/v1/media-servers'),
					apiFetch('/api/v1/providers/youtube/credentials'),
				])
				this.me = (await meResponse.json()).user
				this.health = await healthResponse.json()
				this.tokens = (await tokensResponse.json()).tokens
				this.mediaServers = (await mediaServersResponse.json()).media_servers
				this.youtubeApiKey = await youtubeApiKeyResponse.json()
				this.error = null
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			}
		},

		async createToken() {
			if (this.newTokenName.trim() === '') return
			this.creatingToken = true
			try {
				const response = await apiFetch('/api/v1/auth/tokens', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ name: this.newTokenName.trim() }),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not create that token.'
					return
				}
				const body = (await response.json()) as { token: string }
				this.justCreatedToken = body.token
				this.newTokenName = ''
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.creatingToken = false
			}
		},

		async revokeToken(id: string) {
			if (!window.confirm('Revoke this token? Anything using it will stop working immediately.')) return
			try {
				await apiFetch(`/api/v1/auth/tokens/${id}`, { method: 'DELETE' })
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not revoke that token.'
			}
		},

		async copyToken(token: string) {
			await navigator.clipboard.writeText(token)
		},

		async createMediaServer() {
			if (this.newMediaServerName.trim() === '' || this.newMediaServerBaseUri.trim() === '') return
			this.creatingMediaServer = true
			try {
				const response = await apiFetch('/api/v1/media-servers', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						type: this.newMediaServerType,
						name: this.newMediaServerName.trim(),
						base_uri: this.newMediaServerBaseUri.trim(),
						token: this.newMediaServerToken.trim() || undefined,
					}),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not create that media server connection.'
					return
				}
				this.newMediaServerName = ''
				this.newMediaServerBaseUri = ''
				this.newMediaServerToken = ''
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.creatingMediaServer = false
			}
		},

		async testMediaServer(id: string) {
			this.testingId = id
			try {
				const response = await apiFetch(`/api/v1/media-servers/${id}/test`, { method: 'POST' })
				const body = (await response.json()) as { status: { ok: boolean; message: string; server_name?: string; version?: string } }
				this.testResults = { ...this.testResults, [id]: body.status }
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not test that connection.'
			} finally {
				this.testingId = null
			}
		},

		async deleteMediaServer(id: string) {
			if (!window.confirm('Delete this media server connection?')) return
			try {
				await apiFetch(`/api/v1/media-servers/${id}`, { method: 'DELETE' })
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not delete that connection.'
			}
		},

		async saveYoutubeApiKey() {
			if (this.newYoutubeApiKey.trim() === '') return
			this.savingYoutubeApiKey = true
			try {
				const response = await apiFetch('/api/v1/providers/youtube/credentials', {
					method: 'PUT',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ api_key: this.newYoutubeApiKey.trim() }),
				})
				if (!response.ok) {
					const body = (await response.json()) as { error?: { message?: string } }
					this.error = body.error?.message ?? 'Could not save that key.'
					return
				}
				this.newYoutubeApiKey = ''
				this.editingYoutubeApiKey = false
				this.error = null
				await this.refresh()
			} catch (cause) {
				if (cause instanceof UnauthenticatedError) return
				this.error = 'Could not reach the server.'
			} finally {
				this.savingYoutubeApiKey = false
			}
		},
	}
}

Alpine.data('dashboard', dashboardComponent)
Alpine.data('activity', activityComponent)
Alpine.data('stashes', stashesComponent)
Alpine.data('stashDetail', stashDetailComponent)
Alpine.data('vault', vaultComponent)
Alpine.data('vaultDetail', vaultDetailComponent)
Alpine.data('settings', settingsComponent)

window.Alpine = Alpine
Alpine.start()

document.addEventListener('DOMContentLoaded', () => {
	markActiveNav()
	wireLogout()
	void enforceAuth()
})
