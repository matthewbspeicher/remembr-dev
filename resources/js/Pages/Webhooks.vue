<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    webhooks: Array,
    availableEvents: Array,
});

const showModal = ref(false);
const showingSecret = ref(null);

function toggleSecret(id) {
    if (showingSecret.value === id) {
        showingSecret.value = null;
    } else {
        showingSecret.value = id;
    }
}

const webhookForm = useForm({
    url: '',
    events: [],
    semantic_query: '',
});

function createWebhook() {
    webhookForm.post('/dashboard/webhooks', {
        onSuccess: () => {
            showModal.value = false;
            webhookForm.reset();
        },
    });
}

function deleteWebhook(id) {
    if (confirm('Are you sure you want to delete this webhook?')) {
        webhookForm.delete(`/dashboard/webhooks/${id}`);
    }
}

function testWebhook(id) {
    webhookForm.post(`/dashboard/webhooks/${id}/test`);
}
</script>

<template>
    <Head title="Webhook Management" />
    <AppLayout>
        <div class="max-w-5xl mx-auto py-12">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">Webhooks</h1>
                    <p class="text-gray-400">Receive real-time notifications for agent and memory events.</p>
                </div>
                <button 
                    @click="showModal = true"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2 rounded-lg text-sm transition"
                >
                    Add Webhook
                </button>
            </div>

            <div v-if="webhooks.length === 0" class="bg-gray-900/40 border border-gray-800 rounded-xl p-12 text-center">
                <div class="text-4xl mb-4">🪝</div>
                <h3 class="text-xl font-bold text-white mb-2">No webhooks configured</h3>
                <p class="text-gray-500 mb-6">Create a webhook to start receiving event notifications.</p>
                <button @click="showModal = true" class="text-indigo-400 font-bold hover:text-indigo-300 transition underline">
                    Register your first webhook
                </button>
            </div>

            <div v-else class="grid gap-6">
                <div v-for="webhook in webhooks" :key="webhook.id" class="bg-gray-900/40 border border-gray-800 rounded-xl p-6">
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-bold text-white truncate">{{ webhook.url }}</h3>
                                <span v-if="webhook.is_active" class="bg-emerald-500/10 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/20">ACTIVE</span>
                                <span v-else class="bg-red-500/10 text-red-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-red-500/20">INACTIVE</span>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span v-for="event in webhook.events" :key="event" class="bg-gray-800 text-gray-400 text-[10px] px-2 py-0.5 rounded border border-gray-700">
                                    {{ event }}
                                </span>
                            </div>

                            <div v-if="webhook.semantic_query" class="mb-4">
                                <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold block mb-1">Semantic Match Query</span>
                                <p class="text-xs text-indigo-300 italic">"{{ webhook.semantic_query }}"</p>
                            </div>

                            <div class="mb-4">
                                <span class="text-[10px] text-gray-500 uppercase tracking-widest font-bold block mb-1">Webhook Secret</span>
                                <div class="flex items-center gap-2">
                                    <code class="text-xs font-mono text-gray-400 bg-black/30 px-2 py-1 rounded border border-gray-800">
                                        {{ showingSecret === webhook.id ? webhook.secret : 'whsec_••••••••••••••••••••••••••••••••' }}
                                    </code>
                                    <button @click="toggleSecret(webhook.id)" class="text-[10px] text-indigo-400 hover:text-indigo-300 transition uppercase font-bold tracking-tighter">
                                        {{ showingSecret === webhook.id ? 'Hide' : 'Reveal' }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-[10px] text-gray-500">
                                <span>Created {{ new Date(webhook.created_at).toLocaleDateString() }}</span>
                                <span v-if="webhook.failure_count > 0" class="text-amber-500">{{ webhook.failure_count }} failures</span>
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center gap-2">
                            <button @click="testWebhook(webhook.id)" class="text-xs text-gray-400 hover:text-white transition px-3 py-1.5 rounded bg-gray-800 border border-gray-700">
                                Test
                            </button>
                            <button @click="deleteWebhook(webhook.id)" class="text-xs text-red-400 hover:text-red-300 transition px-3 py-1.5 rounded bg-red-900/20 border border-red-900/30">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Webhook Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8 max-w-xl w-full">
                <h2 class="text-2xl font-bold text-white mb-6">Register New Webhook</h2>
                
                <form @submit.prevent="createWebhook" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Endpoint URL (HTTPS only)</label>
                        <input 
                            v-model="webhookForm.url"
                            type="url" 
                            required
                            placeholder="https://your-agent.com/webhooks"
                            class="w-full bg-black border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Events to Subscribe</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label v-for="event in availableEvents" :key="event" class="flex items-center gap-3 p-3 rounded-lg border border-gray-800 bg-gray-800/50 cursor-pointer hover:bg-gray-800 transition">
                                <input type="checkbox" :value="event" v-model="webhookForm.events" class="rounded border-gray-700 text-indigo-600 focus:ring-indigo-500 bg-black">
                                <span class="text-xs text-gray-300 font-mono">{{ event }}</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="webhookForm.events.includes('memory.semantic_match')">
                        <label class="block text-sm font-medium text-gray-400 mb-2">Semantic Match Query</label>
                        <textarea 
                            v-model="webhookForm.semantic_query"
                            rows="2"
                            placeholder="What concepts should trigger this webhook?"
                            class="w-full bg-black border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:ring-2 focus:ring-indigo-500 outline-none transition text-sm"
                        ></textarea>
                        <p class="mt-2 text-[10px] text-gray-500 italic">Example: "high-frequency trading strategies" or "neural network optimization tips"</p>
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-800">
                        <button type="button" @click="showModal = false" class="text-gray-400 hover:text-white transition text-sm font-medium">Cancel</button>
                        <button 
                            type="submit" 
                            :disabled="webhookForm.processing"
                            class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold px-6 py-2.5 rounded-lg text-sm transition"
                        >
                            Save Webhook
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
