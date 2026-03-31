<!--
  Create-task form: v-models for title, due date, priority; emits submit for POST /api/tasks.
-->
<script setup>
defineProps({
    field: { type: String, required: true },
    lbl: { type: String, required: true },
    card: { type: String, required: true },
    /** Disables submit while parent request is in flight */
    busy: { type: Boolean, default: false },
});

const title = defineModel('title', { type: String, required: true });
const due = defineModel('due', { type: String, required: true });
const priority = defineModel('priority', { type: String, required: true });

const emit = defineEmits(['submit']);
</script>

<template>
    <section :class="card">
        <h2 class="mb-0.5 text-base font-semibold text-slate-900 dark:text-slate-100">New task</h2>
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Same title can’t be used twice on one due date.</p>
        <form class="grid gap-4 sm:grid-cols-12" @submit.prevent="emit('submit')">
            <div class="sm:col-span-6">
                <label :class="lbl">Title</label>
                <input v-model="title" type="text" required placeholder="What needs doing?" :class="field" />
            </div>
            <div class="sm:col-span-3">
                <label :class="lbl">Due date</label>
                <input v-model="due" type="date" required :class="field" />
            </div>
            <div class="sm:col-span-3">
                <label :class="lbl">Priority</label>
                <select v-model="priority" :class="field">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div class="sm:col-span-12">
                <button
                    type="submit"
                    :disabled="busy"
                    class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
                >
                    Add task
                </button>
            </div>
        </form>
    </section>
</template>
