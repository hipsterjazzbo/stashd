<x-stashd-layout title="Dashboard · stashd_">
	<div x-data="dashboard">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">Dashboard</h1>
			<p class="text-[13px] text-error" x-show="error" x-text="error"></p>
		</div>

		<template x-if="loading">
			<p class="flex items-center gap-2 text-[13px] text-muted">
				<span class="h-1.5 w-1.5 rounded-full bg-amber pulse-dot"></span>
				Loading…
			</p>
		</template>

		<template x-if="!loading">
			<div class="space-y-6">
				<section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
					<div class="rounded-lg border border-line bg-panel/60 p-4">
						<p class="text-[11px] uppercase tracking-wide text-muted">Status</p>
						<p class="mt-1 flex items-center gap-2 text-sm font-semibold" x-bind:class="statusBadge(health?.status).text">
							<span class="h-1.5 w-1.5 rounded-full" x-bind:class="[statusBadge(health?.status).dot, statusBadge(health?.status).pulse ? 'pulse-dot' : '']"></span>
							<span x-text="health?.status ?? '—'"></span>
						</p>
					</div>
					<div class="rounded-lg border border-line bg-panel/60 p-4">
						<p class="text-[11px] uppercase tracking-wide text-muted">Database</p>
						<p class="mt-1 text-sm font-semibold"
							x-bind:class="health?.database?.writable ? 'text-success' : 'text-error'"
							x-text="health ? (health.database.writable ? 'writable' : 'not writable') : '—'"></p>
					</div>
					<div class="rounded-lg border border-line bg-panel/60 p-4">
						<p class="text-[11px] uppercase tracking-wide text-muted">Storage</p>
						<p class="mt-1 text-sm font-semibold"
							x-bind:class="health?.storage?.ready ? 'text-success' : 'text-error'"
							x-text="health ? (health.storage.ready ? 'ready' : 'not ready') : '—'"></p>
					</div>
					<div class="rounded-lg border border-line bg-panel/60 p-4">
						<p class="text-[11px] uppercase tracking-wide text-muted">Hardlinks</p>
						<p class="mt-1 text-sm font-semibold"
							x-bind:class="health?.storage?.vault_broadcast_hardlink ? 'text-success' : 'text-warn'"
							x-text="health ? (health.storage.vault_broadcast_hardlink ? 'supported' : 'unsupported') : '—'"></p>
					</div>
				</section>

				<section class="rounded-lg border border-line bg-panel/60">
					<h2 class="border-b border-line px-4 py-3 text-[13px] font-semibold text-cream">Storage locations</h2>
					<table class="w-full text-left text-[13px]">
						<thead>
							<tr class="text-[11px] uppercase tracking-wide text-muted">
								<th class="px-4 py-2 font-normal">Location</th>
								<th class="px-4 py-2 font-normal">Path</th>
								<th class="px-4 py-2 font-normal">State</th>
								<th class="px-4 py-2 font-normal">Hardlinks</th>
							</tr>
						</thead>
						<tbody>
							<template x-for="location in health?.storage?.locations ?? []" x-bind:key="location.key">
								<tr class="border-t border-line/60">
									<td class="px-4 py-2 text-muted" x-text="location.key"></td>
									<td class="px-4 py-2 text-cream" x-text="location.path"></td>
									<td class="px-4 py-2">
										<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(location.state).text">
											<span class="h-1.5 w-1.5 rounded-full" x-bind:class="[statusBadge(location.state).dot, statusBadge(location.state).pulse ? 'pulse-dot' : '']"></span>
											<span x-text="location.state"></span>
										</span>
									</td>
									<td class="px-4 py-2 text-muted" x-text="location.supports_hardlinks ? 'supported' : 'unsupported'"></td>
								</tr>
							</template>
						</tbody>
					</table>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="(health?.storage?.locations ?? []).length === 0">
						No storage locations reported.
					</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60">
					<h2 class="border-b border-line px-4 py-3 text-[13px] font-semibold text-cream">Recent jobs</h2>
					<table class="w-full text-left text-[13px]" x-show="jobs.length > 0">
						<thead>
							<tr class="text-[11px] uppercase tracking-wide text-muted">
								<th class="px-4 py-2 font-normal">ID</th>
								<th class="px-4 py-2 font-normal">Intent</th>
								<th class="px-4 py-2 font-normal">State</th>
								<th class="px-4 py-2 font-normal">Progress</th>
								<th class="px-4 py-2 font-normal">Elapsed</th>
							</tr>
						</thead>
						<tbody>
							<template x-for="job in jobs" x-bind:key="job.id">
								<tr class="border-t border-line/60 align-top">
									<td class="px-4 py-2 text-muted" x-text="job.id"></td>
									<td class="px-4 py-2 text-cream" x-text="job.intent.replace(/_/g, ' ')"></td>
									<td class="px-4 py-2">
										<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(job.state).text">
											<span class="h-1.5 w-1.5 rounded-full" x-bind:class="[statusBadge(job.state).dot, statusBadge(job.state).pulse ? 'pulse-dot' : '']"></span>
											<span x-text="job.state"></span>
										</span>
										<p class="mt-1 text-[12px] text-error" x-show="job.last_error" x-text="job.last_error"></p>
									</td>
									<td class="px-4 py-2 text-muted"
										x-text="job.progress_percent === null ? '—' : (job.progress_label ?? '') + ' ' + job.progress_percent + '%'"></td>
									<td class="px-4 py-2 text-muted" x-text="jobElapsed(job)"></td>
								</tr>
							</template>
						</tbody>
					</table>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="jobs.length === 0">No jobs yet.</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60 p-4">
					<h2 class="mb-1 text-[13px] font-semibold text-cream">Stashes &amp; Vault</h2>
					<p class="text-[13px] text-muted">Counts arrive once the Stash list API lands.</p>
				</section>
			</div>
		</template>
	</div>
</x-stashd-layout>
