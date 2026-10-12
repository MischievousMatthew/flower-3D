<template>
  <section v-if="articles.length" class="journal-teaser" aria-labelledby="journal-teaser-title">
    <div class="bc-container">
      <div class="bc-section-head bc-section-head--split teaser-head">
        <div>
          <p class="bc-label">The BloomCraft Journal</p>
          <h2 id="journal-teaser-title">Flower care &amp; <em>stories</em></h2>
        </div>
        <router-link to="/journal" class="bc-link">Read the journal →</router-link>
      </div>
      <ul class="teaser-grid">
        <li v-for="a in articles" :key="a.id">
          <router-link :to="`/journal/${a.slug}`" class="teaser-card">
            <div class="teaser-img">
              <img v-if="a.cover_url" :src="a.cover_url" :alt="a.title" loading="lazy" />
              <span v-else aria-hidden="true">✿</span>
            </div>
            <span class="teaser-cat">{{ a.category_label }} · {{ a.reading_minutes }} min read</span>
            <h3>{{ a.title }}</h3>
            <p>{{ a.excerpt }}</p>
          </router-link>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
/** Latest journal articles, linking to the full journal */
defineProps({ articles: { type: Array, required: true } });
</script>

<style scoped>
.journal-teaser {
  padding: clamp(60px, 8vw, 120px) 0;
}
.teaser-head {
  margin-bottom: 36px;
}
.teaser-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 28px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.teaser-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  color: inherit;
  text-decoration: none;
}
.teaser-img {
  aspect-ratio: 4 / 3;
  border-radius: 18px;
  overflow: hidden;
  background: var(--ivory-deep);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--rosewood);
  font-size: 40px;
}
.teaser-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.8s var(--ease-out);
}
.teaser-card:hover .teaser-img img {
  transform: scale(1.04);
}
.teaser-cat {
  font-size: 11.5px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--rosewood);
  font-weight: 500;
}
.teaser-card h3 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
  line-height: 1.15;
}
.teaser-card p {
  margin: 0;
  color: var(--ink-soft);
  font-size: 14.5px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
