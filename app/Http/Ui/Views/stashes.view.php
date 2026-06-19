<x-stashd-layout title="Stashes · stashd_">
	<div x-data="stashes">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">Stashes</h1>
			<div class="flex items-center gap-4">
				<p class="text-[13px] text-error" x-show="error" x-text="error"></p>
				<a href="/stashes/new"
					class="rounded bg-amber px-3 py-1.5 text-[13px] font-semibold text-espresso transition-colors hover:bg-amber-dim">
					+ New stash
				</a>
			</div>
		</div>

		<template x-if="loading">
			<p class="text-[13px] text-muted">Loading…</p>
		</template>

		<template x-if="!loading">
			<section class="rounded-lg border border-line bg-panel/60">
				<table class="w-full text-left text-[13px]" x-show="stashes.length > 0">
					<thead>
						<tr class="text-[11px] uppercase tracking-wide text-muted">
							<th class="px-4 py-2 font-normal">Name</th>
							<th class="px-4 py-2 font-normal">Slug</th>
							<th class="px-4 py-2 font-normal">State</th>
							<th class="px-4 py-2 font-normal">Sync</th>
							<th class="px-4 py-2 font-normal">Download</th>
							<th class="px-4 py-2 font-normal">Organization</th>
						</tr>
					</thead>
					<tbody>
						<template x-for="stash in stashes" x-bind:key="stash.id">
							<tr class="border-t border-line/60">
								<td class="px-4 py-2">
									<a class="text-cream transition-colors hover:text-amber" x-bind:href="'/stashes/' + stash.id" x-text="stash.name"></a>
								</td>
								<td class="px-4 py-2 font-mono text-muted" x-text="stash.slug"></td>
								<td class="px-4 py-2">
									<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(stash.state).text">
										<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(stash.state).dot"></span>
										<span x-text="stash.state"></span>
									</span>
								</td>
								<td class="px-4 py-2 text-muted" x-text="stash.sync_mode"></td>
								<td class="px-4 py-2 text-muted" x-text="stash.download_policy.replace(/_/g, ' ')"></td>
								<td class="px-4 py-2 text-muted" x-text="stash.organization_mode"></td>
							</tr>
						</template>
					</tbody>
				</table>
				<p class="px-4 py-3 text-[13px] text-muted" x-show="stashes.length === 0">No stashes yet.</p>
			</section>
		</template>
	</div>
</x-stashd-layout>
