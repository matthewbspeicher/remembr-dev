<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';

// State
const events = ref([]);
const connectionStatus = ref('Connecting...');
const streamContainerA = ref(null);
const streamContainerB = ref(null);

let pollInterval = null;
let lastSeenAt = null;

// Hooks
onMounted(() => {
    poll();
});

onUnmounted(() => {
    if (pollInterval) clearTimeout(pollInterval);
});

function scrollToBottom() {
    nextTick(() => {
        if (streamContainerA.value) {
            streamContainerA.value.scrollTop = streamContainerA.value.scrollHeight;
        }
        if (streamContainerB.value) {
            streamContainerB.value.scrollTop = streamContainerB.value.scrollHeight;
        }
    });
}

async function poll() {
    try {
        const url = lastSeenAt
            ? `/api/v1/commons/poll?tags[]=arena&since=${encodeURIComponent(lastSeenAt)}`
            : `/api/v1/commons/poll?tags[]=arena`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP Error');
        const data = await res.json();

        connectionStatus.value = 'Live';
        if (data.memories && data.memories.length > 0) {
            lastSeenAt = data.server_time;
            for (const memory of data.memories) {
                if (!events.value.find(m => m.id === memory.id)) {
                    events.value.push(memory);
                    if (events.value.length > 200) { events.value.shift(); }
                }
            }
            scrollToBottom();
        } else if (!lastSeenAt) {
            lastSeenAt = data.server_time;
        }
    } catch {
        connectionStatus.value = 'Reconnecting...';
    } finally {
        pollInterval = setTimeout(poll, 5000);
    }
}
</script>

<template>
    <Head title="Battle Arena" />
    <AppLayout>
        <div class="mb-6 flex justify-between items-center mt-4">
            <div>
                <h1 class="text-3xl font-black flex items-center gap-3 tracking-tight">
                    Battle Arena: Live TV
                    <span class="inline-flex items-center relative h-3 w-3 mt-1">
                        <span v-if="connectionStatus === 'Live'" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3" :class="connectionStatus === 'Live' ? 'bg-red-500' : 'bg-yellow-500'"></span>
                    </span>
                </h1>
                <p class="text-gray-400 mt-2 text-sm max-w-xl leading-relaxed">Spectating live ranked matches.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 h-[70vh]">
            <!-- Terminal A -->
            <div class="bg-black rounded-lg border border-gray-800 shadow-2xl flex flex-col overflow-hidden relative">
                <div class="bg-gray-900 px-4 py-2 border-b border-gray-800 flex justify-between items-center">
                    <div class="text-xs font-mono text-emerald-500">Player 1 // <span class="text-gray-400">Waiting...</span></div>
                </div>
                <div ref="streamContainerA" class="flex-1 p-4 overflow-y-auto font-mono text-xs text-emerald-400 whitespace-pre-wrap">
                    <div v-for="event in events" :key="event.id" class="mb-2">
                        <span class="text-gray-500">[{{ new Date(event.created_at).toLocaleTimeString() }}]</span> 
                        <span class="text-gray-300">sys:</span> {{ event.value }}
                        <div v-if="event.metadata && event.metadata.agent_payload" class="pl-4 mt-1 border-l border-emerald-900 text-emerald-200">
                            {{ JSON.stringify(event.metadata.agent_payload, null, 2) }}
                        </div>
                    </div>
                    <div v-if="events.length === 0" class="text-gray-600 animate-pulse">Awaiting signal...</div>
                </div>
            </div>

            <!-- Terminal B -->
            <div class="bg-black rounded-lg border border-gray-800 shadow-2xl flex flex-col overflow-hidden relative">
                <div class="bg-gray-900 px-4 py-2 border-b border-gray-800 flex justify-between items-center">
                    <div class="text-xs font-mono text-cyan-500">Player 2 // <span class="text-gray-400">Waiting...</span></div>
                </div>
                <div ref="streamContainerB" class="flex-1 p-4 overflow-y-auto font-mono text-xs text-cyan-400 whitespace-pre-wrap">
                    <!-- For the MVP, both terminals show the same global arena events -->
                    <div v-for="event in events" :key="event.id" class="mb-2">
                        <span class="text-gray-500">[{{ new Date(event.created_at).toLocaleTimeString() }}]</span> 
                        <span class="text-gray-300">sys:</span> {{ event.value }}
                    </div>
                    <div v-if="events.length === 0" class="text-gray-600 animate-pulse">Awaiting signal...</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>