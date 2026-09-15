<template>
  <footer class="app-footer">
    <div class="footer-container">
      <div class="footer-left">
        <img
          :src="isDark ? '/NiAT-dark.png' : '/NiAT-light.png'"
          alt="NIAT Logo"
          class="logo-imag"
        />
      </div>

      <div class="footer-center">
        <h6>Contact us for inquiries</h6>
        <p><strong>NETWORK INTEGRITY ASSURANCE TECHNOLOGIES (NIAT) SDN BHD</strong></p>

        <p class="footer-line">
          <FeatherIcon icon="phone" />
          <span>+673 238 4660</span>
        </p>

        <p class="footer-line">
          <FeatherIcon icon="map-pin" />
          <span>
            Unit 28, 29 &amp; 30, Block C, 1st Floor, Muhibbah Complex II,<br />
            Jalan Gadong, Bandar Seri Begawan BE 3919,<br />
            Negara Brunei Darussalam
          </span>
        </p>
      </div>

      <div class="footer-right">
        <img
          :src="isDark ? '/observe-dark.png' : '/observe-light.png'"
          alt="Powered by OBSERVE"
          class="powered-logo"
        />
        <p>© {{ year }}</p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import FeatherIcon from '@/components/FeatherIcon.vue'

const darkMode = ref(false)

function syncTheme() {
  darkMode.value = document.documentElement.getAttribute('data-bs-theme') === 'dark'
}

onMounted(() => {
  syncTheme()

  window.addEventListener('theme-changed', syncTheme)
})

const isDark = computed(() => darkMode.value)

const year = computed(() => new Date().getFullYear())
</script>

<style scoped>
.app-footer {
  margin-top: auto;
  padding: 2rem 3rem;
  background: var(--phoenix-card-bg);
  border-top: 1px solid var(--phoenix-border-color);
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 90px 1fr auto;
  align-items: center;
  gap: 1.75rem;
}

.footer-logo {
  width: 72px;
  height: auto;
  object-fit: contain;
}

.footer-center h6 {
  margin-bottom: 0.35rem;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--phoenix-heading-color);
}

.footer-center p {
  margin: 0.2rem 0;
  font-size: 0.85rem;
  line-height: 1.55;
  color: var(--phoenix-secondary-color);
}

.footer-center strong {
  color: var(--phoenix-body-color);
}

.footer-line {
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
}

.footer-line svg {
  width: 14px;
  height: 14px;
  margin-top: 3px;
  flex-shrink: 0;
}

.footer-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.4rem;
}

.powered-logo {
  height: 38px;
  width: auto;
  object-fit: contain;
}

.footer-right p {
  margin-bottom: 0.75rem;
}

.footer-right span {
  display: block;
}

.logo-imag {
  height: 80px;
  display: block;
}

@media (max-width: 768px) {
  .app-footer {
    padding: 2rem 1.25rem;
  }

  .footer-container {
    grid-template-columns: 1fr;
    text-align: center;
    gap: 1rem;
  }

  .footer-logo {
    margin: 0 auto;
  }

  .footer-line {
    justify-content: center;
  }

  .footer-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.4rem;
  }

  .powered-logo {
    height: 28px;
    width: auto;
    object-fit: contain;
  }
}
</style>
