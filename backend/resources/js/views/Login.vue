<template>
  <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f2f5">
    <el-card style="width: 400px">
      <div slot="header" style="text-align: center; font-size: 20px; font-weight: bold">
        Shearerline 钱包管理系统
      </div>
      <el-form :model="form" :rules="rules" ref="loginForm" @submit.native.prevent="handleLogin">
        <el-form-item prop="email">
          <el-input v-model="form.email" prefix-icon="el-icon-user" placeholder="邮箱" />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" prefix-icon="el-icon-lock" type="password" placeholder="密码" show-password />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" style="width: 100%" :loading="loading" native-type="submit">
            登录
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script>
import { mapActions } from 'vuex';

export default {
  name: 'Login',

  data() {
    return {
      form: {
        email: '',
        password: '',
      },
      rules: {
        email: [{ required: true, message: '请输入邮箱', trigger: 'blur' }],
        password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
      },
      loading: false,
    };
  },

  methods: {
    ...mapActions('auth', ['login']),

    async handleLogin() {
      this.$refs.loginForm.validate(async (valid) => {
        if (!valid) return;

        this.loading = true;
        try {
          await this.login(this.form);
          this.$router.push({ name: 'dashboard' });
        } catch (error) {
          const msg = error.response?.data?.message || '登录失败';
          this.$message.error(msg);
        } finally {
          this.loading = false;
        }
      });
    },
  },
};
</script>
