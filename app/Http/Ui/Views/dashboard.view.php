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
								<th class="px-4 py-2 font-normal">Disk usage</th>
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
									<td class="px-4 py-2 text-muted">
										<span x-show="location.total_bytes !== null">
											<span x-text="formatBytes(location.total_bytes - location.free_bytes)"></span> used of <span x-text="formatBytes(location.total_bytes)"></span>
										</span>
										<span x-show="location.total_bytes === null">—</span>
									</td>
								</tr>
							</template>
						</tbody>
						<tfoot x-show="totalDiskBytes() !== null">
							<tr class="border-t border-line text-cream">
								<td class="px-4 py-2 font-semibold" colspan="4">Total</td>
								<td class="px-4 py-2 font-semibold">
									<span x-text="formatBytes(totalDiskBytes() - totalFreeBytes())"></span> used of <span x-text="formatBytes(totalDiskBytes())"></span>
								</td>
							</tr>
						</tfoot>
					</table>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="(health?.storage?.locations ?? []).length === 0">
						No storage locations reported.
					</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60">
					<h2 class="border-b border-line px-4 py-3 text-[13px] font-semibold text-cream">Recent media activity</h2>
					<ul class="divide-y divide-line/60" x-show="activitySummary.length > 0">
						<template x-for="group in activitySummary" x-bind:key="group.type + ':' + group.stash_id + ':' + group.created_at">
							<li class="flex items-start justify-between gap-3 px-4 py-3">
								<div>
									<p class="text-[13px]" x-bind:class="group.level === 'error' ? 'text-error' : (group.level === 'warning' ? 'text-warn' : 'text-cream')" x-text="group.message"></p>
									<p class="mt-0.5 text-[12px] text-muted" x-show="group.count > 1">+<span x-text="group.count - 1"></span> more</p>
								</div>
								<span class="shrink-0 text-[12px] text-muted" x-text="formatRelativeTime(group.created_at)"></span>
							</li>
						</template>
					</ul>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="activitySummary.length === 0">No activity yet.</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60 p-4">
					<h2 class="mb-2 text-[13px] font-semibold text-cream">Stashes &amp; Vault</h2>
					<div class="flex gap-6">
						<a href="/stashes" class="text-[13px] text-muted transition-colors hover:text-cream">
							<span class="text-lg font-semibold text-cream" x-text="stashCount ?? '—'"></span> stashes
						</a>
						<a href="/vault" class="text-[13px] text-muted transition-colors hover:text-cream">
							<span class="text-lg font-semibold text-cream" x-text="vaultCount ?? '—'"></span> vault items
						</a>
					</div>
				</section>
			</div>
		</template>
	</div>
</x-stashd-layout>
