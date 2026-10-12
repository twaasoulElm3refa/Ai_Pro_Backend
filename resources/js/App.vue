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
    <insufficient-credits-notice v-if="creditError" :error="creditError" @close="creditError = null" />
</template>

<script>
import LoaderComponent from "./components/layouts/Loader.vue";
import InsufficientCreditsNotice from "./components/InsufficientCreditsNotice.vue";

export default {
    name: "App",

    components: {
        LoaderComponent,
        InsufficientCreditsNotice,
    },

    data() {
        return {
            isLoading: true,
            creditError: null,
        };
    },

    mounted() {
        this.onInsufficientCredits = (event) => {
            if (event.detail?.message_id && event.detail.message_id === this.creditError?.message_id) return;
            this.creditError = event.detail;
        };
        window.addEventListener("insufficient-credits", this.onInsufficientCredits);
        setTimeout(() => {
            this.isLoading = false;
        }, 1500);
    },

    beforeUnmount() {
        window.removeEventListener("insufficient-credits", this.onInsufficientCredits);
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
