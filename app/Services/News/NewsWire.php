<?php

namespace App\Services\News;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Headlines for the command centre from Google News search feeds (RSS). Only the headline,
 * source, time and link are kept; the story opens on the publisher's site. Feeds are fetched
 * at most every ten minutes, and the last good list is kept if Google cannot be reached.
 */
class NewsWire
{
    private const FRESH_SECONDS = 600;

    private const MAX_ITEMS = 40;

    /** @return array{items: list<array{title: string, source: ?string, url: string, published_at: string, topic: string}>, fetched_at: ?string} */
    public function headlines(): array
    {
        $key = 'newswire:'.config('site.key');
        $cached = Cache::get($key);

        if ($cached && CarbonImmutable::parse($cached['fetched_at'])->diffInSeconds(now(), true) < self::FRESH_SECONDS) {
            return $cached;
        }

        // One fetch at a time; everyone else gets the last list meanwhile.
        $lock = Cache::lock($key.':lock', 30);
        if (! $lock->get()) {
            return $cached ?? ['items' => [], 'fetched_at' => null];
        }

        try {
            $items = $this->fetchAll();
            if ($items === [] && $cached) {
                return $cached;
            }
            $fresh = ['items' => $items, 'fetched_at' => now()->toIso8601ZuluString()];
            Cache::forever($key, $fresh);

            return $fresh;
        } finally {
            $lock->release();
        }
    }

    /** @return list<array{title: string, source: ?string, url: string, published_at: string, topic: string}> */
    private function fetchAll(): array
    {
        $topics = config('site.news_topics') ?? ['Nigeria election' => 'INEC OR "Nigeria election" when:7d'];
        $items = [];

        foreach ($topics as $topic => $query) {
            try {
                $response = Http::timeout(8)->accept('application/rss+xml, application/xml')
                    ->get('https://news.google.com/rss/search', ['q' => $query, 'hl' => 'en-NG', 'gl' => 'NG', 'ceid' => 'NG:en']);
            } catch (ConnectionException) {
                continue;
            }
            if ($response->failed()) {
                continue;
            }
            foreach ($this->parse($response->body(), $topic) as $item) {
                $items[] = $item;
            }
        }

        // The same story turns up under several searches: keep its first appearance.
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $fingerprint = mb_strtolower(preg_replace('/\W+/u', '', $item['title']));
            if (! isset($seen[$fingerprint])) {
                $seen[$fingerprint] = true;
                $unique[] = $item;
            }
        }
        usort($unique, fn ($a, $b) => strcmp($b['published_at'], $a['published_at']));

        return array_slice($unique, 0, self::MAX_ITEMS);
    }

    /** @return list<array{title: string, source: ?string, url: string, published_at: string, topic: string}> */
    private function parse(string $xml, string $topic): array
    {
        try {
            $feed = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA);
        } catch (Throwable) {
            $feed = false;
        }
        if ($feed === false || ! isset($feed->channel->item)) {
            Log::warning('News wire: unreadable feed', ['topic' => $topic]);

            return [];
        }

        $items = [];
        foreach ($feed->channel->item as $entry) {
            $url = (string) $entry->link;
            $source = trim((string) $entry->source) ?: null;
            $title = trim(html_entity_decode((string) $entry->title, ENT_QUOTES | ENT_HTML5));
            // Google appends " - Publisher" to every headline.
            if ($source && str_ends_with($title, ' - '.$source)) {
                $title = mb_substr($title, 0, -mb_strlen(' - '.$source));
            }
            if ($title === '' || ! str_starts_with($url, 'https://')) {
                continue;
            }
            try {
                $published = CarbonImmutable::parse((string) $entry->pubDate)->utc();
            } catch (Throwable) {
                continue;
            }
            $items[] = ['title' => mb_strimwidth($title, 0, 240, '…'), 'source' => $source, 'url' => $url, 'published_at' => $published->toIso8601ZuluString(), 'topic' => $topic];
        }

        return $items;
    }
}
