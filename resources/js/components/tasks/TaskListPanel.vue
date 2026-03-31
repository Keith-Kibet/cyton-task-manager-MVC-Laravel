<!--
  Task list container: Active/Archived tabs, status chips for the current bucket,
  status dropdown filter, and the table or empty state. Forwards row actions to parent.
-->
<script setup>
import { computed } from 'vue';
import TaskStatusBadges from './TaskStatusBadges.vue';
import TaskTable from './TaskTable.vue';
import TaskTabs from './TaskTabs.vue';

const props = defineProps({
    /** Shared input/select class from parent */
    field: { type: String, required: true },
    lbl: { type: String, required: true },
    card: { type: String, required: true },
    tasks: { type: Array, required: true },
    /** Optional API message e.g. “No tasks found.” */
    listMessage: { type: String, default: '' },
    /** { active: statusCounts, archived: statusCounts } from GET /api/tasks */
    counts: {
        type: Object,
        required: true,
    },
});

/** 'active' | 'archived' — drives API `archived` query param */
const tab = defineModel('tab', { type: String, required: true });
/** '' | pending | in_progress | done */
const statusFilter = defineModel('statusFilter', { type: String, required: true });

/** Row actions: advance workflow, soft-delete, restore, permanent delete */
const emit = defineEmits(['advance', 'archive', 'restore', 'force-delete']);

const countsActive = computed(() => props.counts.active ?? { pending: 0, in_progress: 0, done: 0, total: 0 });
const countsArchived = computed(() => props.counts.archived ?? { pending: 0, in_progress: 0, done: 0, total: 0 });

/** Status badges reflect the tab you’re on (active totals vs archived totals) */
const tabCountsForBadges = computed(() => {
    return tab.value === 'archived' ? countsArchived.value : countsActive.value;
});
</script>

<template>
    <section :class="card">
        <div class="mb-4 space-y-4">
            <TaskTabs
                v-model="tab"
                :counts-active="countsActive"
                :counts-archived="countsArchived"
            />
            <TaskStatusBadges :counts="tabCountsForBadges" />
        </div>

        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                    {{ tab === 'archived' ? 'Archived tasks' : 'Your tasks' }}
                </h2>
                <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                    {{ tab === 'archived' ? 'Restore or permanently remove archived tasks.' : 'Priority first, then due date.' }}
                </p>
            </div>
            <div>
                <label :class="[lbl, 'sr-only']">Filter by status</label>
                <select v-model="statusFilter" :class="field + ' max-w-[200px] sm:max-w-none'">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In progress</option>
                    <option value="done">Done</option>
                </select>
            </div>
        </div>

        <p
            v-if="listMessage"
            class="mb-4 rounded-lg bg-slate-100 py-5 text-center text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300"
        >
            {{ listMessage }}
        </p>

        <TaskTable
            v-else-if="tasks.length"
            :tasks="tasks"
            :mode="tab === 'archived' ? 'archived' : 'active'"
            @advance="emit('advance', $event)"
            @archive="emit('archive', $event)"
            @restore="emit('restore', $event)"
            @force-delete="emit('force-delete', $event)"
        />

        <div
            v-else
            class="rounded-lg border border-dashed border-slate-300 py-12 text-center dark:border-slate-600"
        >
            <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
                {{ tab === 'archived' ? 'No archived tasks' : 'No tasks to show' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{ tab === 'archived' ? 'Archive tasks from the Active tab to see them here.' : 'Add one above or change the filter.' }}
            </p>
        </div>
    </section>
</template>
