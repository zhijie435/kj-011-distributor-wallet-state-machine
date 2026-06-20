import api from './axios';

export default {
  list(params = {}) {
    return api.get('/wallets', { params });
  },

  get(id) {
    return api.get(`/wallets/${id}`);
  },

  create(data) {
    return api.post('/wallets', data);
  },

  getBalance(id) {
    return api.get(`/wallets/${id}/balance`);
  },

  activate(id, data) {
    return api.post(`/wallets/${id}/activate`, data);
  },

  freeze(id, data) {
    return api.post(`/wallets/${id}/freeze`, data);
  },

  unfreeze(id, data) {
    return api.post(`/wallets/${id}/unfreeze`, data);
  },

  restrict(id, data) {
    return api.post(`/wallets/${id}/restrict`, data);
  },

  unrestrict(id, data) {
    return api.post(`/wallets/${id}/unrestrict`, data);
  },

  close(id, data) {
    return api.post(`/wallets/${id}/close`, data);
  },

  recharge(id, data) {
    return api.post(`/wallets/${id}/recharge`, data);
  },

  getTransactions(id, params = {}) {
    return api.get(`/wallets/${id}/transactions`, { params });
  },

  getStateLogs(id, params = {}) {
    return api.get(`/wallets/${id}/state-logs`, { params });
  },

  getStatistics(id, params = {}) {
    return api.get(`/wallets/${id}/statistics`, { params });
  },

  getMyBalance() {
    return api.get('/wallets/my-balance');
  },

  getMyTransactions(params = {}) {
    return api.get('/wallets/my-transactions', { params });
  },

  getMyStatistics(params = {}) {
    return api.get('/wallets/my-statistics', { params });
  },
};
