<?php

declare(strict_types=1);

namespace Tests\Unit\Broadcasts;

use App\Broadcasts\SponsorBlockClient;
use App\Providers\ProviderHttpClient;
use App\Providers\ProviderHttpResponse;
use App\Timeline\TimelineEntryCategory;
use Stringable;
use Tempest\Support\Uri\Uri;

test('SponsorBlock client returns valid segments and ignores invalid ones', function (): void {
    $http = new class () implements ProviderHttpClient {
        public function get(Uri|string|Stringable $url): ProviderHttpResponse
        {
            return new ProviderHttpResponse(200, json_encode([
                ['UUID' => 'segment-1', 'category' => 'sponsor', 'segment' => [5, 16.5], 'description' => 'Ad read'],
                ['UUID' => 'bad', 'category' => 'intro', 'segment' => [10, 2]],
            ], JSON_THROW_ON_ERROR));
        }
    };

    $segments = (new SponsorBlockClient($http))->fetch('video-id');

    expect($segments)->toHaveCount(1)
        ->and($segments[0]->externalId)->toBe('segment-1')
        ->and($segments[0]->category)->toBe(TimelineEntryCategory::Sponsor)
        ->and($segments[0]->startSeconds)->toBe(5.0)
        ->and($segments[0]->endSeconds)->toBe(16.5);
});
