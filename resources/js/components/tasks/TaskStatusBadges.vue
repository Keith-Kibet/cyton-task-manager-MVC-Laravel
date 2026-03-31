<!--
  Read-only chips: pending / in_progress / done counts for the current list context.
-->
<script setup>
defineProps({
    /** { pending, in_progress, done } — optional total is unused here */
    counts: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="flex flex-wrap gap-2 text-xs" role="status" aria-label="Counts by status">
        <span
            class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 font-medium text-amber-950 dark:bg-amber-950/40 dark:text-amber-100"
        >
            Pending
            <span class="tabular-nums">{{ counts.pending }}</span>
        </span>
        <span
            class="inline-flex items-center gap-1 rounded-md bg-sky-100 px-2 py-0.5 font-medium text-sky-950 dark:bg-sky-950/50 dark:text-sky-100"
        >
            In progress
            <span class="tabular-nums">{{ counts.in_progress }}</span>
        </span>
        <span
            class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 font-medium text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100"
        >
            Done
            <span class="tabular-nums">{{ counts.done }}</span>
        </span>
    </div>
</template>
