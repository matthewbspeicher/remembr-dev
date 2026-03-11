<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    private const MODEL = 'gemini-embedding-001';

    // The database column is currently set to 1536. 
    // Gemini text-embedding-004 outputs 768. 
    // We will pad it to 1536 so we don't have to rebuild the DB.
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
     */
    public function embedBatch(array $texts): array
    {
        $requests = array_map(function ($text) {
            return [
                'model' => 'models/' . self::MODEL,
                'content' => [
                    'parts' => [['text' => $text]]
                ]
            ];
        }, $texts);

        $response = Http::post('https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':batchEmbedContents?key=' . $this->apiKey, [
            'requests' => $requests,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini embedding API error: '.$response->body());
        }

        $embeddings = [];
        foreach ($response->json('embeddings') as $emb) {
            // gemini-embedding-001 returns 3072 dimensions, but our DB expects 1536.
            $embeddings[] = array_slice($emb['values'], 0, self::DB_DIMENSIONS);
        }

        return $embeddings;
    }

    private function fetchFromApi(string $text): array
    {
        $response = Http::post('https://generativelanguage.googleapis.com/v1beta/models/' . self::MODEL . ':embedContent?key=' . $this->apiKey, [
            'model' => 'models/' . self::MODEL,
            'content' => [
                'parts' => [['text' => $text]]
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini embedding API error: '.$response->body());
        }

        return array_slice($response->json('embedding.values'), 0, self::DB_DIMENSIONS);
    }
}
