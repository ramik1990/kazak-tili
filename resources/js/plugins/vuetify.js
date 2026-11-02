import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

const light = {
    dark: false,
    colors: {
        primary: '#1B5E4A',
        secondary: '#1F7A8C',
        accent: '#C2721B',
        success: '#2E7D5B',
        error: '#C0392B',
        warning: '#D98324',
        info: '#1F7A8C',
        background: '#F6F4EC',
        surface: '#FFFFFF',
        'surface-variant': '#EDE7D8',
    },
};

const dark = {
    dark: true,
    colors: {
        primary: '#6FD3A8',
        secondary: '#6EC5D6',
        accent: '#E0A163',
        success: '#6FD3A8',
        error: '#F2857A',
        warning: '#E8B055',
        info: '#6EC5D6',
        background: '#12201C',
        surface: '#1A2B26',
        'surface-variant': '#243A33',
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
        VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
        VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
        VTextarea: { variant: 'outlined', density: 'comfortable', hideDetails: 'auto' },
        VBtn: { style: 'text-transform: none; letter-spacing: 0.01em; font-weight: 600;' },
        VCard: { elevation: 0, rounded: 'xl' },
        VChip: { rounded: 'lg' },
    },
});
