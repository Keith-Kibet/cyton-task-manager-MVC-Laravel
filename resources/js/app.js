import './bootstrap';
import { createApp } from 'vue';
import TaskApp from './TaskApp.vue';

function syncAuthHeader() {
    const token = localStorage.getItem('jwt');
    if (token) {
        window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        delete window.axios.defaults.headers.common['Authorization'];
    }
}

window.syncAuthHeader = syncAuthHeader;

createApp(TaskApp).mount('#app');
