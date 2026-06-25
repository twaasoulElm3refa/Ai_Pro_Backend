<template>
    <loader-component v-if="isLoading" />

    <div v-else class="d-flex flex-column min-vh-100">
        <navbar-component
            v-if="showNavbar"
            :hide-header="hideHeader"
        />

        <main class="flex-fill">
            <router-view />
        </main>

        <footer-component v-if="!$route.meta.hideFooter" />
    </div>
</template>

<script>
import LoaderComponent from "./components/layouts/Loader.vue";

export default {
    name: "App",

    components: {
        LoaderComponent,
    },

    data() {
        return {
            isLoading: true,
        };
    },

    mounted() {
        setTimeout(() => {
            this.isLoading = false;
        }, 1500);
    },

    computed: {
        showNavbar() {
            return !this.$route.meta.hideNavbar;
        },

        hideHeader() {
            return this.$route.meta.hideHeader === true;
        },
    },
};
</script>
