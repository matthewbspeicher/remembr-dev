<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    private const MODEL = 'text-embedding-3-small';

    private const DIMENSIONS = 1536;

    private const CACHE_TTL = 60 * 60 * 24 * 7; // 7 days

    private readonly string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key') ?? '';
    }

    /**
     * Generate an embedding vector for the given text.
     * Results are cached by content hash to avoid redundant API calls.
     */
    public function embed(string $text): array
    {
        $cacheKey = 'embedding:'.hash('xxh128', $text);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($text) {
            return $this->fetchFromApi($text);
        });
    }

    /**
     * Embed multiple texts in a single API call (more efficient).
     */
    public function embedBatch(array $texts): array
    {
        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => self::MODEL,
                'input' => $texts,
                'dimensions' => self::DIMENSIONS,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embedding API error: '.$response->body());
        }

        return collect($response->json('data'))
            ->sortBy('index')
            ->pluck('embedding')
            ->all();
    }

    private function fetchFromApi(string $text): array
    {
        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => self::MODEL,
                'input' => $text,
                'dimensions' => self::DIMENSIONS,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embedding API error: '.$response->body());
        }

        return $response->json('data.0.embedding');
    }
}
