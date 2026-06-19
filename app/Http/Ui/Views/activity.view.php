<x-stashd-layout title="Activity · stashd_">
	<div x-data="activity">
		<div class="mb-6 flex items-center justify-between">
			<h1 class="text-base font-semibold text-cream">Activity</h1>
			<p class="flex items-center gap-2 text-[12px] text-muted">
				<span class="h-1.5 w-1.5 rounded-full" x-bind:class="connected ? 'bg-success' : 'bg-muted'"></span>
				<span x-text="connected ? 'live' : 'connecting…'"></span>
			</p>
		</div>

		<p class="text-[13px] text-muted" x-show="events.length === 0">No activity yet.</p>

		<ul class="space-y-2" x-show="events.length > 0">
			<template x-for="event in events" x-bind:key="event.id">
				<li class="rounded-lg border border-line bg-panel/60 px-4 py-3">
					<div class="flex items-start gap-3">
						<span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" x-bind:class="eventBadge(event).dot"></span>
						<div class="min-w-0 flex-1">
							<div class="flex items-center justify-between gap-3">
								<span class="text-[12px] uppercase tracking-wide" x-bind:class="eventBadge(event).text" x-text="eventBadge(event).label"></span>
								<span class="shrink-0 text-[12px] text-muted" x-text="formatRelativeTime(event.created_at)"></span>
							</div>
							<p class="mt-0.5 text-[13px] text-cream" x-text="summarize(event)"></p>
							<details class="mt-1">
								<summary class="cursor-pointer text-[11px] text-muted transition-colors hover:text-cream">payload</summary>
								<pre class="mt-1 overflow-x-auto rounded border border-line bg-espresso p-2 text-[11px] text-muted" x-text="JSON.stringify(event.payload, null, 2)"></pre>
							</details>
						</div>
					</div>
				</li>
			</template>
		</ul>
	</div>
</x-stashd-layout>
