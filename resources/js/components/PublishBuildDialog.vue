<script setup lang="ts">
import ConnectPanel from '@/components/byb/ConnectPanel.vue';
import Dialog from '@/components/byb/Dialog.vue';

/**
 * "Publish build" everywhere in the app opens this. Publishing starts in the
 * assistant — the web app only lets you finish a draft the MCP server wrote —
 * so the honest answer to the button is the connect instructions, not a form.
 */
defineProps<{
    mcpUrl: string;
}>();

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Dialog
        v-model:open="open"
        eyebrow="Publish"
        title="Publishing starts in your assistant"
        description="Ask the connected server to publish. The draft lands in My builds, where you check the numbers before it goes public."
        :width="560"
    >
        <ConnectPanel :mcp-url="mcpUrl" filename="server url" />
    </Dialog>
</template>
