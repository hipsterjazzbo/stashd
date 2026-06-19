<?php
/** @var string $id */
?>
<x-stashd-layout title="Vault item · stashd_">
	<div x-data="vaultDetail('<?= htmlspecialchars($id) ?>')">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">Vault item</h1>
			<p class="text-[13px] text-error" x-show="error" x-text="error"></p>
		</div>

		<template x-if="loading">
			<p class="text-[13px] text-muted">Loading…</p>
		</template>

		<template x-if="!loading">
			<div class="space-y-6">
				<section class="rounded-lg border border-line bg-panel/60 p-4">
					<div class="flex items-center justify-between gap-3">
						<h2 class="text-sm font-semibold text-cream" x-text="item?.title"></h2>
						<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(item?.state).text">
							<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(item?.state).dot"></span>
							<span x-text="item?.state"></span>
						</span>
					</div>
					<p class="mt-2 text-[13px] text-muted">
						<span x-text="formatDuration(item?.duration_seconds)"></span>
						·
						<span x-text="formatRelativeTime(item?.published_at)"></span>
					</p>
				</section>

				<section class="rounded-lg border border-line bg-panel/60">
					<h2 class="border-b border-line px-4 py-3 text-[13px] font-semibold text-cream">Assets</h2>
					<table class="w-full text-left text-[13px]" x-show="assets.length > 0">
						<thead>
							<tr class="text-[11px] uppercase tracking-wide text-muted">
								<th class="px-4 py-2 font-normal">Role</th>
								<th class="px-4 py-2 font-normal">Kind</th>
								<th class="px-4 py-2 font-normal">State</th>
								<th class="px-4 py-2 font-normal">Size</th>
								<th class="px-4 py-2 font-normal">Path</th>
							</tr>
						</thead>
						<tbody>
							<template x-for="asset in assets" x-bind:key="asset.id">
								<tr class="border-t border-line/60 align-top">
									<td class="px-4 py-2 text-cream" x-text="asset.role.replace(/_/g, ' ')"></td>
									<td class="px-4 py-2 text-muted" x-text="asset.kind"></td>
									<td class="px-4 py-2">
										<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(asset.state).text">
											<span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusBadge(asset.state).dot"></span>
											<span x-text="asset.state"></span>
										</span>
										<p class="mt-1 text-[12px] text-muted" x-show="asset.derived_from_asset_id" x-text="'generated from ' + asset.derived_from_asset_id"></p>
									</td>
									<td class="px-4 py-2 text-muted" x-text="formatBytes(asset.size_bytes)"></td>
									<td class="px-4 py-2 font-mono text-muted" x-text="asset.relative_path"></td>
								</tr>
							</template>
						</tbody>
					</table>
					<p class="px-4 py-3 text-[13px] text-muted" x-show="assets.length === 0">No assets yet.</p>
					<p class="border-t border-line px-4 py-3 text-[12px] text-muted">
						Regeneration and delete-safety metadata isn't available yet for these assets.
					</p>
				</section>
			</div>
		</template>
	</div>
</x-stashd-layout>
