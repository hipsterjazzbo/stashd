<x-stashd-layout title="New stash · stashd_">
	<div x-data="createStash">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">New stash</h1>
			<p class="text-[13px] text-error" x-show="error" x-text="error"></p>
		</div>

		<div class="max-w-lg space-y-4">
			<section class="rounded-lg border border-line bg-panel/60 p-4" x-show="step === 'paste'">
				<label class="block">
					<span class="mb-1 block text-[12px] text-muted">Source URL</span>
					<input type="text" x-model="sourceUri" placeholder="fake://channel/my-channel"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
				</label>
				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Title (optional)</span>
					<input type="text" x-model="sourceTitle"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
				</label>
				<button type="button" x-on:click="startPreflight()"
					x-bind:disabled="submitting || sourceUri.trim() === ''"
					class="mt-4 w-full rounded bg-amber px-3 py-2 font-semibold text-espresso transition-colors hover:bg-amber-dim disabled:opacity-60">
					Check
				</button>
			</section>

			<section class="rounded-lg border border-line bg-panel/60 p-4 text-[13px] text-muted" x-show="step === 'reviewing'">
				Discovering content at this source…
			</section>

			<section class="rounded-lg border border-line bg-panel/60 p-4" x-show="step === 'configure'">
				<p class="mb-3 text-[13px] text-muted" x-show="estimatedItemCount !== null">
					Found <span class="text-cream" x-text="estimatedItemCount"></span> item(s),
					<span class="text-cream" x-text="formatDuration(estimatedTotalDurationSeconds)"></span> total.
				</p>

				<label class="block">
					<span class="mb-1 block text-[12px] text-muted">Name</span>
					<input type="text" x-model="name"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
				</label>

				<label class="mt-3 block">
					<span class="mb-1 block text-[12px] text-muted">Download policy</span>
					<select x-model="downloadPolicy"
						class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber">
						<option value="video">Video</option>
						<option value="audio_only">Audio only</option>
						<option value="metadata_only">Metadata only</option>
						<option value="manual_download">Manual download</option>
					</select>
				</label>

				<button type="button" x-on:click="advancedOpen = !advancedOpen"
					class="mt-3 text-[12px] text-muted transition-colors hover:text-cream">
					<span x-show="!advancedOpen">Show advanced options</span>
					<span x-show="advancedOpen">Hide advanced options</span>
				</button>

				<div class="mt-3 space-y-3" x-show="advancedOpen">
					<label class="block">
						<span class="mb-1 block text-[12px] text-muted">Slug (optional)</span>
						<input type="text" x-model="slug"
							class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
					</label>
					<label class="block">
						<span class="mb-1 block text-[12px] text-muted">Description (optional)</span>
						<input type="text" x-model="description"
							class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber"/>
					</label>
					<label class="block">
						<span class="mb-1 block text-[12px] text-muted">Sync mode</span>
						<select x-model="syncMode"
							class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber">
							<option value="automatic">Automatic</option>
							<option value="manual">Manual</option>
						</select>
					</label>
					<label class="block">
						<span class="mb-1 block text-[12px] text-muted">Organization mode</span>
						<select x-model="organizationMode"
							class="w-full rounded border border-line bg-espresso px-3 py-2 text-cream outline-none focus:border-amber">
							<option value="flat">Flat</option>
							<option value="chronological">Chronological</option>
							<option value="series">Series</option>
							<option value="seasoned_series">Seasoned series</option>
						</select>
					</label>
				</div>

				<button type="button" x-on:click="submitCreate()"
					x-bind:disabled="submitting || name.trim() === ''"
					class="mt-4 w-full rounded bg-amber px-3 py-2 font-semibold text-espresso transition-colors hover:bg-amber-dim disabled:opacity-60">
					Create stash
				</button>
			</section>

			<section class="rounded-lg border border-line bg-panel/60 p-4 text-[13px] text-muted" x-show="step === 'creating'">
				Creating your stash…
			</section>

			<section class="rounded-lg border border-error/50 bg-error/10 p-4" x-show="step === 'failed'">
				<p class="text-[13px] text-error" x-text="failureMessage"></p>
				<button type="button" x-show="failedStage === 'preflight'" x-on:click="resetToPaste()"
					class="mt-3 rounded border border-line px-3 py-2 text-[12px] text-muted transition-colors hover:text-cream">
					Try a different URL
				</button>
				<button type="button" x-show="failedStage === 'create'" x-on:click="backToConfigure()"
					class="mt-3 rounded border border-line px-3 py-2 text-[12px] text-muted transition-colors hover:text-cream">
					Back
				</button>
			</section>
		</div>
	</div>
</x-stashd-layout>
