/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      // Palette inspiree de l'univers immobilier premium :
      //  - "primary" : teal-slate profond, evoque le serieux et la confiance
      //  - "accent"  : amber chaud, evoque l'accueil et la signature
      //  - "sand"    : neutres chauds pour les fonds (vs slate-50 trop froid)
      //  - "ink"     : noir bleute pour le texte primaire
      colors: {
        brand: {
          50:  '#f1f5f8',
          100: '#dde7ee',
          200: '#b5c8d6',
          300: '#85a3b8',
          400: '#557e96',
          500: '#1F3A4D',  // primaire
          600: '#1a3142',
          700: '#152838',
          800: '#101e2b',
          900: '#0a141d',
        },
        accent: {
          50:  '#fbf6ec',
          100: '#f5e9cf',
          200: '#ead29f',
          300: '#deba6f',
          400: '#d4a24c',
          500: '#bd8b35',  // accent principal
          600: '#956c28',
          700: '#6e4f1d',
          800: '#473214',
          900: '#23180a',
        },
        sand: {
          50:  '#faf8f5',
          100: '#f3eee6',
          200: '#e5dccb',
          300: '#d2c2a4',
        },
        ink: '#0e1a26',
        // Etats : on garde les standards Tailwind (emerald/amber/rose) mais
        // on les expose sous des noms semantiques pour clarifier l'intention.
        success: { 50: '#ecfdf5', 500: '#10b981', 700: '#047857' },
        warning: { 50: '#fffbeb', 500: '#f59e0b', 700: '#b45309' },
        danger:  { 50: '#fef2f2', 500: '#ef4444', 700: '#b91c1c' },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
        display: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        // Ombres douces, plus credibles que celles par defaut Tailwind
        soft: '0 1px 2px 0 rgba(15, 23, 42, 0.04), 0 1px 3px 0 rgba(15, 23, 42, 0.06)',
        card: '0 4px 12px -2px rgba(15, 23, 42, 0.06), 0 2px 4px -1px rgba(15, 23, 42, 0.04)',
        elevated: '0 10px 30px -8px rgba(15, 23, 42, 0.15), 0 4px 8px -4px rgba(15, 23, 42, 0.08)',
      },
      borderRadius: {
        xl2: '1rem',
      },
      backgroundImage: {
        'hero-gradient': 'linear-gradient(135deg, #1F3A4D 0%, #294e66 50%, #2c5871 100%)',
        'sand-gradient': 'linear-gradient(180deg, #faf8f5 0%, #f3eee6 100%)',
      },
    },
  },
  plugins: [],
}
