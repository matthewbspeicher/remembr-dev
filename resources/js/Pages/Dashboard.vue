<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    apiToken: String,
    agents: Array,
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
</script>

<template>
    <AppLayout>
        <h1 class="text-3xl font-bold mb-8">Dashboard</h1>

        <div v-if="flash" class="mb-6 rounded-lg bg-emerald-900/30 border border-emerald-700/50 px-4 py-3 text-emerald-200 text-sm">
            {{ flash }}
        </div>

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
        <section>
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
                        <span class="font-medium text-white">{{ agent.name }}</span>
                        <span class="text-xs text-gray-500">{{ new Date(agent.created_at).toLocaleDateString() }}</span>
                    </div>
                    <p v-if="agent.description" class="mt-1 text-sm text-gray-400">{{ agent.description }}</p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
