// Necesario para que Vite procese las directivas @tailwind de resources/css/app.css.
// Sin este archivo el CSS compilado conservaba "@tailwind base;..." sin expandir.
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
