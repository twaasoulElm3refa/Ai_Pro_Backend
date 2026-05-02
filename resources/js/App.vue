<template>
    <div class="d-flex flex-column min-vh-100">
        <navbar-component v-if="showNavbar" />

        <main class="flex-fill">
            <router-view />
        </main>

        <footer-component v-if="!$route.meta.hideFooter" />
    </div>
</template>

<script>
export default {
    name: "App",

    computed: {
        showNavbar() {
            return !this.$route.meta.hideNavbar;
        },
    },
};
</script>

