<template>
  <div>
    <el-row :gutter="20" v-if="isPlatform">
      <el-col :span="6" v-for="item in statusCards" :key="item.label">
        <el-card shadow="hover">
          <div style="text-align: center">
            <div style="font-size: 28px; font-weight: bold; color: #409EFF">{{ item.count }}</div>
            <div style="color: #909399; margin-top: 8px">{{ item.label }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>
    <el-card style="margin-top: 20px" v-if="!isPlatform">
      <div slot="header">我的钱包</div>
      <div v-if="myBalance">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="总余额">{{ myBalance.total_balance }} {{ myBalance.currency }}</el-descriptions-item>
          <el-descriptions-item label="可用余额">{{ myBalance.available_balance }} {{ myBalance.currency }}</el-descriptions-item>
          <el-descriptions-item label="冻结金额">{{ myBalance.frozen_amount }} {{ myBalance.currency }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(myBalance.status)">{{ myBalance.status_label }}</el-tag>
          </el-descriptions-item>
        </el-descriptions>
      </div>
      <el-empty v-else description="暂无钱包信息" />
    </el-card>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import walletApi from '../api/wallet';

export default {
  name: 'Dashboard',

  data() {
    return {
      statusCards: [
        { label: '正常', count: 0, status: 'active' },
        { label: '已冻结', count: 0, status: 'frozen' },
        { label: '受限', count: 0, status: 'restricted' },
        { label: '未激活', count: 0, status: 'inactive' },
      ],
      myBalance: null,
    };
  },

  computed: {
    ...mapGetters('auth', ['user']),
    isPlatform() {
      return this.user && this.user.user_type === 'platform';
    },
  },

  created() {
    if (this.isPlatform) {
      this.loadDashboard();
    } else {
      this.loadMyBalance();
    }
  },

  methods: {
    async loadDashboard() {
      try {
        const { data } = await walletApi.list({ per_page: 1 });
      } catch (e) {
        // ignore
      }
    },

    async loadMyBalance() {
      try {
        const { data } = await walletApi.getMyBalance();
        this.myBalance = data.data;
      } catch (e) {
        // ignore
      }
    },

    getStatusType(status) {
      const map = { active: 'success', frozen: 'danger', restricted: 'warning', inactive: 'info', closed: '' };
      return map[status] || '';
    },
  },
};
</script>
