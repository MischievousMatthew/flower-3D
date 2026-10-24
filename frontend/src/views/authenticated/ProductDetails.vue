<template>
  <div class="pd-page bc-theme">
    <NavHeader ref="navHeaderRef" :cartCount="cartCount" :isAuthenticated="isAuthenticated" />

    <main class="bc-container pd-main">
      <!-- Loading -->
      <div v-if="loading" class="pd-top" aria-busy="true" aria-label="Loading product">
        <div class="pd-gallery">
          <div class="pd-thumbs"><span v-for="n in 4" :key="n" class="bc-skeleton pd-thumb-skel"></span></div>
          <span class="bc-skeleton pd-stage-skel"></span>
        </div>
        <div class="pd-info">
          <span class="bc-skeleton pd-line w30"></span>
          <span class="bc-skeleton pd-line big"></span>
          <span class="bc-skeleton pd-line w70"></span>
          <span class="bc-skeleton pd-line w40"></span>
        </div>
      </div>

      <div v-else-if="!product" class="pd-missing">
        <h1>This product isn't available</h1>
        <p>It may have been removed or is no longer listed.</p>
        <router-link :to="{ name: 'Shop' }" class="pd-btn pd-btn--solid">Back to the shop</router-link>
      </div>

      <template v-else>
        <nav class="pd-crumbs" aria-label="Breadcrumb">
          <router-link to="/">Home</router-link>
          <span aria-hidden="true">›</span>
          <router-link :to="{ name: 'Shop' }">Shop</router-link>
          <template v-if="categoryLabel">
            <span aria-hidden="true">›</span>
            <router-link :to="{ name: 'Shop', query: { category: product.category } }">{{ categoryLabel }}</router-link>
          </template>
          <span aria-hidden="true">›</span>
          <span aria-current="page">{{ product.product_name }}</span>
        </nav>

        <section class="pd-top">
          <!-- Gallery -->
          <div class="pd-gallery">
            <div v-if="images.length > 1 || hasModel" class="pd-thumbs" role="tablist" aria-label="Product photos">
              <button
                v-for="(image, i) in images"
                :key="image.id || i"
                type="button"
                role="tab"
                class="pd-thumb"
                :class="{ on: !show3D && active === i }"
                :aria-selected="!show3D && active === i"
                :aria-label="`Photo ${i + 1}`"
                @click="showImage(i)"
              >
                <img :src="image.image_url" alt="" loading="lazy" />
              </button>
              <button
                v-if="hasModel"
                type="button"
                role="tab"
                class="pd-thumb pd-thumb--3d"
                :class="{ on: show3D }"
                :aria-selected="show3D"
                aria-label="3D view"
                @click="show3D = true"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9z" /><path d="M4 7.5 12 12l8-4.5M12 12v9" /></svg>
                <span>3D</span>
              </button>
            </div>

            <div class="pd-stage">
              <ModelViewer3D
                v-if="show3D && hasModel"
                :modelUrl="product.models[0].model_url"
                :modelType="product.models[0].model_type"
                class="pd-model"
              />
              <img v-else-if="images.length" ref="imageEl" :src="images[active].image_url" :alt="product.product_name" class="pd-photo" />
              <div v-else class="pd-noimg" aria-hidden="true">✿</div>

              <template v-if="!show3D && images.length > 1">
                <button type="button" class="pd-arrow prev" aria-label="Previous photo" @click="showImage(active - 1)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                </button>
                <button type="button" class="pd-arrow next" aria-label="Next photo" @click="showImage(active + 1)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                </button>
              </template>

              <button v-if="hasModel" type="button" class="pd-3d" :aria-pressed="show3D" @click="show3D = !show3D">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9z" /><path d="M4 7.5 12 12l8-4.5M12 12v9" /></svg>
                {{ show3D ? "Photos" : "3D View" }}
              </button>
            </div>

            <div v-if="!show3D && images.length > 1" class="pd-dots" aria-hidden="true">
              <span v-for="(image, i) in images" :key="i" :class="{ on: active === i }"></span>
            </div>
          </div>

          <!-- Details and buying -->
          <div class="pd-info">
            <p class="pd-label">{{ categoryLabel || "Flowers" }}</p>
            <h1 class="pd-name">{{ product.product_name }}</h1>
            <p v-if="product.description" class="pd-lede">{{ shortDescription }}</p>

            <p class="pd-rating">
              <template v-if="Number(product.total_reviews)">
                <span class="pd-stars" :style="{ '--rating': Number(product.average_rating || 0) }" aria-hidden="true">★★★★★</span>
                <strong>{{ Number(product.average_rating || 0).toFixed(1) }}</strong>
                <a href="#pd-tabs" @click.prevent="openTab('reviews')">({{ product.total_reviews }} {{ Number(product.total_reviews) === 1 ? "review" : "reviews" }})</a>
              </template>
              <span v-else class="pd-new">No reviews yet</span>
              <span v-if="Number(product.sold_count)" class="pd-sold">· {{ product.sold_count }} sold</span>
            </p>

            <div class="pd-price">
              <strong>{{ peso(price) }}</strong>
              <template v-if="onSale">
                <s>{{ peso(product.selling_price) }}</s>
                <span class="pd-save">Save {{ savePercent }}%</span>
              </template>
            </div>

            <p class="pd-shop">
              Sold by
              <button type="button" @click="goToStore">{{ product.vendor_name || "a BloomCraft shop" }}</button>
            </p>

            <div class="pd-qty-row">
              <span class="pd-qty-label">Quantity</span>
              <div class="pd-qty" role="group" aria-label="Quantity">
                <button type="button" aria-label="Fewer" :disabled="qty <= 1" @click="qty--">−</button>
                <input
                  type="number"
                  inputmode="numeric"
                  min="1"
                  :max="maxQty"
                  step="1"
                  aria-label="Quantity"
                  :value="qtyDraft ?? qty"
                  :disabled="soldOut"
                  @focus="$event.target.select()"
                  @input="qtyDraft = $event.target.value"
                  @keydown.enter.prevent="commitQty($event)"
                  @blur="commitQty($event)"
                />
                <button type="button" aria-label="More" :disabled="qty >= maxQty" @click="qty++">+</button>
              </div>
              <span class="pd-stock" :class="{ out: soldOut }">{{ soldOut ? "Sold out" : `${product.quantity_in_stock} in stock` }}</span>
            </div>

            <div class="pd-buy">
              <button type="button" class="pd-btn pd-btn--solid" :disabled="soldOut || busy" @click="addToCart">
                {{ busy === "cart" ? "Adding…" : "Add to Cart" }}
              </button>
              <button type="button" class="pd-btn pd-btn--ghost" :disabled="soldOut || busy" @click="buyNow">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z" /></svg>
                Buy Now
              </button>
              <button
                type="button"
                class="pd-heart"
                :class="{ on: isFavorite }"
                :aria-pressed="isFavorite"
                :aria-label="isFavorite ? 'Remove from favorites' : 'Add to favorites'"
                :title="isFavorite ? 'In your favorites' : 'Add to favorites'"
                :disabled="favoriteBusy"
                @click="toggleFavorite"
              >
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 7.9 3.6 4.5 7 4.5c2 0 3.6 1.1 5 3 1.4-1.9 3-3 5-3 3.4 0 5.6 3.4 4.3 6.8-1.8 4.6-9.3 9.2-9.3 9.2z" /></svg>
              </button>
            </div>

            <ul class="pd-promises">
              <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7" /><circle cx="7" cy="18" r="1.6" /><circle cx="17" cy="18" r="1.6" /></svg>
                <span><strong>Fresh flowers</strong><small>Daily delivery</small></span>
              </li>
              <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3zM3 10h18M7 15h3" /></svg>
                <span><strong>Secure payment</strong><small>PayMongo &amp; COD</small></span>
              </li>
              <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.4-5.7L4 8.5" /><path d="M4 4v4.5h4.5" /></svg>
                <span><strong>Easy returns</strong><small>Hassle-free process</small></span>
              </li>
            </ul>
          </div>
        </section>

        <!-- Promise strip -->
        <ul class="pd-strip" aria-label="Our promise">
          <li>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 1 1 0 5zM16.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 1 1-5 0zM12 16.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5zM7.5 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z" /><circle cx="12" cy="12" r="2" /></svg>
            <span><strong>Fresh &amp; premium flowers</strong><small>Handpicked daily for the best quality.</small></span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7" /><circle cx="7" cy="18" r="1.6" /><circle cx="17" cy="18" r="1.6" /></svg>
            <span><strong>Same-day delivery</strong><small>Available in select areas.</small></span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 3 8.2 7 10 4-1.8 7-5.5 7-10V6z" /><path d="m9 12 2 2 4-4" /></svg>
            <span><strong>Support local florists</strong><small>Empowering small businesses.</small></span>
          </li>
        </ul>

        <!-- Tabs -->
        <section id="pd-tabs" class="pd-tabs">
          <div class="pd-tablist" role="tablist" aria-label="Product information">
            <button
              v-for="tab in TABS"
              :key="tab.key"
              type="button"
              role="tab"
              :id="`pd-tab-${tab.key}`"
              :aria-selected="activeTab === tab.key"
              :aria-controls="`pd-panel-${tab.key}`"
              :class="{ on: activeTab === tab.key }"
              @click="activeTab = tab.key"
            >
              {{ tab.label }}<template v-if="tab.key === 'reviews' && Number(product.total_reviews)"> ({{ product.total_reviews }})</template>
            </button>
          </div>

          <div v-show="activeTab === 'description'" id="pd-panel-description" role="tabpanel" aria-labelledby="pd-tab-description" class="pd-panel pd-desc">
            <div>
              <h2>About {{ product.product_name }}</h2>
              <p class="pd-desc-text">{{ product.description || "Fresh flowers, prepared by a local florist." }}</p>
              <ul class="pd-feats">
                <li>
                  <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 7.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 1 1 0 5zM16.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 1 1-5 0zM12 16.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5zM7.5 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z" /></svg></span>
                  <strong>Fresh {{ (categoryLabel || "flowers").toLowerCase() }}</strong><small>Long-lasting blooms</small>
                </li>
                <li>
                  <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 8h14l-2 12H7z" /><path d="M9 8a3 3 0 0 1 6 0" /></svg></span>
                  <strong>Carefully wrapped</strong><small>Ready to give</small>
                </li>
                <li>
                  <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" /></svg></span>
                  <strong>Handcrafted</strong><small>By skilled florists</small>
                </li>
              </ul>
            </div>
            <figure class="pd-quote">
              <img v-if="images.length" :src="images[images.length > 1 ? 1 : 0].image_url" alt="" loading="lazy" />
              <blockquote>Because every moment deserves flowers.</blockquote>
            </figure>
          </div>

          <div v-show="activeTab === 'details'" id="pd-panel-details" role="tabpanel" aria-labelledby="pd-tab-details" class="pd-panel">
            <dl class="pd-specs">
              <div v-for="row in specs" :key="row.label"><dt>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
            </dl>
          </div>

          <div v-show="activeTab === 'care'" id="pd-panel-care" role="tabpanel" aria-labelledby="pd-tab-care" class="pd-panel">
            <p v-if="product.care_instructions" class="pd-desc-text pd-care">{{ product.care_instructions }}</p>
            <ul v-else class="pd-care-list">
              <li>Trim the stems at an angle and place them in clean, cool water as soon as they arrive.</li>
              <li>Change the water every two days and trim the stems again.</li>
              <li>Keep them away from direct sunlight, heat and ripening fruit.</li>
            </ul>
          </div>

          <div v-if="tabsSeen.reviews" v-show="activeTab === 'reviews'" id="pd-panel-reviews" role="tabpanel" aria-labelledby="pd-tab-reviews" class="pd-panel">
            <ProductReviews :product-id="product.id" @updated="refreshStats" />
            <div class="pd-questions">
              <ProductQuestions :product-id="product.id" />
            </div>
          </div>
        </section>

        <!-- You may also like -->
        <section v-if="related.length" class="pd-related" aria-labelledby="pd-related-title">
          <div class="pd-related-head">
            <h2 id="pd-related-title">You May Also Like</h2>
            <router-link :to="{ name: 'Shop', query: { section: 'products' } }" class="pd-viewall">View all <span aria-hidden="true">→</span></router-link>
          </div>
          <div class="pd-related-grid">
            <ShopProductTile
              v-for="item in related"
              :key="item.id"
              :product="item"
              :adding="addingRelatedId === item.id"
              @open="openProduct"
              @add="addRelated"
            />
          </div>
        </section>
      </template>
    </main>

    <SiteFooter />
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import ModelViewer3D from "../../layouts/3D/3DModelViewer.vue";
import ProductReviews from "../../layouts/components/ProductReviews.vue";
import ProductQuestions from "../../layouts/components/ProductQuestions.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import ShopProductTile from "../../components/shop/ShopProductTile.vue";
import { useAuth } from "../../composables/useAuth";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { useSiteContent } from "../../composables/useSiteContent";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import "../../styles/bloomcraft.css";

const TABS = [
  { key: "description", label: "Description" },
  { key: "details", label: "Details" },
  { key: "care", label: "Care Instructions" },
  { key: "reviews", label: "Reviews" },
];

const route = useRoute();
const router = useRouter();
const { isAuthenticated, user } = useAuth();
const cartStore = useCart();
const { flyToCart } = useFlyToCart();
const { site } = useSiteContent();

const navHeaderRef = ref(null);
const imageEl = ref(null);
const product = ref(null);
const loading = ref(true);
const related = ref([]);
const active = ref(0);
const show3D = ref(false);
const qty = ref(1);
// What's being typed in the quantity box; applied on Enter or leaving the box
const qtyDraft = ref(null);
const busy = ref(null); // "cart" | "buy"
const addingRelatedId = ref(null);
const activeTab = ref("description");
// Favorites (customer accounts): the heart beside Buy Now
const isFavorite = ref(false);
const favoriteBusy = ref(false);
// Reviews load the first time their tab is opened
const tabsSeen = reactive({ reviews: false });
watch(activeTab, (tab) => {
  if (tab === "reviews") tabsSeen.reviews = true;
});

const cartCount = computed(() => cartStore.count?.value ?? 0);
const images = computed(() => {
  const list = product.value?.images?.length ? product.value.images : product.value?.primary_image ? [product.value.primary_image] : [];
  return [...list].sort((a, b) => Number(b.is_primary) - Number(a.is_primary));
});
const hasModel = computed(() => !!product.value?.models?.length);
const onSale = computed(
  () => product.value?.has_discount && Number(product.value.discount_price) > 0 && Number(product.value.discount_price) < Number(product.value.selling_price),
);
const price = computed(() => (onSale.value ? Number(product.value.discount_price) : Number(product.value?.selling_price || 0)));
const savePercent = computed(() => Math.round((1 - price.value / Number(product.value.selling_price)) * 100));
const soldOut = computed(() => Number(product.value?.quantity_in_stock || 0) <= 0);
const maxQty = computed(() => Math.max(1, Number(product.value?.quantity_in_stock || 1)));
const categoryLabel = computed(() => {
  const value = product.value?.category;
  if (!value) return "";
  return site.categories.find((c) => c.value === value)?.label || value.replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
});
// The first sentence or two, for under the name; the full text is in the Description tab
const shortDescription = computed(() => {
  const text = String(product.value?.description || "").trim();
  return text.length > 180 ? `${text.slice(0, 177).trimEnd()}…` : text;
});
const titleCase = (s) => String(s || "").replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
const specs = computed(() => {
  const p = product.value;
  return [
    { label: "Category", value: categoryLabel.value || "—" },
    { label: "Color", value: titleCase(p.color) || "—" },
    { label: "Season", value: p.season === "all-year" ? "All year" : titleCase(p.season) || "—" },
    { label: "Occasions", value: (p.occasion_tags || []).join(", ") || "—" },
    { label: "Shop", value: p.vendor_name || "—" },
    { label: "Availability", value: soldOut.value ? "Sold out" : `${p.quantity_in_stock} in stock` },
  ];
});

const peso = (value) => `₱${Number(value || 0).toLocaleString("en-PH", { maximumFractionDigits: 2 })}`;

// Typed quantities are whole numbers from 1 up to what's in stock
function commitQty(event) {
  if (qtyDraft.value === null) return;
  const typed = Math.floor(Number(qtyDraft.value));
  qtyDraft.value = null;
  if (Number.isFinite(typed) && typed > 0) {
    if (typed > maxQty.value) toast.info(`Only ${maxQty.value} in stock.`);
    qty.value = Math.min(typed, maxQty.value);
  }
  if (event.type === "keydown") event.target.blur();
}

function showImage(i) {
  show3D.value = false;
  const n = images.value.length;
  active.value = ((i % n) + n) % n;
}

function openTab(key) {
  activeTab.value = key;
  document.getElementById("pd-tabs")?.scrollIntoView({ behavior: "smooth" });
}

async function loadFavorite(id) {
  isFavorite.value = false;
  if (user.value?.role !== "customer") return;
  try {
    const { data } = await api.get("/customer/favorites");
    if (String(route.params.id) === String(id)) isFavorite.value = (data?.data ?? []).some((f) => String(f.product_id) === String(id));
  } catch {
    // The heart just starts empty
  }
}

async function toggleFavorite() {
  if (!isAuthenticated.value) {
    toast.info("Sign in to save favorites.");
    return;
  }
  if (user.value?.role !== "customer") {
    toast.info("Favorites are for customer accounts.");
    return;
  }
  const id = product.value?.id;
  if (!id) return;
  favoriteBusy.value = true;
  const adding = !isFavorite.value;
  try {
    await (adding ? api.post(`/customer/favorites/${id}`) : api.delete(`/customer/favorites/${id}`));
    isFavorite.value = adding;
    toast.success(adding ? "Added to your favorites." : "Removed from your favorites.");
  } catch (e) {
    toast.error(e?.response?.data?.message || "Couldn't update your favorites.");
  } finally {
    favoriteBusy.value = false;
  }
}

async function load(id) {
  loading.value = true;
  product.value = null;
  related.value = [];
  active.value = 0;
  show3D.value = false;
  qty.value = 1;
  activeTab.value = "description";
  tabsSeen.reviews = false;
  try {
    const { data } = await api.get(`/customer/products/${id}`);
    product.value = data?.success ? data.data : null;
  } catch {
    product.value = null;
  } finally {
    loading.value = false;
  }
  loadFavorite(id);
  if (product.value) {
    document.title = `${product.value.product_name} · BloomCraft`;
    loadRelated();
  }
}

// Same category first, topped up with the newest products
async function loadRelated() {
  const id = product.value.id;
  const pick = (list) => (Array.isArray(list) ? list : []).filter((p) => p.id !== id);
  const fetchList = async (params) => {
    try {
      const { data } = await api.get("customer/products", { params: { page: 1, per_page: 8, in_stock_only: 1, ...params } });
      return pick(data?.data?.data);
    } catch {
      return [];
    }
  };
  let list = product.value.category ? await fetchList({ category: product.value.category, sort_by: "popular_desc" }) : [];
  if (list.length < 5) {
    const more = await fetchList({ sort_by: "created_at_desc" });
    list = [...list, ...more.filter((p) => !list.some((q) => q.id === p.id))];
  }
  related.value = list.slice(0, 5);
}

async function refreshStats() {
  try {
    const { data } = await api.get(`/customer/products/${product.value.id}`);
    if (data?.success) {
      product.value.average_rating = data.data.average_rating;
      product.value.total_reviews = data.data.total_reviews;
    }
  } catch {
    // keep the numbers shown
  }
}

function needLogin() {
  if (isAuthenticated.value) return false;
  toast.info("Log in to add flowers to your cart.");
  router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
  return true;
}

async function addItem(item, quantity, sourceEl) {
  const result = await cartStore.addToCart({
    product_id: item.id,
    quantity,
    color: item.color || null,
    size: item.size || null,
    customizations: {},
  });
  if (!result?.success) throw new Error(result?.message || "Failed");
  await flyToCart(sourceEl);
  navHeaderRef.value?.triggerCartPulse?.();
  toast.success(`${item.product_name} added to cart!`, { autoClose: 2000 });
}

async function addToCart() {
  if (needLogin()) return;
  busy.value = "cart";
  try {
    await addItem(product.value, qty.value, imageEl.value);
  } catch {
    toast.error("Couldn't add it to your cart. Please try again.");
  } finally {
    busy.value = null;
  }
}

async function addRelated(item, ctx = {}) {
  if (needLogin()) return;
  addingRelatedId.value = item.id;
  try {
    await addItem(item, 1, ctx.imageEl);
  } catch {
    toast.error("Couldn't add it to your cart. Please try again.");
  } finally {
    addingRelatedId.value = null;
  }
}

function buyNow() {
  if (needLogin()) return;
  busy.value = "buy";
  const p = product.value;
  sessionStorage.setItem(
    "directCheckout",
    JSON.stringify({
      items: [{ product_id: p.id, quantity: qty.value, product_name: p.product_name, price: p.selling_price, image: images.value[0]?.image_url || null }],
      total: parseFloat(p.selling_price) * qty.value,
      isDirectCheckout: true,
    }),
  );
  router.push({ path: "/customer/checkout", query: { direct: "true", product_id: p.id, quantity: qty.value } });
}

function goToStore() {
  if (!product.value?.vendor_id) return;
  router.push({ name: "VendorStorefront", state: { storeId: String(product.value.vendor_id) } });
}

function openProduct(item) {
  router.push({ name: "ProductDetails", params: { id: String(item.id) } });
}

watch(
  () => route.params.id,
  (id) => {
    if (!id) return;
    window.scrollTo({ top: 0 });
    load(id);
  },
  { immediate: true },
);
</script>

<style scoped>
.pd-page,
.pd-page * {
  box-sizing: border-box;
}

:global(body) {
  margin: 0;
}

.pd-page {
  min-height: 100vh;
  overflow-x: hidden;
  background: var(--ivory);
  font-family: var(--font-sans);
  color: var(--ink);
}

.pd-page button {
  font-family: var(--font-sans);
}

.pd-main {
  padding-top: 118px;
  padding-bottom: clamp(40px, 6vw, 72px);
}

/* Breadcrumb */
.pd-crumbs {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 22px;
  font-size: 12.5px;
  color: var(--ink-soft);
}

.pd-crumbs a {
  color: var(--leaf-deep);
  text-decoration: none;
}

.pd-crumbs a:hover {
  text-decoration: underline;
}

.pd-crumbs [aria-current] {
  color: var(--ink-soft);
}

/* Top: gallery + info */
.pd-top {
  display: grid;
  grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr);
  gap: clamp(28px, 4vw, 52px);
  align-items: start;
}

.pd-gallery {
  display: grid;
  grid-template-columns: 84px minmax(0, 1fr);
  grid-template-rows: auto auto;
  gap: 14px;
}

.pd-thumbs {
  display: grid;
  align-content: start;
  gap: 10px;
}

.pd-thumb {
  display: grid;
  place-items: center;
  width: 84px;
  height: 84px;
  padding: 0;
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: 10px;
  background: var(--paper);
  cursor: pointer;
}

.pd-thumb.on {
  border-color: var(--leaf);
}

.pd-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pd-thumb--3d {
  gap: 2px;
  border-color: var(--line);
  color: var(--leaf-deep);
  font-size: 11px;
  font-weight: 600;
}

.pd-thumb--3d svg {
  width: 26px;
  height: 26px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linejoin: round;
}

.pd-stage {
  position: relative;
  grid-column: 2;
  aspect-ratio: 1 / 1;
  overflow: hidden;
  border-radius: 16px;
  background: var(--ivory-deep);
}

.pd-gallery:not(:has(.pd-thumbs)) .pd-stage {
  grid-column: 1 / -1;
}

.pd-photo,
.pd-model {
  width: 100%;
  height: 100%;
}

.pd-photo {
  object-fit: cover;
}

.pd-noimg {
  display: grid;
  place-items: center;
  height: 100%;
  font-size: 60px;
  color: var(--leaf);
}

.pd-arrow {
  position: absolute;
  top: 50%;
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgba(250, 251, 250, 0.92);
  color: var(--ink);
  cursor: pointer;
  transform: translateY(-50%);
  box-shadow: 0 8px 20px -12px rgba(54, 63, 78, 0.4);
}

.pd-arrow svg,
.pd-3d svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pd-arrow.prev {
  left: 14px;
}

.pd-arrow.next {
  right: 14px;
}

.pd-3d {
  position: absolute;
  right: 14px;
  bottom: 14px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 16px;
  border: 0;
  border-radius: 999px;
  background: rgba(250, 251, 250, 0.95);
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  box-shadow: 0 8px 20px -12px rgba(54, 63, 78, 0.4);
}

.pd-dots {
  display: flex;
  grid-column: 2;
  justify-content: center;
  gap: 6px;
}

.pd-dots span {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--line);
  transition: width 0.3s;
}

.pd-dots span.on {
  width: 22px;
  background: var(--leaf);
}

.pd-info {
  display: grid;
  gap: 14px;
}

.pd-label {
  margin: 0;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.pd-name {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(34px, 3.6vw, 48px);
  font-weight: 500;
  line-height: 1.08;
}

.pd-lede {
  margin: 0;
  max-width: 520px;
  font-size: 14.5px;
  line-height: 1.65;
  color: var(--ink-soft);
}

.pd-rating {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 13px;
}

.pd-stars {
  --percent: calc(var(--rating) / 5 * 100%);
  font-size: 16px;
  letter-spacing: 2px;
  background: linear-gradient(90deg, #e2a23b var(--percent), #e6ddd2 var(--percent));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.pd-rating a {
  color: var(--ink-soft);
}

.pd-new,
.pd-sold {
  color: var(--ink-soft);
}

.pd-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 12px;
}

.pd-price strong {
  font-family: var(--font-display);
  font-size: 38px;
  font-weight: 600;
  line-height: 1;
  color: var(--leaf-deep);
}

.pd-price s {
  font-size: 16px;
  color: var(--ink-soft);
}

.pd-save {
  padding: 3px 10px;
  border-radius: 6px;
  background: rgba(77, 177, 115, 0.14);
  font-size: 12.5px;
  font-weight: 600;
  color: var(--leaf-deep);
}

.pd-shop {
  margin: 0;
  font-size: 13px;
  color: var(--ink-soft);
}

.pd-shop button {
  padding: 0;
  border: 0;
  border-bottom: 1px solid currentColor;
  background: none;
  font-size: 13px;
  color: var(--leaf-deep);
  cursor: pointer;
}

.pd-qty-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
  margin-top: 4px;
}

.pd-qty-label {
  font-size: 13.5px;
}

.pd-qty {
  display: inline-flex;
  align-items: center;
  height: 42px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
}

.pd-qty button {
  width: 42px;
  height: 40px;
  padding: 0;
  border: 0;
  background: none;
  font-size: 18px;
  color: var(--ink);
  cursor: pointer;
}

.pd-qty button:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.pd-qty input {
  width: 52px;
  height: 32px;
  padding: 0;
  border: 0;
  border-radius: 6px;
  background: transparent;
  font: 500 14px var(--font-sans);
  color: var(--ink);
  text-align: center;
  appearance: textfield;
  -moz-appearance: textfield;
}

.pd-qty input::-webkit-outer-spin-button,
.pd-qty input::-webkit-inner-spin-button {
  margin: 0;
  appearance: none;
}

.pd-qty input:focus {
  background: var(--ivory);
  outline: 1px solid var(--leaf);
}

.pd-stock {
  font-size: 12.5px;
  color: #3f7a4b;
}

.pd-stock.out {
  color: var(--leaf-deep);
}

.pd-buy {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 12px;
}

.pd-heart {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--paper);
  color: var(--ink-soft);
  cursor: pointer;
  transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
}

.pd-heart svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linejoin: round;
}

.pd-heart:hover:not(:disabled) {
  border-color: #e0475a;
  color: #e0475a;
}

.pd-heart.on {
  border-color: #f4c2c9;
  background: #fdecee;
  color: #e0475a;
}

.pd-heart.on svg {
  fill: currentColor;
}

.pd-heart:active:not(:disabled) {
  transform: scale(0.92);
}

.pd-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 52px;
  padding: 0 24px;
  border-radius: 999px;
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.pd-btn svg {
  width: 17px;
  height: 17px;
  fill: currentColor;
}

.pd-btn--solid {
  border: 1px solid var(--leaf-deep);
  background: var(--leaf-deep);
  color: var(--paper);
}

.pd-btn--solid:hover:not(:disabled) {
  border-color: var(--ink);
  background: var(--ink);
}

.pd-btn--ghost {
  border: 1px solid var(--leaf-deep);
  background: var(--paper);
  color: var(--leaf-deep);
}

.pd-btn--ghost:hover:not(:disabled) {
  background: #e9f5ed;
}

.pd-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.pd-promises {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
}

.pd-promises li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 12px;
}

.pd-promises li + li {
  border-left: 1px solid var(--line-soft);
}

.pd-promises svg,
.pd-strip svg,
.pd-feats svg {
  flex-shrink: 0;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pd-promises svg {
  width: 22px;
  height: 22px;
}

.pd-promises span,
.pd-strip span {
  display: grid;
  gap: 2px;
}

.pd-promises strong,
.pd-strip strong {
  font-size: 12.5px;
  font-weight: 500;
}

.pd-promises small,
.pd-strip small {
  font-size: 11.5px;
  color: var(--ink-soft);
}

/* Promise strip */
.pd-strip {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: clamp(32px, 4vw, 48px) 0 0;
  padding: 18px 8px;
  border: 1px solid var(--line-soft);
  border-radius: 14px;
  background: #e9f5ed;
  list-style: none;
}

.pd-strip li {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 6px 26px;
}

.pd-strip li + li {
  border-left: 1px solid rgba(77, 177, 115, 0.2);
}

.pd-strip svg {
  width: 30px;
  height: 30px;
}

/* Tabs */
.pd-tabs {
  margin-top: clamp(32px, 4vw, 48px);
  scroll-margin-top: 110px;
}

.pd-tablist {
  display: flex;
  gap: 28px;
  overflow-x: auto;
  border-bottom: 1px solid var(--line);
  scrollbar-width: none;
}

.pd-tablist button {
  flex-shrink: 0;
  padding: 12px 2px;
  border: 0;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  background: none;
  font-size: 13.5px;
  color: var(--ink-soft);
  cursor: pointer;
}

.pd-tablist button.on {
  border-bottom-color: var(--leaf-deep);
  font-weight: 500;
  color: var(--ink);
}

.pd-panel {
  padding-top: 26px;
}

.pd-desc {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: clamp(24px, 4vw, 48px);
  align-items: start;
}

.pd-desc h2 {
  margin: 0 0 12px;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
}

.pd-desc-text {
  margin: 0;
  font-size: 14.5px;
  line-height: 1.75;
  color: var(--ink-soft);
  white-space: pre-line;
}

.pd-feats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 24px 0 0;
  padding: 0;
  list-style: none;
}

.pd-feats li {
  display: grid;
  grid-template-columns: 44px 1fr;
  grid-template-rows: auto auto;
  column-gap: 10px;
  align-items: center;
  padding: 4px 12px;
}

.pd-feats li + li {
  border-left: 1px solid var(--line-soft);
}

.pd-feats li > span {
  display: grid;
  grid-row: 1 / 3;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.1);
}

.pd-feats svg {
  width: 22px;
  height: 22px;
}

.pd-feats strong {
  align-self: end;
  font-size: 12.5px;
  font-weight: 500;
}

.pd-feats small {
  align-self: start;
  font-size: 11.5px;
  color: var(--ink-soft);
}

.pd-quote {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  min-height: 220px;
  margin: 0;
  overflow: hidden;
  border-radius: 14px;
  background: #e2f1e7;
}

.pd-quote:not(:has(img)) {
  grid-template-columns: 1fr;
}

.pd-quote img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pd-quote blockquote {
  display: grid;
  place-items: center;
  margin: 0;
  padding: 24px;
  font-family: var(--font-display);
  font-size: 26px;
  font-style: italic;
  line-height: 1.3;
  text-align: center;
  color: var(--leaf-deep);
}

.pd-specs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 40px;
  max-width: 760px;
  margin: 0;
}

.pd-specs div {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--line-soft);
  font-size: 13.5px;
}

.pd-specs dt {
  color: var(--ink-soft);
}

.pd-specs dd {
  margin: 0;
}

.pd-care-list {
  display: grid;
  gap: 10px;
  max-width: 640px;
  margin: 0;
  padding-left: 18px;
  font-size: 14px;
  line-height: 1.6;
  color: var(--ink-soft);
}

.pd-questions {
  margin-top: 32px;
}

/* Related */
.pd-related {
  margin-top: clamp(40px, 5vw, 64px);
}

.pd-related-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-bottom: 16px;
}

.pd-related-head h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
}

.pd-viewall {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--leaf-deep);
  text-decoration: none;
}

.pd-related-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 18px;
}

/* Missing / loading */
.pd-missing {
  display: grid;
  justify-items: center;
  gap: 12px;
  padding: 80px 0;
  text-align: center;
}

.pd-missing h1 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 34px;
  font-weight: 500;
}

.pd-missing p {
  margin: 0 0 8px;
  color: var(--ink-soft);
}

.pd-thumb-skel {
  width: 84px;
  height: 84px;
  border-radius: 10px;
}

.pd-stage-skel {
  grid-column: 2;
  aspect-ratio: 1;
  border-radius: 16px;
}

.pd-line {
  display: block;
  height: 14px;
  border-radius: 6px;
}

.pd-line.big {
  height: 42px;
}

.pd-line.w30 {
  width: 30%;
}

.pd-line.w40 {
  width: 40%;
}

.pd-line.w70 {
  width: 70%;
}

.pd-page :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (max-width: 960px) {
  .pd-top,
  .pd-desc {
    grid-template-columns: minmax(0, 1fr);
  }

  .pd-related-grid {
    display: flex;
    overflow-x: auto;
    scrollbar-width: thin;
  }

  .pd-related-grid > * {
    flex: 0 0 200px;
  }
}

@media (max-width: 640px) {
  .pd-main {
    padding-top: 100px;
  }

  .pd-gallery {
    grid-template-columns: minmax(0, 1fr);
  }

  .pd-thumbs {
    display: flex;
    grid-row: 2;
    overflow-x: auto;
  }

  .pd-thumb {
    flex: 0 0 64px;
    width: 64px;
    height: 64px;
  }

  .pd-stage,
  .pd-dots,
  .pd-stage-skel {
    grid-column: 1;
  }

  .pd-promises,
  .pd-strip,
  .pd-feats,
  .pd-specs {
    grid-template-columns: minmax(0, 1fr);
  }

  .pd-promises li + li,
  .pd-strip li + li,
  .pd-feats li + li {
    border-left: 0;
  }

  .pd-strip li {
    padding: 8px 12px;
  }

  .pd-buy {
    grid-template-columns: 1fr auto;
  }

  .pd-buy .pd-btn--solid {
    grid-column: 1 / -1;
  }
}
</style>
