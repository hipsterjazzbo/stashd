<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Providers\Core\DiscoveredItem;
use App\Providers\ProviderDates;
use App\Providers\ProviderException;
use App\Providers\StashdUri;

use function Tempest\Support\str;

final class YouTubeRssParser
{
    /** @return list<DiscoveredItem> */
    public function parse(string $xml, string $feedKind): array
    {
        if (str($xml)->trim()->isEmpty()) {
            throw new ProviderException('YouTube RSS response was empty.', 'rss_empty');
        }

        try {
            $document = new \SimpleXMLElement($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (\Throwable $throwable) {
            throw new ProviderException(
                'YouTube RSS response could not be parsed.',
                'rss_parse_failed',
                previous: $throwable,
            );
        }

        $document->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $document->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
        $document->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');

        $feedTitleNodes = $document->xpath('/atom:feed/atom:title');
        $inputTitle = $feedKind === 'playlist' && isset($feedTitleNodes[0])
            ? str((string) $feedTitleNodes[0])->trim()->toString()
            : null;

        $entries = $document->xpath('//atom:entry') ?: [];

        if ($entries === []) {
            return [];
        }

        $items = [];

        foreach ($entries as $entry) {
            $entry->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
            $entry->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
            $entry->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');

            $videoIdNodes = $entry->xpath('yt:videoId');
            $videoId = isset($videoIdNodes[0]) ? str((string) $videoIdNodes[0])->trim()->toString() : '';

            if ($videoId === '') {
                continue;
            }

            $titleNodes = $entry->xpath('atom:title');
            $title = isset($titleNodes[0])
                ? str((string) $titleNodes[0])->trim()->toString()
                : "YouTube Video {$videoId}";

            $publishedNodes = $entry->xpath('atom:published');
            $publishedAt = isset($publishedNodes[0])
                ? ProviderDates::tryParse(str((string) $publishedNodes[0])->trim()->toString())
                : null;

            $linkNodes = $entry->xpath("atom:link[@rel='alternate']");
            $canonicalUri = isset($linkNodes[0]['href'])
                ? StashdUri::parse(str((string) $linkNodes[0]['href'])->trim()->toString())
                : YouTubeUris::watch($videoId);

            $thumbnailNodes = $entry->xpath('media:group/media:thumbnail');
            $thumbnailUri = isset($thumbnailNodes[0]['url']) && str((string) $thumbnailNodes[0]['url'])->trim()->isNotEmpty()
                ? StashdUri::parse(str((string) $thumbnailNodes[0]['url'])->trim()->toString())
                : null;

            $descriptionNodes = $entry->xpath('media:group/media:description');
            $description = isset($descriptionNodes[0]) && str((string) $descriptionNodes[0])->trim()->isNotEmpty()
                ? str((string) $descriptionNodes[0])->trim()->toString()
                : null;

            $items[] = new DiscoveredItem(
                providerItemId: $videoId,
                canonicalUri: $canonicalUri,
                title: $title,
                description: $description,
                publishedAt: $publishedAt,
                thumbnailUri: $thumbnailUri,
                rawMetadata: [
                    'feed_kind' => $feedKind,
                    'video_id' => $videoId,
                    ...($inputTitle === null || $inputTitle === '' ? [] : ['input_title' => $inputTitle]),
                ],
            );
        }

        return $items;
    }
}
