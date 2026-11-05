import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

const light = {
    dark: false,
    colors: {
        primary: '#1A7A46',
        secondary: '#166A9B',
        accent: '#905D09',
        success: '#1A7A46',
        error: '#B82F2F',
        warning: '#905D09',
        info: '#166A9B',
        background: '#EEF7FD',
        surface: '#FFFFFF',
        'surface-variant': '#EAF3FB',
        'on-surface-variant': '#17303F',
    },
};

const dark = {
    dark: true,
    colors: {
        primary: '#5FD08E',
        secondary: '#6CC2EE',
        accent: '#E8B055',
        success: '#5FD08E',
        error: '#F08A8A',
        warning: '#E8B055',
        info: '#6CC2EE',
        background: '#0F1B24',
        surface: '#17262F',
        'surface-variant': '#1F3240',
        'on-surface-variant': '#E6F0F7',
    },
};

export default createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'light',
        themes: { light, dark },
    },
    defaults: {
        VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto', rounded: 'lg' },
        VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto', rounded: 'lg' },
        VTextarea: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto', rounded: 'lg' },
        VBtn: { rounded: 'pill', style: 'text-transform: none; letter-spacing: 0.01em; font-weight: 700;' },
        VCard: { elevation: 0, rounded: 'xl' },
        VChip: { rounded: 'pill' },
    },
});
