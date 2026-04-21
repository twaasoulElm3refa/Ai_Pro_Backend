import { createRouter, createWebHistory } from "vue-router";
import Home from "../views/home/home.vue";
import Register from "../views/auth/register.vue";
import Profile from "../views/home/user/profile.vue";
import GoogleCallback from "../views/auth/GoogleCallback.vue";
import Contact from "../views/home/contact.vue";
import wallet from "../views/home/user/wallet.vue";
import login from "../views/admin/auth/login.vue";
import admin from "../views/admin/index.vue";
import addUser from "../views/admin/users/add.vue";
import users from "../views/admin/users/all.vue";
import settings from "../views/admin/settings/settings.vue";

const routes = [
    {
        path: "/",
        redirect: () => {
            const lang = localStorage.getItem("language") || "ar"
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


    // admin
    {
        path: "/admin/auth",
        component: login,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin",
        component: admin,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/users",
        component: users,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/users/add",
        component: addUser,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/settings",
        component: settings,
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

    if (to.path === "/admin/auth") {
        return next();
    }

    if (to.path.startsWith("/admin")) {
        if (token && role === "admin") {
            return next();
        }
        return next("/en/auth");
    }

    if (to.path === "/en/auth") {
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
