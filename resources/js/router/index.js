import { createRouter, createWebHistory } from "vue-router";
import i18n from "@/i18n";
import homeService from "@/services/home/homeService";

const Home = () => import("../views/home/home.vue");
const Register = () => import("../views/auth/register.vue");
const Profile = () => import("../views/home/user/profile.vue");
const GoogleCallback = () => import("../views/auth/GoogleCallback.vue");
const Contact = () => import("../views/home/contact.vue");
const tools = () => import("../views/home/tools.vue");
const wallet = () => import("../views/home/user/wallet.vue");
const show = () => import("../views/home/show.vue");
const chat = () => import("../views/home/chat.vue");
const chat2 = () => import("../views/home/chat2.vue");
const chat3 = () => import("../views/home/chat3.vue");
const chat4 = () => import("../views/home/chat4.vue");
const chat5 = () => import("../views/home/chat5.vue");
const charge = () => import("../views/home/user/charge.vue");
const WaitingDeposit = () => import("../views/home/WaitingDeposit.vue");
const success = () => import("../views/home/successDeposit.vue");
const failed = () => import("../views/home/failedDeposit.vue");
const cancelled = () => import("../views/home/CancelledDeposit.vue");
const FreeAiModelShow = () => import("../views/home/free-ai-models/FreeAiModelShow.vue");
const FreeAiModelChat = () => import("../views/home/free-ai-models/FreeAiModelChat.vue");

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
        path: "/:lang/tools",
        component: tools,
        meta: { hideNavbar: false, hideFooter: false, hideHeader: true },
    },
    {
        path: "/:lang/free-ai/:slug",
        name: "free-ai-model.show",
        component: FreeAiModelShow,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/free-ai/:slug/chat/:uuid",
        name: "free-ai-model.chat",
        component: FreeAiModelChat,
        meta: {
            hideNavbar: false,
            hideFooter: true,
            hideHeader: true,
            requiresUserAuth: true,
        },
    },
    {
        path: "/:lang/subtool/:slug/chat/:uuid?",
        component: chat,
        meta: { hideNavbar: false, hideFooter: true, hideHeader: true },
    },
    {
        path: "/:lang/subtool/:slug/chat2/:uuid?",
        component: chat2,
        meta: { hideNavbar: false, hideFooter: true, hideHeader: true },
    },
    {
        path: "/:lang/subtool/:slug/chat3/:uuid?",
        component: chat3,
        meta: { hideNavbar: false, hideFooter: true, hideHeader: true },
    },
    {
        path: "/:lang/subtool/:slug/chat4/:uuid?",
        component: chat4,
        meta: { hideNavbar: false, hideFooter: true, hideHeader: true },
    },
    {
        path: "/:lang/subtool/:slug/chat5/:uuid?",
        component: chat5,
        meta: { hideNavbar: true, hideFooter: true, hideHeader: true },
    },
    {
        path: "/:lang/profile",
        component: Profile,
        meta: { hideNavbar: false, hideFooter: true, hideHeader: true },
    },
    {
        path: "/:lang/wallet",
        component: wallet,
        meta: { hideNavbar: false, hideFooter: true, hideHeader: true },
    },
    {
        path: "/:lang/Deposit/waiting",
        component: WaitingDeposit,
        meta: { hideNavbar: false, hideFooter: false, hideHeader: true },
    },
    {
        path: "/:lang/deposit/success",
        component: success,
        meta: { hideNavbar: false, hideFooter: false, hideHeader: true },
    },
    {
        path: "/:lang/deposit/cancel",
        component: cancelled,
        meta: { hideNavbar: false, hideFooter: false, hideHeader: true },
    },
    {
        path: "/:lang/failed",
        component: failed,
        meta: { hideNavbar: false, hideFooter: false, hideHeader: true },
    },
    {
        path: "/:lang/wallet/charge/:uuid",
        name: "charge-wallet",
        component: charge,
        meta: { hideNavbar: false, hideFooter: true, hideHeader: true },
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
        path: "/admin/free-ai-models",
        name: "admin.free-ai-models.index",
        component: () => import("../views/admin/free-ai-models/index.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/free-ai-models/create",
        name: "admin.free-ai-models.create",
        component: () => import("../views/admin/free-ai-models/create.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/free-ai-models/:id/edit",
        name: "admin.free-ai-models.edit",
        component: () => import("../views/admin/free-ai-models/edit.vue"),
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
        path: "/admin/cost",
        name: "admin.cost.index",
        component: () => import("../views/admin/cost/CostIndex.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/cost/today",
        name: "admin.cost.today",
        component: () => import("../views/admin/cost/CostToday.vue"),
        meta: adminMeta,
    },
    {
        path: "/admin/cost/show/:id",
        name: "admin.cost.show",
        component: () => import("../views/admin/cost/CostShow.vue"),
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

    if (to.meta.requiresUserAuth && !token) {
        return next(`/${homeService.getLang()}/auth`);
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
