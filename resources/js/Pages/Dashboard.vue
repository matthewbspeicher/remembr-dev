<script setup>
import { useForm, usePage, router, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    apiToken: String,
    agents: Array,
    workspaces: Array,
    isPro: Boolean,
    isOnGracePeriod: Boolean,
    hasPaymentFailure: Boolean,
    isDowngraded: Boolean,
    currentPlan: String,
    agentCount: Number,
    maxAgents: [Number, String],
    avgMemoriesPerAgent: Number,
    maxMemoriesPerAgent: Number,
});

const page = usePage();
const flash = computed(() => page.props.flash?.message);

const copied = ref(false);
function copyToken(token) {
    navigator.clipboard.writeText(token);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

const agentForm = useForm({
    name: '',
    description: '',
});

function registerAgent() {
    agentForm.post('/dashboard/agents', {
        onSuccess: () => agentForm.reset(),
    });
}

function rotateAgentToken(agentId) {
    if (confirm('Are you sure you want to rotate this agent\'s API token? The old token will be immediately invalidated.')) {
        router.post(`/dashboard/agents/${agentId}/rotate`, {}, {
            preserveScroll: true,
        });
    }
}

function deleteAgent(agentId) {
    if (confirm('Are you sure you want to permanently delete this agent? This cannot be undone.')) {
        router.delete(`/dashboard/agents/${agentId}`, {
            preserveScroll: true,
        });
    }
}

function rotateOwnerToken() {
    if (confirm('Are you sure you want to rotate your Owner API token? Any services using the current token will immediately lose access.')) {
        router.post('/dashboard/token/rotate', {}, {
            preserveScroll: true,
        });
    }
}

function getConfigJson(agent) {
    return JSON.stringify({
        mcpServers: {
            remembr: {
                command: 'npx',
                args: ['-y', '@remembr/mcp-server'],
                env: { REMEMBR_AGENT_TOKEN: agent.api_token }
            }
        }
    }, null, 2);
}

function copyConfig(agent) {
    navigator.clipboard.writeText(getConfigJson(agent));
}

const workspaceForm = useForm({
    name: '',
    description: '',
});

function createWorkspace() {
    workspaceForm.post('/workspaces', {
        preserveScroll: true,
        onSuccess: () => workspaceForm.reset(),
    });
}

const agentUsagePercent = computed(() => {
    if (props.maxAgents === 'unlimited') return 5;
    return Math.min(100, (props.agentCount / props.maxAgents) * 100);
});

const memoryUsagePercent = computed(() => {
    return Math.min(100, (props.avgMemoriesPerAgent / props.maxMemoriesPerAgent) * 100);
});
</script>

<template>
    <AppLayout>
        <h1 class="text-3xl font-bold mb-8">Dashboard</h1>

        <div v-if="flash" class="mb-6 rounded-lg bg-emerald-900/30 border border-emerald-700/50 px-4 py-3 text-emerald-200 text-sm">
            {{ flash }}
        </div>

        <!-- Billing Banners -->
        <div v-if="hasPaymentFailure" class="mb-6 rounded-lg border border-red-800/50 bg-red-900/20 px-4 py-3 flex items-center gap-2">
            <span class="text-red-400">&#9888;</span>
            <span class="text-sm text-red-200">Payment failed. <a href="/billing/portal" class="text-red-400 underline">Update payment method</a> to keep Pro access.</span>
        </div>

        <div v-if="isOnGracePeriod" class="mb-6 rounded-lg border border-amber-800/50 bg-amber-900/20 px-4 py-3 text-sm text-amber-200">
            Your Pro subscription has been cancelled and will end at the end of the current billing period. <a href="/billing/portal" class="text-amber-400 underline">Resubscribe</a>
        </div>

        <div v-if="isDowngraded" class="mb-6 rounded-lg border border-amber-800/50 bg-amber-900/20 px-4 py-3 text-sm text-amber-200">
            Your account has features beyond the free plan. Some agents and workspace memories are read-only. <Link href="/billing/checkout" class="text-amber-400 underline">Upgrade to Pro</Link> to restore full access.
        </div>

        <!-- Billing Section -->
        <section class="mb-10">
            <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Current Plan</span>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xl font-bold text-white">
                                {{ currentPlan === 'pro' ? 'Pro' : currentPlan === 'unlimited' ? 'Unlimited' : 'Free' }}
                            </span>
                            <span v-if="isPro" class="text-[10px] font-semibold bg-indigo-500/15 text-indigo-400 px-2 py-0.5 rounded-full">ACTIVE</span>
                            <span v-else-if="isDowngraded" class="text-[10px] font-semibold bg-amber-500/15 text-amber-400 px-2 py-0.5 rounded-full">DOWNGRADED</span>
                        </div>
                    </div>
                    <div>
                        <a v-if="isPro" href="/billing/portal" class="text-xs text-gray-400 border border-gray-600 px-3 py-1.5 rounded-md hover:bg-gray-700 transition">
                            Manage Subscription
                        </a>
                        <Link v-else href="/billing/checkout" class="text-xs font-semibold bg-indigo-600 text-white px-3 py-1.5 rounded-md hover:bg-indigo-500 transition">
                            Upgrade to Pro
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <div class="flex justify-between mb-1.5">
                            <span class="text-xs text-gray-400">Agents</span>
                            <span class="text-xs font-semibold text-gray-200">{{ agentCount }} / {{ maxAgents }}</span>
                        </div>
                        <div class="bg-gray-700 rounded h-1.5 overflow-hidden">
                            <div class="bg-indigo-500 h-full rounded" :style="{ width: agentUsagePercent + '%' }"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1.5">
                            <span class="text-xs text-gray-400">Avg memories/agent</span>
                            <span class="text-xs font-semibold text-gray-200">{{ avgMemoriesPerAgent.toLocaleString() }} / {{ maxMemoriesPerAgent.toLocaleString() }}</span>
                        </div>
                        <div class="bg-gray-700 rounded h-1.5 overflow-hidden">
                            <div class="bg-indigo-500 h-full rounded" :style="{ width: memoryUsagePercent + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Owner API Token -->
        <section class="mb-10">
            <h2 class="text-lg font-semibold mb-3 text-gray-200">Your Owner API Token</h2>
            <div class="flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-800 px-4 py-3">
                <code class="flex-1 text-sm text-indigo-300 font-mono break-all">{{ apiToken }}</code>
                <button
                    @click="copyToken(apiToken)"
                    class="shrink-0 rounded bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-200 hover:bg-gray-600 transition"
                >
                    {{ copied ? 'Copied!' : 'Copy' }}
                </button>
                <button
                    @click="rotateOwnerToken"
                    class="shrink-0 rounded border border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-300 hover:text-white hover:bg-gray-700 transition"
                >
                    Rotate
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">Use this token to register agents via the API.</p>
        </section>

        <!-- Register Agent -->
        <section class="mb-10">
            <h2 class="text-lg font-semibold mb-3 text-gray-200">Register New Agent</h2>
            <form @submit.prevent="registerAgent" class="space-y-4">
                <div>
                    <label for="agent-name" class="block text-sm font-medium text-gray-300 mb-1">Agent Name</label>
                    <input
                        id="agent-name"
                        v-model="agentForm.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-4 py-2.5 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="My Agent"
                    />
                    <p v-if="agentForm.errors.name" class="mt-1 text-sm text-red-400">{{ agentForm.errors.name }}</p>
                </div>
                <div>
                    <label for="agent-desc" class="block text-sm font-medium text-gray-300 mb-1">Description (optional)</label>
                    <input
                        id="agent-desc"
                        v-model="agentForm.description"
                        type="text"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-4 py-2.5 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="What does this agent do?"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="agentForm.processing"
                    class="rounded-lg bg-indigo-600 px-4 py-2.5 font-medium text-white hover:bg-indigo-500 disabled:opacity-50 transition"
                >
                    {{ agentForm.processing ? 'Creating...' : 'Create Agent' }}
                </button>
            </form>
        </section>

        <!-- Agents List -->
        <section class="mb-10">
            <h2 class="text-lg font-semibold mb-3 text-gray-200">Your Agents</h2>
            <div v-if="agents.length === 0" class="text-gray-500 text-sm">
                No agents registered yet.
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="agent in agents"
                    :key="agent.id"
                    class="rounded-lg border border-gray-700 bg-gray-800/50 px-4 py-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="font-medium text-white">{{ agent.name }}</span>
                            <span class="text-xs text-gray-500">{{ new Date(agent.created_at).toLocaleDateString() }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="rotateAgentToken(agent.id)"
                                class="rounded bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-300 hover:text-white hover:bg-gray-600 transition"
                            >
                                Rotate Token
                            </button>
                            <button
                                @click="deleteAgent(agent.id)"
                                class="rounded bg-red-900/40 border border-red-800/50 px-3 py-1.5 text-xs font-medium text-red-400 hover:text-red-200 hover:bg-red-800/60 transition"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                    <p v-if="agent.description" class="mt-2 text-sm text-gray-400">{{ agent.description }}</p>
                    <div class="mt-3 rounded-lg bg-gray-800/50 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-mono text-gray-400">Claude Desktop / Cursor config</span>
                            <button @click="copyConfig(agent)" class="text-xs text-indigo-400 hover:text-indigo-300 transition">
                                Copy
                            </button>
                        </div>
                        <pre class="text-xs text-gray-300 overflow-x-auto whitespace-pre"><code>{{ getConfigJson(agent) }}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Create Workspace -->
        <section class="mb-10">
            <h2 class="text-lg font-semibold mb-3 text-gray-200">Create a Workspace</h2>
            <form @submit.prevent="createWorkspace" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="ws-name" class="block text-sm font-medium text-gray-300 mb-1">Workspace Name</label>
                        <input
                            id="ws-name"
                            v-model="workspaceForm.name"
                            type="text"
                            required
                            class="w-full rounded-lg border border-gray-700 bg-gray-800 px-4 py-2.5 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                            placeholder="My Team"
                        />
                        <p v-if="workspaceForm.errors.name" class="mt-1 text-sm text-red-400">{{ workspaceForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="ws-desc" class="block text-sm font-medium text-gray-300 mb-1">Description (optional)</label>
                        <input
                            id="ws-desc"
                            v-model="workspaceForm.description"
                            type="text"
                            class="w-full rounded-lg border border-gray-700 bg-gray-800 px-4 py-2.5 text-white placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                            placeholder="For internal docs & syncs"
                        />
                    </div>
                </div>
                <button
                    type="submit"
                    :disabled="workspaceForm.processing"
                    class="rounded-lg bg-blue-600 px-4 py-2.5 font-medium text-white hover:bg-blue-500 disabled:opacity-50 transition"
                >
                    {{ workspaceForm.processing ? 'Creating...' : 'Create Workspace' }}
                </button>
            </form>
        </section>

        <!-- Workspaces List -->
        <section>
            <h2 class="text-lg font-semibold mb-3 text-gray-200">Your Workspaces</h2>
            <div v-if="workspaces.length === 0" class="text-gray-500 text-sm">
                No workspaces available.
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="ws in workspaces"
                    :key="ws.id"
                    class="rounded-lg border border-gray-700 bg-gray-800/50 px-4 py-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="font-medium text-white">{{ ws.name }}</span>
                            <span class="text-sm text-gray-400" v-if="ws.description">{{ ws.description }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a
                                v-if="page.props.auth.user.id === ws.owner_id"
                                :href="`/workspaces/${ws.id}/settings`"
                                class="rounded border border-indigo-600/50 hover:border-indigo-500 bg-indigo-900/20 px-4 py-2 text-sm font-medium text-indigo-300 hover:text-indigo-200 transition"
                            >
                                Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
