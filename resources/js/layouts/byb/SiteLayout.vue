<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import { computed, ref } from 'vue';
import Button from '@/components/byb/Button.vue';
import ConnectPanel from '@/components/byb/ConnectPanel.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Dialog from '@/components/byb/Dialog.vue';
import Icon from '@/components/byb/Icon.vue';
import Toaster from '@/components/byb/Toaster.vue';
import { cn } from '@/lib/utils';
import { login, logout, myBuilds } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

type GameLink = {
    name: string;
    short_name?: string;
    slug?: string;
    url?: string;
};

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);

const games = computed<GameLink[]>(() => {
    const shared = page.props.games as GameLink[] | undefined;

    if (shared?.length) {
        return shared;
    }

    return [{ name: 'PoE 2', short_name: 'PoE 2', url: '/' }];
});

const mcpUrl = computed<string>(() => {
    const shared = page.props.mcpUrl as string | undefined;

    if (shared) {
        return shared;
    }

    const origin = typeof window === 'undefined' ? '' : window.location.origin;

    return `${origin}/mcp/poe2`;
});

const connectOpen = ref(false);

function gameHref(game: GameLink): string {
    return game.url ?? (game.slug ? `/${game.slug}` : '/');
}

function gameLabel(game: GameLink): string {
    return game.short_name ?? game.name;
}

function handleLogout(): void {
    router.flushAll();
}

const navLinkClass =
    'font-mono text-[11px] font-bold tracking-[0.14em] text-[var(--fg-2)] uppercase [transition:var(--transition-control)] hover:text-[var(--fg-1)]';

const menuItemClass =
    'flex cursor-pointer items-center gap-2.5 rounded-[var(--radius-xs)] px-2.5 py-2 text-[13px] text-[var(--fg-2)] outline-none [transition:var(--transition-control)] data-highlighted:bg-[var(--surface-card-hover)] data-highlighted:text-[var(--fg-1)]';
</script>

<template>
    <div class="flex min-h-svh flex-col bg-[var(--surface-page)]">
        <header
            class="sticky top-0 z-30 flex h-[var(--layout-topbar)] items-center gap-6 border-b border-[var(--border-subtle)] bg-[var(--overlay-glass)] px-6 [backdrop-filter:var(--blur-glass)]"
        >
            <Link href="/" class="shrink-0 no-underline hover:no-underline">
                <span
                    class="font-display text-[16px] leading-none font-extrabold tracking-[-0.02em] text-[var(--fg-1)]"
                >
                    BUILD<span class="text-[var(--teal-400)]">/</span>YOUR<span
                        class="text-[var(--teal-400)]"
                        >/</span
                    >BUILD
                </span>
            </Link>

            <nav class="flex items-center gap-5 overflow-x-auto">
                <Link
                    v-for="game in games"
                    :key="gameLabel(game)"
                    :href="gameHref(game)"
                    :class="cn(navLinkClass, 'whitespace-nowrap')"
                >
                    {{ gameLabel(game) }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-3">
                <Button
                    size="sm"
                    variant="ghost"
                    icon="plug-zap"
                    @click="connectOpen = true"
                >
                    Connect MCP
                </Button>

                <template v-if="user">
                    <Link :href="myBuilds()" :class="navLinkClass">
                        My builds
                    </Link>

                    <DropdownMenuRoot>
                        <DropdownMenuTrigger
                            class="inline-flex h-[var(--control-h-sm)] items-center gap-1.5 rounded-[var(--radius-sm)] border border-[var(--border-subtle)] px-2.5 font-mono text-[12px] whitespace-nowrap text-[var(--fg-2)] outline-none [transition:var(--transition-control)] hover:border-[var(--border-strong)] hover:text-[var(--fg-1)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--focus-ring)]"
                        >
                            <Icon name="user" :size="13" />
                            {{ user.handle ?? user.name }}
                            <Icon name="chevron-down" :size="11" />
                        </DropdownMenuTrigger>
                        <DropdownMenuPortal>
                            <DropdownMenuContent
                                :side-offset="6"
                                align="end"
                                class="z-50 min-w-[180px] rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] p-1.5 [box-shadow:var(--shadow-2)]"
                            >
                                <DropdownMenuItem
                                    as-child
                                    :class="menuItemClass"
                                >
                                    <Link :href="editProfile()">
                                        <Icon name="settings" :size="13" />
                                        Settings
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator
                                    class="my-1.5 h-px bg-[var(--border-hairline)]"
                                />
                                <DropdownMenuItem
                                    as-child
                                    :class="menuItemClass"
                                >
                                    <Link
                                        :href="logout()"
                                        as="button"
                                        data-test="logout-button"
                                        class="w-full"
                                        @click="handleLogout"
                                    >
                                        <Icon name="log-out" :size="13" />
                                        Log out
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenuPortal>
                    </DropdownMenuRoot>
                </template>

                <Button v-else size="sm" variant="ghost" as-child>
                    <Link :href="login()">Sign in</Link>
                </Button>
            </div>
        </header>

        <main class="flex-1">
            <div
                class="mx-auto w-full max-w-[var(--layout-max)] px-[var(--layout-gutter)]"
            >
                <slot />
            </div>
        </main>

        <footer class="mt-16 border-t border-[var(--border-subtle)] py-6">
            <div
                class="mx-auto flex w-full max-w-[var(--layout-max)] items-center gap-4 px-[var(--layout-gutter)]"
            >
                <span :class="LABEL_CLASS">Build Your Build</span>
                <button
                    type="button"
                    :class="cn(navLinkClass, 'ml-auto')"
                    @click="connectOpen = true"
                >
                    Connect MCP
                </button>
            </div>
        </footer>

        <Dialog
            v-model:open="connectOpen"
            eyebrow="MCP server"
            title="Connect Build Your Build"
            description="Add it in your client settings. Pick the client you use."
            :width="560"
        >
            <ConnectPanel :mcp-url="mcpUrl" filename="server url" />
        </Dialog>

        <Toaster />
    </div>
</template>
