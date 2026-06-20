<template>
  <div v-loading="loading">
    <el-card v-if="balance">
      <div slot="header">我的钱包</div>
      <el-descriptions :column="2" border>
        <el-descriptions-item label="钱包编号">{{ balance.wallet_no }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="getStatusType(balance.status)" size="small">{{ balance.status_label }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="总余额">{{ balance.total_balance.toFixed(2) }} {{ balance.currency }}</el-descriptions-item>
        <el-descriptions-item label="可用余额">{{ balance.available_balance.toFixed(2) }} {{ balance.currency }}</el-descriptions-item>
        <el-descriptions-item label="冻结金额">{{ balance.frozen_amount.toFixed(2) }} {{ balance.currency }}</el-descriptions-item>
        <el-descriptions-item label="信用额度">{{ balance.credit_limit.toFixed(2) }} {{ balance.currency }}</el-descriptions-item>
      </el-descriptions>
    </el-card>

    <el-card style="margin-top: 20px" v-if="statistics">
      <div slot="header">收支统计</div>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="收入">{{ statistics.income.toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="支出">{{ statistics.expense.toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="净流">{{ statistics.net_flow.toFixed(2) }}</el-descriptions-item>
      </el-descriptions>
    </el-card>

    <el-card style="margin-top: 20px">
      <div slot="header">交易记录</div>
      <el-table :data="transactions" stripe border size="small">
        <el-table-column prop="transaction_no" label="交易号" width="180" />
        <el-table-column prop="type_label" label="类型" width="80" />
        <el-table-column prop="amount" label="金额" width="100" align="right" />
        <el-table-column prop="balance_after" label="余额" width="100" align="right" />
        <el-table-column prop="remark" label="备注" />
        <el-table-column prop="created_at" label="时间" width="170" />
      </el-table>
      <el-pagination
        v-if="txTotal > 0"
        style="margin-top: 15px; text-align: right"
        :current-page="txPage"
        :page-size="15"
        :total="txTotal"
        layout="total, prev, pager, next"
        @current-change="val => { txPage = val; loadTransactions(); }"
      />
    </el-card>
  </div>
</template>

<script>
import walletApi from '../../api/wallet';

export default {
  name: 'MyWallet',

  data() {
    return {
      loading: false,
      balance: null,
      statistics: null,
      transactions: [],
      txTotal: 0,
      txPage: 1,
    };
  },

  created() {
    this.loadBalance();
    this.loadTransactions();
    this.loadStatistics();
  },

  methods: {
    async loadBalance() {
      this.loading = true;
      try {
        const { data } = await walletApi.getMyBalance();
        this.balance = data.data;
      } catch (e) {
        this.$message.error('加载钱包信息失败');
      } finally {
        this.loading = false;
      }
    },

    async loadTransactions() {
      try {
        const { data } = await walletApi.getMyTransactions({ page: this.txPage, per_page: 15 });
        this.transactions = data.data.data;
        this.txTotal = data.data.total;
      } catch (e) {
        // ignore
      }
    },

    async loadStatistics() {
      try {
        const { data } = await walletApi.getMyStatistics();
        this.statistics = data.data;
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
