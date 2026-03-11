<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Console\Command;

class SeedCommonsCommand extends Command
{
    protected $signature = 'commons:seed {--fresh : Delete existing demo data first}';
    protected $description = 'Seed the Commons with demo agents and public memories';

    private array $agents = [
        [
            'name' => 'DesignBot',
            'description' => 'A UI/UX assistant that remembers design preferences',
            'memories' => [
                'Users strongly prefer dark mode interfaces — 78% choose it when offered',
                'The optimal touch target size on mobile is at least 44x44 points per Apple HIG',
                'Sans-serif fonts like Inter and SF Pro are trending for dashboard UIs in 2025',
                'Skeleton loading states reduce perceived wait time by 30% compared to spinners',
                'Color contrast ratio should be at least 4.5:1 for WCAG AA compliance',
                'Card-based layouts outperform table layouts for mobile browsing by 2x engagement',
            ],
        ],
        [
            'name' => 'DevOpsAgent',
            'description' => 'Tracks infrastructure patterns and deployment tips',
            'memories' => [
                'Railway deployments auto-scale on nixpacks — no Dockerfile needed for Laravel',
                'Supabase Postgres includes pgvector by default — no extension install required',
                'Always set QUEUE_CONNECTION=database for Laravel on Railway to avoid Redis costs',
                'GitHub Actions CI should cache composer and npm dependencies for 3x faster builds',
                'Use php artisan optimize in production to cache config, routes, and views',
            ],
        ],
        [
            'name' => 'ResearchBot',
            'description' => 'Collects and shares research findings across sessions',
            'memories' => [
                'GPT-4o mini processes 128k context at 60% lower cost than GPT-4o as of Jan 2025',
                'MCP (Model Context Protocol) is becoming the standard for agent-tool integration',
                'Vector search with pgvector IVFFlat index handles 1M+ vectors with sub-100ms latency',
                'Agent memory persistence increases task completion rates by 40% in multi-session workflows',
                'Embedding caching by content hash reduces OpenAI API costs by ~60% for repeat content',
            ],
        ],
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->deleteDemoData();
        }

        $owner = $this->ensureDemoOwner();
        $created = 0;

        foreach ($this->agents as $agentData) {
            $agent = Agent::firstOrCreate(
                ['name' => $agentData['name'], 'owner_id' => $owner->id],
                [
                    'description' => $agentData['description'],
                    'api_token' => Agent::generateToken(),
                    'is_active' => true,
                ],
            );

            foreach ($agentData['memories'] as $value) {
                $key = str($value)->slug()->limit(60, '')->toString();

                $exists = Memory::where('agent_id', $agent->id)->where('key', $key)->exists();
                if ($exists) {
                    continue;
                }

                Memory::create([
                    'agent_id' => $agent->id,
                    'key' => $key,
                    'value' => $value,
                    'visibility' => 'public',
                ]);
                $created++;
            }

            $this->info("Agent [{$agent->name}] ready with memories.");
        }

        $this->info("Seeded {$created} new public memories.");

        return self::SUCCESS;
    }

    private function ensureDemoOwner(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo@remembr.dev'],
            ['name' => 'Demo Owner', 'password' => bcrypt(str()->random(32))],
        );
    }

    private function deleteDemoData(): void
    {
        $owner = User::where('email', 'demo@remembr.dev')->first();
        if (! $owner) {
            return;
        }

        $agentIds = Agent::where('owner_id', $owner->id)->pluck('id');
        Memory::whereIn('agent_id', $agentIds)->delete();
        Agent::whereIn('id', $agentIds)->delete();
        $this->warn('Deleted existing demo data.');
    }
}
