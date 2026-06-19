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
}

function statusBadge(state: string | null | undefined): Badge {
	if (!state) return { label: '—', dot: 'bg-muted', text: 'text-muted' }
	return STATE_BADGES[state] ?? { label: state, dot: 'bg-muted', text: 'text-muted' }
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

// The full, fixed set of named SSE events EventsController ever emits
// (app/System/Event/EventPublisher.php) — EventSource has no "any event"
// listener, so every name has to be wired up individually.
const EVENT_TYPES = ['job.created', 'job.progress', 'job.completed', 'job.failed', 'activity.created'] as const

interface StorageLocation {
	key: string
	path: string
	state: string
	readable: boolean
	writable: boolean
	supports_hardlinks: boolean
	last_error: string | null
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

function dashboardComponent() {
	return {
		loading: true,
		error: null as string | null,
		health: null as HealthReport | null,
		jobs: [] as JobSummary[],
		formatRelativeTime,
		statusBadge,

		jobElapsed(job: JobSummary): string {
			const start = job.started_at ?? job.created_at
			const end = job.finished_at ?? new Date().toISOString()
			return formatDuration((new Date(end).getTime() - new Date(start).getTime()) / 1000)
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
				const [healthResponse, jobsResponse] = await Promise.all([
					apiFetch('/api/v1/system/health'),
					apiFetch('/api/v1/jobs'),
				])
				this.health = await healthResponse.json()
				this.jobs = (await jobsResponse.json()).jobs
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
		formatRelativeTime,
		eventBadge,
		summarize: summarizeEvent,

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

Alpine.data('dashboard', dashboardComponent)
Alpine.data('activity', activityComponent)

window.Alpine = Alpine
Alpine.start()

document.addEventListener('DOMContentLoaded', () => {
	markActiveNav()
	wireLogout()
	void enforceAuth()
})
