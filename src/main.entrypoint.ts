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

window.Alpine = Alpine
Alpine.start()

document.addEventListener('DOMContentLoaded', () => {
	markActiveNav()
	wireLogout()
})
