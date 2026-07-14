<x-stashd-layout title="Stashes · stashd_">
	<div x-data="stashes">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">Stashes</h1>
			<div class="flex items-center gap-4">
				<p class="text-[13px] text-error" x-show="error" x-text="error"></p>
				<button type="button" x-on:click="startCreate()"
					class="rounded bg-amber px-3 py-1.5 text-[13px] font-semibold text-espresso transition-colors hover:bg-amber-dim">
					+ New stash
				</button>
			</div>
		</div>

		<template x-if="loading">
			<p class="flex items-center gap-2 text-[13px] text-muted">
				<span class="h-1.5 w-1.5 rounded-full bg-amber pulse-dot"></span>
				Loading…
			</p>
		</template>

		<template x-if="!loading">
			<section class="rounded-lg border border-line bg-panel/60">
				<table class="w-full text-left text-[13px]" x-show="stashes.length > 0">
					<thead>
						<tr class="text-[11px] uppercase tracking-wide text-muted">
							<th class="px-4 py-2 font-normal">Name</th>
							<th class="px-4 py-2 font-normal">State</th>
							<th class="px-4 py-2 font-normal">Sync</th>
							<th class="px-4 py-2 font-normal">Download</th>
							<th class="px-4 py-2 font-normal">Organization</th>
							<th class="px-4 py-2 font-normal">Actions</th>
						</tr>
					</thead>
					<tbody>
						<template x-for="stash in stashes" x-bind:key="stash.id">
							<tr class="border-t border-line/60">
								<td class="px-4 py-2">
									<div class="flex items-center gap-2">
										<img x-show="stash.icon_uri" x-bind:src="stash.icon_uri" class="h-6 w-6 rounded-full object-cover" alt=""/>
										<span x-show="!stash.icon_uri" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-espresso text-[11px] text-muted" x-text="stash.name.charAt(0).toUpperCase()"></span>
										<a class="text-cream transition-colors hover:text-amber" x-bind:href="'/stashes/' + stash.id" x-text="stash.name"></a>
									</div>
								</td>
								<td class="px-4 py-2">
									<span class="inline-flex items-center gap-1.5" x-bind:class="statusBadge(stash.state).text">
										<span class="h-1.5 w-1.5 rounded-full" x-bind:class="[statusBadge(stash.state).dot, statusBadge(stash.state).pulse ? 'pulse-dot' : '']"></span>
										<span x-text="stash.state"></span>
									</span>
								</td>
								<td class="px-4 py-2 text-muted" x-text="stash.sync_mode"></td>
								<td class="px-4 py-2 text-muted" x-text="stash.download_policy.replace(/_/g, ' ')"></td>
								<td class="px-4 py-2 text-muted" x-text="stash.organization_mode"></td>
								<td class="px-4 py-2">
									<div class="flex items-center gap-2">
										<button type="button" x-on:click="startEdit(stash)"
											class="rounded border border-line px-2 py-1 text-[12px] text-muted transition-colors hover:text-cream">Edit</button>
										<button type="button" x-on:click="startDelete(stash)"
											class="rounded border border-line px-2 py-1 text-[12px] text-error transition-colors hover:bg-error/10">Delete</button>
									</div>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
				<p class="px-4 py-3 text-[13px] text-muted" x-show="stashes.length === 0">No stashes yet.</p>
			</section>
		</template>

		<div class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 p-4" x-show="editingStash" x-cloak>
			<div class="w-full max-w-md rounded-lg border border-line bg-panel p-4">
				<h2 class="text-sm font-semibold text-cream">Edit stash</h2>

				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Name</span>
					<input type="text" x-model="editForm.name"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
				</label>
				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Description</span>
					<input type="text" x-model="editForm.description"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
				</label>
				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Sync mode</span>
					<select x-model="editForm.syncMode"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber">
						<option value="automatic">Automatic</option>
						<option value="manual">Manual</option>
					</select>
				</label>
				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Download policy</span>
					<select x-model="editForm.downloadPolicy"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber">
						<option value="video">Video</option>
						<option value="audio_only">Audio only</option>
						<option value="metadata_only">Metadata only</option>
						<option value="manual_download">Manual download</option>
					</select>
				</label>
				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Organization mode</span>
					<select x-model="editForm.organizationMode"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber">
						<option value="flat">Flat</option>
						<option value="chronological">Chronological</option>
						<option value="series">Series</option>
						<option value="seasoned_series">Seasoned series</option>
					</select>
				</label>

				<div class="mt-4 flex justify-end gap-2">
					<button type="button" x-on:click="cancelEdit()"
						class="rounded border border-line px-3 py-2 text-[13px] text-muted transition-colors hover:text-cream">Cancel</button>
					<button type="button" x-on:click="saveEdit()"
						x-bind:disabled="savingEdit || editForm.name.trim() === ''"
						class="rounded bg-amber px-3 py-2 text-[13px] font-semibold text-espresso transition-colors hover:bg-amber-dim disabled:opacity-60">Save</button>
				</div>
			</div>
		</div>

		<div class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 p-4" x-show="creatingStash" x-cloak>
			<div class="w-full max-w-md rounded-lg border border-line bg-panel p-4">
				<h2 class="text-sm font-semibold text-cream">New stash</h2>
				<p class="mt-1 text-[13px] text-error" x-show="newStashError" x-text="newStashError"></p>

				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Title (optional)</span>
					<input type="text" x-model="newStashForm.title"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
				</label>
				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Link (optional)</span>
					<input type="text" x-model="newStashForm.link" placeholder="https://www.youtube.com/@channel"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
				</label>

				<div class="mt-4 flex justify-end gap-2">
					<button type="button" x-on:click="cancelCreate()"
						class="rounded border border-line px-3 py-2 text-[13px] text-muted transition-colors hover:text-cream">Cancel</button>
					<button type="button" x-on:click="submitCreateStash()"
						x-bind:disabled="creatingBusy"
						class="rounded bg-amber px-3 py-2 text-[13px] font-semibold text-espresso transition-colors hover:bg-amber-dim disabled:opacity-60">Create</button>
				</div>
			</div>
		</div>

		<div class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 p-4" x-show="deletingStash" x-cloak>
			<div class="w-full max-w-md rounded-lg border border-line bg-panel p-4">
				<h2 class="text-sm font-semibold text-cream">Delete <span x-text="deletingStash?.name"></span>?</h2>

				<template x-if="loadingDeleteImpact">
					<p class="mt-3 text-[13px] text-muted">Checking what this affects…</p>
				</template>

				<template x-if="!loadingDeleteImpact && deleteImpact">
					<div class="mt-3 space-y-2 text-[13px]">
						<p class="text-muted" x-show="deleteImpact.shared_items.length === 0 && deleteImpact.orphaned_items.length === 0">
							This stash has no items.
						</p>
						<div x-show="deleteImpact.shared_items.length > 0">
							<p class="text-cream"><span x-text="deleteImpact.shared_items.length"></span> item(s) are also used by other stashes and will be kept in the Vault.</p>
						</div>
						<div x-show="deleteImpact.orphaned_items.length > 0">
							<p class="text-warn"><span x-text="deleteImpact.orphaned_items.length"></span> item(s) will become orphaned in the Vault (no longer referenced by any stash).</p>
						</div>
					</div>
				</template>

				<p class="mt-3 text-[12px] text-muted">The stash, its inputs, and its item links will be removed. Vault originals are kept.</p>

				<div class="mt-4 flex justify-end gap-2">
					<button type="button" x-on:click="cancelDelete()"
						class="rounded border border-line px-3 py-2 text-[13px] text-muted transition-colors hover:text-cream">Cancel</button>
					<button type="button" x-on:click="confirmDelete()"
						x-bind:disabled="deletingBusy || loadingDeleteImpact"
						class="rounded bg-error px-3 py-2 text-[13px] font-semibold text-cream transition-colors hover:opacity-90 disabled:opacity-60">Delete stash</button>
				</div>
			</div>
		</div>
	</div>
</x-stashd-layout>
