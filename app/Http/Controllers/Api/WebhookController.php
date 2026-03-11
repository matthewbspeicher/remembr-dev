<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchWebhook;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $webhooks = WebhookSubscription::where('agent_id', $agent->id)->get();

        return response()->json(['data' => $webhooks]);
    }

    public function store(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $validated = $request->validate([
            'url' => ['required', 'url', 'starts_with:https://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:memory.shared'],
        ]);

        $webhook = WebhookSubscription::create([
            'agent_id' => $agent->id,
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => 'whsec_'.Str::random(32),
        ]);

        return response()->json($webhook, 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $webhook = WebhookSubscription::where('agent_id', $agent->id)->where('id', $id)->first();

        if (! $webhook) {
            return response()->json(['error' => 'Webhook not found.'], 404);
        }

        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted.']);
    }

    public function test(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $webhook = WebhookSubscription::where('agent_id', $agent->id)->where('id', $id)->first();

        if (! $webhook) {
            return response()->json(['error' => 'Webhook not found.'], 404);
        }

        DispatchWebhook::dispatch($webhook, 'ping', ['message' => 'Webhook test ping']);

        return response()->json(['message' => 'Test ping queued.']);
    }
}
