import { createRouter, createWebHistory } from 'vue-router'

import HomeView from '@/views/HomeView.vue'
import FirePublicView from '@/views/FirePublicView.vue'
import FloodView from '@/views/FloodView.vue'
import FireWindView from '@/views/FireWindView.vue'
import FireIncidentsListView from '@/views/FireIncidentsListView.vue'
import FireIncidentsFormView from '@/views/FireIncidentFormView.vue'
import FloodIncidentsListView from '@/views/FloodIncidentsListView.vue'
import FloodIncidentsFormView from '@/views/FloodIncidentFormView.vue'
import CadIntakeView from '@/views/CadIntakeView.vue'
import CadQueueView from '@/views/CadQueueView.vue'
import ReportedIncidentView from '@/views/ReportedIncidentView.vue'

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
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
    component: FirePublicView,
  },

  {
    path: '/flood',
    name: 'flood',
    component: FloodView,
  },
  {
    path: '/fire-wind',
    name: 'fire-wind',
    component: FireWindView,
  },
  {
    path: '/fire-list',
    name: 'fire-list',
    component: FireIncidentsListView,
  },
  {
    path: '/fire-list/add',
    name: 'fire-list-add',
    component: FireIncidentsFormView,
  },
  {
    path: '/fire-list/:id/edit',
    name: 'fire-list-edit',
    component: FireIncidentsFormView,
  },
  {
    path: '/flood-list',
    name: 'flood-list',
    component: FloodIncidentsListView,
  },
  {
    path: '/flood-list/add',
    name: 'flood-list-add',
    component: FloodIncidentsFormView,
  },
  {
    path: '/flood-list/:id/edit',
    name: 'flood-list-edit',
    component: FloodIncidentsFormView,
  },
  {
    path: '/cad-intake',
    name: 'cad-intake',
    component: CadIntakeView,
  },
  {
    path: '/cad-queue',
    name: 'cad-queue',
    component: CadQueueView,
  },
  {
    path: '/reported-incident',
    name: 'reported-incident',
    component: ReportedIncidentView,
  },

  // NIAT authentication
  {
    path: '/niat/login',
    name: 'niat-login',
    component: () => import('@/views/NiatLoginView.vue'),
  },
  {
    path: '/niat/reviewer',
    name: 'niat-reviewer',
    component: () => import('@/views/NiatReviewerView.vue'),
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

export default router