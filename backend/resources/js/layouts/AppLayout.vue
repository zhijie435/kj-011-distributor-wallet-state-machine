<template>
  <el-container style="height: 100vh">
    <el-aside width="220px" style="background: #304156">
      <div style="padding: 20px; color: #fff; font-size: 18px; font-weight: bold; text-align: center">
        Shearerline
      </div>
      <el-menu
        :default-active="$route.path"
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409EFF"
        router
      >
        <el-menu-item index="/">
          <i class="el-icon-s-home"></i>
          <span>仪表盘</span>
        </el-menu-item>
        <el-menu-item index="/wallets" v-if="can('wallet.view')">
          <i class="el-icon-wallet"></i>
          <span>钱包管理</span>
        </el-menu-item>
        <el-menu-item index="/my-wallet">
          <i class="el-icon-coin"></i>
          <span>我的钱包</span>
        </el-menu-item>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e6e6e6">
        <span style="font-size: 16px">{{ $route.meta.title || '仪表盘' }}</span>
        <el-dropdown @command="handleCommand">
          <span style="cursor: pointer">
            {{ user ? user.name : '' }} <i class="el-icon-arrow-down el-icon--right"></i>
          </span>
          <el-dropdown-menu slot="dropdown">
            <el-dropdown-item command="logout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </el-dropdown>
      </el-header>
      <el-main>
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';

export default {
  name: 'AppLayout',

  computed: {
    ...mapGetters('auth', ['user', 'can']),
  },

  created() {
    if (!this.user) {
      this.fetchUser();
    }
  },

  methods: {
    ...mapActions('auth', ['fetchUser', 'logout']),

    async handleCommand(command) {
      if (command === 'logout') {
        await this.logout();
        this.$router.push({ name: 'login' });
      }
    },
  },
};
</script>

<style scoped>
.el-menu {
  border-right: none;
}
</style>
