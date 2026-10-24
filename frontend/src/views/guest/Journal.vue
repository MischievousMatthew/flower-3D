<template>
  <div class="journal-page bc-theme">
    <SiteTopBar />

    <main>
      <section class="journal-hero">
        <div class="bc-container">
          <p class="bc-label">The {{ site.settings.site_name || "BloomCraft" }} Journal</p>
          <h1>Notes from the <em>flower world</em></h1>
          <p class="journal-intro">Flower care, seasonal guides, trends and stories from our florists.</p>
          <div class="journal-filters" role="tablist" aria-label="Topics">
            <button type="button" role="tab" :class="{ active: !category }" :aria-selected="!category" @click="setCategory('')">All</button>
            <button
              v-for="(label, key) in categories"
              :key="key"
              type="button"
              role="tab"
              :class="{ active: category === key }"
              :aria-selected="category === key"
              @click="setCategory(key)"
            >
              {{ label }}
            </button>
          </div>
        </div>
      </section>

      <section class="bc-container journal-body">
        <div v-if="loading && !articles.length" class="article-grid">
          <div v-for="n in 6" :key="n" class="article-card">
            <span class="bc-skeleton sk-img"></span>
            <span class="bc-skeleton sk-line" style="width: 40%"></span>
            <span class="bc-skeleton sk-line" style="width: 85%; height: 22px"></span>
            <span class="bc-skeleton sk-line"></span>
          </div>
        </div>
        <p v-else-if="error" class="journal-empty">
          The journal couldn't be loaded. <button type="button" class="bc-link link-btn" @click="load(1)">Try again</button>
        </p>
        <p v-else-if="!articles.length" class="journal-empty">No articles here yet. Check back soon.</p>
        <template v-else>
          <router-link v-if="lead" :to="`/journal/${lead.slug}`" class="lead">
            <div class="lead-img">
              <img v-if="lead.cover_url" :src="lead.cover_url" :alt="lead.title" />
              <span v-else aria-hidden="true">✿</span>
            </div>
            <div class="lead-copy">
              <span class="meta">{{ lead.category_label }} · {{ formatDate(lead.published_at) }} · {{ lead.reading_minutes }} min read</span>
              <h2>{{ lead.title }}</h2>
              <p>{{ lead.excerpt }}</p>
              <span class="bc-link">Read article →</span>
            </div>
          </router-link>

          <ul class="article-grid">
            <li v-for="a in rest" :key="a.id">
              <router-link :to="`/journal/${a.slug}`" class="article-card">
                <div class="card-img">
                  <img v-if="a.cover_url" :src="a.cover_url" :alt="a.title" loading="lazy" />
                  <span v-else aria-hidden="true">✿</span>
                </div>
                <span class="meta">{{ a.category_label }} · {{ a.reading_minutes }} min read</span>
                <h3>{{ a.title }}</h3>
                <p>{{ a.excerpt }}</p>
              </router-link>
            </li>
          </ul>

          <div v-if="meta.current_page < meta.last_page" class="more">
            <button type="button" class="bc-btn bc-btn--dark" :disabled="loading" @click="load(meta.current_page + 1, true)">
              {{ loading ? "Loading…" : "More articles" }}
            </button>
          </div>
        </template>
      </section>
    </main>

    <SiteFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios";
import SiteTopBar from "../../components/site/SiteTopBar.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import { useSiteContent } from "../../composables/useSiteContent";
import "../../styles/bloomcraft.css";

const route = useRoute();
const router = useRouter();
const { site } = useSiteContent();

const articles = ref([]);
const categories = ref({});
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const error = ref(false);
const category = ref(typeof route.query.topic === "string" ? route.query.topic : "");

const lead = computed(() => articles.value[0] ?? null);
const rest = computed(() => articles.value.slice(1));

const load = async (page = 1, append = false) => {
  loading.value = true;
  error.value = false;
  try {
    const { data } = await api.get("/site/articles", { params: { page, category: category.value || undefined } });
    articles.value = append ? [...articles.value, ...data.data] : data.data;
    meta.value = data.meta;
    categories.value = data.categories ?? {};
  } catch {
    error.value = true;
  } finally {
    loading.value = false;
  }
};

const setCategory = (key) => {
  category.value = key;
  router.replace({ query: key ? { topic: key } : {} });
};
watch(category, () => load(1));

const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" }) : "");

onMounted(() => {
  document.title = `Journal · ${site.settings.site_name || "BloomCraft"}`;
  load(1);
});
</script>

<style scoped>
.journal-page {
  min-height: 100vh;
  background: var(--ivory);
}
:global(body) {
  margin: 0;
}
.journal-hero {
  padding: clamp(60px, 8vw, 110px) 0 clamp(30px, 4vw, 50px);
}
.journal-hero h1 {
  margin: 18px 0 14px;
  font-size: clamp(48px, 6vw, 92px);
}
.journal-intro {
  max-width: 520px;
  margin: 0 0 32px;
  color: var(--ink-soft);
  font-size: 16.5px;
}
.journal-filters {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.journal-filters button {
  padding: 9px 18px;
  border-radius: 999px;
  border: 1px solid var(--line);
  background: transparent;
  color: var(--ink);
  font: inherit;
  font-size: 14px;
  cursor: pointer;
}
.journal-filters button.active,
.journal-filters button:hover {
  background: var(--espresso);
  border-color: var(--espresso);
  color: var(--paper);
}
.journal-body {
  padding-bottom: clamp(40px, 6vw, 80px);
}
.journal-empty {
  padding: 60px 0;
  color: var(--ink-soft);
  text-align: center;
}
.link-btn {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  cursor: pointer;
}
.meta {
  font-size: 11.5px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--leaf);
  font-weight: 500;
}
.lead {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: clamp(24px, 4vw, 56px);
  align-items: center;
  margin-bottom: clamp(48px, 6vw, 80px);
  color: inherit;
  text-decoration: none;
}
.lead-img,
.card-img {
  border-radius: 22px;
  overflow: hidden;
  background: var(--ivory-deep);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--leaf);
  font-size: 46px;
}
.lead-img {
  aspect-ratio: 4 / 3;
}
.lead-img img,
.card-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.8s var(--ease-out);
}
.lead:hover img,
.article-card:hover img {
  transform: scale(1.04);
}
.lead-copy {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.lead-copy h2 {
  margin: 0;
  font-size: clamp(34px, 3.6vw, 54px);
  line-height: 1.08;
}
.lead-copy p {
  margin: 0;
  color: var(--ink-soft);
  font-size: 16px;
}
.article-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 40px 28px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.article-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  color: inherit;
  text-decoration: none;
}
.card-img {
  aspect-ratio: 4 / 3;
  margin-bottom: 4px;
}
.article-card h3 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
  line-height: 1.15;
}
.article-card p {
  margin: 0;
  color: var(--ink-soft);
  font-size: 14.5px;
}
.sk-img {
  display: block;
  aspect-ratio: 4 / 3;
  border-radius: 22px;
}
.sk-line {
  display: block;
  height: 13px;
}
.more {
  display: flex;
  justify-content: center;
  margin-top: 48px;
}
@media (max-width: 760px) {
  .lead {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
