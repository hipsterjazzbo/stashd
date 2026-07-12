<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use DateTimeZone;
use Tempest\DateTime\DateTime;

final readonly class PodcastFeedBuilder
{
    /** @param list<PodcastEpisode> $episodes */
    public function build(PodcastFeedMetadata $metadata, array $episodes): string
    {
        $items = $episodes;
        usort($items, static function (PodcastEpisode $a, PodcastEpisode $b): int {
            if ($a->publishedAt->isBefore($b->publishedAt)) {
                return -1;
            }

            if ($a->publishedAt->isAfter($b->publishedAt)) {
                return 1;
            }

            return $a->guid <=> $b->guid;
        });

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<rss version=\"2.0\" xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\" xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:podcast=\"https://podcastindex.org/namespace/1.0\">\n";
        $xml .= "  <channel>\n";
        $xml .= '    <title>' . $this->escape($metadata->title) . "</title>\n";
        $xml .= '    <description>' . $this->escape($metadata->description) . "</description>\n";
        $xml .= '    <content:encoded>' . $this->cdata($this->descriptionHtml($metadata->description)) . "</content:encoded>\n";
        $xml .= '    <link>' . $this->escape($metadata->linkUrl ?? $metadata->feedUrl) . "</link>\n";
        $xml .= '    <language>' . $this->escape($metadata->language) . "</language>\n";
        $xml .= '    <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="'
            . $this->escape($metadata->feedUrl)
            . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
        $xml .= '    <itunes:summary>' . $this->escape($metadata->description) . "</itunes:summary>\n";
        $xml .= '    <itunes:explicit>' . ($metadata->explicit ? 'true' : 'false') . "</itunes:explicit>\n";
        $xml .= "    <itunes:block>yes</itunes:block>\n";
        $xml .= '    <itunes:complete>' . ($metadata->complete ? 'yes' : 'no') . "</itunes:complete>\n";
        $xml .= '    <podcast:medium>podcast</podcast:medium>' . "\n";

        if ($metadata->podcastGuid !== null) {
            $xml .= '    <podcast:guid>' . $this->escape($metadata->podcastGuid) . "</podcast:guid>\n";
        }

        if ($metadata->author !== null) {
            $xml .= '    <itunes:author>' . $this->escape($metadata->author) . "</itunes:author>\n";
        }

        if ($metadata->imageUrl !== null) {
            $xml .= '    <itunes:image href="' . $this->escape($metadata->imageUrl) . "\" />\n";
        }

        if ($metadata->fundingUrl !== null) {
            $xml .= '    <podcast:funding url="' . $this->escape($metadata->fundingUrl) . "\">Support the creator</podcast:funding>\n";
        }

        foreach ($items as $episode) {
            $xml .= "    <item>\n";
            $xml .= '      <title>' . $this->escape($episode->title) . "</title>\n";
            $xml .= '      <description>' . $this->escape($episode->description) . "</description>\n";
            $xml .= '      <content:encoded>' . $this->cdata($this->descriptionHtml($episode->description)) . "</content:encoded>\n";
            $xml .= '      <guid isPermaLink="false">' . $this->escape($episode->guid) . "</guid>\n";
            $xml .= '      <pubDate>' . $this->escape($this->pubDate($episode->publishedAt)) . "</pubDate>\n";
            $xml .= '      <enclosure url="' . $this->escape($episode->enclosureUrl) . '" length="'
                . $episode->enclosureLength
                . '" type="' . $this->escape($episode->enclosureMimeType) . "\" />\n";
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function cdata(string $value): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
    }

    private function descriptionHtml(string $value): string
    {
        $escaped = $this->escape($value);
        $linked = preg_replace('~(?<!["=])(https?://[^\s<]+)~', '<a href="$1">$1</a>', $escaped) ?? $escaped;

        return str_replace(["\r\n", "\r", "\n"], '<br />' . "\n", $linked);
    }

    private function pubDate(DateTime $value): string
    {
        return $value
            ->toNativeDateTime()
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_RSS);
    }
}
