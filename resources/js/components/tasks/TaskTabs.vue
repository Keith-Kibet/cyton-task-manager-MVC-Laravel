<!--
  Segmented control: Active vs Archived. Emits update:modelValue with 'active' | 'archived'.
  Badge numbers are total tasks per bucket (from API counts).
-->
<script setup>
defineProps({
    /** 'active' | 'archived' */
    modelValue: {
        type: String,
        required: true,
    },
    /** { pending, in_progress, done, total } for non-deleted tasks */
    countsActive: { type: Object, required: true },
    /** Same shape for soft-deleted tasks only */
    countsArchived: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

function tabClass(active) {
    return [
        'flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition sm:px-4',
        active
            ? 'bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-white'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
    ].join(' ');
}
</script>

<template>
    <div
        class="flex gap-1 rounded-lg border border-slate-200 p-1 dark:border-slate-600"
        role="tablist"
        aria-label="Task lists"
    >
        <button
            type="button"
            role="tab"
            :aria-selected="modelValue === 'active'"
            :class="tabClass(modelValue === 'active')"
            @click="emit('update:modelValue', 'active')"
        >
            Active tasks
            <span
                class="rounded-md bg-white/80 px-1.5 py-0.5 text-xs tabular-nums dark:bg-slate-900/60"
            >
                {{ countsActive.total }}
            </span>
        </button>
        <button
            type="button"
            role="tab"
            :aria-selected="modelValue === 'archived'"
            :class="tabClass(modelValue === 'archived')"
            @click="emit('update:modelValue', 'archived')"
        >
            Archived
            <span
                class="rounded-md bg-white/80 px-1.5 py-0.5 text-xs tabular-nums dark:bg-slate-900/60"
            >
                {{ countsArchived.total }}
            </span>
        </button>
    </div>
</template>
