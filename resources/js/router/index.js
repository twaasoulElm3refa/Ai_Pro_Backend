import { createRouter, createWebHistory } from "vue-router";
import Home from "../views/home/home.vue";
import Register from "../views/auth/register.vue";
import Profile from "../views/home/profile.vue";
import GoogleCallback from "../views/auth/GoogleCallback.vue";
import Contact from "../views/home/contact.vue";

const routes = [
    {
        path: "/",
        component: Home,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/profile",
        component: Profile,
    },
    {
        path: "/contact",
        component: Contact,
    },
    {
        path: "/auth",
        component: Register,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/google-callback",
        component: GoogleCallback,
        meta: { hideNavbar: true, hideFooter: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem("auth_token");
    const role = localStorage.getItem("user_role");

    if (to.path.startsWith("/admin")) {
        if (token && role === "admin") {
            return next();
        }
        return next("/auth");
    }

    if (to.path === "/auth") {
        if (!token) {
            return next();
        }

        if (role === "admin") {
            return next("/admin");
        }

        return next("/");
    }

    next();
});

export default router;
