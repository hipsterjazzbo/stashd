<?php
/** @var string $id */
?>
<x-stashd-layout title="Stash · stashd_">
	<div x-data="stashDetail('<?= htmlspecialchars($id) ?>')">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">Stash</h1>
			<p class="text-[13px] text-error" x-show="error" x-text="error"></p>
		</div>

		<template x-if="loading">
			<p class="text-[13px] text-muted">Loading…</p>
		</template>

		<template x-if="!loading">
			<div class="space-y-6">
				<section class="rounded-lg border border-line bg-panel/60 p-4">
					<div class="flex items-center justify-between gap-3">
						<h2 class="text-sm font-semibold text-cream" x-text="stash?.name"></h2>
						<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(stash?.state).text">
							<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(stash?.state).dot"></span>
							<span x-text="stash?.state"></span>
						</span>
					</div>
					<p class="mt-1 font-mono text-[12px] text-muted" x-text="stash?.slug"></p>
					<p class="mt-2 text-[13px] text-muted">
						<span x-text="stash?.sync_mode"></span>
						·
						<span x-text="stash?.download_policy.replace(/_/g, ' ')"></span>
						·
						<span x-text="stash?.organization_mode"></span>
					</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60">
					<h2 class="border-b border-line px-4 py-3 text-[13px] font-semibold text-cream">Inputs</h2>
					<table class="w-full text-left text-[13px]" x-show="inputs.length > 0">
						<thead>
							<tr class="text-[11px] uppercase tracking-wide text-muted">
								<th class="px-4 py-2 font-normal">Title</th>
								<th class="px-4 py-2 font-normal">Source</th>
								<th class="px-4 py-2 font-normal">State</th>
								<th class="px-4 py-2 font-normal">Sync</th>
							</tr>
						</thead>
						<tbody>
							<template x-for="input in inputs" x-bind:key="input.id">
								<tr class="border-t border-line/60">
									<td class="px-4 py-2 text-cream" x-text="input.title ?? input.provider_input_id"></td>
									<td class="px-4 py-2 font-mono text-muted" x-text="input.source_uri"></td>
									<td class="px-4 py-2">
										<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(input.state).text">
											<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(input.state).dot"></span>
											<span x-text="input.state"></span>
										</span>
									</td>
									<td class="px-4 py-2 text-muted" x-text="input.sync_mode ?? '—'"></td>
								</tr>
							</template>
						</tbody>
					</table>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="inputs.length === 0">No inputs yet.</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60">
					<h2 class="border-b border-line px-4 py-3 text-[13px] font-semibold text-cream">Items</h2>
					<table class="w-full text-left text-[13px]" x-show="items.length > 0">
						<thead>
							<tr class="text-[11px] uppercase tracking-wide text-muted">
								<th class="px-4 py-2 font-normal">Item</th>
								<th class="px-4 py-2 font-normal">State</th>
							</tr>
						</thead>
						<tbody>
							<template x-for="item in items" x-bind:key="item.id">
								<tr class="border-t border-line/60">
									<td class="px-4 py-2">
										<a class="text-cream transition-colors hover:text-amber" x-bind:href="'/vault/' + item.media_item_id" x-text="item.display_title ?? item.media_item_id"></a>
									</td>
									<td class="px-4 py-2">
										<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(item.state).text">
											<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(item.state).dot"></span>
											<span x-text="item.state"></span>
										</span>
									</td>
								</tr>
							</template>
						</tbody>
					</table>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="items.length === 0">No items yet.</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60">
					<h2 class="border-b border-line px-4 py-3 text-[13px] font-semibold text-cream">Broadcasts</h2>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="broadcasts.length === 0">No broadcasts yet.</p>
					<ul class="divide-y divide-line/60" x-show="broadcasts.length > 0">
						<template x-for="broadcast in broadcasts" x-bind:key="broadcast.id">
							<li class="px-4 py-3">
								<div class="flex items-center justify-between gap-3">
									<div>
										<p class="text-[13px] font-semibold text-cream" x-text="broadcast.name"></p>
										<p class="text-[12px] text-muted" x-text="broadcast.type.replace(/_/g, ' ')"></p>
									</div>
									<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(broadcast.state).text">
										<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(broadcast.state).dot"></span>
										<span x-text="broadcast.state"></span>
									</span>
								</div>

								<p class="mt-1 text-[12px] text-error" x-show="broadcast.last_error" x-text="broadcast.last_error"></p>

								<div class="mt-2 flex flex-wrap gap-2">
									<button type="button"
										class="rounded border border-line px-2 py-1 text-[12px] text-muted transition-colors hover:text-cream disabled:opacity-50"
										x-bind:disabled="actionPending === broadcast.id + ':rebuild'"
										x-on:click="runBroadcastAction(broadcast.id, 'rebuild')">rebuild</button>
									<button type="button"
										class="rounded border border-line px-2 py-1 text-[12px] text-muted transition-colors hover:text-cream disabled:opacity-50"
										x-bind:disabled="actionPending === broadcast.id + ':verify'"
										x-on:click="runBroadcastAction(broadcast.id, 'verify')">verify</button>
									<button type="button"
										class="rounded border border-line px-2 py-1 text-[12px] text-muted transition-colors hover:text-cream disabled:opacity-50"
										x-bind:disabled="actionPending === broadcast.id + ':prune'"
										x-on:click="runBroadcastAction(broadcast.id, 'prune')">prune</button>
									<button type="button"
										class="rounded border border-line px-2 py-1 text-[12px] text-muted transition-colors hover:text-cream disabled:opacity-50"
										x-show="broadcast.feed_url"
										x-bind:disabled="actionPending === broadcast.id + ':rotate_token'"
										x-on:click="runBroadcastAction(broadcast.id, 'rotate_token')">rotate token</button>
								</div>

								<div class="mt-3 rounded border border-line bg-espresso p-2" x-show="broadcast.feed_url">
									<p class="text-[11px] uppercase tracking-wide text-muted">Private feed URL</p>
									<div class="mt-1 flex items-center gap-2">
										<code class="flex-1 overflow-x-auto text-[12px] text-cream" x-text="broadcast.feed_url"></code>
										<button type="button"
											class="shrink-0 rounded border border-line px-2 py-1 text-[12px] text-muted transition-colors hover:text-cream"
											x-on:click="copyFeedUrl(broadcast.feed_url)">copy</button>
									</div>
									<p class="mt-1 text-[12px] text-warn">Anyone with this link can listen — treat it like a password.</p>
								</div>
							</li>
						</template>
					</ul>

					<div class="flex items-center gap-2 border-t border-line p-4">
						<select x-model="newBroadcastType"
							class="rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber">
							<option value="filesystem_series">Filesystem series</option>
							<option value="jellyfin_series">Jellyfin series</option>
							<option value="plex_series">Plex series</option>
							<option value="audio_podcast">Audio podcast</option>
							<option value="video_podcast">Video podcast</option>
						</select>
						<input type="text" x-model="newBroadcastName" placeholder="Broadcast name"
							class="flex-1 rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
						<button type="button" x-on:click="createBroadcast()"
							x-bind:disabled="creatingBroadcast || newBroadcastName.trim() === ''"
							class="shrink-0 rounded bg-amber px-3 py-2 text-[13px] font-semibold text-espresso transition-colors hover:bg-amber-dim disabled:opacity-60">
							Add broadcast
						</button>
					</div>
				</section>
			</div>
		</template>
	</div>
</x-stashd-layout>
