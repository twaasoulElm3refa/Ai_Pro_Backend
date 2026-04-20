import { createRouter, createWebHistory } from "vue-router";
import Home from "../views/home/home.vue";
import Register from "../views/auth/register.vue";
import Profile from "../views/home/user/profile.vue";
import GoogleCallback from "../views/auth/GoogleCallback.vue";
import Contact from "../views/home/contact.vue";
import wallet from "../views/home/user/wallet.vue";

const routes = [
    {
        path: "/",
        redirect: () => {
            const lang = localStorage.getItem("lang") || "ar"
            return `/${lang}`
        },
    },
    {
        path: "/:lang/",
        component: Home,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/profile",
        component: Profile,
    },
    {
        path: "/:lang/wallet",
        component: wallet,
    },
    {
        path: "/:lang/contact",
        component: Contact,
    },
    {
        path: "/:lang/auth",
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
