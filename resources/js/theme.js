import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';

// Preset de "Mi Billetera": acento ámbar/amarillo sobre superficies neutras
// oscuras (estilo Money Manager). El modo claro usa los valores por defecto de Aura.
const MiBilleteraPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{amber.50}',
            100: '{amber.100}',
            200: '{amber.200}',
            300: '{amber.300}',
            400: '{amber.400}',
            500: '{amber.500}',
            600: '{amber.600}',
            700: '{amber.700}',
            800: '{amber.800}',
            900: '{amber.900}',
            950: '{amber.950}',
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{amber.500}',
                    contrastColor: '{zinc.950}',
                    hoverColor: '{amber.600}',
                    activeColor: '{amber.700}',
                },
            },
            dark: {
                primary: {
                    color: '{amber.400}',
                    contrastColor: '{zinc.950}',
                    hoverColor: '{amber.300}',
                    activeColor: '{amber.200}',
                },
                surface: {
                    0: '#ffffff',
                    50: '{zinc.50}',
                    100: '{zinc.100}',
                    200: '{zinc.200}',
                    300: '{zinc.300}',
                    400: '{zinc.400}',
                    500: '{zinc.500}',
                    600: '{zinc.600}',
                    700: '{zinc.700}',
                    800: '{zinc.800}',
                    900: '#1c1c1e',
                    950: '#0d0d0f',
                },
            },
        },
    },
});

export default MiBilleteraPreset;
