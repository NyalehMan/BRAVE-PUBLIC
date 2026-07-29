import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref([])

  const unreadNotificationCount = computed(() => {
    return notifications.value.filter((item) => !item.read).length
  })

  function addNotification({
    title,
    message,
    severity = 'medium',
    icon = 'bell',
    kind = 'system',
    graphic = null,
  }) {
    notifications.value.unshift({
      id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
      title,
      message,
      severity,
      icon,
      kind,
      graphic,
      read: false,
      time: new Date().toLocaleString(),
    })

    notifications.value = notifications.value.slice(0, 50)
  }

  function markAllAsRead() {
    notifications.value.forEach((item) => {
      item.read = true
    })
  }

  function markAsRead(id) {
    const notification = notifications.value.find((item) => item.id === id)

    if (notification) {
      notification.read = true
    }
  }

  function clearNotifications() {
    notifications.value = []
  }

  return {
    notifications,
    unreadNotificationCount,
    addNotification,
    markAllAsRead,
    markAsRead,
    clearNotifications,
  }
})
