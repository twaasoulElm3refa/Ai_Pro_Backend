import { createRouter, createWebHistory } from "vue-router";
import i18n from "@/i18n";
import homeService from "@/services/home/homeService";

const Home = () => import("../views/home/home.vue");
const Register = () => import("../views/auth/register.vue");
const Profile = () => import("../views/home/user/profile.vue");
const GoogleCallback = () => import("../views/auth/GoogleCallback.vue");
const Contact = () => import("../views/home/contact.vue");
const wallet = () => import("../views/home/user/wallet.vue");
const show = () => import("../views/home/show.vue");
const chat = () => import("../views/home/chat.vue");
const charge = () => import("../views/home/user/charge.vue");
const WaitingDeposit = () => import("../views/home/WaitingDeposit.vue");
const success = () => import("../views/home/successDeposit.vue");
const failed = () => import("../views/home/failedDeposit.vue");

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
            const lang = homeService.getLang();
            return `/${lang}`;
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
        path: "/:lang/subtool/:slug/chat/:uuid?",
        component: chat,
        meta: { hideNavbar: false, hideFooter: true },
    },
    {
        path: "/:lang/profile",
        component: Profile,
        meta: { hideNavbar: false, hideFooter: true },
    },
    {
        path: "/:lang/wallet",
        component: wallet,
        meta: { hideNavbar: false, hideFooter: true },
    },
    {
        path: "/:lang/Deposit/waiting",
        component: WaitingDeposit,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/deposit/success",
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
        meta: { hideNavbar: false, hideFooter: true },
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
    {
        path: "/admin/payments",
        name: "admin.payments.index",
        component: () => import("../views/admin/payments/PaymentsIndex.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/payments/:id",
        name: "admin.payments.show",
        component: () => import("../views/admin/payments/PaymentShow.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/statistics",
        name: "admin.statistics",
        component: () => import("../views/admin/statistics/Statistics.vue"),
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

    // Sync language with i18n + storage + cache behavior.
    if (to.params.lang) {
        const requestedLang = String(to.params.lang).toLowerCase();
        const currentLang = homeService.getLang();

        if (currentLang !== requestedLang) {
            homeService.setLang(requestedLang);
        }

        const normalizedLang = homeService.getLang();
        if (i18n.global.locale.value !== normalizedLang) {
            i18n.global.locale.value = normalizedLang;
        }

        if (requestedLang !== normalizedLang) {
            return next({
                ...to,
                params: {
                    ...to.params,
                    lang: normalizedLang,
                },
                replace: true,
            });
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

    if (to.matched.some((record) => record.path === "/:lang/auth")) {
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
