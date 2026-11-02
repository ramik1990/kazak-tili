import { computed } from 'vue';
import { useLocaleStore } from '@/stores/locale';

export function useI18n() {
    const store = useLocaleStore();

    return {
        locale: computed(() => store.locale),
        t: (path) => store.t(path),
        toggle: () => store.toggle(),
        set: (value) => store.set(value),
    };
}
