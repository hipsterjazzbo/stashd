<?php
/**
 * Pre-auth login / first-run admin setup page.
 *
 * Standalone shell (no dashboard nav). Posts to the JSON auth API; on success
 * the server sets the HttpOnly session cookie and we redirect to the dashboard.
 * Plain vanilla JS keeps this one page free of Alpine/Tempest attribute
 * interplay.
 *
 * @var bool $setupRequired Whether the admin account still needs creating.
 */
?>
<!doctype html>
<html lang="en" class="h-dvh">
<head>
	<meta charset="UTF-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
	<meta name="color-scheme" content="dark"/>
	<title>{{ $setupRequired ? 'Set up · stashd_' : 'Sign in · stashd_' }}</title>
	<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%231c1714'/><rect x='8' y='22' width='16' height='3' rx='1.5' fill='%23d99a4e'/></svg>"/>
	<x-vite-tags entrypoint="src/main.entrypoint.ts"/>
</head>
<body class="min-h-full bg-espresso text-cream font-mono antialiased text-sm flex items-center justify-center px-5">
	<main class="w-full max-w-sm">
		<div class="mb-6 text-center">
			<div class="text-2xl font-semibold tracking-tight">stashd<span class="brand-underscore">_</span></div>
			<p class="mt-2 text-muted text-[13px]">
				{{ $setupRequired ? 'Create the admin account.' : 'Because the internet forgets.' }}
			</p>
		</div>

		<form id="auth-form" class="space-y-3 rounded-lg border border-line bg-panel/60 p-5">
			<div id="auth-error" class="hidden rounded border border-error/50 bg-error/10 px-3 py-2 text-[13px] text-error"></div>

			<label class="block">
				<span class="mb-1 block text-[12px] text-muted">Username</span>
				<input name="username" type="text" autocomplete="username" required
					class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
			</label>

			<label class="block">
				<span class="mb-1 block text-[12px] text-muted">Password</span>
				<input name="password" type="password" autocomplete="{{ $setupRequired ? 'new-password' : 'current-password' }}" required
					class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
			</label>

			<button type="submit" id="auth-submit"
				class="w-full rounded bg-amber px-3 py-2 font-semibold text-espresso transition-colors hover:bg-amber-dim disabled:opacity-60">
				{{ $setupRequired ? 'Create admin' : 'Sign in' }}
			</button>
		</form>
	</main>

	<script>
		(function () {
			const setupRequired = {{ $setupRequired ? 'true' : 'false' }};
			const form = document.getElementById('auth-form');
			const errorBox = document.getElementById('auth-error');
			const submit = document.getElementById('auth-submit');

			form.addEventListener('submit', async function (event) {
				event.preventDefault();
				errorBox.classList.add('hidden');
				submit.disabled = true;

				const data = Object.fromEntries(new FormData(form).entries());
				const path = setupRequired ? '/api/v1/auth/setup' : '/api/v1/auth/login';

				try {
					const res = await fetch(path, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(data),
					});

					if (res.ok) {
						window.location.assign('/');
						return;
					}

					const payload = await res.json().catch(() => ({}));
					errorBox.textContent = payload?.error?.message ?? 'Something went wrong.';
					errorBox.classList.remove('hidden');
				} catch (_error) {
					errorBox.textContent = 'Could not reach the server.';
					errorBox.classList.remove('hidden');
				} finally {
					submit.disabled = false;
				}
			});
		})();
	</script>
</body>
</html>
