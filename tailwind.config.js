/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                camel: ['Camel', 'sans-serif'],
                sans: ['Camel', 'sans-serif'],
            },
        },
    },

    plugins: [],
}
