<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Config\YouTubeConfig;
use App\Providers\Core\DiscoveredItem;
use App\Providers\Provider;
use App\Providers\ProviderException;
use App\Providers\ProviderStrategy;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;
use App\Providers\StrategyCost;
use App\Providers\StrategyPurpose;
use InvalidArgumentException;

final readonly class YouTubeProvider implements Provider
{
    public const string KEY = 'youtube';

    public function __construct(
        private YouTubeConfig $config,
        private YouTubeRssDiscoveryStrategy $rssDiscovery,
        private YouTubeDataApiMetadataStrategy $dataApiMetadata,
        private YtdlpDownloadAdapter $downloadAdapter,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'YouTube';
    }

    public function supportsUri(StashdUri $uri): bool
    {
        return YouTubeUriDetector::isYouTube($uri);
    }

    public function resolveInput(StashdUri $uri): ResolvedInput
    {
        return YouTubeUriResolver::resolve($uri);
    }

    public function discoveryStrategies(): array
    {
        return [
            new ProviderStrategy(
                key: YouTubeRssDiscoveryStrategy::STRATEGY_KEY,
                purpose: StrategyPurpose::Discovery,
                cost: StrategyCost::Low,
                supportsIncremental: true,
                supportsBackfill: true,
                priority: 10,
            ),
        ];
    }

    public function metadataStrategies(): array
    {
        return [
            new ProviderStrategy(
                key: YouTubeDataApiMetadataStrategy::STRATEGY_KEY,
                purpose: StrategyPurpose::Metadata,
                cost: StrategyCost::Medium,
                requiresAuth: true,
                supportsBackfill: true,
                priority: 10,
            ),
        ];
    }

    public function downloadStrategies(): array
    {
        return [
            new ProviderStrategy(
                key: YouTubeYtdlpDownloadStrategy::STRATEGY_KEY,
                purpose: StrategyPurpose::Download,
                cost: StrategyCost::LastResort,
                priority: 100,
            ),
        ];
    }

    public function discover(ResolvedInput $input, ProviderStrategy $strategy): array
    {
        if ($strategy->key === YouTubeRssDiscoveryStrategy::STRATEGY_KEY) {
            return $this->rssDiscovery->discover($input);
        }

        throw new InvalidArgumentException("Unsupported YouTube discovery strategy: {$strategy->key}");
    }

    public function isStrategyAvailable(ProviderStrategy $strategy): bool
    {
        return match ($strategy->key) {
            YouTubeRssDiscoveryStrategy::STRATEGY_KEY => true,
            YouTubeDataApiMetadataStrategy::STRATEGY_KEY => $this->config->hasDataApiKey(),
            YouTubeYtdlpDownloadStrategy::STRATEGY_KEY => $this->downloadAdapter->isAvailable(),
            default => false,
        };
    }

    public function enrichMetadata(ResolvedInput $input, DiscoveredItem $item, ProviderStrategy $strategy): DiscoveredItem
    {
        return match ($strategy->key) {
            YouTubeDataApiMetadataStrategy::STRATEGY_KEY => $this->dataApiMetadata->enrich($input, $item),
            YouTubeRssDiscoveryStrategy::STRATEGY_KEY => $item,
            default => throw new ProviderException("Unsupported YouTube metadata strategy: {$strategy->key}", 'unsupported_metadata_strategy'),
        };
    }
}
