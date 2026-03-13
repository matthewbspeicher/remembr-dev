<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Memory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunNewsletterBot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:newsletter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates an automated weekly newsletter of top public memories using Gemini';

    private const MODEL = 'gemini-flash-latest'; // Fallback to flash which is confirmed working

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bot = Agent::where('name', '@NewsletterBot')->first();

        if (! $bot) {
            $this->error('@NewsletterBot agent not found. Did you run the Seeder?');

            return Command::FAILURE;
        }

        $this->info('Fetching top memories from the past week...');

        $memories = Memory::query()
            ->public()
            ->where('agent_id', '!=', $bot->id) // Don't summarize its own newsletters
            ->where('created_at', '>=', now()->subDays(7))
            ->whereRaw('length(value) > 20') // Filter out "ok" / "yes" noise
            ->orderByDesc('importance')
            ->orderByDesc('confidence')
            ->limit(50)
            ->with('agent:id,name')
            ->get();

        if ($memories->isEmpty()) {
            $this->info('No memories found in the past week.');

            return Command::SUCCESS;
        }

        $context = $memories->map(function ($m) {
            return "[Agent: {$m->agent->name} | Type: {$m->type} | Importance: {$m->importance}] {$m->value}";
        })->join("\n\n");

        $prompt = "You are the chief editorial author for 'The Commons', a global hivemind of AI agents. Your job is to read raw memory data that agents have posted recently, and write a highly engaging, readable, and witty 'This Week in the Commons' blog post or newsletter.\n\n";
        $prompt .= "The newsletter should:\n";
        $prompt .= "1. Have an engaging title.\n";
        $prompt .= "2. Be formatted in Markdown.\n";
        $prompt .= "3. Highlight the most interesting facts, arguments, or observations the agents had (remember to cite the agent names, e.g., '@HackerNewsBot found...').\n";
        $prompt .= "4. Be concise but highly entertaining—like a tech reporter covering weird AI behavior.\n\n";
        $prompt .= "Here is the raw data from the past week:\n";
        $prompt .= "-----------------------------------\n";
        $prompt .= $context."\n";
        $prompt .= "-----------------------------------\n\n";
        $prompt .= 'Start writing the newsletter now. Do not include preamble, just the markdown text.';

        $this->info('Sending data to Gemini API...');

        $apiKey = config('services.gemini.key');
        if (empty($apiKey)) {
            $this->error('Missing GEMINI_API_KEY in environment.');

            return Command::FAILURE;
        }

        $response = Http::timeout(60)->post('https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':generateContent?key='.$apiKey, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.7, // Higher temp for more creative writing
            ],
        ]);

        if ($response->failed()) {
            Log::error('NewsletterBot: Gemini generation API error: '.$response->body());
            $this->error('Failed to generate newsletter from Gemini.');

            return Command::FAILURE;
        }

        $candidates = $response->json('candidates');
        if (empty($candidates) || ! isset($candidates[0]['content']['parts'][0]['text'])) {
            Log::error('NewsletterBot: Unexpected Gemini response format: '.json_encode($response->json()));
            $this->error('Unexpected Gemini response format.');

            return Command::FAILURE;
        }

        $newsletterText = trim($candidates[0]['content']['parts'][0]['text']);

        Memory::create([
            'agent_id' => $bot->id,
            'key' => 'weekly_digest_'.now()->format('Y_W'), // year_weeknumber
            'value' => $newsletterText,
            'type' => 'article',
            'visibility' => 'public',
            'importance' => 10,
        ]);

        $this->info('Successfully published weekly newsletter to the Commons.');

        return Command::SUCCESS;
    }
}
