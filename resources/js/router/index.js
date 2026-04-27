import { createRouter, createWebHistory } from "vue-router";
import Home from "../views/home/home.vue";
import Register from "../views/auth/register.vue";
import Profile from "../views/home/user/profile.vue";
import GoogleCallback from "../views/auth/GoogleCallback.vue";
import Contact from "../views/home/contact.vue";
import wallet from "../views/home/user/wallet.vue";
import show from "../views/home/show.vue";
import chat from "../views/home/chat.vue";
import charge from "../views/home/user/charge.vue";
import WaitingDeposit from '../views/home/WaitingDeposit.vue'
import success from '../views/home/successDeposit.vue'
import failed from '../views/home/failedDeposit.vue'

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
        path: "/:lang/tool/:slug",
        component: show,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/subtool/:slug/chat",
        component: chat,
        meta: { hideNavbar: false, hideFooter: true },
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
        path: "/:lang/Deposit/waiting",
        component: WaitingDeposit,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/en/deposit/success",
        component: success,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/failed",
        component: failed,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/wallet/charge/:uuid",
        name: "charge-wallet",
        component: charge,
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
        name: "admin.dashboard",
        component: () => import("../views/admin/index.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users",
        name: "admin.users.index",
        component: () => import("../views/admin/users/all_users.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users/add",
        name: "admin.users.create",
        component: () => import("../views/admin/users/add_user.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users/edit/:id",
        name: "admin.users.edit",
        component: () => import("../views/admin/users/edit_user.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/users/show/:id",
        name: "admin.users.show",
        component: () => import("../views/admin/users/show_user.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/footer",
        name: "admin.footer",
        component: () => import("../views/admin/settings/footer/footer.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/contacts",
        name: "admin.contacts",
        component: () => import("../views/admin/settings/contacts/contacts.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/profile",
        name: "admin.profile",
        component: () => import("../views/admin/profile/ProfilePage.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/profile/edit",
        name: "admin.profile.edit",
        component: () => import("../views/admin/profile/EditProfilePage.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/password",
        name: "admin.password",
        component: () => import("../views/admin/profile/ChangePasswordPage.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/logout",
        name: "admin.logout",
        component: () => import("../views/admin/profile/LogoutPage.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/tools",
        name: "admin.tools.index",
        component: () => import("../views/admin/tools/ToolsList.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/tools/create",
        name: "admin.tools.create",
        component: () => import("../views/admin/tools/ToolCreate.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/tools/:id/edit",
        name: "admin.tools.edit",
        component: () => import("../views/admin/tools/ToolEdit.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/tools/:id",
        name: "admin.tools.show",
        component: () => import("../views/admin/tools/ToolShow.vue"),
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

    // ✅ Sync language with localStorage
    if (to.params.lang) {
        const currentLang = localStorage.getItem("language");

        if (currentLang !== to.params.lang) {
            localStorage.setItem("language", to.params.lang);
        }
    }

    // =========================

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
