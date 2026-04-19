import { createRouter, createWebHistory } from "vue-router";
import Home from "../views/home/home.vue";
import Register from "../views/auth/register.vue";
import profile from "../views/home/profile.vue";
import contact from "../views/home/contact.vue";
import admin from "../views/admin/index.vue";
import All from "../views/admin/users/all.vue";
import Add from "../views/admin/users/add.vue";
import allBrands from "../views/admin/brands/allBrands.vue";
import addBrand from "../views/admin/brands/addBrand.vue";
import showBrand from "../views/admin/brands/showBrand.vue";
import allcategories from "../views/admin/categorey/allcategories.vue";
import showCategorey from "../views/admin/categorey/showCategorey.vue";
import addcategorey from "../views/admin/categorey/addcategorey.vue";
import allProducts from "../views/admin/products/allProducts.vue";
import addProduct from "../views/admin/products/addProduct.vue";
import showProduct from "../views/admin/products/showProduct.vue";

const routes = [
    {
        path: "/",
        component: Home,
        meta: { hideNavbar: true, hideFooter: false },
    },
    {
        path: "/profile",
        component: profile,
    },
    {
        path: "/contact",
        component: contact,
    },
    {
        path: "/auth",
        component: Register,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin",
        component: admin,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/products",
        component: allProducts,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/products/:id",
        component: showProduct,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/products/create",
        component: addProduct,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/settings",
        component: admin,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/users",
        component: All,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/users/add",
        component: Add,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/brands",
        component: allBrands,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/brands/:id",
        component: showBrand,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/brands/create",
        component: addBrand,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/categories",
        component: allcategories,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/categories/:id",
        component: showCategorey,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/categories/create",
        component: addcategorey,
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

    // ===== Admin Routes =====
    if (to.path.startsWith("/admin")) {
        if (token && role === "admin") {
            return next();
        } else {
            return next("/auth");
        }
    }

    // ===== Auth Route =====
    if (to.path === "/auth") {
        if (!token) {
            return next();
        } else {
            if (role === "admin") return next("/admin");
            return next("/");
        }
    }

    next();
});

export default router;
