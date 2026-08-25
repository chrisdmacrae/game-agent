<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Badge from '@/components/byb/Badge.vue';
import Button from '@/components/byb/Button.vue';
import Card from '@/components/byb/Card.vue';
import { LABEL_CLASS } from '@/components/byb/controls';
import Icon from '@/components/byb/Icon.vue';
import { bybIcons } from '@/components/byb/icons';
import type { IconName } from '@/components/byb/icons';
import StatBlock from '@/components/byb/StatBlock.vue';
import Tag from '@/components/byb/Tag.vue';
import { stageColor } from '@/components/byb/tokens';
import PublishBuildDialog from '@/components/PublishBuildDialog.vue';
import SeoHead from '@/components/SeoHead.vue';
import { compactNumber, gameMcpUrl, stageLabel } from '@/lib/hub';
import { cn } from '@/lib/utils';
import { edit as editBuild } from '@/routes/games/builds';
import { edit as editProfile } from '@/routes/profile';
import type { HubBuild, MyBuildsGroup, MyBuildsStats } from '@/types/hub';

/**
 * `/my-builds` (scope §3.6). Grouped by game with drafts pinned to the top of
 * each group — the drafts are the work in progress, so they lead.
 */
const props = defineProps<{
    groups: MyBuildsGroup[];
    stats: MyBuildsStats;
    handle: string;
}>();

const page = usePage();

const publishOpen = ref(false);

/** Publishing starts in the assistant, so the dialog shows the live game's server. */
const mcpUrl = computed(() =>
    gameMcpUrl(
        page.props.mcpUrl,
        props.groups.find((group) => group.game.is_live)?.game.slug ?? 'poe2',
    ),
);

function gameIcon(icon: string): IconName {
    return icon in bybIcons ? (icon as IconName) : 'swords';
}

function gameAccent(accent: string): string {
    return `var(--${accent})`;
}

/** "3 builds · 1 draft", or just the build count when nothing is unpublished. */
function groupMeta(builds: HubBuild[]): string {
    const drafts = builds.filter(
        (build) => build.visibility === 'draft',
    ).length;

    const parts = [
        `${builds.length} ${builds.length === 1 ? 'build' : 'builds'}`,
    ];

    if (drafts > 0) {
        parts.push(`${drafts} draft${drafts === 1 ? '' : 's'}`);
    }

    return parts.join(' · ');
}

function classLine(build: HubBuild): string {
    return [build.class, build.ascendancy].filter(Boolean).join(' / ');
}
</script>

<template>
    <div>
        <SeoHead />

        <div class="py-10">
            <div class="mb-8 flex flex-wrap items-end gap-4">
                <div>
                    <p :class="cn(LABEL_CLASS, 'text-[var(--teal-400)]')">
                        Signed in as {{ handle }}
                    </p>
                    <h1
                        class="mt-4 font-display text-[36px] leading-none font-bold tracking-[-0.02em] text-[var(--fg-1)]"
                    >
                        My builds
                    </h1>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <Button size="sm" variant="ghost" icon="settings" as-child>
                        <Link :href="editProfile()">Settings</Link>
                    </Button>
                    <Button
                        size="sm"
                        variant="accent"
                        icon="plus"
                        @click="publishOpen = true"
                    >
                        Publish build
                    </Button>
                </div>
            </div>

            <div
                class="mb-10 flex flex-wrap gap-8 border-b border-[var(--border-subtle)] pb-7"
            >
                <StatBlock label="Published" :value="stats.published" />
                <StatBlock
                    label="Drafts"
                    :value="stats.drafts"
                    tone="var(--mag-400)"
                />
                <StatBlock
                    label="Endorsements"
                    :value="stats.endorsements"
                    icon="flame"
                    tone="var(--teal-400)"
                />
                <StatBlock
                    label="Member since"
                    :value="stats.member_since ?? 'unknown'"
                />
            </div>

            <section
                v-for="group in groups"
                :key="group.game.slug"
                class="mb-10"
            >
                <div class="mb-5 flex flex-wrap items-center gap-4">
                    <span
                        class="inline-flex"
                        :style="{ color: gameAccent(group.game.accent) }"
                    >
                        <Icon :name="gameIcon(group.game.icon)" :size="20" />
                    </span>
                    <h2
                        class="font-display text-[22px] leading-[1.2] font-bold tracking-[-0.02em] text-[var(--fg-1)]"
                    >
                        {{ group.game.name }}
                    </h2>
                    <span
                        v-if="group.builds.length"
                        class="font-mono text-[12px] text-[var(--fg-3)]"
                    >
                        {{ groupMeta(group.builds) }}
                    </span>
                    <Link
                        :href="group.game.url"
                        :class="
                            cn(
                                LABEL_CLASS,
                                'ml-auto text-[var(--teal-400)] no-underline hover:no-underline',
                            )
                        "
                    >
                        {{ group.game.is_live ? 'Open hub' : 'Open waitlist' }}
                    </Link>
                </div>

                <ul
                    v-if="group.builds.length"
                    class="flex list-none flex-col gap-5 p-0"
                >
                    <li v-for="build in group.builds" :key="build.id">
                        <Card
                            :accent-edge="stageColor(stageLabel(build.stage))"
                            class="flex flex-wrap items-center gap-6"
                        >
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex flex-wrap items-center gap-3 pb-1.5"
                                >
                                    <Badge
                                        :tone="
                                            build.visibility === 'draft'
                                                ? 'magenta'
                                                : 'accent'
                                        "
                                    >
                                        {{
                                            build.visibility === 'draft'
                                                ? 'Draft'
                                                : 'Public'
                                        }}
                                    </Badge>
                                    <Tag
                                        v-if="stageLabel(build.stage)"
                                        :dot="
                                            stageColor(stageLabel(build.stage))
                                        "
                                    >
                                        {{ stageLabel(build.stage) }}
                                    </Tag>
                                    <span
                                        v-if="build.patch"
                                        class="font-mono text-[12px] text-[var(--fg-3)]"
                                    >
                                        {{ build.patch }}
                                    </span>
                                </div>

                                <Link
                                    :href="build.url"
                                    class="text-[18px] leading-[1.28] font-semibold text-[var(--fg-1)] no-underline hover:no-underline"
                                >
                                    {{ build.name }}
                                </Link>

                                <p
                                    class="mt-0.5 text-[13px] text-[var(--fg-2)]"
                                >
                                    <span
                                        v-if="build.class"
                                        class="text-[var(--teal-400)]"
                                    >
                                        {{ build.class }}
                                    </span>
                                    <span v-if="build.ascendancy">
                                        <span class="text-[var(--ink-500)]">
                                            /
                                        </span>
                                        {{ build.ascendancy }}
                                    </span>
                                    <span v-if="build.updated_at">
                                        <span
                                            v-if="classLine(build)"
                                            class="text-[var(--ink-500)]"
                                        >
                                            ·
                                        </span>
                                        updated {{ build.updated_at }}
                                    </span>
                                </p>
                            </div>

                            <div class="flex items-center gap-8">
                                <StatBlock
                                    v-if="build.dps !== null"
                                    label="DPS"
                                    :value="compactNumber(build.dps) ?? '—'"
                                />
                                <StatBlock
                                    v-if="build.ehp !== null"
                                    label="EHP"
                                    :value="compactNumber(build.ehp) ?? '—'"
                                />
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    icon="eye"
                                    as-child
                                >
                                    <Link :href="build.url">View</Link>
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    icon="sliders-horizontal"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            editBuild.url([
                                                group.game.slug,
                                                build.id,
                                            ])
                                        "
                                    >
                                        Edit
                                    </Link>
                                </Button>
                            </div>
                        </Card>
                    </li>
                </ul>

                <div
                    v-else
                    class="rounded-[var(--radius-md)] border border-dashed border-[var(--border-subtle)] p-8 text-center"
                >
                    <p class="text-[15px] text-[var(--fg-2)]">
                        Nothing published for
                        {{ group.game.short_name ?? group.game.name }} yet.
                    </p>
                    <p class="mt-1.5 text-[13px] text-[var(--fg-3)]">
                        <template v-if="group.game.is_live">
                            Connect the server, ask your assistant for a build,
                            and the draft lands here.
                        </template>
                        <template v-else>
                            Not supported by the server yet — vote to move it up
                            the queue.
                        </template>
                    </p>
                    <Button
                        v-if="group.game.is_live"
                        class="mt-6"
                        size="sm"
                        variant="ghost"
                        icon="plug-zap"
                        @click="publishOpen = true"
                    >
                        Connect the server
                    </Button>
                    <Button
                        v-else
                        class="mt-6"
                        size="sm"
                        variant="ghost"
                        icon-right="chevron-right"
                        as-child
                    >
                        <Link :href="group.game.url">Vote for it</Link>
                    </Button>
                </div>
            </section>
        </div>

        <PublishBuildDialog v-model:open="publishOpen" :mcp-url="mcpUrl" />
    </div>
</template>
