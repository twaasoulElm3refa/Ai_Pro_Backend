<template>
  <aside class="admin-sidebar">
    <div class="sidebar-shell">
      <div class="sidebar-brand">
        <div class="sidebar-brand-mark">
          <i class="fas fa-layer-group"></i>
        </div>
        <div>
          <h2>Admin Panel</h2>
          <p>Control Center</p>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a
          v-for="item in links"
          :key="item.label"
          :href="item.href"
          class="sidebar-link"
          :class="isActive(item.route) ? 'is-active' : ''"
        >
          <i :class="item.icon"></i>
          <span>{{ item.label }}</span>
        </a>
      </nav>
    </div>
  </aside>
</template>

<script>
export default {
  name: 'AdminSidebar',

  data() {
    return {
      links: [
        {
          label: 'Dashboard',
          href: '/admin',
          route: 'admin.index',
          icon: 'fas fa-chart-line',
        },
        {
          label: 'Users',
          href: '/admin/users',
          route: 'admin.users',
          icon: 'fas fa-users',
        },
        {
          label: 'Contacts',
          href: '/admin/contacts',
          route: 'admin.contacts',
          icon: 'fas fa-envelope',
        },
      ],
    }
  },

  methods: {
    isActive(routeName) {
      return document.body.dataset.route === routeName
    },
  },
}
</script>

<style scoped>
.admin-sidebar {
  width: 16rem;
  min-height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  padding: 18px;
  background: linear-gradient(180deg, #f8fbff, #eef7fc);
}

.sidebar-shell {
  height: calc(100vh - 36px);
  border-radius: 1.75rem;
  background: #ffffff;
  border: 1px solid rgba(21, 70, 119, 0.1);
  box-shadow: 0 24px 46px rgba(21, 70, 119, 0.1);
  padding: 24px 18px;
  display: flex;
  flex-direction: column;
  gap: 26px;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  padding-bottom: 18px;
  border-bottom: 1px solid rgba(21, 70, 119, 0.08);
}

.sidebar-brand-mark {
  width: 48px;
  height: 48px;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #154677, #2ba6de);
  color: #ffffff;
  box-shadow: 0 16px 30px rgba(21, 70, 119, 0.18);
}

.sidebar-brand h2 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 800;
  color: #154677;
}

.sidebar-brand p {
  margin: 4px 0 0;
  font-size: 0.85rem;
  color: #5f7288;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 46px;
  padding: 0 14px;
  border-radius: 1rem;
  color: #5f7288;
  text-decoration: none;
  font-weight: 600;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.sidebar-link:hover {
  transform: translateY(-1px);
  background: rgba(43, 166, 222, 0.08);
  color: #154677;
}

.sidebar-link.is-active {
  background: linear-gradient(135deg, #154677, #2ba6de);
  color: #ffffff;
  box-shadow: 0 16px 28px rgba(21, 70, 119, 0.18);
}
</style>
