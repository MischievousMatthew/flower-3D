<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
          </button>
          <div>
            <h1 class="page-title">Promotion Management</h1>
            <p class="page-subtitle">Banners, featured products, promotions, announcements and the journal. Changes show on the shop as soon as you save.</p>
          </div>
        </div>
        <div class="header-right">
          <a href="/shop" target="_blank" rel="noopener" class="ghost-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><polyline points="15 3 21 3 21 9" /><line x1="10" y1="14" x2="21" y2="3" /></svg>
            View shop
          </a>
        </div>
      </header>

      <div class="page-body">
        <nav class="tabs" role="tablist">
          <button v-for="t in TABS" :key="t.key" type="button" role="tab" class="tab" :class="{ active: tab === t.key }" :aria-selected="tab === t.key" @click="setTab(t.key)">
            <span class="tab-icon" v-html="t.icon"></span>
            {{ t.label }}
            <span v-if="badge(t.key)" class="tab-badge">{{ badge(t.key) }}</span>
          </button>
        </nav>

        <!-- ═══ Banners ═══ -->
        <section v-if="tab === 'banners'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Homepage banners</h2>
              <p class="panel-sub">Slides at the top of the shop. Without any, the shop shows its standard welcome.</p>
            </div>
            <button type="button" class="primary-btn" @click="editBanner()">+ New banner</button>
          </div>
          <SkeletonRows v-if="loading.banners" />
          <p v-else-if="!banners.length" class="empty">No banners yet. Add one to feature a collection, season or store.</p>
          <ul v-else class="media-list">
            <li v-for="(b, i) in banners" :key="b.id" :class="{ off: b.state !== 'showing' }">
              <div class="reorder">
                <button type="button" :disabled="i === 0" aria-label="Move up" @click="move('banners', banners, i, -1)">▲</button>
                <button type="button" :disabled="i === banners.length - 1" aria-label="Move down" @click="move('banners', banners, i, 1)">▼</button>
              </div>
              <img :src="b.image_url" alt="" class="thumb wide" />
              <div class="media-main">
                <span class="strong">{{ b.title }}</span>
                <span class="sub">{{ b.subtitle || "No subtitle" }}</span>
                <span class="sub">Button: {{ b.button_label || "Shop now" }} → {{ describeLink(b) }}</span>
              </div>
              <div class="media-side">
                <span class="pill" :class="`state-${b.state}`">{{ STATE[b.state] }}</span>
                <span class="sub">{{ scheduleText(b) }}</span>
              </div>
              <div class="row-actions">
                <button type="button" class="mini-btn" @click="editBanner(b)">Edit</button>
                <button type="button" class="mini-btn danger" @click="confirmDelete('banners', b, b.title)">Delete</button>
              </div>
            </li>
          </ul>
        </section>

        <!-- ═══ Featured products ═══ -->
        <section v-else-if="tab === 'featured'" class="split">
          <div class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">Featured picks</h2>
                <p class="panel-sub">Shown near the top of the shop, in this order. Up to 12.</p>
              </div>
              <span class="count-pill">{{ featured.length }} / 12</span>
            </div>
            <SkeletonRows v-if="loading.featured" />
            <p v-else-if="!featured.length" class="empty">Nothing featured yet. Search for a product on the right to add it.</p>
            <ul v-else class="media-list">
              <li v-for="(p, i) in featured" :key="p.featured_id" :class="{ off: !p.live }">
                <div class="reorder">
                  <button type="button" :disabled="i === 0" aria-label="Move up" @click="move('featured', featured, i, -1, 'featured_id')">▲</button>
                  <button type="button" :disabled="i === featured.length - 1" aria-label="Move down" @click="move('featured', featured, i, 1, 'featured_id')">▼</button>
                </div>
                <img v-if="p.image" :src="p.image" alt="" class="thumb" />
                <span v-else class="thumb placeholder">🌸</span>
                <div class="media-main">
                  <span class="strong">{{ p.name }}</span>
                  <span class="sub">{{ p.vendor?.store_name }} · {{ money(p.price) }}</span>
                  <span v-if="!p.live" class="sub warn">Hidden from the shop right now (not available to buy)</span>
                </div>
                <button type="button" class="mini-btn danger" @click="removeFeatured(p)">Remove</button>
              </li>
            </ul>
          </div>
          <div class="panel">
            <h2 class="panel-title">Add a product</h2>
            <p class="panel-sub">Only listings customers can buy right now appear here.</p>
            <div class="search-wrap">
              <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
              <input v-model="productSearch" type="search" class="input search-input" placeholder="Product or store name" />
            </div>
            <SkeletonRows v-if="loading.search" :rows="3" />
            <p v-else-if="!searchResults.length" class="empty small">{{ productSearch ? "No matching products." : "No more products to add." }}</p>
            <ul v-else class="media-list compact">
              <li v-for="p in searchResults" :key="p.id">
                <img v-if="p.image" :src="p.image" alt="" class="thumb" />
                <span v-else class="thumb placeholder">🌸</span>
                <div class="media-main">
                  <span class="strong">{{ p.name }}</span>
                  <span class="sub">{{ p.vendor?.store_name }} · {{ money(p.price) }}</span>
                </div>
                <button type="button" class="mini-btn ok" :disabled="featured.length >= 12" @click="addFeatured(p)">Add</button>
              </li>
            </ul>
          </div>
        </section>

        <!-- ═══ Journal ═══ -->
        <section v-else-if="tab === 'journal'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Journal</h2>
              <p class="panel-sub">Flower care articles and blog posts. Published ones appear in the shop, on the landing page and at /journal.</p>
            </div>
            <button type="button" class="primary-btn" @click="editArticle()">+ New article</button>
          </div>
          <div class="seg">
            <button v-for="f in ARTICLE_FILTERS" :key="f.value" type="button" class="seg-btn" :class="{ active: articleFilter === f.value }" @click="articleFilter = f.value">{{ f.label }}</button>
          </div>
          <SkeletonRows v-if="loading.articles" />
          <p v-else-if="!articles.length" class="empty">No articles{{ articleFilter ? " here" : " yet" }}.</p>
          <ul v-else class="media-list">
            <li v-for="a in articles" :key="a.id" class="clickable" tabindex="0" @click="editArticle(a)" @keydown.enter="editArticle(a)">
              <img v-if="a.cover_url" :src="a.cover_url" alt="" class="thumb" />
              <span v-else class="thumb placeholder">✿</span>
              <div class="media-main">
                <span class="strong">{{ a.title }}<span v-if="a.is_featured" class="star" title="Featured">★</span></span>
                <span class="sub">{{ a.category_label }} · {{ a.reading_minutes }} min read · {{ a.views }} views</span>
              </div>
              <div class="media-side">
                <span class="pill" :class="`article-${a.state}`">{{ ARTICLE_STATE[a.state] }}</span>
                <span class="sub">{{ a.state === "draft" ? `Edited ${timeAgo(a.updated_at)}` : formatDateTime(a.published_at) }}</span>
              </div>
            </li>
          </ul>
        </section>

        <!-- ═══ Announcements & promotions ═══ -->
        <section v-else-if="tab === 'announcements'" class="split even">
          <div v-for="group in ANNOUNCEMENT_GROUPS" :key="group.kind" class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">{{ group.title }}</h2>
                <p class="panel-sub">{{ group.hint }}</p>
              </div>
              <button type="button" class="primary-btn" @click="editAnnouncement(null, group.kind)">+ New</button>
            </div>
            <SkeletonRows v-if="loading.announcements" :rows="3" />
            <p v-else-if="!announcementsOf(group.kind).length" class="empty small">None yet.</p>
            <ul v-else class="media-list compact">
              <li v-for="(a, i) in announcementsOf(group.kind)" :key="a.id" :class="{ off: a.state !== 'showing' }">
                <div class="reorder">
                  <button type="button" :disabled="i === 0" aria-label="Move up" @click="moveAnnouncement(group.kind, i, -1)">▲</button>
                  <button type="button" :disabled="i === announcementsOf(group.kind).length - 1" aria-label="Move down" @click="moveAnnouncement(group.kind, i, 1)">▼</button>
                </div>
                <img v-if="a.image_url" :src="a.image_url" alt="" class="thumb" />
                <span v-else class="tone-dot" :class="`tone-${a.tone}`"></span>
                <div class="media-main">
                  <span class="strong">{{ a.title }}</span>
                  <span class="sub">{{ a.message || "—" }}</span>
                  <span v-if="a.promo_code" class="code">{{ a.promo_code }}</span>
                </div>
                <div class="media-side">
                  <span class="pill" :class="`state-${a.state}`">{{ STATE[a.state] }}</span>
                  <span class="sub">{{ scheduleText(a) }}</span>
                </div>
                <div class="row-actions">
                  <button type="button" class="mini-btn" @click="editAnnouncement(a)">Edit</button>
                  <button type="button" class="mini-btn danger" @click="confirmDelete('announcements', a, a.title)">Delete</button>
                </div>
              </li>
            </ul>
          </div>
        </section>

      </div>
    </main>

    <!-- ═══ Editor drawer ═══ -->
    <Transition name="fade"><div v-if="editor.type" class="drawer-backdrop" @click="closeEditor"></div></Transition>
    <Transition name="slide">
      <aside v-if="editor.type" class="drawer" role="dialog" aria-modal="true">
        <header class="drawer-head">
          <h2>{{ editorTitle }}</h2>
          <button type="button" class="icon-btn" aria-label="Close" @click="closeEditor"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></button>
        </header>

        <form id="editor-form" class="drawer-body" @submit.prevent="saveEditor">
          <!-- Image -->
          <div class="image-field" :class="{ required: editor.type === 'banner' }">
            <span class="field-label">{{ editor.type === "article" ? "Cover photo" : editor.type === "banner" ? "Banner image *" : "Image (optional)" }}</span>
            <label class="image-drop" :class="{ filled: imagePreview }">
              <img v-if="imagePreview" :src="imagePreview" alt="" />
              <span v-else>Click to choose a JPG, PNG or WebP (max 5 MB)<template v-if="editor.type === 'banner'"><br />Wide photos (about 2400 × 1100) look best</template></span>
              <input type="file" accept="image/jpeg,image/png,image/webp" @change="pickImage" />
            </label>
            <button v-if="editor.type === 'article' && imagePreview" type="button" class="link-btn" @click="clearImage">Remove photo</button>
          </div>

          <!-- Banner -->
          <template v-if="editor.type === 'banner'">
            <label class="field"><span>Small heading</span><input v-model="form.eyebrow" class="input" placeholder="e.g. Spring collection" maxlength="60" /></label>
            <label class="field"><span>Title *</span><input v-model="form.title" class="input" maxlength="140" required /></label>
            <label class="field"><span>Subtitle</span><textarea v-model="form.subtitle" class="input" rows="2" maxlength="300"></textarea></label>
            <label class="field"><span>Button text</span><input v-model="form.button_label" class="input" placeholder="Shop now" maxlength="40" /></label>
            <LinkPicker v-model:type="form.link_type" v-model:value="form.link_value" :options="linkOptions" />
          </template>

          <!-- Announcement / promotion -->
          <template v-else-if="editor.type === 'announcement'">
            <div class="seg wide">
              <button type="button" class="seg-btn" :class="{ active: form.kind === 'announcement' }" @click="form.kind = 'announcement'">Announcement</button>
              <button type="button" class="seg-btn" :class="{ active: form.kind === 'promotion' }" @click="form.kind = 'promotion'">Promotion</button>
            </div>
            <label class="field"><span>Title *</span><input v-model="form.title" class="input" maxlength="120" required :placeholder="form.kind === 'promotion' ? 'e.g. Mother\'s Day pre-orders' : 'e.g. Free delivery this weekend'" /></label>
            <label class="field"><span>Message</span><textarea v-model="form.message" class="input" rows="2" maxlength="300"></textarea></label>
            <label v-if="form.kind === 'promotion'" class="field">
              <span>Promo code</span>
              <input v-model="form.promo_code" class="input mono" maxlength="40" placeholder="e.g. MOM10" />
              <small class="hint">Shown for customers to copy. Letters, numbers, - and _ only. Vendors honour it at checkout or in chat.</small>
            </label>
            <div v-else class="field">
              <span>Colour</span>
              <div class="tones">
                <button v-for="t in TONES" :key="t.value" type="button" class="tone-btn" :class="[`tone-${t.value}`, { active: form.tone === t.value }]" @click="form.tone = t.value">{{ t.label }}</button>
              </div>
            </div>
            <label class="field"><span>Button text</span><input v-model="form.link_label" class="input" placeholder="Learn more" maxlength="40" /></label>
            <LinkPicker v-model:type="form.link_type" v-model:value="form.link_value" :options="linkOptions" optional />
          </template>

          <!-- Article -->
          <template v-else-if="editor.type === 'article'">
            <label class="field"><span>Title *</span><input v-model="form.title" class="input" maxlength="160" required /></label>
            <div class="two">
              <label class="field">
                <span>Topic *</span>
                <select v-model="form.category" class="input">
                  <option v-for="(label, key) in articleCategories" :key="key" :value="key">{{ label }}</option>
                </select>
              </label>
              <label class="check"><input v-model="form.is_featured" type="checkbox" /> Feature at the top of the journal</label>
            </div>
            <label class="field"><span>Short summary</span><textarea v-model="form.excerpt" class="input" rows="2" maxlength="300" placeholder="Shown on article cards. Left empty, the start of the article is used."></textarea></label>
            <div class="field">
              <div class="body-head">
                <span>Article *</span>
                <div class="seg small">
                  <button type="button" class="seg-btn" :class="{ active: !previewBody }" @click="previewBody = false">Write</button>
                  <button type="button" class="seg-btn" :class="{ active: previewBody }" @click="previewBody = true">Preview</button>
                </div>
              </div>
              <textarea v-if="!previewBody" v-model="form.body" class="input body-input" rows="16" required></textarea>
              <div v-else class="body-preview">
                <template v-for="(block, i) in articleBlocks(form.body)" :key="i">
                  <h2 v-if="block.type === 'h2'">{{ block.text }}</h2>
                  <h3 v-else-if="block.type === 'h3'">{{ block.text }}</h3>
                  <blockquote v-else-if="block.type === 'quote'">{{ block.text }}</blockquote>
                  <ul v-else-if="block.type === 'ul'"><li v-for="(it, j) in block.items" :key="j">{{ it }}</li></ul>
                  <ol v-else-if="block.type === 'ol'"><li v-for="(it, j) in block.items" :key="j">{{ it }}</li></ol>
                  <p v-else>{{ block.text }}</p>
                </template>
                <p v-if="!form.body.trim()" class="sub">Nothing written yet.</p>
              </div>
              <small class="hint">Formatting: <code>## Heading</code> · <code>### Smaller heading</code> · <code>- bullet</code> · <code>1. step</code> · <code>&gt; quote</code> · blank line for a new paragraph</small>
            </div>
            <div v-if="editor.item" class="publish-box">
              <div>
                <span class="strong">Status: {{ ARTICLE_STATE[editor.item.state] }}</span>
                <span class="sub" v-if="editor.item.state !== 'draft'">{{ editor.item.state === "scheduled" ? "Goes live" : "Published" }} {{ formatDateTime(editor.item.published_at) }}</span>
                <a v-if="editor.item.state === 'published'" :href="`/journal/${editor.item.slug}`" target="_blank" rel="noopener" class="link-btn">View on site ↗</a>
              </div>
              <label v-if="editor.item.state === 'draft'" class="field small-field">
                <span>Publish later (optional)</span>
                <input v-model="form.publish_at" type="datetime-local" class="input" />
              </label>
            </div>
          </template>

          <!-- Schedule (banners + announcements) -->
          <div v-if="editor.type !== 'article'" class="schedule">
            <span class="field-label">Schedule (optional)</span>
            <div class="two">
              <label class="field"><span>Starts</span><input v-model="form.starts_at" type="datetime-local" class="input" /></label>
              <label class="field"><span>Ends</span><input v-model="form.ends_at" type="datetime-local" class="input" /></label>
            </div>
            <label class="check"><input v-model="form.is_active" type="checkbox" /> Switched on</label>
          </div>

          <p v-if="editor.error" class="form-error">{{ editor.error }}</p>
        </form>

        <footer class="drawer-foot">
          <button v-if="editor.type === 'article' && editor.item" type="button" class="ghost-btn danger-text" @click="confirmDelete('articles', editor.item, editor.item.title)">Delete</button>
          <span class="spacer"></span>
          <template v-if="editor.type === 'article'">
            <button v-if="editor.item && editor.item.state !== 'draft'" type="button" class="ghost-btn" :disabled="saving" @click="unpublish">Move to drafts</button>
            <button type="submit" form="editor-form" class="ghost-btn" :disabled="saving">{{ saving ? "Saving…" : editor.item?.state === "draft" || !editor.item ? "Save draft" : "Save changes" }}</button>
            <button v-if="!editor.item || editor.item.state === 'draft'" type="button" class="primary-btn" :disabled="saving" @click="saveAndPublish">
              {{ form.publish_at ? "Schedule" : "Publish" }}
            </button>
          </template>
          <button v-else type="submit" form="editor-form" class="primary-btn" :disabled="saving">{{ saving ? "Saving…" : "Save" }}</button>
        </footer>
      </aside>
    </Transition>

    <!-- Delete confirm -->
    <Transition name="fade">
      <div v-if="deleting.item" class="dialog-backdrop" @click.self="deleting.item = null">
        <div class="dialog" role="dialog" aria-modal="true">
          <h3>Delete “{{ deleting.label }}”?</h3>
          <p>It disappears from the website straight away. This can't be undone.</p>
          <div class="dialog-actions">
            <button type="button" class="ghost-btn" :disabled="deleting.busy" @click="deleting.item = null">Cancel</button>
            <button type="button" class="primary-btn danger" :disabled="deleting.busy" @click="doDelete">{{ deleting.busy ? "Deleting…" : "Delete" }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import LinkPicker from "./components/LinkPicker.vue";
import { articleBlocks } from "../../utils/articleBlocks";
import api from "../../plugins/axios";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();

const SkeletonRows = defineComponent({
  props: { rows: { type: Number, default: 4 } },
  setup: (props) => () => h("div", { class: "sk-rows" }, Array.from({ length: props.rows }, (_, i) => h("div", { key: i, class: "sk sk-row" }))),
});

const svg = (d) => `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${d}</svg>`;
const TABS = [
  { key: "banners", label: "Banners", icon: svg('<rect x="3" y="4" width="18" height="12" rx="2"/><line x1="7" y1="20" x2="17" y2="20"/>') },
  { key: "featured", label: "Featured products", icon: svg('<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>') },
  { key: "journal", label: "Journal", icon: svg('<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>') },
  { key: "announcements", label: "Announcements & promos", icon: svg('<path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>') },
];
const STATE = { showing: "Showing", scheduled: "Scheduled", ended: "Ended", off: "Off" };
const ARTICLE_STATE = { draft: "Draft", scheduled: "Scheduled", published: "Published" };
const ARTICLE_FILTERS = [
  { value: "", label: "All" },
  { value: "published", label: "Published" },
  { value: "scheduled", label: "Scheduled" },
  { value: "draft", label: "Drafts" },
];
const ANNOUNCEMENT_GROUPS = [
  { kind: "announcement", title: "Announcements", hint: "A slim bar at the top of the shop. Customers can dismiss each one." },
  { kind: "promotion", title: "Promotions", hint: "Cards under the shop banner, with an optional promo code to copy." },
];
const TONES = [
  { value: "brand", label: "Dark" },
  { value: "info", label: "Blue" },
  { value: "success", label: "Green" },
  { value: "warning", label: "Amber" },
];
const tab = ref(TABS.some((t) => t.key === route.query.tab) ? route.query.tab : "banners");
const loading = reactive({ banners: false, featured: false, search: false, articles: false, announcements: false });
const saving = ref(false);
const overview = ref(null);
const linkOptions = ref({});

const banners = ref([]);
const featured = ref([]);
const searchResults = ref([]);
const productSearch = ref("");
const articles = ref([]);
const articleCategories = ref({ care: "Flower care", guide: "Seasonal guide", trends: "Trends", stories: "Vendor stories", news: "News" });
const articleFilter = ref("");
const announcements = ref([]);

const badge = (key) => {
  const o = overview.value;
  if (!o) return null;
  return { banners: o.banners.showing, featured: o.featured, journal: o.articles.published, announcements: o.announcements.showing + o.announcements.promotions }[key] || null;
};

// ── Loading ──────────────────────────────────────────────────────
const get = async (key, url, target, params) => {
  loading[key] = true;
  try {
    const { data } = await api.get(url, { params });
    target.value = data.data;
    return data;
  } catch {
    toast.error("Something couldn't be loaded. Try refreshing.");
  } finally {
    loading[key] = false;
  }
};

const fetchOverview = async () => {
  try {
    overview.value = (await api.get("/admin/content/overview")).data.data;
  } catch {
    // badges are optional
  }
};
const fetchLinkOptions = async () => {
  try {
    linkOptions.value = (await api.get("/admin/content/link-options")).data.data;
  } catch {
    // the picker just has fewer choices
  }
};
const fetchBanners = () => get("banners", "/admin/content/banners", banners);
const fetchFeatured = () => get("featured", "/admin/content/featured", featured);
const fetchArticles = async () => {
  const data = await get("articles", "/admin/content/articles", articles, { status: articleFilter.value || undefined });
  if (data?.categories) articleCategories.value = data.categories;
};
const fetchAnnouncements = () => get("announcements", "/admin/content/announcements", announcements);
let searchTimer = null;
const searchProducts = async () => {
  loading.search = true;
  try {
    searchResults.value = (await api.get("/admin/content/featured/search", { params: { q: productSearch.value.trim() || undefined } })).data.data;
  } catch {
    searchResults.value = [];
  } finally {
    loading.search = false;
  }
};
watch(productSearch, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(searchProducts, 300);
});
watch(articleFilter, fetchArticles);

const loadTab = (key) => {
  if (key === "banners") fetchBanners();
  if (key === "featured") {
    fetchFeatured();
    searchProducts();
  }
  if (key === "journal") fetchArticles();
  if (key === "announcements") fetchAnnouncements();
};
const setTab = (key) => {
  tab.value = key;
  router.replace({ query: { ...route.query, tab: key } });
  loadTab(key);
};

// ── Ordering ─────────────────────────────────────────────────────
const saveOrder = async (type, list, idKey = "id") => {
  try {
    await api.post(`/admin/content/${type}/reorder`, { ids: list.map((x) => x[idKey]) });
  } catch {
    toast.error("The new order couldn't be saved.");
  }
};
const move = (type, list, index, delta, idKey) => {
  const target = index + delta;
  [list[index], list[target]] = [list[target], list[index]];
  saveOrder(type, list, idKey);
};
const announcementsOf = (kind) => announcements.value.filter((a) => a.kind === kind);
const moveAnnouncement = (kind, index, delta) => {
  const group = announcementsOf(kind);
  const target = index + delta;
  [group[index], group[target]] = [group[target], group[index]];
  const others = announcements.value.filter((a) => a.kind !== kind);
  announcements.value = kind === "announcement" ? [...group, ...others] : [...others, ...group];
  saveOrder("announcements", announcements.value);
};

// ── Featured ─────────────────────────────────────────────────────
const addFeatured = async (p) => {
  try {
    const { data } = await api.post("/admin/content/featured", { product_id: p.id });
    toast.success(data.message);
    await Promise.all([fetchFeatured(), searchProducts(), fetchOverview()]);
  } catch (e) {
    toast.error(e.response?.data?.message || "It couldn't be added.");
  }
};
const removeFeatured = async (p) => {
  try {
    await api.delete(`/admin/content/featured/${p.featured_id}`);
    featured.value = featured.value.filter((x) => x.featured_id !== p.featured_id);
    searchProducts();
    fetchOverview();
  } catch {
    toast.error("It couldn't be removed.");
  }
};

// ── Editor ───────────────────────────────────────────────────────
const editor = reactive({ type: null, item: null, error: "" });
const form = reactive({});
const imageFile = ref(null);
const imagePreview = ref(null);
const removeImage = ref(false);
const previewBody = ref(false);

const toLocalInput = (iso) => {
  if (!iso) return "";
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const openEditor = (type, item, values, image) => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, values);
  Object.assign(editor, { type, item, error: "" });
  imageFile.value = null;
  imagePreview.value = image ?? null;
  removeImage.value = false;
  previewBody.value = false;
  if (!Object.keys(linkOptions.value).length) fetchLinkOptions();
};

const editBanner = (b = null) =>
  openEditor("banner", b, {
    eyebrow: b?.eyebrow ?? "", title: b?.title ?? "", subtitle: b?.subtitle ?? "", button_label: b?.button_label ?? "",
    link_type: b?.link_type ?? "products", link_value: b?.link_value ?? "",
    starts_at: toLocalInput(b?.starts_at), ends_at: toLocalInput(b?.ends_at), is_active: b ? b.is_active : true,
  }, b?.image_url);

const editAnnouncement = (a = null, kind = "announcement") =>
  openEditor("announcement", a, {
    kind: a?.kind ?? kind, title: a?.title ?? "", message: a?.message ?? "", promo_code: a?.promo_code ?? "",
    tone: a?.tone ?? "brand", link_label: a?.link_label ?? "", link_type: a?.link_type ?? "", link_value: a?.link_value ?? "",
    starts_at: toLocalInput(a?.starts_at), ends_at: toLocalInput(a?.ends_at), is_active: a ? a.is_active : true,
  }, a?.image_url);

const editArticle = async (a = null) => {
  openEditor("article", a, { title: a?.title ?? "", category: a?.category ?? "care", excerpt: a?.excerpt ?? "", body: "", is_featured: a?.is_featured ?? false, publish_at: "" }, a?.cover_url);
  if (a) {
    try {
      const { data } = await api.get(`/admin/content/articles/${a.id}`);
      if (editor.item?.id === a.id) {
        form.body = data.data.body;
        editor.item = data.data;
      }
    } catch {
      editor.error = "The article couldn't be loaded.";
    }
  }
};

const editorTitle = computed(() => {
  const isNew = !editor.item;
  if (editor.type === "banner") return isNew ? "New banner" : "Edit banner";
  if (editor.type === "article") return isNew ? "New article" : "Edit article";
  return `${isNew ? "New" : "Edit"} ${form.kind === "promotion" ? "promotion" : "announcement"}`;
});

const closeEditor = () => {
  if (!saving.value) editor.type = null;
};

const pickImage = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    toast.error("That image is over 5 MB.");
    return;
  }
  imageFile.value = file;
  removeImage.value = false;
  imagePreview.value = URL.createObjectURL(file);
};
const clearImage = () => {
  imageFile.value = null;
  imagePreview.value = null;
  removeImage.value = true;
};

const buildForm = (fields, imageKey) => {
  const fd = new FormData();
  for (const [key, value] of Object.entries(fields)) {
    if (value === null || value === undefined) continue;
    fd.append(key, typeof value === "boolean" ? (value ? "1" : "0") : value);
  }
  if (imageFile.value) fd.append(imageKey, imageFile.value);
  return fd;
};

const firstError = (e) => {
  const errors = e.response?.data?.errors;
  return errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "It couldn't be saved.";
};

const saveEditor = async () => {
  editor.error = "";
  if (editor.type === "banner" && !imagePreview.value) {
    editor.error = "Choose a banner image.";
    return;
  }
  saving.value = true;
  try {
    if (editor.type === "banner") {
      const { link_type, link_value, ...rest } = form;
      const fd = buildForm({ ...rest, link_type, link_value: link_type === "products" ? "" : link_value }, "image");
      await api.post(editor.item ? `/admin/content/banners/${editor.item.id}` : "/admin/content/banners", fd);
      await fetchBanners();
    } else if (editor.type === "announcement") {
      const fd = buildForm({ ...form, promo_code: form.kind === "promotion" ? form.promo_code : "", link_type: form.link_type || "", link_value: form.link_type && form.link_type !== "products" ? form.link_value : "" }, "image");
      await api.post(editor.item ? `/admin/content/announcements/${editor.item.id}` : "/admin/content/announcements", fd);
      await fetchAnnouncements();
    } else {
      const { publish_at, ...rest } = form;
      const fd = buildForm({ ...rest, remove_cover: removeImage.value }, "cover");
      const { data } = await api.post(editor.item ? `/admin/content/articles/${editor.item.id}` : "/admin/content/articles", fd);
      editor.item = data.data;
      await fetchArticles();
      toast.success("Saved.");
      fetchOverview();
      return data.data;
    }
    toast.success("Saved. It's on the website now if it's switched on.");
    editor.type = null;
    fetchOverview();
  } catch (e) {
    editor.error = firstError(e);
  } finally {
    saving.value = false;
  }
};

const saveAndPublish = async () => {
  if (!form.title.trim() || form.body.trim().length < 20) {
    editor.error = "Add a title and at least a few sentences before publishing.";
    return;
  }
  const article = await saveEditor();
  if (!article) return;
  saving.value = true;
  try {
    const { data } = await api.post(`/admin/content/articles/${article.id}/publish`, { published_at: form.publish_at || undefined });
    toast.success(data.message);
    editor.type = null;
    fetchArticles();
    fetchOverview();
    fetchLinkOptions();
  } catch (e) {
    editor.error = firstError(e);
  } finally {
    saving.value = false;
  }
};

const unpublish = async () => {
  saving.value = true;
  try {
    const { data } = await api.post(`/admin/content/articles/${editor.item.id}/unpublish`);
    editor.item = data.data;
    toast.success(data.message);
    fetchArticles();
    fetchOverview();
  } catch (e) {
    editor.error = firstError(e);
  } finally {
    saving.value = false;
  }
};

// ── Delete ───────────────────────────────────────────────────────
const deleting = reactive({ type: null, item: null, label: "", busy: false });
const confirmDelete = (type, item, label) => Object.assign(deleting, { type, item, label, busy: false });
const doDelete = async () => {
  deleting.busy = true;
  try {
    await api.delete(`/admin/content/${deleting.type}/${deleting.item.id}`);
    toast.success("Deleted.");
    if (deleting.type === "banners") fetchBanners();
    if (deleting.type === "announcements") fetchAnnouncements();
    if (deleting.type === "articles") {
      fetchArticles();
      editor.type = null;
    }
    fetchOverview();
    deleting.item = null;
  } catch {
    toast.error("It couldn't be deleted.");
  } finally {
    deleting.busy = false;
  }
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => peso.format(Number(v || 0));
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
const timeAgo = (v) => {
  const days = Math.floor((Date.now() - new Date(v)) / 86_400_000);
  return days < 1 ? "today" : days === 1 ? "yesterday" : `${days} days ago`;
};
const scheduleText = (item) => {
  if (item.starts_at && item.state === "scheduled") return `Starts ${formatDateTime(item.starts_at)}`;
  if (item.ends_at) return `${item.state === "ended" ? "Ended" : "Until"} ${formatDateTime(item.ends_at)}`;
  return "No end date";
};
const describeLink = (item) => {
  const find = (list) => (linkOptions.value[list] ?? []).find((o) => o.value === item.link_value)?.label ?? item.link_value;
  return (
    {
      products: "All products",
      category: `Category: ${find("categories")}`,
      occasion: `Occasion: ${find("occasions")}`,
      store: `Store: ${find("stores")}`,
      article: `Article: ${find("articles")}`,
      url: item.link_value,
    }[item.link_type] ?? "—"
  );
};

onMounted(() => {
  fetchOverview();
  fetchLinkOptions();
  if (route.query.tab === "settings") return router.replace({ path: "/admin/settings", query: { tab: "website" } });
  if (route.query.tab === "taxonomies") return router.replace("/admin/categories");
  loadTab(tab.value);
});
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
}
.page-body {
  padding: 18px 28px 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Header */
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 20px 28px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  position: sticky;
  top: 0;
  z-index: 100;
}
.header-left,
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
}
.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 2px;
}
.tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid #e9edf3;
  background: #fff;
  color: #475569;
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
}
.tab.active {
  background: #ede9fe;
  border-color: #c7c2fb;
  color: #4c3fd0;
}
.tab-icon {
  display: inline-flex;
}
.tab-badge {
  font-size: 10.5px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 10px;
  background: #dcfce7;
  color: #15803d;
}

/* Buttons */
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  text-decoration: none;
}
.ghost-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
}
.ghost-btn:hover:not(:disabled) {
  border-color: #c7c2fb;
  color: #5b4fc9;
}
.ghost-btn.danger-text {
  color: #b91c1c;
}
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}
.primary-btn:hover:not(:disabled) {
  background: #5b4fc9;
}
.primary-btn.danger {
  background: #dc2626;
}
.ghost-btn:disabled,
.primary-btn:disabled,
.mini-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.mini-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  white-space: nowrap;
}
.mini-btn:hover:not(:disabled) {
  border-color: #c7c2fb;
  color: #5b4fc9;
}
.mini-btn.danger { color: #b91c1c; }
.mini-btn.danger:hover:not(:disabled) { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
.mini-btn.ok { color: #15803d; }
.mini-btn.ok:hover:not(:disabled) { background: #16a34a; border-color: #16a34a; color: #fff; }
.link-btn {
  border: none;
  background: none;
  color: #6d5ff0;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
  text-decoration: none;
}
.icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* Panels */
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
}
.panel-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 3px 0 0;
  max-width: 640px;
}
.split {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.split.even {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.count-pill {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
  background: #f5f3ff;
  color: #5b4fc9;
}
.empty {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 32px 0;
  margin: 0;
}
.empty.small {
  padding: 18px 0;
}

/* Skeleton */
.sk {
  border-radius: 10px;
  background: linear-gradient(100deg, #eef1f6 30%, #f8fafc 50%, #eef1f6 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sk-row {
  height: 64px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Lists */
.media-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.media-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #eef1f6;
  border-radius: 12px;
  background: #fbfcfe;
}
.media-list li.off {
  opacity: 0.65;
}
.media-list li.clickable {
  cursor: pointer;
}
.media-list li.clickable:hover,
.media-list li.clickable:focus-visible {
  border-color: #c7c2fb;
  background: #fafaff;
  outline: none;
}
.media-list.compact li {
  padding: 8px 10px;
}
.thumb {
  width: 56px;
  height: 56px;
  border-radius: 10px;
  object-fit: cover;
  flex-shrink: 0;
  background: #f1f5f9;
}
.thumb.wide {
  width: 104px;
}
.thumb.placeholder {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #c084fc;
}
.media-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.media-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  text-align: right;
}
.row-actions {
  display: flex;
  gap: 6px;
}
.strong {
  font-size: 13.5px;
  font-weight: 600;
  color: #1e2739;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sub {
  font-size: 11.5px;
  color: #94a3b8;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sub.warn {
  color: #b45309;
}
.sub.note {
  margin-top: 14px;
  display: block;
}
.star {
  color: #f59e0b;
  margin-left: 6px;
}
.code {
  align-self: flex-start;
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 11px;
  font-weight: 600;
  padding: 1px 7px;
  border-radius: 6px;
  border: 1px dashed #c084fc;
  color: #7c3aed;
}
.reorder {
  display: flex;
  flex-direction: column;
}
.reorder button {
  border: none;
  background: none;
  font-size: 8px;
  padding: 2px 3px;
  color: #94a3b8;
  cursor: pointer;
}
.reorder button:disabled {
  opacity: 0.3;
  cursor: default;
}
.pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  background: #f1f5f9;
  color: #64748b;
}
.state-showing,
.article-published { background: #dcfce7; color: #15803d; }
.state-scheduled,
.article-scheduled { background: #e0f2fe; color: #0369a1; }
.state-ended,
.state-off,
.article-draft { background: #f1f5f9; color: #64748b; }
.tone-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  flex-shrink: 0;
}
.tone-brand { background: #1b1612; color: #fff; }
.tone-info { background: #dbeafe; color: #1e3a8a; }
.tone-success { background: #dcfce7; color: #166534; }
.tone-warning { background: #fef3c7; color: #92400e; }

.seg {
  display: inline-flex;
  padding: 3px;
  background: #f1f5f9;
  border-radius: 9px;
  gap: 2px;
  margin-bottom: 12px;
}
.seg.wide {
  display: flex;
}
.seg.wide .seg-btn {
  flex: 1;
}
.seg.small {
  margin: 0;
}
.seg-btn {
  border: none;
  background: transparent;
  padding: 6px 12px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
}
.seg-btn.active {
  background: #fff;
  color: #5b4fc9;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

/* Taxonomy preview */
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.chip {
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 20px;
  background: #f5f3ff;
  color: #5b4fc9;
}

/* Forms */
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}
.field.full {
  grid-column: 1 / -1;
}
.field-label {
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}
.input {
  padding: 9px 11px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 13px;
  color: #1e2739;
  background: #fff;
  font-family: inherit;
  width: 100%;
}
.input:focus {
  outline: none;
  border-color: #7c6ff7;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.input.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  text-transform: uppercase;
}
textarea.input {
  resize: vertical;
}
.hint {
  font-weight: 400;
  color: #94a3b8;
  font-size: 11.5px;
}
.hint code {
  background: #f1f5f9;
  padding: 0 4px;
  border-radius: 4px;
  font-size: 11px;
}
.form-error {
  color: #b91c1c;
  font-size: 12.5px;
  margin: 10px 0 0;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
  margin: 12px 0;
}
.search-ico {
  position: absolute;
  left: 11px;
  color: #94a3b8;
}
.search-input {
  padding-left: 32px;
  background: #f8fafc;
}
.two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  align-items: end;
}
.check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #334155;
  cursor: pointer;
  padding-bottom: 9px;
}
.check input {
  accent-color: #7c6ff7;
}
.tones {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.tone-btn {
  border: 2px solid transparent;
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.tone-btn.active {
  border-color: #7c6ff7;
}

/* Footer preview */

/* Drawer */
.drawer-backdrop,
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.38);
  backdrop-filter: blur(2px);
  z-index: 300;
}
.dialog-backdrop {
  z-index: 400;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(640px, 100vw);
  background: #f8fafc;
  z-index: 301;
  display: flex;
  flex-direction: column;
  box-shadow: -20px 0 50px rgba(15, 23, 42, 0.18);
}
.drawer-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
}
.drawer-head h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 600;
  color: #1e2739;
}
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 18px 20px 28px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.drawer-foot {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  background: #fff;
  border-top: 1px solid #e9edf3;
}
.spacer {
  flex: 1;
}
.image-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.image-drop {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 150px;
  border: 1.5px dashed #c7c2fb;
  border-radius: 12px;
  background: #faf9ff;
  color: #6d5ff0;
  font-size: 12.5px;
  text-align: center;
  padding: 14px;
  cursor: pointer;
  overflow: hidden;
}
.image-drop.filled {
  padding: 0;
  border-style: solid;
}
.image-drop img {
  width: 100%;
  max-height: 260px;
  object-fit: cover;
  display: block;
}
.image-drop input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.schedule {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid #eef1f6;
}
.body-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.body-input {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 12.5px;
  line-height: 1.6;
}
.body-preview {
  min-height: 300px;
  padding: 16px 18px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  background: #fff;
  font-family: Georgia, serif;
  font-size: 15px;
  line-height: 1.7;
  color: #1e2739;
  font-weight: 400;
}
.body-preview h2 {
  font-size: 22px;
  margin: 16px 0 8px;
}
.body-preview h3 {
  font-size: 17px;
  margin: 14px 0 6px;
}
.body-preview blockquote {
  margin: 12px 0;
  padding-left: 14px;
  border-left: 3px solid #b86558;
  font-style: italic;
}
.publish-box {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px 14px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #e9edf3;
}
.publish-box > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.small-field {
  min-width: 220px;
}

/* Dialog */
.dialog {
  width: min(420px, 100%);
  background: #fff;
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
}
.dialog h3 {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 600;
  color: #1e2739;
}
.dialog p {
  margin: 0;
  font-size: 13px;
  color: #475569;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 18px;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.25s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
}
@media (max-width: 1100px) {
  .split,
  .split.even {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .mobile-toggle {
    display: flex;
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 14px 16px 32px;
  }
  .form-grid,
  .two {
    grid-template-columns: minmax(0, 1fr);
  }
  .media-list li {
    flex-wrap: wrap;
  }
  .media-side {
    align-items: flex-start;
    text-align: left;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
  .slide-enter-active,
  .slide-leave-active,
  .fade-enter-active,
  .fade-leave-active {
    transition: none;
  }
}
</style>
