<template>
    <AdminLayout>
        <section class="space-y-6">
            <div class="rounded-2xl border border-[#154677]/10 bg-white p-8 shadow-sm shadow-[#154677]/10">
                <span class="inline-flex items-center rounded-full bg-[#154677]/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#154677]">
                    Admin Dashboard
                </span>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-[#154677]">Welcome back to AI PRO</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500">
                    Use the dashboard shortcuts below to jump into statistics, payments, users, tools, and other admin workflows.
                </p>
            </div>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <RouterLink
                    v-for="item in dashboardLinks"
                    :key="item.title"
                    :to="item.to"
                    class="rounded-2xl border border-[#154677]/10 bg-white p-5 shadow-sm shadow-[#154677]/10 transition duration-200 hover:-translate-y-1 hover:shadow-lg"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#154677]/10 text-[#154677]">
                        <i class="bi" :class="item.icon"></i>
                    </div>
                    <h2 class="mt-5 text-lg font-semibold text-[#154677]">{{ item.title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ item.description }}</p>
                    <div class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-[#154677]">
                        Open
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </RouterLink>
            </section>
        </section>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/layouts/AdminLayout.vue'
import { computed } from 'vue'

const dashboardLinks = computed(() => [
    {
        title: 'Statistics',
        description: 'View platform revenue, user growth, wallet balance, and latest activity.',
        to: { name: 'admin.statistics' },
        icon: 'bi-bar-chart-line',
    },
    {
        title: 'Payments',
        description: 'Review and manage payment records with status-level visibility.',
        to: { name: 'admin.payments.index' },
        icon: 'bi-credit-card-2-front',
    },
    {
        title: 'Users',
        description: 'Access user accounts, activity, and moderation tools.',
        to: { name: 'admin.users.index' },
        icon: 'bi-people',
    },
    {
        title: 'Tools',
        description: 'Manage tools, subtools, and product inventory from one place.',
        to: { name: 'admin.tools.index' },
        icon: 'bi-grid',
    },
])
</script>
