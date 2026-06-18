<?php
/**
 * Stashd dashboard shell.
 *
 * Server-rendered HTML frame for every authenticated UI page. Holds no data of
 * its own — pages hydrate client-side via the bearer cookie against /api/v1.
 * See docs/Stashd-Branding-Plan.md for the calm, dense, warm-dark direction.
 *
 * @var string|null $title The page title (suffixed with the wordmark).
 */
?>
<!doctype html>
<html lang="en" class="h-dvh">
<head>
	<meta charset="UTF-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
	<meta name="color-scheme" content="dark"/>
	<title>{{ $title ?? 'stashd_' }}</title>
	<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%231c1714'/><rect x='8' y='22' width='16' height='3' rx='1.5' fill='%23d99a4e'/></svg>"/>
	<x-vite-tags entrypoint="src/main.entrypoint.ts"/>
	<x-slot name="head"/>
</head>
<body class="min-h-full bg-espresso text-cream font-mono antialiased text-sm">
	<header class="border-b border-line bg-panel/60">
		<div class="mx-auto flex max-w-7xl items-center gap-8 px-5 py-3">
			<a href="/" class="text-lg font-semibold tracking-tight text-cream">stashd<span class="brand-underscore">_</span></a>
			<nav class="flex items-center gap-5 text-[13px]">
				<a href="/" data-nav="/" class="border-b-2 border-transparent pb-0.5 text-muted transition-colors hover:text-cream">Dashboard</a>
				<a href="/stashes" data-nav="/stashes" class="border-b-2 border-transparent pb-0.5 text-muted transition-colors hover:text-cream">Stashes</a>
				<a href="/vault" data-nav="/vault" class="border-b-2 border-transparent pb-0.5 text-muted transition-colors hover:text-cream">Vault</a>
				<a href="/activity" data-nav="/activity" class="border-b-2 border-transparent pb-0.5 text-muted transition-colors hover:text-cream">Activity</a>
				<a href="/settings" data-nav="/settings" class="border-b-2 border-transparent pb-0.5 text-muted transition-colors hover:text-cream">Settings</a>
			</nav>
			<button type="button" data-logout class="ml-auto text-[13px] text-muted transition-colors hover:text-amber">log out</button>
		</div>
	</header>

	<main class="mx-auto max-w-7xl px-5 py-6">
		<x-slot/>
	</main>

	<x-slot name="scripts"/>
</body>
</html>
