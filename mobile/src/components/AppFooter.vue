<template>
  <footer class="app-footer">
    <div class="footer-inner">
      <div class="footer-left">
        <img src="@/assets/niat-logo.png" alt="NIAT" class="footer-niat-logo" />

        <div class="footer-contact">
          <strong>Contact us for inquiries</strong>

          <h6>NETWORK INTEGRITY ASSURANCE TECHNOLOGIES (NIAT) SDN BHD</h6>

          <p>☎ +673 238 4660</p>

          <p>
            📍 Unit 28, 29 & 30, Block C, 1st Floor, Muhibbah Complex II,<br />
            Jalan Gadong, Bandar Seri Begawan BE 3919,<br />
            Negara Brunei Darussalam
          </p>
        </div>
      </div>

      <div class="footer-right">
        <img src="@/assets/observe-light.png" alt="BSERVE" class="footer-bserve-logo" />
        <p>© 2026</p>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.app-footer {
  margin-top: 2rem;
  padding: 2rem 2.5rem;
  background: var(--phoenix-card-bg);
  border-top: 1px solid var(--phoenix-card-border, #d8e2ef);
}

.footer-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
}

.footer-left {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.footer-niat-logo {
  width: 110px;
  height: auto;
}

.footer-contact strong,
.footer-contact h6,
.footer-contact p,
.footer-right p {
  color: var(--phoenix-heading);
}

.footer-contact strong {
  display: block;
  font-size: 0.9rem;
  font-weight: 800;
  margin-bottom: 0.45rem;
}

.footer-contact h6 {
  margin: 0 0 0.45rem;
  font-size: 0.9rem;
  font-weight: 900;
}

.footer-contact p {
  margin: 0.15rem 0;
  font-size: 0.85rem;
  line-height: 1.45;
}

.footer-right {
  text-align: center;
  min-width: 180px;
}

.footer-bserve-logo {
  width: 150px;
  height: auto;
  margin-bottom: 0.75rem;
}

.footer-right p {
  margin: 0;
  font-size: 0.9rem;
}

:global(html[data-bs-theme='dark']) .app-footer {
  background: var(--phoenix-card-bg);
  border-color: var(--phoenix-card-border);
}

:global(html[data-bs-theme='dark']) .app-footer {
  background: #111827;
  border-color: rgba(255, 255, 255, 0.14);
}

:global(html[data-bs-theme='dark']) .footer-contact strong,
:global(html[data-bs-theme='dark']) .footer-contact h6,
:global(html[data-bs-theme='dark']) .footer-contact p,
:global(html[data-bs-theme='dark']) .footer-right p {
  color: #f8fafc !important;
}

:global(html[data-bs-theme='dark']) .footer-contact p {
  color: #cbd5e1 !important;
}

@media (max-width: 768px) {
  .app-footer {
    padding: 1.5rem;
  }

  .footer-inner,
  .footer-left {
    flex-direction: column;
    text-align: center;
  }
}
</style>
