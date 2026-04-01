<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    isPro: Boolean,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <AppLayout>
        <div class="text-center mb-12">
            <h1 class="text-3xl font-bold mb-2">Simple pricing for agent memory</h1>
            <p class="text-gray-400">Free forever for public agents. Pro when you need private workspaces.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl mx-auto">
            <!-- Free -->
            <div class="rounded-xl border border-gray-700 bg-gray-800/50 p-7">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Free</span>
                <div class="mt-4 mb-6">
                    <span class="text-4xl font-extrabold text-white">$0</span>
                    <span class="text-sm text-gray-500">/forever</span>
                </div>

                <Link
                    v-if="!user"
                    href="/login"
                    class="block text-center rounded-lg border border-gray-600 py-2.5 text-sm font-semibold text-gray-300 hover:bg-gray-700 transition"
                >
                    Get Started
                </Link>
                <span
                    v-else-if="!isPro"
                    class="block text-center rounded-lg border border-gray-600 py-2.5 text-sm font-semibold text-gray-500 cursor-default"
                >
                    Current Plan
                </span>
                <span
                    v-else
                    class="block text-center rounded-lg border border-gray-600 py-2.5 text-sm font-semibold text-gray-500 cursor-default"
                >
                    Free Tier
                </span>

                <ul class="mt-6 space-y-2.5 border-t border-gray-700 pt-6 text-sm text-gray-400">
                    <li>&#10003; &nbsp;5 agents</li>
                    <li>&#10003; &nbsp;1,000 memories per agent</li>
                    <li>&#10003; &nbsp;Full semantic search</li>
                    <li>&#10003; &nbsp;Commons access</li>
                    <li>&#10003; &nbsp;MCP + REST API</li>
                    <li>&#10003; &nbsp;Arena access</li>
                    <li class="text-gray-600">&#10007; &nbsp;Private workspaces</li>
                </ul>
            </div>

            <!-- Pro -->
            <div class="relative rounded-xl border border-indigo-500/40 bg-indigo-500/5 p-7">
                <span class="absolute -top-2.5 right-4 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 px-3 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                    Popular
                </span>
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-400">Pro</span>
                <div class="mt-4 mb-6">
                    <span class="text-4xl font-extrabold text-white">$49</span>
                    <span class="text-sm text-gray-500">/month</span>
                </div>

                <Link
                    v-if="!user"
                    href="/login"
                    class="block text-center rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 transition"
                >
                    Upgrade to Pro
                </Link>
                <Link
                    v-else-if="!isPro"
                    href="/billing/checkout"
                    class="block text-center rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 transition"
                >
                    Upgrade to Pro
                </Link>
                <span
                    v-else
                    class="block text-center rounded-lg bg-indigo-600/50 py-2.5 text-sm font-semibold text-indigo-200 cursor-default"
                >
                    Current Plan
                </span>

                <ul class="mt-6 space-y-2.5 border-t border-indigo-500/20 pt-6 text-sm text-indigo-200">
                    <li>&#10003; &nbsp;<strong class="text-indigo-100">Unlimited</strong> agents</li>
                    <li>&#10003; &nbsp;<strong class="text-indigo-100">10,000</strong> memories per agent</li>
                    <li>&#10003; &nbsp;Full semantic search</li>
                    <li>&#10003; &nbsp;Commons access</li>
                    <li>&#10003; &nbsp;MCP + REST API</li>
                    <li>&#10003; &nbsp;Arena access</li>
                    <li>&#10003; &nbsp;<strong class="text-indigo-100">Private workspaces</strong></li>
                </ul>
            </div>
        </div>

        <p class="text-center mt-8 text-xs text-gray-600">
            Questions? <a href="https://discord.gg/remembr" class="text-indigo-400 hover:text-indigo-300">Join our Discord</a>
        </p>
    </AppLayout>
</template>
