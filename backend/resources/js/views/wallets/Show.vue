<template>
  <div v-loading="loading">
    <el-page-header @back="$router.push({ name: 'wallets.index' })" :content="wallet ? wallet.wallet_no : ''" />
    <div v-if="wallet" style="margin-top: 20px">
      <el-row :gutter="20">
        <el-col :span="8">
          <el-card>
            <div slot="header">钱包信息</div>
            <el-descriptions :column="1" border size="small">
              <el-descriptions-item label="钱包编号">{{ wallet.wallet_no }}</el-descriptions-item>
              <el-descriptions-item label="经销商">{{ wallet.distributor ? wallet.distributor.name : '-' }}</el-descriptions-item>
              <el-descriptions-item label="状态">
                <el-tag :type="getStatusType(wallet.status)" size="small">{{ getStatusLabel(wallet.status) }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="余额">{{ parseFloat(wallet.balance).toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="冻结金额">{{ parseFloat(wallet.frozen_amount).toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="可用余额">{{ (parseFloat(wallet.balance) - parseFloat(wallet.frozen_amount)).toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="信用额度">{{ parseFloat(wallet.credit_limit).toFixed(2) }}</el-descriptions-item>
            </el-descriptions>
          </el-card>

          <el-card style="margin-top: 20px">
            <div slot="header">充值</div>
            <el-form :model="rechargeForm" label-width="80px" size="small">
              <el-form-item label="金额">
                <el-input-number v-model="rechargeForm.amount" :min="0.01" :precision="2" style="width: 100%" />
              </el-form-item>
              <el-form-item label="备注">
                <el-input v-model="rechargeForm.remark" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="recharging" @click="handleRecharge">充值</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <el-card style="margin-top: 20px">
            <div slot="header">统计</div>
            <el-descriptions :column="1" border size="small" v-if="statistics">
              <el-descriptions-item label="期间">{{ statistics.period.start_date }} ~ {{ statistics.period.end_date }}</el-descriptions-item>
              <el-descriptions-item label="收入">{{ statistics.income.toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="支出">{{ statistics.expense.toFixed(2) }}</el-descriptions-item>
              <el-descriptions-item label="净流">{{ statistics.net_flow.toFixed(2) }}</el-descriptions-item>
            </el-descriptions>
          </el-card>
        </el-col>

        <el-col :span="16">
          <el-card>
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

          <el-card style="margin-top: 20px">
            <div slot="header">状态变更记录</div>
            <el-timeline>
              <el-timeline-item
                v-for="log in stateLogs"
                :key="log.id"
                :timestamp="log.created_at"
                placement="top"
              >
                <el-tag size="mini" type="info">{{ log.from_status_label }}</el-tag>
                <i class="el-icon-right" style="margin: 0 5px"></i>
                <el-tag size="mini" :type="getStatusType(log.to_status)">{{ log.to_status_label }}</el-tag>
                <span style="margin-left: 10px; color: #909399">{{ log.action_label }}</span>
                <div v-if="log.reason" style="color: #909399; font-size: 12px; margin-top: 4px">原因：{{ log.reason }}</div>
                <div v-if="log.operator_name" style="color: #909399; font-size: 12px">操作人：{{ log.operator_name }}</div>
              </el-timeline-item>
            </el-timeline>
          </el-card>
        </el-col>
      </el-row>
    </div>
  </div>
</template>

<script>
import walletApi from '../../api/wallet';

export default {
  name: 'WalletShow',

  data() {
    return {
      wallet: null,
      loading: false,
      transactions: [],
      txTotal: 0,
      txPage: 1,
      stateLogs: [],
      statistics: null,
      rechargeForm: { amount: 0.01, remark: '' },
      recharging: false,
    };
  },

  created() {
    this.loadWallet();
    this.loadTransactions();
    this.loadStateLogs();
    this.loadStatistics();
  },

  methods: {
    async loadWallet() {
      this.loading = true;
      try {
        const { data } = await walletApi.get(this.$route.params.id);
        this.wallet = data.data;
      } finally {
        this.loading = false;
      }
    },

    async loadTransactions() {
      try {
        const { data } = await walletApi.getTransactions(this.$route.params.id, { page: this.txPage, per_page: 15 });
        this.transactions = data.data.data;
        this.txTotal = data.data.total;
      } catch (e) {
        // ignore
      }
    },

    async loadStateLogs() {
      try {
        const { data } = await walletApi.getStateLogs(this.$route.params.id, { per_page: 50 });
        this.stateLogs = data.data.data;
      } catch (e) {
        // ignore
      }
    },

    async loadStatistics() {
      try {
        const { data } = await walletApi.getStatistics(this.$route.params.id);
        this.statistics = data.data;
      } catch (e) {
        // ignore
      }
    },

    async handleRecharge() {
      this.recharging = true;
      try {
        await walletApi.recharge(this.$route.params.id, this.rechargeForm);
        this.$message.success('充值成功');
        this.rechargeForm = { amount: 0.01, remark: '' };
        this.loadWallet();
        this.loadTransactions();
        this.loadStatistics();
      } catch (e) {
        this.$message.error(e.response?.data?.message || '充值失败');
      } finally {
        this.recharging = false;
      }
    },

    getStatusType(status) {
      const map = { active: 'success', frozen: 'danger', restricted: 'warning', inactive: 'info', closed: '' };
      return map[status] || '';
    },

    getStatusLabel(status) {
      const map = { active: '正常', frozen: '已冻结', restricted: '受限', inactive: '未激活', closed: '已注销' };
      return map[status] || status;
    },
  },
};
</script>
