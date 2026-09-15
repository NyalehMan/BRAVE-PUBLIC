<template>
  <div class="brave-home">
    <!-- HERO -->
    <section ref="heroRef" class="hero-card">
      <div class="hero-bg"></div>
      <div class="hero-smoke smoke-one"></div>
      <div class="hero-smoke smoke-two"></div>
      <div class="hero-dark-overlay"></div>

      <div class="hero-overlay">
        <span class="hero-eyebrow">National situational awareness platform</span>
        <h1 class="hero-title">BRAVE</h1>
        <h2 class="hero-subtitle">
          Brunei Response &amp; Action for Vital Emergencies
        </h2>
        <p class="hero-description">
          Bringing hazard information, public observations and verified incident
          updates together in one clear view.
        </p>

        <div class="hero-actions">
          <RouterLink to="/fire-public" class="hero-btn hero-btn-primary">
            Explore Fire Dashboard
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </RouterLink>

          <a href="#reporting-process" class="hero-btn hero-btn-secondary">
            How public reporting works
          </a>
        </div>
      </div>

      <div class="hero-capabilities" aria-label="BRAVE capabilities">
        <div>
          <span class="capability-number">01</span>
          <span>Monitor hazards</span>
        </div>
        <div>
          <span class="capability-number">02</span>
          <span>Receive public reports</span>
        </div>
        <div>
          <span class="capability-number">03</span>
          <span>Share verified information</span>
        </div>
      </div>
    </section>

    <!-- ABOUT BRAVE -->
    <section class="content-section about-section">
      <div class="section-heading">
        <span class="section-kicker">About BRAVE</span>
        <h2>All in one platform for everything disaster news</h2>
        <p>
          BRAVE is a centralized disaster monitoring platform developed to keep track of disaster related events
          all across Brunei Darussalam.
        </p>
      </div>

      <div class="about-grid">
        <article class="about-card">
          <span class="about-icon about-icon-map">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="m9 18-6 3V6l6-3 6 3 6-3v15l-6 3-6-3Z" />
              <path d="M9 3v15M15 6v15" />
            </svg>
          </span>
          <h3>One operational view</h3>
          <p>
            Maps, incident locations and relevant environmental information are
            brought together in a single, accessible dashboard.
          </p>
        </article>

        <article class="about-card">
          <span class="about-icon about-icon-alert">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M10.3 3.4 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.4a2 2 0 0 0-3.4 0Z" />
              <path d="M12 9v4M12 17h.01" />
            </svg>
          </span>
          <h3>Early public awareness</h3>
          <p>
            People can explore verified incident information and contribute useful
            observations through the public reporting tool.
          </p>
        </article>

        <article class="about-card">
          <span class="about-icon about-icon-check">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
              <path d="m9 12 2 2 4-4" />
            </svg>
          </span>
          <h3>Information with context</h3>
          <p>
            Public submissions pass through an internal verification process before
            they are treated as verified map information.
          </p>
        </article>
      </div>
    </section>

    <!-- MONITORING MODULES -->
    <section class="content-section modules-section">
      <div class="section-heading section-heading-row">
        <div>
          <span class="section-kicker">Explore BRAVE</span>
          <h2>Disaster monitoring modules</h2>
        </div>
        <p>
          BRAVE-FIRE is the first active public module, with further hazard modules
          planned as the platform grows.
        </p>
      </div>

      <div class="brave-grid">
        <div v-for="item in categories" :key="item.title" class="brave-col">
          <div class="card brave-category-card h-100">
            <div class="image-wrapper">
              <img :src="item.image" :alt="item.title" />
              <span class="module-status" :class="{ live: item.title === 'Fire' }">
                {{ item.title === 'Fire' ? 'Live' : 'Planned' }}
              </span>
            </div>

            <div class="card-body">
              <h3>{{ item.title }}</h3>
              <p>{{ item.description }}</p>

              <RouterLink
                v-if="item.title === 'Fire'"
                :to="item.to"
                class="module-link"
              >
                Open Fire Dashboard
                <span aria-hidden="true">→</span>
              </RouterLink>

              <span v-else class="module-link module-link-disabled">
                Coming soon
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- PUBLIC REPORTING WORKFLOW -->
    <section id="reporting-process" class="content-section reporting-section">
      <div class="reporting-intro">
        <span class="section-kicker section-kicker-light">Public reporting</span>
        <h2>From a public observation to verified map information</h2>
        <p>
          A report helps BRAVE build awareness, but it is not published as a verified
          incident immediately. NIAT reviews each submission first to reduce unclear,
          duplicate or inaccurate information.
        </p>

        <RouterLink to="/fire-public" class="reporting-cta">
          <span class="reporting-cta-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M13 2S7 7 7 13a5 5 0 0 0 10 0c0-3-1.5-5-4-7 0 2-1 3-2 4 0-3 2-6 2-8Z" />
            </svg>
          </span>
          Open the map to report a fire
        </RouterLink>
      </div>

      <div class="workflow-panel">
        <ol class="workflow-list">
          <li class="workflow-step">
            <span class="workflow-number">1</span>
            <div>
              <span class="workflow-label">Public submission</span>
              <h3>Tell us what you observed</h3>
              <p>
                Select the fire type, add details and provide an accurate location
                using GPS or a pin on the map.
              </p>
            </div>
          </li>

          <li class="workflow-step">
            <span class="workflow-number">2</span>
            <div>
              <span class="workflow-label">Pending review</span>
              <h3>The report enters a holding queue</h3>
              <p>
                The submission is recorded as pending verification and kept separate
                from incidents already shown as verified.
              </p>
            </div>
          </li>

          <li class="workflow-step workflow-step-review">
            <span class="workflow-number">3</span>
            <div>
              <span class="workflow-label">NIAT verification</span>
              <h3>Information is checked internally</h3>
              <p>
                NIAT reviews the description, location, relevance and possible
                duplicates, then records a verification decision.
              </p>
              <div class="review-tags" aria-label="Possible review outcomes">
                <span class="review-tag verified">Verified</span>
                <span class="review-tag clarification">Needs clarification</span>
                <span class="review-tag rejected">Not verified</span>
              </div>
            </div>
          </li>

          <li class="workflow-step workflow-step-final">
            <span class="workflow-number">4</span>
            <div>
              <span class="workflow-label">Map update</span>
              <h3>Verified information is reflected on BRAVE</h3>
              <p>
                Once approved, the incident is added to the map and
                becomes part of the public situational picture.
              </p>
            </div>
          </li>
        </ol>
      </div>

      <div class="reporting-notice">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 10v6M12 7h.01" />
        </svg>
        <p>
          <strong>Public reports support awareness.</strong> They do not replace an
          emergency call. If there is immediate danger, contact the appropriate
          emergency service first.
        </p>
      </div>
    </section>

    <!-- LATEST NEWS -->
    <section class="content-section latest-news-section">
      <div class="latest-news-header">
        <div>
          <span class="section-kicker">Stay informed</span>
          <h2 class="latest-news-title">Latest News</h2>
          <p class="latest-news-subtitle">
            Recent fire-related updates relevant to Brunei Darussalam
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
    description: 'Verified fire incidents, environmental context and public reporting.',
    to: '/fire-public',
  },
  {
    title: 'Flood',
    image: '/images/flood.jpeg',
    description: 'Flood conditions, affected locations and situational updates.',
    to: '/flood',
  },
  {
    title: 'Wind',
    image: '/images/wind.png',
    description: 'Gusty wind observations, forecasts and affected areas.',
    to: '/wind',
  },
  {
    title: 'Landslide',
    image: '/images/landslide.jpeg',
    description: 'Landslide hotspots and location-based risk information.',
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
  width: 100%;
  max-width: none;
  margin: 0;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  padding: 0 1rem 4rem;
  color: var(--phoenix-body-color);
}

.brave-home > *:not(.brave-footer) {
  flex-shrink: 0;
}

/* =========================
   HERO
========================= */
.hero-card {
  position: relative;
  width: 100%;
  min-height: 650px;
  margin: 1rem 0 5.5rem;
  border-radius: 1.5rem;
  overflow: hidden;
  box-shadow: 0 1.5rem 3.5rem rgba(15, 23, 42, 0.22);
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
    linear-gradient(90deg, rgba(5, 14, 20, 0.93) 0%, rgba(5, 14, 20, 0.7) 54%, rgba(5, 14, 20, 0.35) 100%),
    linear-gradient(0deg, rgba(5, 14, 20, 0.78) 0%, transparent 48%);
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
  min-height: 650px;
  max-width: 860px;
  padding: 5.5rem 4.5rem 9rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  text-align: left;
  color: #fff;
}

.hero-eyebrow,
.hero-title,
.hero-subtitle,
.hero-description,
.hero-actions {
  opacity: 0;
  animation: fadeUp 0.9s ease forwards;
  text-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.65);
}

.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  margin-bottom: 1rem;
  color: #fbbf24;
  font-size: 0.78rem;
  font-weight: 900;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  animation-delay: 0.05s;
}

.hero-eyebrow::before {
  width: 2rem;
  height: 2px;
  background: currentColor;
  content: '';
}

.hero-title {
  font-size: clamp(4.25rem, 9vw, 7.8rem);
  font-weight: 900;
  letter-spacing: 0.08em;
  line-height: 0.9;
  margin: 0 0 1.1rem;
  animation-delay: 0.15s;
  color: #ffffff;
}

.hero-subtitle {
  max-width: 760px;
  font-size: clamp(1.55rem, 3vw, 2.55rem);
  font-weight: 750;
  line-height: 1.15;
  margin: 0 0 1.2rem;
  animation-delay: 0.35s;
  color: #e5edf0;
}

.hero-description {
  max-width: 660px;
  margin: 0;
  color: rgba(255, 255, 255, 0.78);
  font-size: clamp(1rem, 1.6vw, 1.2rem);
  font-weight: 500;
  line-height: 1.7;
  animation-delay: 0.55s;
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  flex-wrap: wrap;
  margin-top: 2rem;
  animation-delay: 0.7s;
}

.hero-btn {
  min-height: 48px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  border: 1px solid transparent;
  border-radius: 999px;
  padding: 0.75rem 1.25rem;
  font-size: 0.9rem;
  font-weight: 850;
  text-decoration: none;
  transition: 0.2s ease;
}

.hero-btn svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.hero-btn-primary {
  background: #fbbf24;
  color: #111827;
  box-shadow: 0 0.8rem 2rem rgba(251, 191, 36, 0.22);
}

.hero-btn-primary:hover {
  background: #fcd34d;
  color: #111827;
  transform: translateY(-2px);
}

.hero-btn-secondary {
  border-color: rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
  backdrop-filter: blur(10px);
}

.hero-btn-secondary:hover {
  border-color: rgba(255, 255, 255, 0.55);
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
  transform: translateY(-2px);
}

.hero-capabilities {
  position: absolute;
  z-index: 5;
  left: 4.5rem;
  right: 4.5rem;
  bottom: 0;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-top: 1px solid rgba(255, 255, 255, 0.16);
  color: #ffffff;
}

.hero-capabilities > div {
  min-height: 82px;
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 1.2rem 1.4rem;
  font-size: 0.85rem;
  font-weight: 800;
}

.hero-capabilities > div + div {
  border-left: 1px solid rgba(255, 255, 255, 0.16);
}

.capability-number {
  color: #fbbf24;
  font-size: 0.72rem;
  font-weight: 900;
  letter-spacing: 0.08em;
}

/* =========================
   SHARED CONTENT
========================= */
.content-section {
  width: calc(100% - 5rem);
  max-width: 1200px;
  margin: 0 auto 6rem;
}

.section-heading {
  max-width: 780px;
  margin-bottom: 2.5rem;
}

.section-kicker {
  display: block;
  margin-bottom: 0.65rem;
  color: #b45309;
  font-size: 0.75rem;
  font-weight: 900;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.section-heading h2,
.reporting-intro h2 {
  margin: 0 0 1rem;
  color: var(--phoenix-heading-color);
  font-size: clamp(2rem, 4vw, 3.25rem);
  font-weight: 900;
  letter-spacing: -0.035em;
  line-height: 1.08;
}

.section-heading > p,
.section-heading-row > p,
.reporting-intro > p {
  margin: 0;
  color: var(--phoenix-secondary-color);
  font-size: 1.02rem;
  line-height: 1.75;
}

.section-heading-row {
  max-width: none;
  display: grid;
  grid-template-columns: 1.25fr 0.75fr;
  align-items: end;
  gap: 3rem;
}

.section-heading-row h2 {
  margin-bottom: 0;
}

/* =========================
   ABOUT BRAVE
========================= */
.about-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.about-card {
  min-height: 270px;
  padding: 1.75rem;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1.25rem;
  background: var(--phoenix-card-bg);
  box-shadow: 0 0.8rem 2.5rem rgba(15, 23, 42, 0.05);
  transition: transform 0.22s ease, box-shadow 0.22s ease;
}

.about-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 1.25rem 2.8rem rgba(15, 23, 42, 0.1);
}

.about-icon {
  width: 50px;
  height: 50px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.4rem;
  border-radius: 14px;
}

.about-icon svg {
  width: 25px;
  height: 25px;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.8;
}

.about-icon-map {
  background: #e0f2fe;
  color: #0369a1;
}

.about-icon-alert {
  background: #ffedd5;
  color: #c2410c;
}

.about-icon-check {
  background: #dcfce7;
  color: #15803d;
}

.about-card h3 {
  margin: 0 0 0.7rem;
  color: var(--phoenix-heading-color);
  font-size: 1.18rem;
  font-weight: 900;
}

.about-card p {
  margin: 0;
  color: var(--phoenix-secondary-color);
  font-size: 0.93rem;
  line-height: 1.7;
}

/* =========================
   CATEGORY CARDS
========================= */
.brave-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
}

.brave-category-card {
  height: 100%;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1.15rem;
  overflow: hidden;
  background: var(--phoenix-card-bg);
  box-shadow: 0 0.75rem 2rem rgba(15, 23, 42, 0.05);
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.brave-category-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.12);
}

.image-wrapper {
  position: relative;
  height: 185px;
  overflow: hidden;
  background: #dfe7ea;
}

.image-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.35s ease;
}

.brave-category-card:hover .image-wrapper img {
  transform: scale(1.045);
}

.module-status {
  position: absolute;
  top: 0.85rem;
  left: 0.85rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 999px;
  padding: 0.35rem 0.65rem;
  background: rgba(15, 23, 42, 0.72);
  color: #ffffff;
  font-size: 0.7rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  backdrop-filter: blur(8px);
}

.module-status::before {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #94a3b8;
  content: '';
}

.module-status.live::before {
  background: #4ade80;
  box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.18);
}

.brave-category-card .card-body {
  min-height: 205px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 1.35rem;
  text-align: left;
}

.brave-category-card h3 {
  margin: 0 0 0.55rem;
  color: var(--phoenix-heading-color);
  font-size: 1.35rem;
  font-weight: 900;
}

.brave-category-card p {
  margin: 0 0 1.25rem;
  color: var(--phoenix-secondary-color);
  font-size: 0.88rem;
  line-height: 1.55;
}

.module-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: auto;
  color: #9a6700;
  font-size: 0.84rem;
  font-weight: 900;
  text-decoration: none;
}

.module-link:hover {
  color: #c2410c;
}

.module-link-disabled,
.module-link-disabled:hover {
  color: #64748b;
  cursor: default;
}

/* =========================
   PUBLIC REPORTING
========================= */
.reporting-section {
  display: grid;
  grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
  gap: 4rem;
  scroll-margin-top: 2rem;
  padding: 4rem;
  border-radius: 2rem;
  background:
    radial-gradient(circle at 5% 0%, rgba(251, 191, 36, 0.13), transparent 30%),
    linear-gradient(145deg, #102c35 0%, #173f49 55%, #102c35 100%);
  color: #ffffff;
  box-shadow: 0 1.5rem 4rem rgba(15, 45, 54, 0.2);
}

.section-kicker-light {
  color: #fbbf24;
}

.reporting-intro {
  align-self: start;
  position: sticky;
  top: 2rem;
}

.reporting-intro h2 {
  color: #ffffff;
  font-size: clamp(2rem, 3.6vw, 3.15rem);
}

.reporting-intro > p {
  color: rgba(255, 255, 255, 0.7);
}

.reporting-cta {
  width: fit-content;
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 1.8rem;
  border-radius: 999px;
  padding: 0.65rem 1.05rem 0.65rem 0.7rem;
  background: #fbbf24;
  color: #11272e;
  font-size: 0.86rem;
  font-weight: 900;
  text-decoration: none;
  transition: transform 0.2s ease, background 0.2s ease;
}

.reporting-cta:hover {
  background: #fcd34d;
  color: #11272e;
  transform: translateY(-2px);
}

.reporting-cta-icon {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #11272e;
  color: #fbbf24;
}

.reporting-cta-icon svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.8;
}

.workflow-panel {
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 1.4rem;
  background: rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(12px);
}

.workflow-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.workflow-step {
  position: relative;
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: 1rem;
  padding: 1.6rem;
}

.workflow-step + .workflow-step {
  border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.workflow-step:not(:last-child)::after {
  position: absolute;
  z-index: 0;
  left: calc(1.6rem + 23px);
  top: 3.8rem;
  bottom: -1.6rem;
  width: 2px;
  background: rgba(251, 191, 36, 0.28);
  content: '';
}

.workflow-number {
  position: relative;
  z-index: 1;
  width: 48px;
  height: 48px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(251, 191, 36, 0.45);
  border-radius: 50%;
  background: #173a44;
  color: #fbbf24;
  font-size: 0.83rem;
  font-weight: 900;
}

.workflow-label {
  display: block;
  margin-bottom: 0.3rem;
  color: #fbbf24;
  font-size: 0.68rem;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.workflow-step h3 {
  margin: 0 0 0.45rem;
  color: #ffffff;
  font-size: 1.08rem;
  font-weight: 850;
}

.workflow-step p {
  margin: 0;
  color: rgba(255, 255, 255, 0.66);
  font-size: 0.86rem;
  line-height: 1.65;
}

.workflow-step-review {
  background: rgba(251, 191, 36, 0.055);
}

.workflow-step-final .workflow-number {
  border-color: rgba(74, 222, 128, 0.6);
  background: rgba(22, 101, 52, 0.35);
  color: #86efac;
}

.review-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-top: 0.85rem;
}

.review-tag {
  display: inline-flex;
  border: 1px solid transparent;
  border-radius: 999px;
  padding: 0.32rem 0.6rem;
  font-size: 0.67rem;
  font-weight: 850;
}

.review-tag.verified {
  border-color: rgba(134, 239, 172, 0.28);
  background: rgba(22, 163, 74, 0.16);
  color: #bbf7d0;
}

.review-tag.clarification {
  border-color: rgba(253, 224, 71, 0.28);
  background: rgba(202, 138, 4, 0.15);
  color: #fef08a;
}

.review-tag.rejected {
  border-color: rgba(252, 165, 165, 0.25);
  background: rgba(220, 38, 38, 0.13);
  color: #fecaca;
}

.reporting-notice {
  grid-column: 1 / -1;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-top: -2rem;
  padding-top: 1.4rem;
  border-top: 1px solid rgba(255, 255, 255, 0.14);
}

.reporting-notice svg {
  width: 21px;
  height: 21px;
  flex: 0 0 21px;
  fill: none;
  stroke: #fbbf24;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.8;
}

.reporting-notice p {
  margin: 0;
  color: rgba(255, 255, 255, 0.62);
  font-size: 0.78rem;
  line-height: 1.55;
}

.reporting-notice strong {
  color: #ffffff;
}

/* =========================
   LATEST NEWS
========================= */
.latest-news-section {
  text-align: left;
}

.news-region-toggle {
  max-width: 1100px;
  margin-right: auto;
  margin-left: auto;
  display: flex;
  gap: 0.5rem;
  margin-top: 0.75rem;
  margin-bottom: 1.25rem;
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
  .hero-overlay {
    padding-right: 3rem;
    padding-left: 3rem;
  }

  .hero-capabilities {
    right: 3rem;
    left: 3rem;
  }

  .content-section {
    width: calc(100% - 3rem);
  }

  .about-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .brave-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .reporting-section {
    grid-template-columns: 1fr;
    gap: 2.5rem;
    padding: 3rem;
  }

  .reporting-intro {
    position: static;
  }

  .reporting-notice {
    margin-top: 0;
  }
}

@media (max-width: 768px) {
  .brave-home {
    padding-bottom: 2rem;
  }

  .hero-card {
    min-height: auto;
    margin-bottom: 4rem;
    border-radius: 0 0 1.4rem 1.4rem;
  }

  .hero-overlay {
    min-height: 590px;
    padding: 4.5rem 1.5rem 3.5rem;
  }

  .hero-title {
    font-size: clamp(3.75rem, 18vw, 5.5rem);
  }

  .hero-subtitle {
    font-size: clamp(1.35rem, 7vw, 2rem);
  }

  .hero-actions {
    width: 100%;
    align-items: stretch;
    flex-direction: column;
  }

  .hero-btn {
    width: 100%;
  }

  .hero-capabilities {
    position: relative;
    right: auto;
    bottom: auto;
    left: auto;
    grid-template-columns: 1fr;
    background: rgba(5, 14, 20, 0.9);
  }

  .hero-capabilities > div {
    min-height: 60px;
    padding: 0.9rem 1.5rem;
  }

  .hero-capabilities > div + div {
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    border-left: 0;
  }

  .content-section {
    width: calc(100% - 2rem);
    margin-bottom: 4.5rem;
  }

  .section-heading,
  .section-heading-row {
    margin-bottom: 2rem;
  }

  .section-heading-row {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .about-grid {
    grid-template-columns: 1fr;
  }

  .about-card {
    min-height: auto;
  }

  .reporting-section {
    gap: 2rem;
    padding: 2rem 1.25rem;
    border-radius: 1.4rem;
  }

  .workflow-step {
    grid-template-columns: 42px 1fr;
    gap: 0.8rem;
    padding: 1.25rem 1rem;
  }

  .workflow-number {
    width: 42px;
    height: 42px;
  }

  .workflow-step:not(:last-child)::after {
    left: calc(1rem + 20px);
    top: 3.35rem;
    bottom: -1.25rem;
  }

  .reporting-notice {
    padding-top: 1.1rem;
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

  .image-wrapper {
    height: 210px;
  }
}

@media (min-width: 769px) {
  .brave-home {
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
