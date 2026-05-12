/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.twig',
    './src/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [
    require('daisyui'),
  ],
  daisyui: {
    themes: [
      {
        folksk: {
          'primary':          '#2d6a4f',   // tmavá zelená — folk, príroda
          'primary-content':  '#ffffff',
          'secondary':        '#74c69d',   // svetlá zelená
          'secondary-content':'#1b1b1b',
          'accent':           '#d4a017',   // zlatá — folklórny motív
          'accent-content':   '#1b1b1b',
          'neutral':          '#2b2d42',
          'neutral-content':  '#f0f0f0',
          'base-100':         '#ffffff',
          'base-200':         '#f5f5f0',   // jemne teplá biela
          'base-300':         '#e8e8e0',
          'base-content':     '#1f2937',
          'info':             '#3abff8',
          'success':          '#36d399',
          'warning':          '#fbbd23',
          'error':            '#f87272',
        },
      },
      'light',
    ],
    darkTheme: false,
    base: true,
    styled: true,
    utils: true,
    logs: false,
  },
}
