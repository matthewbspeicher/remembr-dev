<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    private const MODEL = 'gemini-embedding-2-preview';

    // Gemini outputs 3072 dims by default. Our DB column is vector(1536),
    // so we truncate using Matryoshka slicing (supported by the model).
    private const DB_DIMENSIONS = 1536;

    private const CACHE_TTL = 60 * 60 * 24 * 7; // 7 days

    private readonly string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? '';
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
     * Individual results are cached by content hash.
     */
    public function embedBatch(array $texts): array
    {
        $results = [];
        $uncached = [];
        $uncachedIndices = [];

        foreach ($texts as $i => $text) {
            $cacheKey = 'embedding:'.hash('xxh128', $text);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $results[$i] = $cached;
            } else {
                $uncached[] = $text;
                $uncachedIndices[] = $i;
            }
        }

        if (! empty($uncached)) {
            $requests = array_map(function ($text) {
                return [
                    'model' => 'models/'.self::MODEL,
                    'content' => [
                        'parts' => [['text' => $text]],
                    ],
                ];
            }, $uncached);

            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':batchEmbedContents', [
                'requests' => $requests,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Gemini embedding API error: '.$response->body());
            }

            foreach ($response->json('embeddings') as $j => $emb) {
                $embedding = array_slice($emb['values'], 0, self::DB_DIMENSIONS);
                $originalIndex = $uncachedIndices[$j];
                $results[$originalIndex] = $embedding;

                $cacheKey = 'embedding:'.hash('xxh128', $uncached[$j]);
                Cache::put($cacheKey, $embedding, self::CACHE_TTL);
            }
        }

        ksort($results);

        return array_values($results);
    }

    private function fetchFromApi(string $text): array
    {
        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey,
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':embedContent', [
            'model' => 'models/'.self::MODEL,
            'content' => [
                'parts' => [['text' => $text]],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini embedding API error: '.$response->body());
        }

        return array_slice($response->json('embedding.values'), 0, self::DB_DIMENSIONS);
    }
}
