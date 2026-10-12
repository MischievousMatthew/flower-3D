<template>
  <div class="article-page bc-theme">
    <SiteTopBar />

    <main class="bc-container">
      <template v-if="loading">
        <div class="article-head">
          <span class="bc-skeleton sk" style="width: 180px; height: 14px"></span>
          <span class="bc-skeleton sk" style="width: 80%; height: 54px; margin-top: 20px"></span>
          <span class="bc-skeleton sk" style="width: 50%; height: 14px; margin-top: 20px"></span>
        </div>
        <span class="bc-skeleton sk cover-sk"></span>
      </template>

      <div v-else-if="notFound" class="missing">
        <h1>This article isn't <em>available</em></h1>
        <p>It may have been moved or unpublished.</p>
        <router-link to="/journal" class="bc-btn bc-btn--dark">Back to the journal</router-link>
      </div>

      <article v-else-if="article">
        <header class="article-head">
          <router-link :to="{ path: '/journal', query: { topic: article.category } }" class="meta">{{ article.category_label }}</router-link>
          <h1>{{ article.title }}</h1>
          <p class="byline">By {{ article.author }} · {{ formatDate(article.published_at) }} · {{ article.reading_minutes }} min read</p>
        </header>

        <figure v-if="article.cover_url" class="cover">
          <img :src="article.cover_url" :alt="article.title" />
        </figure>

        <div class="prose">
          <template v-for="(block, i) in blocks" :key="i">
            <h2 v-if="block.type === 'h2'">{{ block.text }}</h2>
            <h3 v-else-if="block.type === 'h3'">{{ block.text }}</h3>
            <blockquote v-else-if="block.type === 'quote'">{{ block.text }}</blockquote>
            <ul v-else-if="block.type === 'ul'"><li v-for="(item, j) in block.items" :key="j">{{ item }}</li></ul>
            <ol v-else-if="block.type === 'ol'"><li v-for="(item, j) in block.items" :key="j">{{ item }}</li></ol>
            <p v-else>{{ block.text }}</p>
          </template>
        </div>

        <aside class="article-cta">
          <p class="bc-label">Ready for fresh flowers?</p>
          <h2>Find something <em>beautiful</em></h2>
          <router-link :to="ctaLink" class="bc-btn bc-btn--dark">Shop flowers <span class="bc-btn-arrow" aria-hidden="true">→</span></router-link>
        </aside>

        <section v-if="related.length" class="related">
          <h2>More to read</h2>
          <ul>
            <li v-for="r in related" :key="r.id">
              <router-link :to="`/journal/${r.slug}`">
                <span class="meta">{{ r.category_label }}</span>
                <span class="related-title">{{ r.title }}</span>
              </router-link>
            </li>
          </ul>
        </section>
      </article>
    </main>

    <SiteFooter />
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import api from "../../plugins/axios";
import SiteTopBar from "../../components/site/SiteTopBar.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import { useSiteContent } from "../../composables/useSiteContent";
import { articleBlocks } from "../../utils/articleBlocks";
import "../../styles/bloomcraft.css";

const route = useRoute();
const { site } = useSiteContent();

const article = ref(null);
const related = ref([]);
const loading = ref(true);
const notFound = ref(false);

const blocks = computed(() => articleBlocks(article.value?.body));
// Care and seasonal articles point readers at the shop's products
const ctaLink = computed(() => ({ name: "Shop", query: { section: "products" } }));

const load = async (slug) => {
  loading.value = true;
  notFound.value = false;
  try {
    const { data } = await api.get(`/site/articles/${encodeURIComponent(slug)}`);
    article.value = data.data;
    related.value = data.related ?? [];
    document.title = `${data.data.title} · ${site.settings.site_name || "BloomCraft"}`;
    window.scrollTo({ top: 0 });
  } catch {
    notFound.value = true;
    article.value = null;
  } finally {
    loading.value = false;
  }
};

watch(() => route.params.slug, (slug) => slug && load(slug), { immediate: true });

const formatDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" }) : "");
</script>

<style scoped>
.article-page {
  min-height: 100vh;
  background: var(--ivory);
}
:global(body) {
  margin: 0;
}
.article-head {
  max-width: 820px;
  margin: 0 auto;
  padding: clamp(56px, 8vw, 100px) 0 36px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.meta {
  font-size: 12px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--rosewood);
  font-weight: 500;
  text-decoration: none;
}
.article-head h1 {
  margin: 18px 0;
  font-size: clamp(42px, 5.4vw, 80px);
  line-height: 1.04;
}
.byline {
  margin: 0;
  color: var(--ink-soft);
  font-size: 14px;
}
.sk {
  display: block;
}
.cover-sk {
  height: 420px;
  border-radius: 28px;
}
.cover {
  margin: 0 auto 56px;
  max-width: 1080px;
  border-radius: 28px;
  overflow: hidden;
}
.cover img {
  display: block;
  width: 100%;
  max-height: 620px;
  object-fit: cover;
}
.prose {
  max-width: 680px;
  margin: 0 auto;
  font-size: 18px;
  line-height: 1.85;
  color: var(--ink);
}
.prose p {
  margin: 0 0 1.3em;
}
.prose h2 {
  margin: 1.8em 0 0.6em;
  font-size: 36px;
  line-height: 1.15;
}
.prose h3 {
  margin: 1.6em 0 0.5em;
  font-family: var(--font-sans);
  font-size: 19px;
  font-weight: 600;
}
.prose ul,
.prose ol {
  margin: 0 0 1.3em;
  padding-left: 1.4em;
}
.prose li {
  margin-bottom: 0.4em;
}
.prose blockquote {
  margin: 1.6em 0;
  padding: 0 0 0 24px;
  border-left: 2px solid var(--rosewood);
  font-family: var(--font-display);
  font-size: 28px;
  font-style: italic;
  line-height: 1.35;
  color: var(--rosewood-dark);
}
.article-cta {
  max-width: 680px;
  margin: 64px auto 0;
  padding: 40px;
  border-radius: 24px;
  background: var(--paper);
  border: 1px solid var(--line);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}
.article-cta h2 {
  margin: 0 0 8px;
  font-size: 40px;
}
.related {
  max-width: 680px;
  margin: 64px auto 0;
}
.related h2 {
  font-size: 30px;
  margin: 0 0 16px;
}
.related ul {
  margin: 0;
  padding: 0;
  list-style: none;
}
.related a {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 18px 0;
  border-top: 1px solid var(--line);
  color: inherit;
  text-decoration: none;
}
.related-title {
  font-family: var(--font-display);
  font-size: 24px;
}
.related a:hover .related-title {
  color: var(--rosewood);
}
.missing {
  padding: 120px 0;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
}
.missing h1 {
  font-size: clamp(40px, 5vw, 72px);
  margin: 0;
}
.missing p {
  color: var(--ink-soft);
  margin: 0;
}
</style>
