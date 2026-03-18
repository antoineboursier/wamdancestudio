/** @type {import('tailwindcss').Config} */
module.exports = {
    // Purge : scan tous les fichiers PHP, JS du thème
    content: [
        './**/*.php',
        './assets/js/**/*.js',
        // Exclure node_modules
        '!./node_modules/**',
    ],

    darkMode: ['class', 'html.wam-theme-light'], // inversé : dark = défaut, light = classe

    theme: {
        extend: {

            /* -------------------------------------------------------
               Couleurs du design system WAM (depuis theme.json)
               Utilisables en classe : text-wam-green, bg-wam-bg800...
               Les couleurs WP restent dispo en CSS var dans les composants.
               ------------------------------------------------------- */
            colors: {
                wam: {
                    // Fonds
                    'bg800': '#131620',
                    'bg600': '#1A1D28',
                    'bg500': '#232734',
                    'bg200': '#0575B3',
                    // Textes
                    'text': '#F9F4EB',
                    'subtext': '#E1CDAC',
                    'muted': '#7F7F7F',
                    // Accents
                    'green': '#01E3BD',
                    'yellow': '#FFDC08',
                    'orange': '#FF843D',
                    'pink': '#FFBAD5',
                    // Semi-transparents
                    'glass-white': 'rgba(249, 244, 236, 0.06)',
                    'glass-white-hover': 'rgba(249, 244, 236, 0.12)',
                    'glass-green': 'rgba(1, 227, 189, 0.12)',
                    'glass-yellow': 'rgba(253, 217, 0, 0.10)',
                },
            },

            /* -------------------------------------------------------
         Espacements : on utilise l'échelle Tailwind native.
         Correspondance tokens Figma ↔ Tailwind (1 unit = 4px) :
           3xs:  4px  → 1   2xs:  8px  → 2   xs:  12px → 3
           sm:  16px  → 4   md:  20px  → 5   lg:  24px → 6
           xl:  32px  → 8   2xl: 40px  → 10  3xl: 48px → 12
           4xl: 56px  → 14  5xl: 64px  → 16  6xl: 72px → 18
           7xl: 80px  → 20  8xl: 88px  → 22  9xl: 96px → 24
         ------------------------------------------------------- */
            // Pas de spacing custom — utiliser gap-4, p-6, py-14, px-24, etc.

            /* -------------------------------------------------------
               Polices
               ------------------------------------------------------- */
            fontFamily: {
                'outfit': ['Outfit', 'sans-serif'],
                'mallia': ['Mallia', 'Georgia', 'serif'],
                'cholo': ['"Cholo Rhita"', 'sans-serif'],
                // Override default Tailwind font-sans to use Ourfit everywhere
                'sans': ['Outfit', 'sans-serif'],
            },

            /* -------------------------------------------------------
               Tailles de polices exactes Figma
               ------------------------------------------------------- */
            fontSize: {
                'wam-xs': ['14px', { lineHeight: '1.25' }],
                'wam-sm': ['16px', { lineHeight: '1.25' }],
                'wam-md': ['20px', { lineHeight: '1.25' }],
                'wam-lg': ['26px', { lineHeight: '1.25' }],
                // Titres
                'wam-sign-sm': ['24px', { lineHeight: '1.1' }],
                'wam-sign-md': ['32px', { lineHeight: '1.1' }],
                'wam-sign-lg': ['46px', { lineHeight: '1.3' }],
                'wam-cool-md': ['48px', { lineHeight: '1' }],
                'wam-cool-lg': ['36px', { lineHeight: '1' }],
                'wam-norm-md': ['32px', { lineHeight: '1.1' }],
            },

            /* -------------------------------------------------------
               Border radius
               ------------------------------------------------------- */
            borderRadius: {
                'wam-xs': '4px',
                'wam-sm': '8px',
                'wam-md': '12px',
                'wam-lg': '16px',
                'wam-xl': '20px',
                'wam-2xl': '24px',
                'wam-3xl': '32px',
                'wam-pill': '999px',
            },

            /* -------------------------------------------------------
               Ombres
               ------------------------------------------------------- */
            boxShadow: {
                'wam-sm': '0 4px 16px rgba(6, 8, 14, 0.3)',
                'wam-md': '0 8px 32px rgba(6, 8, 14, 0.45)',
                'wam-card': '0 8px 44px rgba(6, 8, 14, 0.39)',
                'wam-panel': '0 16px 64px rgba(6, 8, 14, 0.65)',
            },

            /* -------------------------------------------------------
               Largeurs max
               ------------------------------------------------------- */
            maxWidth: {
                'wam-content': '1200px',
                'wam-screen': '1536px',
            },
        },
    },

    plugins: [],
};
