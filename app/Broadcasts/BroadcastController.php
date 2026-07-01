<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Broadcasts\Api\BroadcastItemResource;
use App\Broadcasts\Api\BroadcastResource;
use App\Broadcasts\Podcasts\PodcastEpisodeUrlBuilder;
use App\Broadcasts\Podcasts\PodcastMediaKind;
use App\Broadcasts\Podcasts\PodcastTokenService;
use App\Http\Api\ApiJson;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Stashes\DownloadPolicy;
use App\Stashes\StashInputRepository;
use App\Stashes\StashRepository;
use App\Support\PrefixedUlid;
use App\System\Storage\PathSanitizer;
use Tempest\Http\Request;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Get;
use Tempest\Router\Patch;
use Tempest\Router\Post;
use Tempest\Router\WithMiddleware;

use function Tempest\Support\str;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class BroadcastController
{
    public function __construct(
        private StashRepository $stashes,
        private StashInputRepository $stashInputs,
        private BroadcastRepository $broadcasts,
        private BroadcastItemRepository $broadcastItems,
        private PodcastTokenService $podcastTokens,
        private PodcastEpisodeUrlBuilder $podcastUrls,
    ) {
    }

    #[Get('/api/v1/broadcast-plugins')]
    public function plugins(): Json
    {
        $plugins = [];

        foreach (BroadcastPluginRegistry::all() as $discovered) {
            foreach ($discovered->broadcastKeys as $key) {
                $plugins[] = $this->mapPlugin($key, $discovered);
            }
        }

        return new Json(['plugins' => $plugins]);
    }

    #[Get('/api/v1/stashes/{stashId}/broadcasts')]
    public function index(string $stashId): Json
    {
        $stash = $this->stashes->find($stashId);

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        return new Json([
            'broadcasts' => array_map(
                fn ($broadcast): array => $this->mapBroadcast($broadcast),
                $this->broadcasts->listForStash(PrefixedUlid::parse($stashId)),
            ),
        ]);
    }

    #[Post('/api/v1/stashes/{stashId}/broadcasts')]
    public function create(string $stashId, Request $request): Json
    {
        $stash = $this->stashes->find($stashId);

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        $body = ApiJson::normalizeRequest($request->body);
        $typeRaw = trim((string) ($body['type'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        $slugRaw = trim((string) ($body['slug'] ?? ''));

        if ($typeRaw === '' || $name === '') {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'type and name are required.',
                ],
            ], Status::BAD_REQUEST);
        }

        // Validate against known plugin keys.
        if (! in_array($typeRaw, BroadcastPluginRegistry::broadcastKeys(), true)) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Unsupported broadcast type.',
                ],
            ], Status::BAD_REQUEST);
        }

        $slug = $slugRaw !== '' ? $slugRaw : str($name)->slug()->toString();

        try {
            $slug = PathSanitizer::sanitizeSegment($slug);
        } catch (\InvalidArgumentException) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Invalid broadcast slug.',
                ],
            ], Status::BAD_REQUEST);
        }

        $stashUlid = PrefixedUlid::parse($stashId);

        if ($this->broadcasts->findByStashAndSlug($stashUlid, $slug) !== null) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Broadcast slug already exists for this stash.',
                ],
            ], Status::BAD_REQUEST);
        }

        $settings = is_array($body['settings'] ?? null) ? ApiJson::encode($body['settings']) : null;

        $broadcast = $this->broadcasts->create(
            stashId: $stashUlid,
            type: $typeRaw,
            name: $name,
            slug: $slug,
            settings: $settings,
        );

        return new Json([
            'broadcast' => $this->mapBroadcast($broadcast),
            'policy_mismatch' => $this->policyMismatch($stash->downloadPolicy, $broadcast),
        ], Status::CREATED);
    }

    #[Get('/api/v1/broadcasts/{id}')]
    public function show(string $id): Json
    {
        $broadcast = $this->broadcasts->find(PrefixedUlid::parse($id));

        if ($broadcast === null) {
            return $this->notFound('Broadcast not found.');
        }

        return new Json([
            'broadcast' => $this->mapBroadcast($broadcast),
        ]);
    }

    #[Get('/api/v1/broadcasts/{id}/items')]
    public function items(string $id): Json
    {
        $broadcast = $this->broadcasts->find(PrefixedUlid::parse($id));

        if ($broadcast === null) {
            return $this->notFound('Broadcast not found.');
        }

        return new Json([
            'items' => array_map(
                static fn ($item): array => BroadcastItemResource::fromRecord($item)->toArray(),
                $this->broadcastItems->listForBroadcast(PrefixedUlid::parse($id)),
            ),
        ]);
    }

    #[Patch('/api/v1/broadcasts/{id}/season-mapping')]
    public function updateSeasonMapping(string $id, Request $request): Json
    {
        $broadcast = $this->broadcasts->find(PrefixedUlid::parse($id));

        if ($broadcast === null) {
            return $this->notFound('Broadcast not found.');
        }

        if (! $this->isSeriesBroadcast($broadcast->type)) {
            return $this->validationError('Season mapping only applies to Jellyfin/Plex series broadcasts.');
        }

        // Read 'mapping' from the raw body, not ApiJson::normalizeRequest()'s
        // output: its keys are opaque stash_input_id strings, not DTO field
        // names, and the snake/camel transform would corrupt them.
        $rawMapping = $request->body['mapping'] ?? null;

        if (! is_array($rawMapping)) {
            return $this->validationError('mapping is required.');
        }

        $validInputIds = array_map(
            static fn ($input): string => (string) $input->id,
            $this->stashInputs->listForStash($broadcast->stashId),
        );

        $mapping = [];

        foreach ($rawMapping as $stashInputId => $season) {
            if (! is_string($stashInputId) || ! in_array($stashInputId, $validInputIds, true)) {
                return $this->validationError("Unknown stash input for this broadcast's stash: {$stashInputId}");
            }

            if (! is_int($season) || $season < 1) {
                return $this->validationError("Season for input {$stashInputId} must be a positive integer.");
            }

            $mapping[$stashInputId] = $season;
        }

        $settings = $this->decodeSettings($broadcast);

        if ($mapping === []) {
            unset($settings['season_mapping']);
        } else {
            $settings['season_mapping'] = $mapping;
        }

        $broadcast->settingsJson = $settings === [] ? null : json_encode($settings, JSON_THROW_ON_ERROR);
        $this->broadcasts->save($broadcast);

        return new Json([
            'broadcast' => $this->mapBroadcast($broadcast),
        ]);
    }

    /** @return array<string, mixed> */
    private function decodeSettings(BroadcastRecord $broadcast): array
    {
        if ($broadcast->settingsJson === null) {
            return [];
        }

        $decoded = json_decode($broadcast->settingsJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function validationError(string $message): Json
    {
        return new Json([
            'error' => [
                'code' => 'validation_error',
                'message' => $message,
            ],
        ], Status::BAD_REQUEST);
    }

    /** @return array<string, mixed>|null */
    private function policyMismatch(DownloadPolicy $policy, BroadcastRecord $broadcast): ?array
    {
        $type = $broadcast->type;
        $mediaKind = PodcastMediaKind::forBroadcast($broadcast);

        if ($this->isTypeSatisfiedByPolicy($type, $policy, $mediaKind)) {
            return null;
        }

        return [
            'download_policy' => $policy->value,
            'broadcast_type' => $type,
            'message' => "This stash's \"{$policy->value}\" download policy won't produce media for a \"{$type}\" broadcast.",
            'compatible_download_policies' => array_values(array_map(
                fn (DownloadPolicy $candidate): string => $candidate->value,
                array_filter(
                    DownloadPolicy::cases(),
                    fn (DownloadPolicy $candidate): bool => $this->isTypeSatisfiedByPolicy($type, $candidate, $mediaKind),
                ),
            )),
        ];
    }

    /** Check if a broadcast type (string key) satisfies the download policy. */
    private function isTypeSatisfiedByPolicy(string $type, DownloadPolicy $policy, ?PodcastMediaKind $mediaKind): bool
    {
        return match ($policy) {
            DownloadPolicy::MetadataOnly => false,
            DownloadPolicy::AudioOnly => $type !== 'podcast' || $mediaKind !== PodcastMediaKind::Video,
            DownloadPolicy::Video, DownloadPolicy::ManualDownload => true,
        };
    }

    /** Check if a broadcast type (string key) is a series-type broadcast. */
    private function isSeriesBroadcast(string $type): bool
    {
        $plugin = BroadcastPluginRegistry::findByKey($type);

        return $plugin !== null && $plugin->plugin->supportedFileKinds() === [FileKind::Video];
    }

    private function notFound(string $message): Json
    {
        return new Json([
            'error' => [
                'code' => 'not_found',
                'message' => $message,
            ],
        ], Status::NOT_FOUND);
    }

    /** @return array<string, mixed> */
    private function mapBroadcast(BroadcastRecord $broadcast): array
    {
        if ($broadcast->type !== 'podcast') {
            return BroadcastResource::fromRecord($broadcast)->toArray();
        }

        $token = $this->podcastTokens->ensureBroadcastToken($broadcast);

        return BroadcastResource::fromRecord($broadcast, $this->podcastUrls->feedUrl($token))->toArray();
    }

    private function mapPlugin(string $key, DiscoveredPlugin $discovered): array
    {
        return ApiJson::encode([
            'key' => $key,
            'label' => $discovered->name,
            'description' => $discovered->description,
            'supportedFileKinds' => array_map(
                static fn (FileKind $kind): string => $kind->value,
                $discovered->plugin->supportedFileKinds(),
            ),
            'uiControls' => array_map(
                static fn (UiControl $control): array => [
                    'name' => $control->name,
                    'label' => $control->label,
                    'type' => $control->type,
                    'default' => $control->default,
                    'options' => $control->options,
                ],
                $discovered->plugin->uiControls(),
            ),
        ]);
    }
}
