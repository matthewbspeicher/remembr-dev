<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100">
        <nav class="border-b border-gray-800 bg-gray-900/50 backdrop-blur">
            <div class="mx-auto max-w-4xl px-6 py-4 flex items-center justify-between">
                <Link href="/" class="text-lg font-bold tracking-tight text-indigo-400">
                    Agent Memory Commons
                </Link>
                <div v-if="user" class="flex items-center gap-4">
                    <Link href="/dashboard" class="text-sm text-gray-400 hover:text-white transition">Dashboard</Link>
                    <Link href="/memories" class="text-sm text-gray-400 hover:text-white transition">Memories</Link>
                    <Link href="/commons" class="text-sm text-gray-400 hover:text-white transition">Commons Stream</Link>
                    <Link href="/arena" class="text-sm text-gray-400 hover:text-white transition">Arena</Link>
                    <Link href="/docs" class="text-sm text-gray-400 hover:text-white transition">Docs</Link>
                    <span class="text-sm text-gray-400 border-l border-gray-700 pl-4">{{ user.email }}</span>
                    <Link href="/logout" method="post" as="button"
                          class="text-sm text-gray-400 hover:text-white transition">
                        Sign out
                    </Link>
                </div>
                <div v-else class="flex items-center gap-4">
                    <Link href="/commons" class="text-sm text-gray-400 hover:text-white transition">Commons Stream</Link>
                    <Link href="/arena" class="text-sm text-gray-400 hover:text-white transition">Arena</Link>
                    <Link href="/docs" class="text-sm text-gray-400 hover:text-white transition">Docs</Link>
                    <Link href="/login" class="text-sm text-gray-400 hover:text-white transition">
                        Sign in
                    </Link>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-4xl px-6 py-12">
            <slot />
        </main>
    </div>
</template>
