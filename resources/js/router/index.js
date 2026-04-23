import { createRouter, createWebHistory } from "vue-router";
import Home from "../views/home/home.vue";
import Register from "../views/auth/register.vue";
import Profile from "../views/home/user/profile.vue";
import GoogleCallback from "../views/auth/GoogleCallback.vue";
import Contact from "../views/home/contact.vue";
import wallet from "../views/home/user/wallet.vue";

const adminMeta = {
    hideNavbar: true,
    hideFooter: true,
    requiresAuth: true,
    role: "admin",
};

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
        alias: "/login",
        component: () => import("../views/admin/auth/login.vue"),
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin",
        component: () => import("../views/admin/index.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users",
        component: () => import("../views/admin/users/all_users.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users/add",
        component: () => import("../views/admin/users/add_user.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users/edit/:id",
        component: () => import("../views/admin/users/edit_user.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users/show/:id",
        component: () => import("../views/admin/users/show_user.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/footer",
        component: () => import("../views/admin/settings/footer/footer.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/contacts",
        component: () => import("../views/admin/settings/contacts/contacts.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/profile",
        component: () => import("../views/admin/profile/ProfilePage.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/profile/edit",
        component: () => import("../views/admin/profile/EditProfilePage.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/password",
        component: () => import("../views/admin/profile/ChangePasswordPage.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/logout",
        component: () => import("../views/admin/profile/LogoutPage.vue"),
        meta: adminMeta,
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem("auth_token");
    const role = localStorage.getItem("user_role");

    if (to.path === "/admin/auth" || to.path === "/login") {
        if (token && role === "admin") {
            return next("/admin");
        }
        return next();
    }

    if (to.meta.requiresAuth) {
        if (!token) {
            return next("/login");
        }

        if (!to.meta.role || role === to.meta.role) {
            return next();
        }

        return next("/login");
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
