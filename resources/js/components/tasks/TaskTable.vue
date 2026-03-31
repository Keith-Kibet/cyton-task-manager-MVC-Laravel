<!--
  Renders task rows: active mode shows Start/Mark done + Archive; archived shows Restore + Delete forever.
-->
<script setup>
defineProps({
    tasks: { type: Array, required: true },
    /** active: workflow + archive; archived: restore + force delete */
    mode: {
        type: String,
        required: true,
        validator: (v) => ['active', 'archived'].includes(v),
    },
});

const emit = defineEmits(['advance', 'archive', 'restore', 'force-delete']);

/** Tailwind chip colors by priority */
function priorityClass(p) {
    if (p === 'high') return 'bg-red-100 text-red-900 dark:bg-red-950/50 dark:text-red-200';
    if (p === 'medium') return 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100';
    return 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200';
}

/** Human-readable status for the Status column */
function statusLabel(s) {
    const m = { pending: 'Pending', in_progress: 'In progress', done: 'Done' };
    return m[s] || s;
}

/** Dot color next to title — mirrors workflow stages */
function statusDotClass(s) {
    if (s === 'pending') return 'bg-amber-400';
    if (s === 'in_progress') return 'bg-sky-500';
    return 'bg-emerald-500';
}

/** Mirrors backend: only one step forward (used to label Start vs Mark done) */
function nextStatus(s) {
    if (s === 'pending') return 'in_progress';
    if (s === 'in_progress') return 'done';
    return null;
}
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-2.5 sm:px-4">Task</th>
                    <th class="px-3 py-2.5 sm:px-4">Due</th>
                    <th class="px-3 py-2.5 sm:px-4">Priority</th>
                    <th class="px-3 py-2.5 sm:px-4">Status</th>
                    <th class="px-3 py-2.5 text-right sm:px-4">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr v-for="t in tasks" :key="t.id" class="bg-white/50 dark:bg-slate-900/30">
                    <td class="px-3 py-3 align-top sm:px-4">
                        <div class="flex gap-2">
                            <span
                                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                                :class="statusDotClass(t.status)"
                                aria-hidden="true"
                            />
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ t.title }}</span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 align-top text-slate-600 dark:text-slate-400 sm:px-4">
                        {{ t.due_date?.slice(0, 10) ?? '—' }}
                    </td>
                    <td class="px-3 py-3 align-top sm:px-4">
                        <span :class="['inline-block rounded px-2 py-0.5 text-xs font-medium capitalize', priorityClass(t.priority)]">
                            {{ t.priority }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 align-top text-slate-700 dark:text-slate-300 sm:px-4">
                        {{ statusLabel(t.status) }}
                    </td>
                    <td class="px-3 py-3 align-top text-right sm:px-4">
                        <div class="flex flex-wrap justify-end gap-1.5">
                            <template v-if="mode === 'active'">
                                <button
                                    v-if="nextStatus(t.status)"
                                    type="button"
                                    class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                                    @click="emit('advance', t)"
                                >
                                    {{ t.status === 'pending' ? 'Start' : 'Mark done' }}
                                </button>
                                <button
                                    v-if="t.status === 'done'"
                                    type="button"
                                    class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                    @click="emit('archive', t)"
                                >
                                    Archive
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    type="button"
                                    class="rounded-md border border-teal-600/50 bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-900 hover:bg-teal-100 dark:bg-teal-950/40 dark:text-teal-100 dark:hover:bg-teal-950/60"
                                    @click="emit('restore', t)"
                                >
                                    Restore
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-red-300 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/50"
                                    @click="emit('force-delete', t)"
                                >
                                    Delete forever
                                </button>
                            </template>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
