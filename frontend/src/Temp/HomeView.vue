<template>
  <div class="brave-home">
    <!-- HERO -->
    <section ref="heroRef" class="hero-card">
      <div class="hero-bg"></div>
      <div class="hero-smoke smoke-one"></div>
      <div class="hero-smoke smoke-two"></div>
      <div class="hero-dark-overlay"></div>

      <div class="hero-overlay">
        <h1 class="hero-title">BRAVE</h1>
        <h2 class="hero-subtitle">Brunei Response &amp; Action for Vital Emergencies</h2>
        <p class="hero-description">Centralized National Disaster Monitoring System</p>
      </div>
    </section>

    <!-- INTRO -->
    <section class="intro-section">
      <h4>A Central Hub for monitoring disastrous Events in Real-Time</h4>
    </section>

    <!-- CATEGORY CARDS -->
    <section class="brave-grid mb-5">
      <div v-for="item in categories" :key="item.title" class="brave-col">
        <div class="card brave-category-card h-100">
          <div class="image-wrapper">
            <img :src="item.image" :alt="item.title" />
          </div>

          <div class="card-body text-center">
            <h3>{{ item.title }}</h3>
            <p>{{ item.description }}</p>

            <RouterLink
              v-if="item.title === 'Fire'"
              :to="item.to"
              class="btn btn-dark rounded-pill px-4"
            >
              Fire Dashboard
            </RouterLink>

            <button
              v-else
              type="button"
              class="btn btn-outline-secondary rounded-pill px-4 coming-soon-btn"
              disabled
            >
              Coming Soon
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- LATEST NEWS -->
    <section class="latest-news-section">
      <div class="latest-news-header">
        <div>
          <h2 class="latest-news-title">Latest News</h2>
          <p class="latest-news-subtitle">
            Fire-related updates 
          </p>
        </div>

        <button
          type="button"
          class="news-refresh-btn"
          :disabled="newsLoading"
          @click="loadLatestNews"
        >
          {{ newsLoading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>

      <div class="news-region-toggle">
  <button
    type="button"
    :class="['news-region-btn', { active: newsRegion === 'brunei' }]"
    @click="setNewsRegion('brunei')"
  >
    Brunei
  </button>


</div>

      <div v-if="newsLoading" class="news-loading">
        Loading latest news...
      </div>

      <div v-else-if="newsError" class="news-error">
        <span>{{ newsError }}</span>
        <button type="button" class="news-retry-btn" @click="loadLatestNews">
          Retry
        </button>
      </div>

      <div v-else-if="latestNews.length === 0" class="news-empty">
        No latest news available right now.
      </div>

      <div v-else class="news-list">
        <a
          v-for="news in latestNews"
          :key="news.url"
          :href="news.url"
          target="_blank"
          rel="noopener"
          class="news-card-row"
        >
          <div class="news-image-wrapper">
            <img
              :src="getNewsImage(news)"
              :alt="news.title"
              class="news-image"
              loading="lazy"
              decoding="async"
              referrerpolicy="no-referrer"
              @error="handleNewsImageError"
            />
          </div>

          <div class="news-info">
            <div class="news-meta-row">
              <span class="news-source-pill">
                {{ news.source || 'News Source' }}
              </span>

              <span v-if="news.published_at" class="news-date">
                {{ formatNewsDate(news.published_at) }}
              </span>
            </div>

            <h4>{{ news.title }}</h4>

            <p>
              {{ news.description || 'Click to read full article.' }}
            </p>

            <span class="read-more-text">
              Read full article →
            </span>
          </div>
        </a>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import http from '@/api/http'

const heroRef = ref(null)
const newsRegion = ref('brunei')

function setNewsRegion(region) {
  newsRegion.value = region
  loadLatestNews()
}

const categories = [
  {
  title: 'Fire',
  image: '/images/fire.jpg',
  description: 'Real time fire event monitoring dashboard',
  to: '/fire-public',
  },
  {
    title: 'Flood',
    image: '/images/flood.jpeg',
    description: 'Real-time flood updates and monitoring dashboard',
    to: '/flood',
  },
  {
    title: 'Wind',
    image: '/images/wind.png',
    description: 'Occurrence of gusty wind and daily forecast',
    to: '/wind',
  },
  {
    title: 'Landslide',
    image: '/images/landslide.jpeg',
    description: 'Landslide hotspots and hazard map for risk management',
    to: '/landslide',
  },
]

const latestNews = ref([])
const newsLoading = ref(false)
const newsError = ref('')
const newsPlaceholder = '/images/news-placeholder.jpg'

async function loadLatestNews() {
  newsLoading.value = true
  newsError.value = ''

  try {
    const res = await http.get('/api/fire-news', {
      params: {
        region: newsRegion.value,
        _: Date.now(),
        },
        })

    console.log('Fire news response:', res.data)

    if (Array.isArray(res.data)) {
      latestNews.value = res.data
    } else if (Array.isArray(res.data.data)) {
      latestNews.value = res.data.data
    } else {
      latestNews.value = []
    }
    } catch (error) {
    console.error('Failed to load news:', error)

    if (error.response) {
      console.error('Status:', error.response.status)
      console.error('Data:', error.response.data)

      newsError.value = `Latest news could not be loaded. API returned ${error.response.status}.`
    } else if (error.request) {
      console.error('No response received:', error.request)

      newsError.value = 'Latest news could not be loaded. Laravel API did not respond.'
    } else {
      newsError.value = `Latest news could not be loaded. ${error.message}`
    }

    latestNews.value = []
  } finally {
    newsLoading.value = false
  }
}

function formatNewsDate(dateValue) {
  if (!dateValue) return ''

  const date = new Date(dateValue)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return date.toLocaleDateString('en-BN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function getNewsImage(news) {
  const image = typeof news?.image === 'string'
    ? news.image.trim()
    : ''

  return image || newsPlaceholder
}

function handleNewsImageError(event) {
  const image = event.currentTarget

  if (!image || image.dataset.fallbackApplied === 'true') {
    return
  }

  image.dataset.fallbackApplied = 'true'
  image.src = newsPlaceholder
}

onMounted(() => {
  loadLatestNews()
})


</script>

<style scoped>
.brave-home {
  max-width: 1400px;
  margin: 0 auto;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  padding-bottom: 0;
}

.brave-home > *:not(.brave-footer) {
  flex-shrink: 0;
}

/* =========================
   HERO
========================= */
.hero-card {
  position: relative;
  min-height: 430px;
  border-radius: 1.25rem;
  overflow: hidden;
  margin-bottom: 2rem;
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.18);
  background: #111827;
  transform-style: preserve-3d;
  --move-x: 0px;
  --move-y: 0px;
  --rotate-x: 0deg;
  --rotate-y: 0deg;
}

.hero-bg {
  position: absolute;
  inset: -28px;
  background: url('/images/brave-hero.png') center / cover no-repeat;
  transform: translate3d(var(--move-x), var(--move-y), 0) scale(1.08)
    rotateX(var(--rotate-x)) rotateY(var(--rotate-y));
  transition: transform 0.18s ease-out;
  animation: heroZoom 12s ease-in-out infinite alternate;
  z-index: 1;
}

.hero-dark-overlay {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at center, rgba(0, 0, 0, 0.08), rgba(0, 0, 0, 0.65)),
    linear-gradient(rgba(0, 0, 0, 0.12), rgba(0, 0, 0, 0.5));
  z-index: 3;
}

.hero-smoke {
  position: absolute;
  inset: -25%;
  background:
    radial-gradient(circle at 20% 40%, rgba(255, 255, 255, 0.18), transparent 22%),
    radial-gradient(circle at 60% 35%, rgba(255, 255, 255, 0.14), transparent 25%),
    radial-gradient(circle at 80% 65%, rgba(255, 255, 255, 0.1), transparent 22%);
  filter: blur(20px);
  pointer-events: none;
  z-index: 2;
  mix-blend-mode: screen;
}

.smoke-one {
  opacity: 0.45;
  animation: smokeMoveOne 16s ease-in-out infinite alternate;
}

.smoke-two {
  opacity: 0.28;
  animation: smokeMoveTwo 22s ease-in-out infinite alternate;
}

.hero-overlay {
  position: relative;
  z-index: 4;
  min-height: 430px;
  padding: 3rem 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: #fff;
}

.hero-title,
.hero-subtitle,
.hero-description {
  opacity: 0;
  animation: fadeUp 0.9s ease forwards;
  text-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.65);
}

.hero-title {
  font-size: clamp(4rem, 9vw, 7rem);
  font-weight: 800;
  letter-spacing: 0.12em;
  margin-bottom: 0.25rem;
  animation-delay: 0.15s;
  color: #cbd5e1;
}

.hero-subtitle {
  max-width: 1050px;
  font-size: clamp(2rem, 4.6vw, 4.4rem);
  font-weight: 500;
  line-height: 1.08;
  margin-bottom: 1.25rem;
  animation-delay: 0.35s;
  color: #cbd5e1;
}

.hero-description {
  font-size: clamp(1.15rem, 2vw, 2rem);
  font-weight: 600;
  animation-delay: 0.55s;
}

/* =========================
   INTRO
========================= */
.intro-section {
  text-align: center;
  margin-bottom: 2rem;
}

.intro-section h4 {
  font-weight: 600;
  color: var(--phoenix-body-color);
}

/* =========================
   CATEGORY CARDS
========================= */
.brave-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.brave-category-card {
  border-radius: 1rem;
  overflow: hidden;
  border: none;
  transition: 0.25s ease;
}

.brave-category-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.12);
}

.image-wrapper {
  position: relative;
  height: 200px;
  overflow: hidden;
}

.image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.brave-category-card .card-body {
  padding: 1.5rem;
}

.brave-category-card h3 {
  font-size: 1.6rem;
  font-weight: 700;
  margin-bottom: 0.75rem;
}

.brave-category-card p {
  font-size: 0.95rem;
  color: var(--phoenix-secondary-color);
  margin-bottom: 1.25rem;
}

.coming-soon-btn {
  opacity: 0.75;
  cursor: not-allowed;
  border: 1px solid #cbd5e1;
  color: #64748b;
  background: #f8fafc;
}

.coming-soon-btn:hover {
  background: #f8fafc;
  color: #64748b;
}

/* =========================
   LATEST NEWS
========================= */
.latest-news-section {
  margin-top: 3rem;
  text-align: left;
}

.news-region-toggle {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

.news-region-btn {
  border: 1px solid var(--phoenix-border-color);
  border-radius: 999px;
  padding: 0.45rem 0.9rem;
  background: var(--phoenix-card-bg);
  color: var(--phoenix-body-color);
  font-weight: 800;
}

.news-region-btn.active {
  background: #111827;
  color: #ffffff;
  border-color: #111827;
}
.latest-news-header {
  max-width: 1100px;
  margin: 0 auto 1.5rem;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.latest-news-title {
  font-size: 2.4rem;
  font-weight: 900;
  color: var(--phoenix-heading-color);
  margin-bottom: 0.25rem;
}

.latest-news-subtitle {
  margin: 0;
  color: var(--phoenix-secondary-color);
  font-weight: 600;
}

.news-refresh-btn,
.news-retry-btn {
  border: none;
  border-radius: 999px;
  padding: 0.55rem 1rem;
  background: #111827;
  color: #ffffff;
  font-weight: 800;
  transition: 0.2s ease;
}

.news-refresh-btn:hover,
.news-retry-btn:hover {
  background: #000000;
  transform: translateY(-1px);
}

.news-refresh-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.news-loading,
.news-empty,
.news-error {
  max-width: 760px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  background: var(--phoenix-card-bg);
  color: var(--phoenix-secondary-color);
  font-weight: 700;
  text-align: center;
}

.news-error {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.news-list {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.news-card-row {
  display: grid;
  grid-template-columns: 300px 1fr;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  overflow: hidden;
  color: inherit;
  text-decoration: none;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
  transition: 0.22s ease;
}

.news-card-row:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12);
}

.news-card-row:hover h4 {
  color: #b77900;
}

.news-image-wrapper {
  width: 100%;
  height: 220px;
  background: #e5e7eb;
  overflow: hidden;
}

.news-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.news-info {
  padding: 1.5rem 1.75rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: left;
}

.news-meta-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 0.8rem;
}

.news-source-pill {
  display: inline-flex;
  width: fit-content;
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.14);
  color: #92400e;
  font-size: 0.8rem;
  font-weight: 900;
}

.news-date {
  color: var(--phoenix-secondary-color);
  font-size: 0.85rem;
  font-weight: 700;
}

.news-info h4 {
  color: var(--phoenix-heading-color);
  font-size: 1.25rem;
  font-weight: 900;
  line-height: 1.35;
  margin-bottom: 0.75rem;
  text-decoration: none;
}

.news-info p {
  color: var(--phoenix-body-color);
  margin-bottom: 1rem;
  line-height: 1.55;

  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.read-more-text {
  color: #b77900;
  font-weight: 900;
  font-size: 0.9rem;
}

/* =========================
   FOOTER
========================= */
.brave-footer {
  margin-top: 4rem;
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
  opacity: 0.95;
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

.footer-center p strong {
  color: var(--phoenix-body-color);
}

.footer-right {
  align-self: end;
  text-align: right;
  font-size: 0.8rem;
  color: var(--phoenix-secondary-color);
}

.footer-right p {
  margin-bottom: 0.75rem;
}

.footer-right span {
  display: block;
}

/* =========================
   ANIMATIONS
========================= */
@keyframes heroZoom {
  from {
    filter: brightness(0.95) contrast(1.05);
  }

  to {
    filter: brightness(1.08) contrast(1.15);
  }
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(24px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes smokeMoveOne {
  from {
    transform: translate(-4%, -2%) scale(1);
  }

  to {
    transform: translate(5%, 3%) scale(1.12);
  }
}

@keyframes smokeMoveTwo {
  from {
    transform: translate(5%, 3%) scale(1.08);
  }

  to {
    transform: translate(-5%, -3%) scale(1.16);
  }
}

/* =========================
   RESPONSIVE
========================= */
@media (max-width: 992px) {
  .brave-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .hero-overlay h1 {
    font-size: 2.5rem;
  }

  .latest-news-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .latest-news-title {
    font-size: 1.8rem;
  }

  .news-card-row {
    grid-template-columns: 1fr;
  }

  .news-image-wrapper {
    height: 220px;
  }

  .news-info {
    padding: 1.25rem;
  }

  .brave-footer {
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

  .footer-center,
  .footer-right {
    text-align: center;
  }
}

@media (max-width: 576px) {
  .brave-grid {
    grid-template-columns: 1fr;
  }
}

@media (min-width: 769px) {
  .brave-home {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
  }

  .brave-footer {
    margin-bottom: 0 !important;
  }

  .main-content,
  .app-content,
  .page-content {
    padding-bottom: 0 !important;
  }
}
</style>
