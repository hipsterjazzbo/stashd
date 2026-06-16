<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

final readonly class PodcastFeedBuilder
{
    /** @param list<PodcastEpisode> $episodes */
    public function build(PodcastFeedMetadata $metadata, array $episodes): string
    {
        $items = $episodes;
        usort($items, static fn (PodcastEpisode $a, PodcastEpisode $b): int => [
            $a->publishedAt,
            $a->guid,
        ] <=> [
            $b->publishedAt,
            $b->guid,
        ]);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<rss version=\"2.0\" xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\">\n";
        $xml .= "  <channel>\n";
        $xml .= '    <title>' . $this->escape($metadata->title) . "</title>\n";
        $xml .= '    <description>' . $this->escape($metadata->description) . "</description>\n";
        $xml .= '    <link>' . $this->escape($metadata->linkUrl ?? $metadata->feedUrl) . "</link>\n";
        $xml .= '    <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="'
            . $this->escape($metadata->feedUrl)
            . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
        $xml .= '    <itunes:summary>' . $this->escape($metadata->description) . "</itunes:summary>\n";
        $xml .= "    <itunes:explicit>false</itunes:explicit>\n";

        if ($metadata->author !== null) {
            $xml .= '    <itunes:author>' . $this->escape($metadata->author) . "</itunes:author>\n";
        }

        if ($metadata->imageUrl !== null) {
            $xml .= '    <itunes:image href="' . $this->escape($metadata->imageUrl) . "\" />\n";
        }

        if ($metadata->fundingUrl !== null) {
            $xml .= '    <funding url="' . $this->escape($metadata->fundingUrl) . "\" />\n";
        }

        foreach ($items as $episode) {
            $xml .= "    <item>\n";
            $xml .= '      <title>' . $this->escape($episode->title) . "</title>\n";
            $xml .= '      <description>' . $this->escape($episode->description) . "</description>\n";
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

    private function pubDate(string $value): string
    {
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            $timestamp = 0;
        }

        return gmdate(DATE_RSS, $timestamp);
    }
}
