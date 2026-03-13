<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SummarizationService
{
    private const MODEL = 'gemini-flash-latest';

    private readonly string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? '';
    }

    /**
     * Compact a collection of memories into a single summary string.
     */
    public function summarize(Collection $memories, Agent $agent): string
    {
        if ($memories->isEmpty()) {
            return '';
        }

        $context = $memories->map(fn ($m) => "- [{$m->key}] {$m->value}")->join("\n");

        $prompt = "You are an AI assistant helping to compact and summarize the memories of an agent named '{$agent->name}'.\n\n";
        $prompt .= "Below are several granular memories. Please synthesize them into a single, high-density, comprehensive summary that retains all the critical facts, insights, and conclusions, but removes redundancy and extreme verbosity.\n\n";
        $prompt .= "Memories to compact:\n".$context."\n\n";
        $prompt .= 'Provide ONLY the final summary text.';

        $response = Http::post('https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':generateContent?key='.$this->apiKey, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini generation API error: '.$response->body());
        }

        $candidates = $response->json('candidates');
        if (empty($candidates) || ! isset($candidates[0]['content']['parts'][0]['text'])) {
            throw new RuntimeException('Unexpected Gemini response format: '.json_encode($response->json()));
        }

        return trim($candidates[0]['content']['parts'][0]['text']);
    }
}
