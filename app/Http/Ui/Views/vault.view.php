<x-stashd-layout title="Vault · stashd_">
	<div x-data="vault">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">Vault</h1>
			<p class="text-[13px] text-error" x-show="error" x-text="error"></p>
		</div>

		<template x-if="loading">
			<p class="text-[13px] text-muted">Loading…</p>
		</template>

		<template x-if="!loading">
			<section class="rounded-lg border border-line bg-panel/60">
				<table class="w-full text-left text-[13px]" x-show="items.length > 0">
					<thead>
						<tr class="text-[11px] uppercase tracking-wide text-muted">
							<th class="px-4 py-2 font-normal"></th>
							<th class="px-4 py-2 font-normal">Title</th>
							<th class="px-4 py-2 font-normal">State</th>
							<th class="px-4 py-2 font-normal">Duration</th>
							<th class="px-4 py-2 font-normal">Published</th>
						</tr>
					</thead>
					<tbody>
						<template x-for="item in items" x-bind:key="item.id">
							<tr class="border-t border-line/60">
								<td class="px-4 py-2">
									<template x-if="item.thumbnail_uri">
										<img class="h-10 w-16 rounded object-cover" x-bind:src="item.thumbnail_uri" loading="lazy" alt=""/>
									</template>
								</td>
								<td class="px-4 py-2">
									<a class="text-cream transition-colors hover:text-amber" x-bind:href="'/vault/' + item.id" x-text="item.title"></a>
								</td>
								<td class="px-4 py-2">
									<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(item.state).text">
										<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(item.state).dot"></span>
										<span x-text="item.state"></span>
									</span>
								</td>
								<td class="px-4 py-2 text-muted" x-text="formatDuration(item.duration_seconds)"></td>
								<td class="px-4 py-2 text-muted" x-text="formatRelativeTime(item.published_at)"></td>
							</tr>
						</template>
					</tbody>
				</table>
				<p class="px-4 py-3 text-[13px] text-muted" x-show="items.length === 0">No vault items yet.</p>
			</section>
		</template>
	</div>
</x-stashd-layout>
