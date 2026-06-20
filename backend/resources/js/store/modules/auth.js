import api from '../../api/axios';

export default {
  namespaced: true,

  state: {
    user: null,
    token: localStorage.getItem('token') || '',
  },

  getters: {
    isAuthenticated: state => !!state.token,
    user: state => state.user,
    can: state => (permission) => {
      if (!state.user) return false;
      if (state.user.user_type === 'platform') return true;
      if (!state.user.roles) return false;
      return state.user.roles.some(role =>
        role.permissions && role.permissions.some(p => p.name === permission)
      );
    },
  },

  mutations: {
    SET_TOKEN(state, token) {
      state.token = token;
      localStorage.setItem('token', token);
    },
    SET_USER(state, user) {
      state.user = user;
    },
    CLEAR_AUTH(state) {
      state.token = '';
      state.user = null;
      localStorage.removeItem('token');
    },
  },

  actions: {
    async login({ commit }, credentials) {
      const { data } = await api.post('/login', credentials);
      commit('SET_TOKEN', data.data.token);
      commit('SET_USER', data.data.user);
      return data;
    },

    async fetchUser({ commit }) {
      const { data } = await api.get('/me');
      commit('SET_USER', data.data);
      return data;
    },

    async logout({ commit }) {
      try {
        await api.post('/logout');
      } catch (e) {
        // ignore
      }
      commit('CLEAR_AUTH');
    },
  },
};
