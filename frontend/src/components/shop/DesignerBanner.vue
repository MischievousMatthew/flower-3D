<template>
  <section class="db" aria-labelledby="db-title">
    <div class="db-card">
      <div class="db-copy">
        <p class="db-eyebrow">
          <span class="db-badge">3D</span>
          Bouquet Designer
        </p>
        <h2 id="db-title">Build your own <em>bouquet</em></h2>
        <p class="db-lede">
          Choose the flowers, fillers, wrap and ribbon from a local shop, turn it around in 3D, then order it just the way you made it.
        </p>
        <ol class="db-steps">
          <li><span>1</span>Pick a shop</li>
          <li><span>2</span>Arrange your flowers</li>
          <li><span>3</span>Preview in 3D and order</li>
        </ol>
        <div class="db-actions">
          <button type="button" class="db-btn" @click="$emit('design')">
            Start designing <span aria-hidden="true">→</span>
          </button>
          <button type="button" class="db-ghost" @click="$emit('shops')">Browse flower shops</button>
        </div>
      </div>

      <!-- A bouquet on a turntable -->
      <div class="db-art" aria-hidden="true">
        <svg viewBox="0 0 320 320">
          <defs>
            <radialGradient id="db-glow" cx="50%" cy="45%" r="55%">
              <stop offset="0" stop-color="#4db173" stop-opacity="0.35" />
              <stop offset="1" stop-color="#4db173" stop-opacity="0" />
            </radialGradient>
          </defs>
          <circle cx="160" cy="150" r="150" fill="url(#db-glow)" />
          <ellipse class="db-orbit" cx="160" cy="268" rx="118" ry="22" />
          <ellipse class="db-orbit db-orbit--b" cx="160" cy="268" rx="84" ry="14" />
          <g class="db-spin">
            <path d="M160 260 L132 170 L188 170 Z" fill="#e9dcc6" />
            <path d="M132 170 L160 260 L146 170 Z" fill="#d9c7a8" />
            <path d="M150 236 q10 -8 20 0 l-4 12 h-12 z" fill="#4db173" />
            <path d="M118 150 q-24 -28 6 -46 q10 26 -6 46z" fill="#2e7a4f" />
            <path d="M202 150 q24 -28 -6 -46 q-10 26 6 46z" fill="#2e7a4f" />
            <circle cx="160" cy="112" r="30" fill="#f4a6c0" />
            <circle cx="160" cy="112" r="13" fill="#e27a9c" />
            <circle cx="122" cy="136" r="24" fill="#f7b997" />
            <circle cx="122" cy="136" r="10" fill="#ef9670" />
            <circle cx="198" cy="136" r="24" fill="#fafbfa" />
            <circle cx="198" cy="136" r="9" fill="#f5c518" />
            <circle cx="140" cy="80" r="16" fill="#c3a8e3" />
            <circle cx="184" cy="82" r="14" fill="#f5c518" />
            <circle cx="160" cy="154" r="20" fill="#c8102e" />
            <circle cx="160" cy="154" r="8" fill="#9b0c24" />
          </g>
          <g class="db-cursor">
            <path d="M246 196 a40 14 0 0 1 -18 18" fill="none" stroke="#8fd1a6" stroke-width="2.5" stroke-linecap="round" />
            <path d="M226 208 l2 7 l7 -2" fill="none" stroke="#8fd1a6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </g>
        </svg>
      </div>
    </div>
  </section>
</template>

<script setup>
/** "Build your own bouquet": the 3D designer, in a banner of its own */
defineEmits(["design", "shops"]);
</script>

<style scoped>
.db-card {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  align-items: center;
  gap: 24px;
  height: 100%;
  padding: clamp(28px, 4vw, 52px);
  overflow: hidden;
  border-radius: 28px;
  background:
    radial-gradient(120% 90% at 100% 0%, rgba(77, 177, 115, 0.28), transparent 60%),
    linear-gradient(135deg, var(--espresso), var(--ink));
  color: var(--paper);
}

.db-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 14px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--leaf-light);
}

.db-badge {
  padding: 3px 9px;
  border-radius: 8px;
  background: var(--leaf);
  color: var(--espresso);
  letter-spacing: 0.04em;
}

.db-copy h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(36px, 4vw, 54px);
  font-weight: 500;
  line-height: 1.02;
  color: var(--paper);
}

.db-copy h2 em {
  color: var(--leaf-light);
}

.db-lede {
  max-width: 46ch;
  margin: 14px 0 20px;
  font-size: 14.5px;
  line-height: 1.65;
  color: rgba(250, 251, 250, 0.78);
}

.db-steps {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 18px;
  margin: 0 0 26px;
  padding: 0;
  list-style: none;
  font-size: 13px;
  color: rgba(250, 251, 250, 0.9);
}

.db-steps li {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.db-steps span {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border: 1px solid rgba(143, 209, 166, 0.6);
  border-radius: 50%;
  font-size: 11.5px;
  color: var(--leaf-light);
}

.db-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.db-btn,
.db-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 48px;
  padding: 0 24px;
  border-radius: 999px;
  font: 500 14px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.db-btn {
  border: 0;
  background: var(--leaf);
  color: var(--espresso);
}

.db-btn:hover {
  background: var(--leaf-light);
}

.db-ghost {
  border: 1px solid rgba(250, 251, 250, 0.35);
  background: transparent;
  color: var(--paper);
}

.db-ghost:hover {
  border-color: var(--paper);
}

.db-art svg {
  display: block;
  width: 100%;
  max-width: 340px;
  margin: 0 auto;
}

.db-orbit {
  fill: none;
  stroke: rgba(143, 209, 166, 0.45);
  stroke-width: 1.5;
  stroke-dasharray: 4 6;
}

.db-orbit--b {
  stroke: rgba(250, 251, 250, 0.2);
}

.db-spin {
  transform-box: fill-box;
  transform-origin: center;
  animation: db-sway 6s ease-in-out infinite;
}

@keyframes db-sway {
  0%,
  100% {
    transform: rotateY(0deg) rotate(-2deg);
  }
  50% {
    transform: rotateY(0deg) rotate(2deg);
  }
}

.db :focus-visible {
  outline: 2px solid var(--leaf-light);
  outline-offset: 3px;
}

@media (max-width: 760px) {
  .db-card {
    grid-template-columns: minmax(0, 1fr);
  }

  .db-art {
    order: -1;
  }

  .db-art svg {
    max-width: 200px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .db-spin {
    animation: none;
  }
}
</style>
