<!--
  Root SPA: auth (login/register), JWT in localStorage, axios Bearer header,
  task list with Active/Archived tabs + status filter, daily report, admin user list.
  Task fetches use AbortController so rapid tab/filter changes cannot show stale rows.
-->
<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import TaskListPanel from './components/tasks/TaskListPanel.vue';
import TaskNewForm from './components/tasks/TaskNewForm.vue';

/** localStorage keys for session and UI preference */
const STORAGE_JWT = 'jwt';
const STORAGE_USER = 'user';
const STORAGE_THEME = 'theme';

/** light | dark — toggles `dark` class on <html> */
const colorMode = ref('light');

const authTab = ref('login');
const loginEmail = ref('');
const loginPassword = ref('');
const regName = ref('');
const regEmail = ref('');
const regPassword = ref('');

const user = ref(null);
const jwt = ref(localStorage.getItem(STORAGE_JWT) || '');

/** Which main section is shown: tasks | report | admin */
const activeView = ref('tasks');

/** Rows for the current tab/filter; cleared on fetch unless preserveList */
const tasks = ref([]);
const listMessage = ref('');
const statusFilter = ref('');
/** active = non-deleted tasks; archived = soft-deleted only */
const taskListTab = ref('active');

/** Default shape for counts.active / counts.archived from GET /api/tasks */
function emptyTabCounts() {
    return { pending: 0, in_progress: 0, done: 0, total: 0 };
}

const taskCounts = ref({
    active: emptyTabCounts(),
    archived: emptyTabCounts(),
});

const newTitle = ref('');
const newDue = ref('');
const newPriority = ref('medium');

/** YYYY-MM-DD for GET /api/tasks/report */
const reportDate = ref(new Date().toISOString().slice(0, 10));
/** Response body with `summary` matrix (priority × status) */
const reportData = ref(null);

/** GET /api/admin/users when admin opens Users view */
const adminUsers = ref([]);

const busy = ref(false);
const error = ref('');

/** Cancels the previous GET /api/tasks when the tab or filter changes so rows never “ghost”. */
let tasksFetchAbort = null;

const field =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-slate-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-slate-400';

const lbl = 'mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300';

const card = 'rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900';

const navBtn = (active) =>
    [
        'shrink-0 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors sm:px-4',
        active
            ? 'bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-white'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
    ].join(' ');

const themeToggleWrap = 'inline-flex rounded-lg border border-slate-200 p-0.5 text-xs dark:border-slate-600';

const themeBtn = (active) =>
    [
        'rounded-md px-2.5 py-1.5 transition sm:px-3',
        active ? 'bg-slate-100 font-medium dark:bg-slate-700' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-300',
    ].join(' ');

/** Clears the global error banner */
function clearError() {
    error.value = '';
}

/** Syncs `dark` class on documentElement with colorMode */
function applyTheme() {
    const root = document.documentElement;
    if (colorMode.value === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
}

function setColorMode(mode) {
    colorMode.value = mode;
    localStorage.setItem(STORAGE_THEME, mode);
    applyTheme();
}

/** Read theme from localStorage on first paint */
function initTheme() {
    const t = localStorage.getItem(STORAGE_THEME);
    colorMode.value = t === 'dark' ? 'dark' : t === 'light' ? 'light' : 'light';
    applyTheme();
}

/** Persist JWT + user, set axios default Authorization, or clear everything on logout */
function setSession(token, u) {
    jwt.value = token;
    user.value = u;
    if (token) {
        localStorage.setItem(STORAGE_JWT, token);
        localStorage.setItem(STORAGE_USER, JSON.stringify(u));
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        activeView.value = 'tasks';
    } else {
        localStorage.removeItem(STORAGE_JWT);
        localStorage.removeItem(STORAGE_USER);
        delete axios.defaults.headers.common['Authorization'];
        tasks.value = [];
        taskCounts.value = { active: emptyTabCounts(), archived: emptyTabCounts() };
        reportData.value = null;
        adminUsers.value = [];
    }
}

/** Hydrate user from localStorage on load; invalid JSON clears session */
function loadUserFromStorage() {
    const t = localStorage.getItem(STORAGE_JWT) || '';
    jwt.value = t;
    let u = null;
    const raw = localStorage.getItem(STORAGE_USER);
    if (raw) {
        try {
            u = JSON.parse(raw);
        } catch {
            u = null;
        }
    }
    if (t && u) {
        user.value = u;
        axios.defaults.headers.common['Authorization'] = `Bearer ${t}`;
    } else {
        user.value = null;
        jwt.value = '';
        localStorage.removeItem(STORAGE_JWT);
        localStorage.removeItem(STORAGE_USER);
        delete axios.defaults.headers.common['Authorization'];
    }
}

/** POST /api/login — stores JWT and loads tasks */
async function login() {
    clearError();
    busy.value = true;
    try {
        const { data } = await axios.post('/api/login', {
            email: loginEmail.value,
            password: loginPassword.value,
        });
        setSession(data.token, data.user);
        await fetchTasks({ preserveList: true });
    } catch (e) {
        error.value = e.response?.data?.message || 'Login failed.';
    } finally {
        busy.value = false;
    }
}

/** POST /api/register — same session handling as login */
async function register() {
    clearError();
    busy.value = true;
    try {
        const { data } = await axios.post('/api/register', {
            name: regName.value,
            email: regEmail.value,
            password: regPassword.value,
        });
        setSession(data.token, data.user);
        await fetchTasks({ preserveList: true });
    } catch (e) {
        const msg = e.response?.data?.message;
        const errs = e.response?.data?.errors;
        error.value =
            msg ||
            (errs ? Object.values(errs).flat().join(' ') : 'Registration failed.');
    } finally {
        busy.value = false;
    }
}

/** Clears token, tasks, report, admin list */
function logout() {
    setSession('', null);
}

/** True when axios/AbortController aborted the request (not a real error) */
function isTasksFetchCanceled(e) {
    return (
        axios.isCancel(e) ||
        e?.code === 'ERR_CANCELED' ||
        e?.name === 'CanceledError' ||
        e?.name === 'AbortError'
    );
}

/**
 * @param {{ preserveList?: boolean }} [options] — set preserveList when refreshing after a mutation so the table doesn’t blank out.
 */
async function fetchTasks(options = {}) {
    if (!jwt.value) return;
    clearError();

    const preserveList = options.preserveList === true;

    tasksFetchAbort?.abort();
    const controller = new AbortController();
    tasksFetchAbort = controller;

    if (!preserveList) {
        tasks.value = [];
        listMessage.value = '';
    }

    try {
        const params = { archived: taskListTab.value === 'archived' };
        if (statusFilter.value) params.status = statusFilter.value;
        const { data } = await axios.get('/api/tasks', { params, signal: controller.signal });
        tasks.value = data.tasks || [];
        if (data.counts) {
            taskCounts.value = {
                active: { ...emptyTabCounts(), ...data.counts.active },
                archived: { ...emptyTabCounts(), ...data.counts.archived },
            };
        }
        listMessage.value = data.message || '';
    } catch (e) {
        if (isTasksFetchCanceled(e)) {
            return;
        }
        error.value = e.response?.data?.message || 'Could not load tasks.';
        tasks.value = [];
    }
}

/** POST /api/tasks then refresh list without clearing rows mid-flight */
async function createTask() {
    clearError();
    busy.value = true;
    try {
        await axios.post('/api/tasks', {
            title: newTitle.value,
            due_date: newDue.value,
            priority: newPriority.value,
        });
        newTitle.value = '';
        newDue.value = '';
        newPriority.value = 'medium';
        await fetchTasks({ preserveList: true });
    } catch (e) {
        const errs = e.response?.data?.errors;
        error.value =
            errs ? Object.values(errs).flat().join(' ') : e.response?.data?.message || 'Could not create task.';
    } finally {
        busy.value = false;
    }
}

/** PATCH status one step forward */
async function advanceStatus(task) {
    const next = nextStatus(task.status);
    if (!next) return;
    clearError();
    try {
        await axios.patch(`/api/tasks/${task.id}/status`, { status: next });
        await fetchTasks({ preserveList: true });
    } catch (e) {
        error.value = e.response?.data?.message || 'Status update failed.';
    }
}

/** DELETE /api/tasks/:id (soft-delete / archive) */
async function archiveTask(task) {
    if (!confirm('Archive this task? You can restore it later from the Archived tab.')) return;
    clearError();
    try {
        await axios.delete(`/api/tasks/${task.id}`);
        await fetchTasks({ preserveList: true });
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not archive task.';
    }
}

/** POST /api/tasks/:id/restore */
async function restoreTask(task) {
    clearError();
    try {
        await axios.post(`/api/tasks/${task.id}/restore`);
        await fetchTasks({ preserveList: true });
    } catch (e) {
        error.value = e.response?.data?.message || 'Restore failed.';
    }
}

/** DELETE /api/tasks/:id/force */
async function permanentDeleteTask(task) {
    if (
        !confirm(
            'Permanently delete this task? This cannot be undone. Click OK to delete forever, or Cancel to keep it in Archive.',
        )
    ) {
        return;
    }
    clearError();
    try {
        await axios.delete(`/api/tasks/${task.id}/force`);
        await fetchTasks({ preserveList: true });
    } catch (e) {
        error.value = e.response?.data?.message || 'Permanent delete failed.';
    }
}

/** GET /api/tasks/report?date=… */
async function fetchReport() {
    if (!jwt.value) return;
    clearError();
    try {
        const { data } = await axios.get('/api/tasks/report', {
            params: { date: reportDate.value },
        });
        reportData.value = data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Report failed.';
        reportData.value = null;
    }
}

/** GET /api/admin/users (admins only) */
async function fetchAdminUsers() {
    if (user.value?.role !== 'admin') return;
    try {
        const { data } = await axios.get('/api/admin/users');
        adminUsers.value = data.users || [];
    } catch {
        adminUsers.value = [];
    }
}

/** One-step workflow: pending → in_progress → done; no other moves */
function nextStatus(s) {
    if (s === 'pending') return 'in_progress';
    if (s === 'in_progress') return 'done';
    return null;
}

const isAdmin = computed(() => user.value?.role === 'admin');

watch(statusFilter, () => fetchTasks());

watch(taskListTab, () => fetchTasks());

watch(activeView, (v) => {
    if (!user.value) return;
    if (v === 'report') fetchReport();
    if (v === 'admin' && user.value?.role === 'admin') fetchAdminUsers();
});

onMounted(() => {
    initTheme();
    loadUserFromStorage();
    if (jwt.value && user.value) {
        fetchTasks();
        fetchReport();
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <header
            class="sticky top-0 z-40 overflow-x-hidden border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/95"
        >
            <!-- Full-width bar; main content below stays max-w-3xl for reading -->
            <div class="w-full min-w-0 px-4 py-3 sm:px-6 lg:px-8 xl:px-12">
                <!-- Mobile: column. Desktop: 3-column grid so nothing shares the same flex row -->
                <div
                    class="grid min-w-0 grid-cols-1 gap-3 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center lg:gap-x-6 xl:gap-x-10"
                >
                    <!-- Brand + theme (theme only on small screens) -->
                    <div class="flex min-w-0 items-start justify-between gap-3 lg:items-center">
                        <div class="min-w-0">
                            <p class="text-base font-semibold tracking-tight text-slate-900 dark:text-slate-50">Cyton Task Management</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Keep today’s work in one place</p>
                        </div>
                        <div :class="[themeToggleWrap, 'shrink-0 lg:hidden']" role="group" aria-label="Theme">
                            <button type="button" :class="themeBtn(colorMode === 'light')" @click="setColorMode('light')">
                                Light
                            </button>
                            <button type="button" :class="themeBtn(colorMode === 'dark')" @click="setColorMode('dark')">
                                Dark
                            </button>
                        </div>
                    </div>

                    <!-- Main nav: one instance; scrolls horizontally if needed -->
                    <nav
                        v-if="user"
                        class="flex min-w-0 justify-center gap-1.5 overflow-x-auto overscroll-x-contain px-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:gap-2 lg:justify-center lg:gap-2 lg:px-2"
                        aria-label="Main"
                    >
                        <button type="button" :class="navBtn(activeView === 'tasks')" @click="activeView = 'tasks'">
                            My tasks
                        </button>
                        <button type="button" :class="navBtn(activeView === 'report')" @click="activeView = 'report'">
                            Report
                        </button>
                        <button
                            v-if="isAdmin"
                            type="button"
                            :class="navBtn(activeView === 'admin')"
                            @click="activeView = 'admin'"
                        >
                            Users
                        </button>
                    </nav>
                    <!-- Keeps 3-column balance when logged out (nav hidden) -->
                    <div v-else class="hidden min-w-0 lg:block lg:min-h-10" aria-hidden="true" />

                    <!-- Tools: desktop — full width of right column, no artificial max-width -->
                    <div
                        class="hidden min-w-0 flex-nowrap items-center justify-end gap-2 sm:gap-3 lg:flex"
                    >
                        <div :class="themeToggleWrap" role="group" aria-label="Theme">
                            <button type="button" :class="themeBtn(colorMode === 'light')" @click="setColorMode('light')">
                                Light
                            </button>
                            <button type="button" :class="themeBtn(colorMode === 'dark')" @click="setColorMode('dark')">
                                Dark
                            </button>
                        </div>
                        <a
                            href="/api/documentation"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="shrink-0 text-xs text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
                        >
                            API docs
                        </a>
                        <template v-if="user">
                            <span
                                v-if="isAdmin"
                                class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Admin
                            </span>
                            <span class="max-w-56 shrink truncate text-xs text-slate-600 sm:max-w-xs lg:max-w-md dark:text-slate-300" :title="user.email">
                                {{ user.email }}
                            </span>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                                @click="logout"
                            >
                                Log out
                            </button>
                        </template>
                    </div>

                    <!-- Account strip: small screens only -->
                    <div
                        v-if="user"
                        class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 lg:hidden dark:border-slate-800"
                    >
                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                            <span
                                v-if="isAdmin"
                                class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Admin
                            </span>
                            <span class="min-w-0 max-w-full truncate sm:max-w-[min(100%,16rem)]" :title="user.email">
                                {{ user.email }}
                            </span>
                            <a
                                href="/api/documentation"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="shrink-0 font-medium text-slate-700 underline-offset-2 hover:underline dark:text-slate-300"
                            >
                                API docs
                            </a>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                            @click="logout"
                        >
                            Log out
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-6 sm:px-5 sm:py-8">
            <div
                v-if="error"
                class="mb-5 flex items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/60 dark:text-red-100"
            >
                <span>{{ error }}</span>
                <button type="button" class="shrink-0 text-red-800 underline dark:text-red-200" @click="clearError">Dismiss</button>
            </div>

            <!-- Auth -->
            <div v-if="!user" class="mx-auto max-w-md pt-2">
                <p class="mb-5 text-center text-sm text-slate-600 dark:text-slate-400">
                    Log in to add tasks and run the daily report.
                </p>
                <div :class="card">
                    <div class="mb-5 flex gap-1 rounded-lg border border-slate-200 p-0.5 dark:border-slate-600">
                        <button
                            type="button"
                            class="flex-1 rounded-md py-2 text-sm font-medium transition"
                            :class="
                                authTab === 'login'
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                                    : 'text-slate-600 dark:text-slate-400'
                            "
                            @click="authTab = 'login'"
                        >
                            Log in
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-md py-2 text-sm font-medium transition"
                            :class="
                                authTab === 'register'
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                                    : 'text-slate-600 dark:text-slate-400'
                            "
                            @click="authTab = 'register'"
                        >
                            Register
                        </button>
                    </div>

                    <form v-if="authTab === 'login'" class="space-y-4" @submit.prevent="login">
                        <div>
                            <label :class="lbl">Email</label>
                            <input v-model="loginEmail" type="email" required :class="field" autocomplete="username" />
                        </div>
                        <div>
                            <label :class="lbl">Password</label>
                            <input
                                v-model="loginPassword"
                                type="password"
                                required
                                :class="field"
                                autocomplete="current-password"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="busy"
                            class="w-full rounded-lg bg-teal-600 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
                        >
                            {{ busy ? 'One moment…' : 'Log in' }}
                        </button>
                    </form>

                    <form v-else class="space-y-4" @submit.prevent="register">
                        <div>
                            <label :class="lbl">Name</label>
                            <input v-model="regName" type="text" required :class="field" />
                        </div>
                        <div>
                            <label :class="lbl">Email</label>
                            <input v-model="regEmail" type="email" required :class="field" />
                        </div>
                        <div>
                            <label :class="lbl">Password</label>
                            <input v-model="regPassword" type="password" required :class="field" />
                        </div>
                        <button
                            type="submit"
                            :disabled="busy"
                            class="w-full rounded-lg bg-teal-600 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
                        >
                            {{ busy ? 'One moment…' : 'Create account' }}
                        </button>
                    </form>
                </div>
                <p class="mt-5 text-center text-xs text-slate-500">
                    <a href="/api/documentation" class="text-slate-600 underline-offset-2 hover:underline dark:text-slate-400">API documentation</a>
                </p>
            </div>

            <!-- Tasks -->
            <div v-else-if="activeView === 'tasks'" class="space-y-5">
                <TaskNewForm
                    v-model:title="newTitle"
                    v-model:due="newDue"
                    v-model:priority="newPriority"
                    :field="field"
                    :lbl="lbl"
                    :card="card"
                    :busy="busy"
                    @submit="createTask"
                />
                <TaskListPanel
                    v-model:tab="taskListTab"
                    v-model:status-filter="statusFilter"
                    :tasks="tasks"
                    :list-message="listMessage"
                    :counts="taskCounts"
                    :field="field"
                    :lbl="lbl"
                    :card="card"
                    @advance="advanceStatus"
                    @archive="archiveTask"
                    @restore="restoreTask"
                    @force-delete="permanentDeleteTask"
                />
            </div>

            <!-- Report -->
            <section v-else-if="activeView === 'report'" :class="card">
                <h2 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Daily report</h2>
                <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">
                    Counts by priority and status for tasks due on the date you pick.
                </p>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[160px]">
                        <label :class="lbl">Date</label>
                        <input v-model="reportDate" type="date" :class="field" />
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-200 dark:text-slate-900 dark:hover:bg-white"
                        @click="fetchReport"
                    >
                        Run report
                    </button>
                </div>
                <div v-if="reportData?.summary" class="mt-8 overflow-x-auto rounded-xl border border-slate-200/80 dark:border-slate-700">
                    <table class="w-full min-w-[320px] text-left text-sm">
                        <thead class="bg-slate-100 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Priority</th>
                                <th class="px-4 py-3">Pending</th>
                                <th class="px-4 py-3">In progress</th>
                                <th class="px-4 py-3">Done</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr
                                v-for="p in ['high', 'medium', 'low']"
                                :key="p"
                                class="bg-white/50 dark:bg-slate-900/30"
                            >
                                <td class="px-4 py-3 font-medium capitalize">{{ p }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ reportData.summary[p]?.pending ?? 0 }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ reportData.summary[p]?.in_progress ?? 0 }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ reportData.summary[p]?.done ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="mt-6 text-sm text-slate-500">Run a report to see the summary table.</p>
            </section>

            <!-- Admin -->
            <section v-else-if="activeView === 'admin' && isAdmin" :class="card">
                <h2 class="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Users</h2>
                <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">All registered accounts (read-only in this UI).</p>
                <ul v-if="adminUsers.length" class="divide-y divide-slate-200 rounded-xl border border-slate-200/80 dark:divide-slate-700 dark:border-slate-700">
                    <li
                        v-for="u in adminUsers"
                        :key="u.id"
                        class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm first:rounded-t-xl last:rounded-b-xl odd:bg-slate-50/80 dark:odd:bg-slate-800/40"
                    >
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ u.name }}</span>
                        <span class="text-slate-500 dark:text-slate-400">{{ u.email }}</span>
                        <span
                            class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-800 dark:bg-slate-700 dark:text-slate-200"
                        >
                            {{ u.role }}
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500">No users yet.</p>
            </section>
        </main>
    </div>
</template>

