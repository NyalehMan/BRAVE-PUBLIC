import { createRouter, createWebHistory } from 'vue-router'
import { Preferences } from '@capacitor/preferences'

import ReportIncidentView from '@/views/ReportIncidentView.vue'
import HomeView from '@/views/HomeView.vue'
import LoginView from '@/views/LoginView.vue'
import MapView from '@/views/MapView.vue'
import MyReportsView from '@/views/MyReportsView.vue'
import ProfileView from '@/views/ProfileView.vue'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: {
      guestOnly: true,
    },
  },
  {
    path: '/',
    name: 'home',
    component: HomeView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/map',
    name: 'map',
    component: MapView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/report-incident',
    name: 'report-incident',
    component: ReportIncidentView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/my-reports',
    name: 'my-reports',
    component: MyReportsView,
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/profile',
    name: 'profile',
    component: ProfileView,
    meta: {
      requiresAuth: true,
    },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,

  scrollBehavior() {
    return {
      top: 0,
      left: 0,
      behavior: 'smooth',
    }
  },
})

router.beforeEach(async (to) => {
  const { value: token } = await Preferences.get({
    key: 'auth_token',
  })

  if (to.meta.requiresAuth && !token) {
    return {
      name: 'login',
      query: {
        redirect: to.fullPath,
      },
    }
  }

  if (to.meta.guestOnly && token) {
    return {
      name: 'home',
    }
  }

  return true
})

router.afterEach(() => {
  setTimeout(() => {
    window.scrollTo({
      top: 0,
      left: 0,
      behavior: 'smooth',
    })

    document.documentElement.scrollTop = 0
    document.body.scrollTop = 0

    const scrollContainers = document.querySelectorAll(
      '.mobile-report-page, .mobile-reports-page, .mobile-home-page, .mobile-map-page, .profile-page',
    )

    scrollContainers.forEach((el) => {
      el.scrollTop = 0
    })
  }, 50)
})

export default router
