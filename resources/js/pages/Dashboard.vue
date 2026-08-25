<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

interface DashboardBuild {
    id: string;
    name: string;
    summary: string | null;
    class: string | null;
    ascendancy: string | null;
    level: number | null;
    game_version: string | null;
    url: string;
    updated_at: string;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    builds: DashboardBuild[];
}>();
</script>

<template>
    <SeoHead title="Dashboard" noindex />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Your builds</h1>
        </div>

        <div
            v-if="builds.length === 0"
            class="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center dark:border-sidebar-border"
        >
            <p class="font-medium">No builds yet</p>
            <p class="max-w-md text-sm text-muted-foreground">
                Connect your AI assistant to the authenticated MCP endpoint and
                ask it to craft and save a build — it will show up here.
            </p>
        </div>

        <div v-else class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Link
                v-for="build in builds"
                :key="build.id"
                :href="build.url"
                class="group"
            >
                <Card
                    class="h-full transition-colors group-hover:border-foreground/30"
                >
                    <CardHeader>
                        <CardTitle class="line-clamp-2">
                            {{ build.name }}
                        </CardTitle>
                        <CardDescription class="line-clamp-3">
                            {{ build.summary ?? 'No summary.' }}
                        </CardDescription>
                        <div
                            class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Badge v-if="build.ascendancy" variant="secondary">
                                {{ build.ascendancy }}
                            </Badge>
                            <Badge v-else-if="build.class" variant="secondary">
                                {{ build.class }}
                            </Badge>
                            <span v-if="build.level"
                                >Level {{ build.level }}</span
                            >
                            <span v-if="build.game_version">
                                {{ build.game_version }}
                            </span>
                            <span>Updated {{ build.updated_at }}</span>
                        </div>
                    </CardHeader>
                </Card>
            </Link>
        </div>
    </div>
</template>
