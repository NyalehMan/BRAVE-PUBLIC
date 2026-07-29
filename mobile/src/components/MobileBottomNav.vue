<template>
  <nav class="mobile-bottom-nav">
    <RouterLink to="/" class="nav-item">
      <span class="nav-icon">🏠</span>
      <small>Home</small>
    </RouterLink>

    <RouterLink to="/map" class="nav-item">
      <span class="nav-icon">🗺️</span>
      <small>Map</small>
    </RouterLink>

    <RouterLink to="/report-incident" class="nav-item report-nav">
      <span class="report-icon">🚨</span>
      <small>Reports</small>
    </RouterLink>

    <RouterLink to="/my-reports" class="nav-item">
      <span class="nav-icon">📋</span>
      <small>My Report</small>
    </RouterLink>

    <RouterLink to="/profile" class="nav-item">
      <span class="nav-icon">👤</span>
      <small>Profile</small>
    </RouterLink>
  </nav>
</template>

<style scoped>
.mobile-bottom-nav {
  position: fixed;
  left: 50%;
  bottom: calc(12px + env(safe-area-inset-bottom));
  transform: translateX(-50%);
  z-index: 9999;

  width: min(calc(100% - 24px), 392px);
  height: 76px;
  padding: 6px 8px;

  display: grid;
  grid-template-columns: 1fr 1fr 1.18fr 1fr 1fr;
  align-items: center;
  gap: 2px;

  background: linear-gradient(180deg, #e8bd00, #d8ae00);
  border-radius: 28px;

  box-shadow:
    0 18px 36px rgba(15, 23, 42, 0.2),
    0 6px 14px rgba(15, 23, 42, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.32);

  overflow: visible;
}

.nav-item {
  position: relative;
  height: 62px;
  border-radius: 22px;
  text-decoration: none;
  color: rgba(17, 24, 39, 0.82);

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  font-size: 0.6rem;

  font-weight: 950;
  line-height: 1;

  transition:
    transform 0.18s ease,
    background 0.18s ease,
    color 0.18s ease;
}

.nav-item span {
  font-size: 1.55rem;
  line-height: 1;
}

.nav-item small {
  font-size: 0.9rem;
  line-height: 1;
  font-weight: 900;
  white-space: nowrap;
  letter-spacing: -0.02em;
}

.nav-item.router-link-active:not(.report-item) {
  color: #111827;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.42));
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.75),
    0 8px 18px rgba(120, 90, 0, 0.14);
}

.nav-item.router-link-active:not(.report-item)::after {
  content: '';
  position: absolute;
  bottom: 7px;
  width: 28px;
  height: 4px;
  border-radius: 999px;
  background: #ffffff;
}

/* center emergency report button */
.report-item {
  transform: translateY(-19px);
  height: 88px;
  gap: 5px;
}

.report-item span {
  width: 58px;
  height: 58px;
  border-radius: 999px;

  display: grid;
  place-items: center;

  background:
    radial-gradient(circle at 35% 25%, rgba(255, 255, 255, 0.45), transparent 28%),
    linear-gradient(135deg, #ff6b4a, #dc3545 55%, #b00020);

  color: #fff;
  font-size: 1.55rem;

  border: 5px solid rgba(255, 255, 255, 0.96);

  box-shadow:
    0 14px 28px rgba(220, 53, 69, 0.36),
    0 5px 12px rgba(120, 30, 30, 0.18);
}

.report-item small {
  color: #111827;
  font-size: 0.58rem;
  font-weight: 900;
}

.report-item.router-link-active {
  background: transparent;
}

.nav-item:active {
  transform: scale(0.94);
}

.report-item:active {
  transform: translateY(-19px) scale(0.96);
}

.mobile-home-page,
.mobile-report-page,
.mobile-map-page,
.mobile-profile-page,
.mobile-my-reports-page {
  padding-bottom: 7.4rem;
}

/* dark mode keeps BRAVE yellow branding */
:global(html[data-bs-theme='dark']) .mobile-bottom-nav {
  background: linear-gradient(180deg, #e8bd00, #d8ae00);
  border-color: rgba(255, 255, 255, 0.92);
}

:global(html[data-bs-theme='dark']) .nav-item {
  color: rgba(17, 24, 39, 0.82);
}

:global(html[data-bs-theme='dark']) .nav-item.router-link-active:not(.report-item) {
  color: #111827;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.42));
}
</style>
