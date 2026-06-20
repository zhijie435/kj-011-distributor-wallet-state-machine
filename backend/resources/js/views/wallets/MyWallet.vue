<template>
  <div v-loading="loading">
    <el-card v-if="balance">
      <div slot="header" style="display: flex; justify-content: space-between; align-items: center">
        <span>我的钱包</span>
        <el-tag :type="getStatusType(balance.status)" size="medium">
          <i :class="getStatusIcon(balance.status)" style="margin-right: 4px"></i>
          {{ balance.status_label }}
        </el-tag>
      </div>
      <el-descriptions :column="2" border>
        <el-descriptions-item label="钱包编号">{{ balance.wallet_no }}</el-descriptions-item>
        <el-descriptions-item label="状态值">{{ balance.status }}</el-descriptions-item>
        <el-descriptions-item label="总余额">
          <span style="font-size: 18px; font-weight: bold; color: #409EFF">{{ Number(balance.total_balance).toFixed(2) }}</span>
          {{ balance.currency }}
        </el-descriptions-item>
        <el-descriptions-item label="可用余额">
          <span style="font-size: 16px; font-weight: bold; color: #67C23A">{{ Number(balance.available_balance).toFixed(2) }}</span>
          {{ balance.currency }}
        </el-descriptions-item>
        <el-descriptions-item label="冻结金额">
          <span style="color: #E6A23C">{{ Number(balance.frozen_amount).toFixed(2) }}</span>
          {{ balance.currency }}
        </el-descriptions-item>
        <el-descriptions-item label="信用额度">{{ Number(balance.credit_limit).toFixed(2) }} {{ balance.currency }}</el-descriptions-item>
      </el-descriptions>
    </el-card>

    <el-alert
      v-if="balance && balance.status === 'frozen'"
      title="钱包已冻结"
      type="warning"
      :closable="false"
      description="您的钱包已被冻结，暂时无法进行充值、消费等操作，请联系客服处理。"
      show-icon
      style="margin-top: 20px"
    />
    <el-alert
      v-if="balance && balance.status === 'restricted'"
      title="钱包受限"
      type="warning"
      :closable="false"
      description="您的钱包处于受限状态，部分操作可能受到限制，请联系客服处理。"
      show-icon
      style="margin-top: 20px"
    />
    <el-alert
      v-if="balance && balance.status === 'inactive'"
      title="钱包未激活"
      type="info"
      :closable="false"
      description="您的钱包尚未激活，请联系系统管理员激活钱包。"
      show-icon
      style="margin-top: 20px"
    />
    <el-alert
      v-if="balance && balance.status === 'closed'"
      title="钱包已注销"
      type="error"
      :closable="false"
      description="您的钱包已注销，无法进行任何操作。"
      show-icon
      style="margin-top: 20px"
    />

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
        <el-table-column label="金额" width="120" align="right">
          <template slot-scope="{ row }">
            <span :style="{ color: row.is_income ? '#67C23A' : row.is_expense ? '#F56C6C' : '#909399', fontWeight: 'bold' }">
              {{ row.amount_display || (row.is_income ? '+' : '-') + Number(row.amount).toFixed(2) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="变动前" width="100" align="right">
          <template slot-scope="{ row }">{{ Number(row.balance_before).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="变动后" width="100" align="right">
          <template slot-scope="{ row }">{{ Number(row.balance_after).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" />
        <el-table-column prop="operator_name" label="操作人" width="100" />
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
      const map = { active: 'success', frozen: 'danger', restricted: 'warning', inactive: 'info', closed: 'info' };
      return map[status] || '';
    },

    getStatusIcon(status) {
      const map = {
        active: 'el-icon-circle-check',
        frozen: 'el-icon-lock',
        restricted: 'el-icon-warning',
        inactive: 'el-icon-time',
        closed: 'el-icon-close',
      };
      return map[status] || '';
    },
  },
};
</script>
