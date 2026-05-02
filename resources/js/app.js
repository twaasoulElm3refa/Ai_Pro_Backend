import { createApp } from "vue";
import App from "./App.vue";
import i18n from './i18n'
import router from "./router";

// your imports (bootstrap, css, etc.)
import "./bootstrap";
import "../css/app.css";
import "bootstrap-icons/font/bootstrap-icons.css";
import "bootstrap/dist/css/bootstrap.min.css";

// My components imports
import navbarComponent from "./components/layouts/Navbar.vue";
import footer from "./components/layouts/footer.vue";

// ===============================
// 🌙 Theme Init (IMPORTANT)
// ===============================
const theme = localStorage.getItem("theme");

if (!theme) {
    localStorage.setItem("theme", "light");
    document.documentElement.setAttribute("data-theme", "light");
} else {
    document.documentElement.setAttribute("data-theme", theme);
}

// ===============================

const app = createApp(App);
app.use(i18n)
app.component("navbar-component", navbarComponent);
app.component("footer-component", footer);

app.use(router);
app.mount("#app");

const loadBootstrapBundle = () => import("bootstrap/dist/js/bootstrap.bundle.min.js");

if ("requestIdleCallback" in window) {
    window.requestIdleCallback(() => {
        loadBootstrapBundle();
    });
} else {
    setTimeout(() => {
        loadBootstrapBundle();
    }, 1500);
}
