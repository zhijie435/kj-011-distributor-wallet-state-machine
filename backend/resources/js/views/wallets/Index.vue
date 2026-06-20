<template>
  <div>
    <el-card>
      <div slot="header" style="display: flex; justify-content: space-between; align-items: center">
        <span>钱包管理</span>
        <el-button type="primary" size="small" @click="showCreateDialog = true">创建钱包</el-button>
      </div>

      <el-form :inline="true" :model="filters" @submit.native.prevent="loadWallets">
        <el-form-item label="状态">
          <el-select v-model="filters.status" clearable placeholder="全部" @change="loadWallets">
            <el-option label="未激活" value="inactive" />
            <el-option label="正常" value="active" />
            <el-option label="已冻结" value="frozen" />
            <el-option label="受限" value="restricted" />
            <el-option label="已注销" value="closed" />
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input v-model="filters.search" placeholder="经销商名称" clearable @clear="loadWallets" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadWallets">查询</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="wallets" v-loading="loading" stripe border>
        <el-table-column prop="wallet_no" label="钱包编号" width="180" />
        <el-table-column prop="distributor_name" label="经销商" width="150" />
        <el-table-column label="状态" width="100">
          <template slot-scope="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">{{ getStatusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="balance" label="余额" width="120" align="right">
          <template slot-scope="{ row }">{{ parseFloat(row.balance).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="frozen_amount" label="冻结金额" width="120" align="right">
          <template slot-scope="{ row }">{{ parseFloat(row.frozen_amount).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" fixed="right" width="320">
          <template slot-scope="{ row }">
            <el-button size="mini" @click="$router.push({ name: 'wallets.show', params: { id: row.id } })">详情</el-button>
            <template v-if="row.status === 'inactive'">
              <el-button size="mini" type="success" @click="handleTransition(row.id, 'activate', '激活')">激活</el-button>
            </template>
            <template v-if="row.status === 'active'">
              <el-button size="mini" type="warning" @click="handleTransition(row.id, 'freeze', '冻结')">冻结</el-button>
              <el-button size="mini" type="warning" @click="handleTransition(row.id, 'restrict', '限制')">限制</el-button>
            </template>
            <template v-if="row.status === 'frozen'">
              <el-button size="mini" type="success" @click="handleTransition(row.id, 'unfreeze', '解冻')">解冻</el-button>
            </template>
            <template v-if="row.status === 'restricted'">
              <el-button size="mini" type="success" @click="handleTransition(row.id, 'unrestrict', '解除限制')">解除限制</el-button>
              <el-button size="mini" type="warning" @click="handleTransition(row.id, 'freeze', '冻结')">冻结</el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="total > 0"
        style="margin-top: 20px; text-align: right"
        :current-page="page"
        :page-size="perPage"
        :total="total"
        layout="total, prev, pager, next"
        @current-change="handlePageChange"
      />
    </el-card>

    <el-dialog title="创建钱包" :visible.sync="showCreateDialog" width="500px">
      <el-form :model="createForm" label-width="100px">
        <el-form-item label="经销商ID">
          <el-input-number v-model="createForm.distributor_id" :min="1" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="handleCreate">确定</el-button>
      </div>
    </el-dialog>

    <el-dialog :title="transitionTitle" :visible.sync="showTransitionDialog" width="500px">
      <el-form :model="transitionForm" label-width="100px">
        <el-form-item label="操作原因" required>
          <el-input v-model="transitionForm.reason" type="textarea" :rows="3" placeholder="请输入操作原因" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="showTransitionDialog = false">取消</el-button>
        <el-button type="primary" :loading="transitioning" @click="confirmTransition">确定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import walletApi from '../../api/wallet';

export default {
  name: 'WalletIndex',

  data() {
    return {
      wallets: [],
      loading: false,
      total: 0,
      page: 1,
      perPage: 15,
      filters: {
        status: '',
        search: '',
      },
      showCreateDialog: false,
      creating: false,
      createForm: {
        distributor_id: null,
      },
      showTransitionDialog: false,
      transitioning: false,
      transitionWalletId: null,
      transitionAction: '',
      transitionTitle: '',
      transitionForm: {
        reason: '',
      },
    };
  },

  created() {
    this.loadWallets();
  },

  methods: {
    async loadWallets() {
      this.loading = true;
      try {
        const params = {
          page: this.page,
          per_page: this.perPage,
          ...this.filters,
        };
        const { data } = await walletApi.list(params);
        this.wallets = data.data.data;
        this.total = data.data.total;
      } catch (e) {
        this.$message.error('加载钱包列表失败');
      } finally {
        this.loading = false;
      }
    },

    handlePageChange(page) {
      this.page = page;
      this.loadWallets();
    },

    async handleCreate() {
      this.creating = true;
      try {
        await walletApi.create(this.createForm);
        this.$message.success('钱包创建成功');
        this.showCreateDialog = false;
        this.createForm.distributor_id = null;
        this.loadWallets();
      } catch (e) {
        this.$message.error(e.response?.data?.message || '创建失败');
      } finally {
        this.creating = false;
      }
    },

    handleTransition(walletId, action, title) {
      this.transitionWalletId = walletId;
      this.transitionAction = action;
      this.transitionTitle = title;
      this.transitionForm.reason = '';
      this.showTransitionDialog = true;
    },

    async confirmTransition() {
      if (!this.transitionForm.reason) {
        this.$message.warning('请填写操作原因');
        return;
      }

      this.transitioning = true;
      try {
        await walletApi[this.transitionAction](this.transitionWalletId, this.transitionForm);
        this.$message.success('操作成功');
        this.showTransitionDialog = false;
        this.loadWallets();
      } catch (e) {
        this.$message.error(e.response?.data?.message || '操作失败');
      } finally {
        this.transitioning = false;
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
