import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";

// your imports (bootstrap, css, etc.)
import "./bootstrap";
import "animate.css";
import "../css/app.css";
import "bootstrap/dist/css/bootstrap.css";
import "bootstrap-icons/font/bootstrap-icons.css";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

// My components imports
import navbarComponent from "./components/layouts/Navbar.vue";
import footer from "./components/layouts/footer.vue";
import AdminHeader from "./layouts/AdminHeader.vue";
import AdminLayout from "./layouts/AdminLayout.vue";
import AdminSidebar from "./layouts/AdminSidebar.vue";
const app = createApp(App);

app.component("navbar-component", navbarComponent);
app.component("footer-component", footer);
app.component("admin-header", AdminHeader);
app.component("admin-layout", AdminLayout);
app.component("admin-sidebar", AdminSidebar);

app.use(router);
app.mount("#app");
