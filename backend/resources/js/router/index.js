import Vue from 'vue';
import VueRouter from 'vue-router';
import store from '../store';

Vue.use(VueRouter);

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/Login.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    component: () => import('../layouts/AppLayout.vue'),
    meta: { auth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('../views/Dashboard.vue'),
      },
      {
        path: 'wallets',
        name: 'wallets.index',
        component: () => import('../views/wallets/Index.vue'),
        meta: { permission: 'wallet.view', title: '钱包管理' },
      },
      {
        path: 'wallets/:id',
        name: 'wallets.show',
        component: () => import('../views/wallets/Show.vue'),
        meta: { permission: 'wallet.view', title: '钱包详情' },
      },
      {
        path: 'my-wallet',
        name: 'my-wallet',
        component: () => import('../views/wallets/MyWallet.vue'),
        meta: { title: '我的钱包' },
      },
    ],
  },
];

const router = new VueRouter({
  mode: 'history',
  routes,
});

router.beforeEach((to, from, next) => {
  const isAuthenticated = store.getters['auth/isAuthenticated'];

  if (to.meta.guest && isAuthenticated) {
    return next({ name: 'dashboard' });
  }

  if (to.meta.auth && !isAuthenticated) {
    return next({ name: 'login' });
  }

  next();
});

export default router;
