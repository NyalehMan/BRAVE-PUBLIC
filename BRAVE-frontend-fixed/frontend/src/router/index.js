import { createRouter, createWebHistory } from 'vue-router'
import { getCurrentUser } from '@/api/authApi'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: {
      hideNotifications: true,
    },
  },

  // Redirect old FireView links to the updated dashboard
  {
    path: '/fire',
    name: 'fire',
    redirect: {
      name: 'fire-public',
    },
  },
  {
    path: '/fire-public',
    name: 'fire-public',
    component: () => import('@/views/FirePublicView.vue'),
  },

  {
    path: '/flood',
    name: 'flood',
    component: () => import('@/views/FloodView.vue'),
  },
  {
    path: '/fire-wind',
    name: 'fire-wind',
    component: () => import('@/views/FireWindView.vue'),
  },
  {
    path: '/fire-list',
    name: 'fire-list',
    component: () => import('@/views/FireIncidentsListView.vue'),
  },
  {
    path: '/fire-list/add',
    name: 'fire-list-add',
    component: () => import('@/views/FireIncidentFormView.vue'),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/fire-list/:id/edit',
    name: 'fire-list-edit',
    component: () => import('@/views/FireIncidentFormView.vue'),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/flood-list',
    name: 'flood-list',
    component: () => import('@/views/FloodIncidentsListView.vue'),
  },
  {
    path: '/flood-list/add',
    name: 'flood-list-add',
    component: () => import('@/views/FloodIncidentFormView.vue'),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/flood-list/:id/edit',
    name: 'flood-list-edit',
    component: () => import('@/views/FloodIncidentFormView.vue'),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/cad-intake',
    name: 'cad-intake',
    component: () => import('@/views/CadIntakeView.vue'),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/cad-queue',
    name: 'cad-queue',
    component: () => import('@/views/CadQueueView.vue'),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/reported-incident',
    name: 'reported-incident',
    component: () => import('@/views/ReportedIncidentView.vue'),
    meta: {
      requiresAuth: true,
    },
  },

  // NIAT authentication
  {
    path: '/niat/login',
    name: 'niat-login',
    component: () => import('@/views/NiatLoginView.vue'),
    meta: {
      hideAppShell: true,
    },
  },
  {
    path: '/niat/reviewer',
    name: 'niat-reviewer',
    component: () => import('@/views/NiatReviewerView.vue'),
    meta: {
      hideAppShell: true,
      requiresAuth: true,
    },
  },

  // Keep this last
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  if (!to.meta.requiresAuth) {
    return true
  }

  try {
    await getCurrentUser()
    return true
  } catch {
    return {
      name: 'niat-login',
      query: {
        redirect: to.fullPath,
      },
    }
  }
})

export default router
