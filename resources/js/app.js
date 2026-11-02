import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import vuetify from './plugins/vuetify';
import { useLocaleStore } from './stores/locale';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.use(vuetify);

useLocaleStore().set(localStorage.getItem('uchebnik_locale') || 'kk');

app.mount('#app');
