<template>
  <div class="pd-page bc-theme">
    <NavHeader ref="navHeaderRef" :cartCount="cartCount" :isAuthenticated="isAuthenticated" />

    <main class="bc-container pd-main">
      <!-- Loading: the same shapes as the page -->
      <div v-if="loading" class="pd-top" aria-busy="true" aria-label="Loading product">
        <div class="pd-gallery">
          <div class="pd-thumbs"><span v-for="n in 4" :key="n" class="bc-skeleton pd-thumb-skel"></span></div>
          <span class="bc-skeleton pd-stage-skel"></span>
        </div>
        <div class="pd-info pd-info--skel">
          <span class="bc-skeleton pd-line w30"></span>
          <span class="bc-skeleton pd-line big"></span>
          <span class="bc-skeleton pd-line w40"></span>
          <span class="bc-skeleton pd-line w70"></span>
          <span class="bc-skeleton pd-line block"></span>
          <span class="bc-skeleton pd-line btn"></span>
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
          <span aria-hidden="true">/</span>
          <router-link :to="{ name: 'Shop' }">Shop</router-link>
          <template v-if="categoryLabel">
            <span aria-hidden="true">/</span>
            <router-link :to="{ name: 'Shop', query: { category: product.category } }">{{ categoryLabel }}</router-link>
          </template>
          <span aria-hidden="true">/</span>
          <span aria-current="page">{{ product.product_name }}</span>
        </nav>

        <section class="pd-top">
          <!-- ── Gallery ─────────────────────────────────────────────── -->
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
                <img :src="sizedImage(image.image_url, 120)" :srcset="sizedSrcset(image.image_url, 120)" alt="" loading="lazy" />
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

            <div class="pd-stage" :class="{ 'is-3d': show3D }">
              <ModelViewer3D
                v-if="show3D && hasModel"
                :modelUrl="product.models[0].model_url"
                :modelType="product.models[0].model_type"
                class="pd-model"
              />
              <!-- Hover to zoom in on the photo; click for full screen -->
              <button
                v-else-if="images.length"
                type="button"
                class="pd-zoom"
                :class="{ zooming: zoom.on }"
                aria-label="Open the photo full screen"
                @click="openLightbox(active)"
                @mousemove="zoomAt"
                @mouseleave="zoom.on = false"
              >
                <img
                  ref="imageEl"
                  :src="sizedImage(images[active].image_url, 900)"
                  :srcset="sizedSrcset(images[active].image_url, 900)"
                  sizes="(max-width: 900px) 100vw, 640px"
                  :alt="product.product_name"
                  class="pd-photo"
                  :style="zoom.on ? { transformOrigin: `${zoom.x}% ${zoom.y}%` } : null"
                  fetchpriority="high"
                  decoding="async"
                />
              </button>
              <div v-else class="pd-noimg" aria-hidden="true">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" /><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5" /></svg>
              </div>

              <span v-if="onSale" class="pd-badge">−{{ savePercent }}%</span>

              <div class="pd-stage-tools">
                <button v-if="images.length && !show3D" type="button" class="pd-round" aria-label="View full screen" title="Full screen" @click="openLightbox(active)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" /></svg>
                </button>
                <button type="button" class="pd-round" aria-label="Share this product" title="Share" @click="share">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4" /></svg>
                </button>
              </div>

              <template v-if="!show3D && images.length > 1">
                <button type="button" class="pd-arrow prev" aria-label="Previous photo" @click.stop="showImage(active - 1)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                </button>
                <button type="button" class="pd-arrow next" aria-label="Next photo" @click.stop="showImage(active + 1)">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                </button>
                <span class="pd-counter" aria-hidden="true">{{ active + 1 }} / {{ images.length }}</span>
              </template>

              <button v-if="hasModel" type="button" class="pd-3d" :aria-pressed="show3D" @click="show3D = !show3D">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 7.5v9L12 21l8-4.5v-9z" /><path d="M4 7.5 12 12l8-4.5M12 12v9" /></svg>
                {{ show3D ? "Back to photos" : "View in 3D" }}
              </button>
            </div>
            <p v-if="images.length && !show3D" class="pd-zoom-hint">Hover to zoom · click for full screen</p>
          </div>

          <!-- ── Buying panel ────────────────────────────────────────── -->
          <div class="pd-info">
            <div class="pd-eyebrow">
              <button type="button" class="pd-shop" @click="goToStore">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9l2-5h12l2 5M4 9h16v11H4zM9 20v-5h6v5" /></svg>
                {{ product.vendor_name || "BloomCraft shop" }}
              </button>
              <span v-if="categoryLabel" class="pd-cat">{{ categoryLabel }}</span>
            </div>

            <!-- Sale, Best seller, Trending, Top rated… in the admin's order -->
            <div v-if="pageTags.length" class="pd-tags">
              <ProductTag v-for="tag in pageTags" :key="tag" :tag="tag" />
            </div>

            <h1 class="pd-name">{{ product.product_name }}</h1>

            <p class="pd-rating">
              <template v-if="Number(product.total_reviews)">
                <span class="pd-stars" :style="{ '--rating': Number(product.average_rating || 0) }" aria-hidden="true">★★★★★</span>
                <strong>{{ Number(product.average_rating || 0).toFixed(1) }}</strong>
                <a href="#pd-tabs" @click.prevent="openTab('reviews')">{{ product.total_reviews }} {{ Number(product.total_reviews) === 1 ? "review" : "reviews" }}</a>
              </template>
              <span v-else class="pd-muted">No reviews yet</span>
              <template v-if="Number(product.sold_count)">
                <span class="pd-dot" aria-hidden="true"></span>
                <span class="pd-muted">{{ product.sold_count }} sold</span>
              </template>
            </p>

            <div class="pd-price">
              <strong>{{ peso(price) }}</strong>
              <template v-if="onSale">
                <s>{{ peso(product.selling_price) }}</s>
                <span class="pd-save">Save {{ peso(product.selling_price - price) }}</span>
              </template>
            </div>

            <p v-if="product.description" class="pd-lede">{{ shortDescription }}</p>

            <!-- Colours to choose from (when the shop offers more than one) -->
            <div v-if="colorOptions.length" class="pd-section">
              <div class="pd-label-row">
                <h2 id="pd-color-label" class="pd-label">Color<span v-if="chosenColor" class="pd-label-value">{{ chosenColor }}</span></h2>
              </div>
              <div class="pd-colors" role="radiogroup" aria-labelledby="pd-color-label">
                <button
                  v-for="c in colorOptions"
                  :key="c.name"
                  type="button"
                  role="radio"
                  class="pd-color"
                  :class="{ on: chosenColor === c.name }"
                  :aria-checked="chosenColor === c.name"
                  :aria-label="c.name"
                  :title="c.name"
                  @click="chosenColor = c.name"
                >
                  <i :style="{ background: swatchFor(c.name, c.hex) }" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <!-- The flowers in the bouquet, and how many of each -->
            <div v-if="composition.length" class="pd-section">
              <div class="pd-label-row">
                <h2 class="pd-label">What's inside</h2>
                <span class="pd-muted pd-small">{{ totalStems }} {{ totalStems === 1 ? "stem" : "stems" }} in all</span>
              </div>
              <ul class="pd-compo">
                <li v-for="(f, i) in composition" :key="i">
                  <i class="pd-swatch" :style="{ background: swatchFor(f.color || f.flower) }" aria-hidden="true"></i>
                  <span>{{ f.color ? `${titleCase(f.color)} ${f.flower.toLowerCase()}` : f.flower }}</span>
                  <strong>× {{ f.count }}</strong>
                </li>
              </ul>
            </div>

            <!-- Its colour (when there's no choice) and the occasions it suits -->
            <div v-if="(product.color && !colorOptions.length) || occasions.length" class="pd-section pd-facts">
              <div v-if="product.color && !colorOptions.length" class="pd-fact">
                <h2 class="pd-label">Color</h2>
                <span class="pd-chip">
                  <i class="pd-swatch" :style="{ background: swatch }" aria-hidden="true"></i>
                  {{ titleCase(product.color) }}
                </span>
              </div>
              <div v-if="occasions.length" class="pd-fact">
                <h2 class="pd-label">Good for</h2>
                <div class="pd-chips">
                  <span v-for="o in occasions" :key="o" class="pd-chip">{{ o }}</span>
                </div>
              </div>
            </div>

            <!-- Availability -->
            <p class="pd-avail">
              <ProductTag :tag="stockTag(product.quantity_in_stock)" />
              <strong v-if="availability.title">{{ availability.title }}</strong>
              <span v-if="availability.note">{{ availability.note }}</span>
            </p>

            <!-- Delivery date: the shop's open days -->
            <div v-if="!soldOut && product.vendor_id" class="pd-section">
              <div class="pd-label-row">
                <h2 class="pd-label">Delivery date</h2>
                <span v-if="chosenDate" class="pd-muted pd-small">You can change it at checkout</span>
              </div>
              <div v-if="datesLoading" class="pd-dates" aria-label="Loading delivery dates">
                <span v-for="n in 5" :key="n" class="bc-skeleton pd-date-skel"></span>
              </div>
              <div v-else-if="dates.length" class="pd-dates" role="radiogroup" aria-label="Delivery date">
                <button
                  v-for="d in dates"
                  :key="d.date"
                  type="button"
                  role="radio"
                  class="pd-date"
                  :class="{ on: chosenDate === d.date }"
                  :aria-checked="chosenDate === d.date"
                  @click="chosenDate = chosenDate === d.date ? null : d.date"
                >
                  <small>{{ d.top }}</small>
                  <strong>{{ d.day }}</strong>
                  <small>{{ d.month }}</small>
                </button>
              </div>
              <p v-else class="pd-muted pd-small">Pick your delivery date at checkout.</p>
            </div>

            <!-- Quantity + buy -->
            <div ref="buyEl" class="pd-buy">
              <div class="pd-qty" role="group" aria-label="Quantity">
                <button type="button" aria-label="Fewer" :disabled="qty <= 1 || soldOut" @click="qty--">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12h12" /></svg>
                </button>
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
                <button type="button" aria-label="More" :disabled="qty >= maxQty || soldOut" @click="qty++">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6v12M6 12h12" /></svg>
                </button>
              </div>
              <button type="button" class="pd-btn pd-btn--solid pd-btn--grow" :disabled="soldOut || !!busy" @click="addToCart">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z" /><path d="M9 7a3 3 0 0 1 6 0" /></svg>
                {{ busy === "cart" ? "Adding…" : "Add to cart" }}
              </button>
            </div>
            <button type="button" class="pd-btn pd-btn--ghost pd-btn--full" :disabled="soldOut || !!busy" @click="buyNow">
              Buy now<template v-if="qty > 1"> · {{ peso(price * qty) }}</template>
            </button>

            <div class="pd-links">
              <button type="button" class="pd-link" @click="askShop">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z" /></svg>
                Ask the shop
              </button>
              <button type="button" class="pd-link" @click="share">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4" /></svg>
                Share
              </button>
            </div>

            <ul class="pd-promises">
              <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7" /><circle cx="7" cy="18" r="1.6" /><circle cx="17" cy="18" r="1.6" /></svg>
                Fresh, delivered by the shop
              </li>
              <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3zM3 10h18M7 15h3" /></svg>
                Secure payment or COD
              </li>
              <li>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.4-5.7L4 8.5" /><path d="M4 4v4.5h4.5" /></svg>
                Easy returns
              </li>
            </ul>
          </div>
        </section>

        <!-- ── Information tabs ─────────────────────────────────────── -->
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
              {{ tab.label }}<span v-if="tab.key === 'reviews' && Number(product.total_reviews)" class="pd-tabcount">{{ product.total_reviews }}</span>
            </button>
          </div>

          <div v-show="activeTab === 'description'" id="pd-panel-description" role="tabpanel" aria-labelledby="pd-tab-description" class="pd-panel pd-desc">
            <p class="pd-desc-text">{{ product.description || "Fresh flowers, prepared by a local florist." }}</p>
            <ul class="pd-feats">
              <li>
                <span aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="2.5" /><path d="M12 15.5A3.5 3.5 0 1 1 8.5 12 3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 1 1 3.5 3.5 3.5 3.5 0 1 1-3.5 3.5" /></svg></span>
                <div><strong>Fresh {{ (categoryLabel || "flowers").toLowerCase() }}</strong><small>{{ product.freshness_days ? `Stays fresh about ${product.freshness_days} days` : "Long-lasting blooms" }}</small></div>
              </li>
              <li>
                <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 8h14l-2 12H7z" /><path d="M9 8a3 3 0 0 1 6 0" /></svg></span>
                <div><strong>Carefully wrapped</strong><small>Ready to give</small></div>
              </li>
              <li>
                <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" /></svg></span>
                <div><strong>Handcrafted</strong><small>By a local florist</small></div>
              </li>
            </ul>
          </div>

          <div v-show="activeTab === 'details'" id="pd-panel-details" role="tabpanel" aria-labelledby="pd-tab-details" class="pd-panel">
            <dl class="pd-specs">
              <div v-for="row in specs" :key="row.label"><dt>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
            </dl>
          </div>

          <div v-show="activeTab === 'care'" id="pd-panel-care" role="tabpanel" aria-labelledby="pd-tab-care" class="pd-panel">
            <p v-if="product.care_instructions" class="pd-desc-text">{{ product.care_instructions }}</p>
            <ol v-else class="pd-care-list">
              <li>Trim the stems at an angle and place them in clean, cool water as soon as they arrive.</li>
              <li>Change the water every two days and trim the stems again.</li>
              <li>Keep them away from direct sunlight, heat and ripening fruit.</li>
            </ol>
          </div>

          <div v-if="tabsSeen.reviews" v-show="activeTab === 'reviews'" id="pd-panel-reviews" role="tabpanel" aria-labelledby="pd-tab-reviews" class="pd-panel">
            <ProductReviews :product-id="product.id" @updated="refreshStats" />
            <div class="pd-questions">
              <ProductQuestions :product-id="product.id" />
            </div>
          </div>
        </section>

        <!-- ── You may also like ────────────────────────────────────── -->
        <section v-if="loadingRelated || related.length" class="pd-row" aria-labelledby="pd-related-title" :aria-busy="loadingRelated && !related.length">
          <div class="pd-row-head">
            <div>
              <h2 id="pd-related-title">{{ forYou ? "You may like" : "You may also like" }}</h2>
              <!-- Personal picks come from the customer's "Flowers you love" -->
              <p v-if="forYou" class="pd-row-sub">
                Picked from the flowers you love.
                <router-link :to="{ name: 'Profile', hash: '#pf-preferences' }">Change what you like</router-link>
              </p>
              <p v-else-if="isAuthenticated && !loadingRelated" class="pd-row-sub">
                <router-link :to="{ name: 'Profile', hash: '#pf-preferences' }">Tell us the flowers you love</router-link> for picks made for you.
              </p>
            </div>
            <router-link :to="{ name: 'Shop', query: { section: 'products' } }" class="pd-viewall">View all <span aria-hidden="true">→</span></router-link>
          </div>
          <div v-if="!related.length" class="pd-grid" aria-label="Loading suggestions">
            <div v-for="n in 5" :key="n" class="pd-rel-skel" aria-hidden="true">
              <span class="bc-skeleton pd-rel-skel-img"></span>
              <span class="bc-skeleton pd-rel-skel-line w80"></span>
              <span class="bc-skeleton pd-rel-skel-line w50"></span>
            </div>
          </div>
          <div v-else class="pd-grid">
            <ShopProductTile
              v-for="item in related"
              :key="item.id"
              :product="item"
              :context-tag="item.for_you ? 'you_may_like' : ''"
              :adding="addingRelatedId === item.id"
              @open="openProduct"
              @add="addRelated"
            />
          </div>
        </section>

        <!-- ── Recently viewed (this browser) ───────────────────────── -->
        <section v-if="recent.length" class="pd-row" aria-labelledby="pd-recent-title">
          <div class="pd-row-head">
            <h2 id="pd-recent-title">Recently viewed</h2>
          </div>
          <div class="pd-grid">
            <ShopProductTile
              v-for="item in recent"
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

    <!-- Phones: price and Add to cart stay in reach once the buttons scroll away -->
    <Transition name="pd-bar">
      <div v-if="product && !loading && showBar" class="pd-bar" role="region" aria-label="Buy this product">
        <div class="pd-bar-price">
          <strong>{{ peso(price) }}</strong>
          <s v-if="onSale">{{ peso(product.selling_price) }}</s>
        </div>
        <button type="button" class="pd-btn pd-btn--solid" :disabled="soldOut || !!busy" @click="addToCart">
          {{ soldOut ? "Sold out" : busy === "cart" ? "Adding…" : "Add to cart" }}
        </button>
      </div>
    </Transition>

    <ImageLightbox
      v-if="lightboxAt !== null && images.length"
      :images="images.map((i) => i.image_url)"
      :start="lightboxAt"
      :alt="product.product_name"
      @change="active = $event"
      @close="lightboxAt = null"
    />

    <SiteFooter />
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent, h, nextTick, onBeforeUnmount, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios.js";
import NavHeader from "../../layouts/NavHeader.vue";
import { sizedImage, sizedSrcset } from "../../utils/cloudinaryImage.js";
import { productPreview, rememberProduct } from "../../utils/productPreview.js";
import { recentlyViewed, rememberViewed } from "../../utils/recentlyViewed.js";
// three.js is big: it loads only when the customer opens 3D View
const ModelViewer3D = defineAsyncComponent({
  loader: () => import("../../layouts/3D/3DModelViewer.vue"),
  loadingComponent: { render: () => h("span", { class: "bc-skeleton pd-model-skel", "aria-label": "Loading 3D view" }) },
  delay: 0,
});
import ProductReviews from "../../layouts/components/ProductReviews.vue";
import ProductQuestions from "../../layouts/components/ProductQuestions.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import ShopProductTile from "../../components/shop/ShopProductTile.vue";
import ImageLightbox from "../../components/shop/ImageLightbox.vue";
import ProductTag, { LOW_STOCK, stockTag } from "../../components/shop/ProductTag.vue";
import { useAuth } from "../../composables/useAuth";
import { useCart } from "../../composables/useCart";
import { useFlyToCart } from "../../composables/useFlyToCart";
import { useSiteContent } from "../../composables/useSiteContent";
import { toast } from "@/utils/snackbar";
import "../../styles/bloomcraft.css";

const TABS = [
  { key: "description", label: "Description" },
  { key: "details", label: "Details" },
  { key: "care", label: "Care" },
  { key: "reviews", label: "Reviews" },
];
const PH_TZ = "Asia/Manila";
const CHECKOUT_DRAFT_KEY = "checkout_payment_draft"; // Checkout.vue picks the date up from here
const DATE_CHOICES = 6;

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const cartStore = useCart();
const { flyToCart } = useFlyToCart();
const { site } = useSiteContent();

const navHeaderRef = ref(null);
const imageEl = ref(null);
const buyEl = ref(null);
const product = ref(null);
const loading = ref(true);
const related = ref([]);
const loadingRelated = ref(false); // "You may also like" shows skeleton tiles until it arrives
const forYou = ref(false); // the row was picked from the customer's "Flowers you love"
const recent = ref([]);
const active = ref(0);
const show3D = ref(false);
const qty = ref(1);
// What's being typed in the quantity box; applied on Enter or leaving the box
const qtyDraft = ref(null);
const busy = ref(null); // "cart" | "buy"
const addingRelatedId = ref(null);
const activeTab = ref("description");
const lightboxAt = ref(null); // photo index open full screen
const zoom = reactive({ on: false, x: 50, y: 50 });
const dates = ref([]);
const datesLoading = ref(false);
const chosenDate = ref(null);
const showBar = ref(false);
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
const prepDays = computed(() => Number(product.value?.preparation_days || 0));
const categoryLabel = computed(() => {
  const value = product.value?.category;
  if (!value) return "";
  return site.categories.find((c) => c.value === value)?.label || value.replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
});
// The first sentence or two, under the price; the full text is in the Description tab
const shortDescription = computed(() => {
  const text = String(product.value?.description || "").trim();
  return text.length > 160 ? `${text.slice(0, 157).trimEnd()}…` : text;
});
const titleCase = (s) => String(s || "").replace(/[-_]/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());

// "Focal" (the star flowers) or "Filler" (the greenery around them)
const flowerRole = computed(() => ({ focal: "Focal flower", filler: "Filler" })[product.value?.flower_type] || titleCase(product.value?.flower_type));
const occasions = computed(() => (product.value?.occasion_tags || []).map(titleCase).slice(0, 4));
// A colour dot: the shop's hex if given, else the colour's name when the
// browser knows it ("Light pink" → lightpink), else a soft mixed dot
const MIXED = "conic-gradient(#f4a7b9, #f9e27d, #a7d8f4, #c7a7f4, #f4a7b9)";
function swatchFor(name, hex = null) {
  if (hex) return hex;
  const css = String(name || "").toLowerCase().replace(/[^a-z]/g, "");
  return css && typeof CSS !== "undefined" && CSS.supports("color", css) ? css : MIXED;
}
const swatch = computed(() => swatchFor(product.value?.color));

// Colours the customer can pick, and the flowers in the bouquet (both set by the shop)
const colorOptions = computed(() => (Array.isArray(product.value?.color_options) ? product.value.color_options : []));
const chosenColor = ref(null);
const composition = computed(() => (Array.isArray(product.value?.composition) ? product.value.composition : []));
const totalStems = computed(() => composition.value.reduce((sum, f) => sum + Number(f.count || 0), 0));
/** Starts on the product's own colour if it's one of the choices, else the first */
function pickDefaultColor() {
  const list = colorOptions.value;
  const own = String(product.value?.color || "").toLowerCase();
  chosenColor.value = list.find((c) => c.name.toLowerCase() === own)?.name || list[0]?.name || null;
}
/** The colour that goes with the order: the one picked, else the product's own */
const orderColor = () => chosenColor.value || product.value?.color || null;

// The product's tags, in the admin's order; stock has its own tag below the price
const pageTags = computed(() => (product.value?.tags || []).filter((t) => t !== "out_of_stock"));

// Next to the stock tag: how many, and whether it's made to order
const availability = computed(() => {
  const stock = Number(product.value?.quantity_in_stock || 0);
  if (stock <= 0) return { title: "", note: "Check back soon, or ask the shop." };
  const days = prepDays.value;
  const ready = days > 0 ? `Made to order · ready in ${days} ${days === 1 ? "day" : "days"}` : "";
  if (stock <= LOW_STOCK) return { title: `Only ${stock} left`, note: ready };
  return { title: `${stock} available`, note: ready };
});

const specs = computed(() => {
  const p = product.value;
  return [
    { label: "Category", value: categoryLabel.value || "—" },
    { label: "Flower type", value: flowerRole.value || "—" },
    { label: "Color", value: colorOptions.value.length ? colorOptions.value.map((c) => c.name).join(", ") : titleCase(p.color) || "—" },
    ...(composition.value.length ? [{ label: "Stems", value: `${totalStems.value} in all` }] : []),
    { label: "Season", value: p.season === "all-year" ? "All year" : titleCase(p.season) || "—" },
    { label: "Occasions", value: (p.occasion_tags || []).join(", ") || "—" },
    { label: "Preparation", value: prepDays.value ? `${prepDays.value} ${prepDays.value === 1 ? "day" : "days"}` : "Ready to send" },
    { label: "Stays fresh", value: p.freshness_days ? `About ${p.freshness_days} days` : "—" },
    { label: "Shop", value: p.vendor_name || "—" },
    { label: "Availability", value: soldOut.value ? "Sold out" : `${p.quantity_in_stock} in stock` },
  ];
});

const peso = (value) => `₱${Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

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
  zoom.on = false;
  const n = images.value.length;
  active.value = ((i % n) + n) % n;
}

// ── Zoom and full screen ──────────────────────────────────────────────
const canHover = typeof window !== "undefined" && window.matchMedia?.("(hover: hover) and (pointer: fine)").matches;
function zoomAt(event) {
  if (!canHover) return;
  const r = event.currentTarget.getBoundingClientRect();
  zoom.x = ((event.clientX - r.left) / r.width) * 100;
  zoom.y = ((event.clientY - r.top) / r.height) * 100;
  zoom.on = true;
}
function openLightbox(i) {
  zoom.on = false;
  lightboxAt.value = i;
}

function openTab(key) {
  activeTab.value = key;
  document.getElementById("pd-tabs")?.scrollIntoView({ behavior: "smooth" });
}

// ── Delivery dates: the shop's next open days ─────────────────────────
const phToday = () => new Intl.DateTimeFormat("en-CA", { timeZone: PH_TZ }).format(new Date()); // YYYY-MM-DD
function addDays(iso, n) {
  const [y, m, d] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(y, m - 1, d + n));
  return date.toISOString().slice(0, 10);
}
function dateChip(iso, today) {
  const [y, m, d] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(y, m - 1, d));
  const fmt = (o) => new Intl.DateTimeFormat("en-PH", { timeZone: "UTC", ...o }).format(date);
  const top = iso === today ? "Today" : iso === addDays(today, 1) ? "Tmrw" : fmt({ weekday: "short" });
  return { date: iso, top, day: String(d), month: fmt({ month: "short" }) };
}
async function loadDates() {
  const p = product.value;
  dates.value = [];
  chosenDate.value = null;
  if (!p?.vendor_id || soldOut.value) return;
  const id = p.id;
  const today = phToday();
  datesLoading.value = true;
  try {
    const { data } = await api.get("/customer/availability", {
      params: { vendor_id: p.vendor_id, start_date: today, end_date: addDays(today, 30) },
    });
    if (product.value?.id !== id) return;
    const earliest = addDays(today, prepDays.value);
    const open = Object.entries(data?.data?.calendar || {})
      .filter(([date, day]) => !day.is_disabled && date >= earliest)
      .map(([date]) => date)
      .sort()
      .slice(0, DATE_CHOICES);
    dates.value = open.map((date) => dateChip(date, today));
  } catch {
    dates.value = []; // the date is still picked at checkout
  } finally {
    if (product.value?.id === id) datesLoading.value = false;
  }
}
/** The chosen date goes to checkout, which opens with it selected */
function carryDate() {
  if (!chosenDate.value) return;
  try {
    const draft = JSON.parse(sessionStorage.getItem(CHECKOUT_DRAFT_KEY) || "null") || {};
    sessionStorage.setItem(CHECKOUT_DRAFT_KEY, JSON.stringify({ ...draft, selectedDate: chosenDate.value }));
  } catch {
    // Storage blocked: the date is picked at checkout instead
  }
}

// ── Share ─────────────────────────────────────────────────────────────
async function share() {
  const url = window.location.href;
  const title = product.value?.product_name || "BloomCraft";
  try {
    if (navigator.share) {
      await navigator.share({ title, text: `${title} on BloomCraft`, url });
      return;
    }
    await navigator.clipboard.writeText(url);
    toast.success("Link copied. Paste it anywhere to share.");
  } catch (error) {
    if (error?.name !== "AbortError") toast.info(`Copy this link to share: ${url}`);
  }
}

// ── Phones: the buy bar shows once the buy buttons are off screen ─────
let barWatch = null;
function watchBuyButtons() {
  barWatch?.disconnect();
  showBar.value = false;
  if (!buyEl.value || typeof IntersectionObserver === "undefined") return;
  barWatch = new IntersectionObserver(([entry]) => {
    showBar.value = !entry.isIntersecting && entry.boundingClientRect.top < 0;
  });
  barWatch.observe(buyEl.value);
}
onBeforeUnmount(() => barWatch?.disconnect());

async function load(id) {
  const preview = productPreview(id);
  loading.value = !preview;
  product.value = preview;
  related.value = [];
  loadingRelated.value = true;
  active.value = 0;
  show3D.value = false;
  zoom.on = false;
  lightboxAt.value = null;
  qty.value = 1;
  activeTab.value = "description";
  tabsSeen.reviews = false;
  try {
    const { data } = await api.get(`/customer/products/${id}`);
    product.value = data?.success ? data.data : null;
  } catch {
    // Couldn't reach the server: keep what the tile already showed
    product.value = preview;
  } finally {
    loading.value = false;
  }
  if (product.value) {
    document.title = `${product.value.product_name} · BloomCraft`;
    recent.value = recentlyViewed().filter((p) => String(p.id) !== String(product.value.id)).slice(0, 5);
    rememberViewed(product.value);
    refreshRecent();
    countView(product.value.id);
    pickDefaultColor();
    loadRelated();
    loadDates();
    nextTick(watchBuyButtons);
  } else {
    loadingRelated.value = false;
  }
}

/** Counts this page being opened, once per product per visit (for the POPULAR tag) */
function countView(id) {
  try {
    const seen = JSON.parse(sessionStorage.getItem("bc_viewed_products") || "[]");
    if (seen.includes(id)) return;
    sessionStorage.setItem("bc_viewed_products", JSON.stringify([...seen, id].slice(-200)));
  } catch {
    // storage blocked: count it anyway
  }
  api.post(`customer/products/${id}/view`).catch(() => {});
}

/**
 * The saved copies can be out of date (price, stock, tags), so the row is
 * shown from them straight away, then swapped for the current details.
 * Products no longer listed drop out.
 */
async function refreshRecent() {
  const saved = recent.value;
  if (!saved.length) return;
  const id = product.value.id;
  try {
    const { data } = await api.get("customer/products", {
      params: { ids: saved.map((p) => p.id).join(","), per_page: saved.length, page: 1, in_stock_only: 0 },
    });
    const fresh = new Map((data?.data?.data || []).map((p) => [String(p.id), p]));
    if (product.value?.id === id) recent.value = saved.map((p) => fresh.get(String(p.id))).filter(Boolean);
  } catch {
    // keep the saved copies
  }
}

// Same category first, topped up with the newest products
async function loadRelated() {
  const id = product.value.id;
  loadingRelated.value = true;
  try {
    await fillRelated(id);
  } finally {
    // A newer product's list may have started meanwhile; only the current one ends the skeleton
    if (product.value?.id === id) loadingRelated.value = false;
  }
}

/**
 * "You may like": picked from the customer's "Flowers you love" (never what
 * they asked to avoid). Guests and customers who haven't said get similar
 * flowers; if that can't load, the older same-category list below.
 */
async function fillRelated(id) {
  forYou.value = false;
  try {
    const { data } = await api.get("customer/recommendations", {
      params: { exclude: id, category: product.value.category || "", limit: 5 },
    });
    const list = Array.isArray(data?.data) ? data.data : [];
    if (list.length) {
      if (product.value?.id === id) {
        related.value = list;
        forYou.value = !!data.personalized;
      }
      return;
    }
  } catch {
    // fall back to the same-category list
  }
  await fillSimilar(id);
}

async function fillSimilar(id) {
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
  if (product.value?.id === id) related.value = list.slice(0, 5);
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
    await addItem({ ...product.value, color: orderColor() }, qty.value, imageEl.value);
    carryDate();
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
  carryDate();
  sessionStorage.setItem(
    "directCheckout",
    JSON.stringify({
      items: [{ product_id: p.id, quantity: qty.value, color: orderColor(), product_name: p.product_name, price: p.selling_price, image: images.value[0]?.image_url || null }],
      total: parseFloat(p.selling_price) * qty.value,
      isDirectCheckout: true,
    }),
  );
  router.push({ path: "/customer/checkout", query: { direct: "true", product_id: p.id, quantity: qty.value } });
}

// Opens the chat with this shop; the question is sent with a card for this product
function askShop() {
  if (!product.value?.vendor_id) return;
  if (!isAuthenticated.value) {
    toast.info("Log in to message the shop.");
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }
  router.push({
    path: "/customer/chat",
    query: { vendor_id: String(product.value.vendor_id), vendor_name: product.value.vendor_name || "" },
    state: {
      chatAbout: JSON.stringify({ type: "product", id: product.value.id, name: product.value.product_name, image: images.value[0]?.image_url || null }),
    },
  });
}

function goToStore() {
  if (!product.value?.vendor_id) return;
  router.push({ name: "VendorStorefront", state: { storeId: String(product.value.vendor_id) } });
}

function openProduct(item) {
  rememberProduct(item);
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
  --pd-line: rgba(54, 63, 78, 0.1);
  --pd-line-strong: rgba(54, 63, 78, 0.18);
  --pd-surface: #ffffff;
  --pd-red: #b4332a;
  --pd-amber: #9a5b0b;
  min-height: 100vh;
  overflow-x: hidden;
  background: var(--ivory);
  color: var(--ink);
  font-family: var(--font-sans);
}

.pd-page button {
  font-family: var(--font-sans);
}

.pd-page svg {
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pd-main {
  padding-top: 104px;
  padding-bottom: clamp(40px, 6vw, 72px);
}

/* Breadcrumb */
.pd-crumbs {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  color: var(--ink-soft);
  font-size: 12px;
}

.pd-crumbs a {
  color: var(--ink-soft);
  text-decoration: none;
  transition: color 0.15s ease;
}

.pd-crumbs a:hover {
  color: var(--ink);
}

.pd-crumbs span[aria-hidden] {
  opacity: 0.45;
}

.pd-crumbs [aria-current] {
  overflow: hidden;
  max-width: 32ch;
  color: var(--ink);
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ── Top: gallery + buying panel ─────────────────────────────────── */
.pd-top {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
  gap: clamp(24px, 4vw, 56px);
  align-items: start;
}

/* Gallery: thumbnails down the side, a contained square photo */
.pd-gallery {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr);
  grid-template-areas: "thumbs stage" ". hint";
  gap: 6px 12px;
  position: sticky;
  top: 96px;
  align-self: start;
}

.pd-gallery:not(:has(.pd-thumbs)) {
  grid-template-columns: minmax(0, 1fr);
  grid-template-areas: "stage" "hint";
}

.pd-thumbs {
  grid-area: thumbs;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 560px;
  overflow-y: auto;
  scrollbar-width: none;
}

.pd-thumb {
  flex: none;
  width: 64px;
  height: 64px;
  padding: 0;
  overflow: hidden;
  border: 1px solid var(--pd-line);
  border-radius: 12px;
  background: var(--pd-surface);
  opacity: 0.6;
  cursor: pointer;
  transition: opacity 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.pd-thumb:hover {
  opacity: 1;
}

.pd-thumb.on {
  border-color: var(--ink);
  box-shadow: 0 0 0 1px var(--ink);
  opacity: 1;
}

.pd-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pd-thumb--3d {
  display: grid;
  place-content: center;
  gap: 1px;
  color: var(--ink);
  font-size: 10.5px;
  font-weight: 600;
}

.pd-thumb--3d svg {
  width: 20px;
  height: 20px;
  margin: 0 auto;
}

.pd-stage {
  grid-area: stage;
  position: relative;
  aspect-ratio: 1 / 1;
  max-height: 560px;
  overflow: hidden;
  border: 1px solid var(--pd-line);
  border-radius: 20px;
  background: var(--pd-surface);
}

.pd-zoom {
  display: block;
  width: 100%;
  height: 100%;
  padding: 0;
  overflow: hidden;
  border: 0;
  background: none;
  cursor: zoom-in;
}

.pd-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.pd-zoom.zooming .pd-photo {
  transform: scale(2);
  transition: transform 0.15s ease;
}

.pd-model {
  width: 100%;
  height: 100%;
}

.pd-noimg {
  display: grid;
  place-items: center;
  height: 100%;
  color: var(--ivory-deep);
}

.pd-noimg svg {
  width: 72px;
  height: 72px;
  color: var(--ink-soft);
  opacity: 0.35;
}

.pd-badge {
  position: absolute;
  top: 14px;
  left: 14px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--pd-red);
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  pointer-events: none;
}

.pd-stage-tools {
  position: absolute;
  top: 12px;
  right: 12px;
  display: flex;
  gap: 6px;
}

.pd-round,
.pd-arrow {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  color: var(--ink);
  cursor: pointer;
  box-shadow: 0 2px 10px rgba(30, 39, 57, 0.12);
  transition: background 0.15s ease, opacity 0.2s ease, transform 0.15s ease;
}

.pd-round:hover,
.pd-arrow:hover {
  background: #ffffff;
  transform: scale(1.05);
}

.pd-round svg,
.pd-arrow svg {
  width: 17px;
  height: 17px;
  stroke-width: 1.9;
}

.pd-arrow {
  position: absolute;
  top: 50%;
  margin-top: -19px;
  opacity: 0;
}

.pd-arrow.prev {
  left: 12px;
}

.pd-arrow.next {
  right: 12px;
}

.pd-stage:hover .pd-arrow,
.pd-arrow:focus-visible {
  opacity: 1;
}

@media (hover: none) {
  .pd-arrow {
    opacity: 1;
  }
}

.pd-counter {
  position: absolute;
  right: 14px;
  bottom: 14px;
  padding: 3px 9px;
  border-radius: 999px;
  background: rgba(30, 39, 57, 0.6);
  color: #ffffff;
  font-size: 11.5px;
  font-variant-numeric: tabular-nums;
  pointer-events: none;
}

.pd-3d {
  position: absolute;
  bottom: 14px;
  left: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 0 14px;
  border: 0;
  border-radius: 999px;
  background: var(--ink);
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(30, 39, 57, 0.2);
}

.pd-3d svg {
  width: 16px;
  height: 16px;
}

.pd-zoom-hint {
  grid-area: hint;
  margin: 0;
  color: var(--ink-soft);
  font-size: 11.5px;
  text-align: center;
}

@media (hover: none) {
  .pd-zoom-hint {
    display: none;
  }
}

/* ── Buying panel ───────────────────────────────────────────────── */
.pd-info {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

.pd-eyebrow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.pd-shop {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.pd-shop:hover {
  text-decoration: underline;
}

.pd-shop svg {
  width: 15px;
  height: 15px;
}

.pd-cat {
  padding: 2px 9px;
  border: 1px solid var(--pd-line);
  border-radius: 999px;
  color: var(--ink-soft);
  font-size: 11.5px;
}

.pd-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: -4px;
}

.pd-name {
  margin: -6px 0 0;
  font-family: var(--font-display);
  font-size: clamp(28px, 3vw, 36px);
  font-weight: 500;
  line-height: 1.08;
  letter-spacing: -0.01em;
  overflow-wrap: anywhere;
}

.pd-rating {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: -6px 0 0;
  font-size: 13px;
}

.pd-rating a {
  color: var(--ink-soft);
  text-decoration: underline;
  text-underline-offset: 3px;
}

.pd-stars {
  --rating: 0;
  font-size: 14px;
  letter-spacing: 1px;
  background: linear-gradient(90deg, #e0a526 calc(var(--rating) / 5 * 100%), #d9dde2 calc(var(--rating) / 5 * 100%));
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.pd-muted {
  color: var(--ink-soft);
}

.pd-small {
  font-size: 12px;
}

.pd-dot {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: var(--ink-soft);
  opacity: 0.6;
}

.pd-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 10px;
}

.pd-price strong {
  font-size: 26px;
  font-weight: 600;
  letter-spacing: -0.01em;
  font-variant-numeric: tabular-nums;
}

.pd-price s {
  color: var(--ink-soft);
  font-size: 15px;
}

.pd-save {
  padding: 2px 8px;
  border-radius: 6px;
  background: #fbe9e7;
  color: var(--pd-red);
  font-size: 12px;
  font-weight: 600;
}

.pd-lede {
  margin: 0;
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

.pd-section {
  display: grid;
  gap: 10px;
  padding-top: 16px;
  border-top: 1px solid var(--pd-line);
}

.pd-label-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}

.pd-label {
  margin: 0;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 12px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.pd-label-value {
  margin-left: 8px;
  color: var(--ink-soft);
  font-weight: 500;
  letter-spacing: 0;
  text-transform: none;
}

/* Colour choices: round swatches with a ring on the chosen one */
.pd-colors {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pd-color {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--pd-line);
  border-radius: 50%;
  background: var(--pd-surface);
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.pd-color i {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
}

.pd-color:hover {
  border-color: var(--pd-line-strong);
}

.pd-color.on {
  border-color: var(--ink);
  box-shadow: 0 0 0 1px var(--ink);
}

/* What's inside: one line per flower, the count on the right */
.pd-compo {
  display: grid;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pd-compo li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px dashed var(--pd-line);
  font-size: 13.5px;
}

.pd-compo li:last-child {
  border-bottom: 0;
}

.pd-compo span {
  flex: 1;
  min-width: 0;
}

.pd-compo strong {
  color: var(--ink);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.pd-facts {
  grid-template-columns: auto minmax(0, 1fr);
  gap: 16px 28px;
}

.pd-fact {
  display: grid;
  align-content: start;
  gap: 8px;
}

.pd-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.pd-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 0 12px;
  border: 1px solid var(--pd-line);
  border-radius: 999px;
  background: var(--pd-surface);
  font-size: 13px;
}

.pd-chip svg {
  width: 15px;
  height: 15px;
  color: var(--leaf-deep);
}

.pd-swatch {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
}

/* IN STOCK / LOW STOCK / OUT OF STOCK, then a short note */
.pd-avail {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 8px;
  margin: 0;
  font-size: 13px;
}

.pd-avail strong {
  font-weight: 600;
}

.pd-avail span {
  color: var(--ink-soft);
}

/* Delivery dates: a row of day cards */
.pd-dates {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 6px;
}

.pd-date {
  display: grid;
  justify-items: center;
  gap: 0;
  padding: 7px 4px;
  border: 1px solid var(--pd-line);
  border-radius: 12px;
  background: var(--pd-surface);
  color: var(--ink);
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.pd-date:hover {
  border-color: var(--pd-line-strong);
}

.pd-date small {
  color: var(--ink-soft);
  font-size: 10.5px;
  line-height: 1.3;
}

.pd-date strong {
  font-size: 17px;
  font-weight: 600;
  line-height: 1.25;
  font-variant-numeric: tabular-nums;
}

.pd-date.on {
  border-color: var(--ink);
  background: var(--ink);
  color: #ffffff;
}

.pd-date.on small {
  color: rgba(255, 255, 255, 0.75);
}

.pd-date-skel {
  height: 62px;
  border-radius: 12px;
}

/* Quantity + Add to cart */
.pd-buy {
  display: flex;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--pd-line);
}

.pd-qty {
  display: inline-flex;
  align-items: center;
  flex: none;
  height: 48px;
  border: 1px solid var(--pd-line-strong);
  border-radius: 12px;
  background: var(--pd-surface);
}

.pd-qty button {
  display: grid;
  place-items: center;
  width: 40px;
  height: 100%;
  border: 0;
  background: none;
  color: var(--ink);
  cursor: pointer;
}

.pd-qty button svg {
  width: 16px;
  height: 16px;
  stroke-width: 2;
}

.pd-qty button:disabled {
  color: var(--ink-soft);
  opacity: 0.4;
  cursor: not-allowed;
}

.pd-qty input {
  width: 40px;
  height: 100%;
  border: 0;
  background: none;
  color: var(--ink);
  font: 600 15px var(--font-sans);
  text-align: center;
  -moz-appearance: textfield;
  appearance: textfield;
}

.pd-qty input::-webkit-outer-spin-button,
.pd-qty input::-webkit-inner-spin-button {
  margin: 0;
  -webkit-appearance: none;
}

.pd-qty input:focus {
  outline: none;
}

.pd-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 48px;
  padding: 0 22px;
  border: 1px solid transparent;
  border-radius: 12px;
  font-size: 14.5px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.1s ease;
}

.pd-btn svg {
  width: 18px;
  height: 18px;
}

.pd-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.pd-btn--solid {
  background: var(--ink);
  color: #ffffff;
}

.pd-btn--solid:hover:not(:disabled) {
  background: var(--leaf-deep);
}

.pd-btn--ghost {
  border-color: var(--pd-line-strong);
  background: var(--pd-surface);
  color: var(--ink);
}

.pd-btn--ghost:hover:not(:disabled) {
  border-color: var(--ink);
}

.pd-btn--grow {
  flex: 1;
}

.pd-btn--full {
  width: 100%;
  margin-top: -8px;
}

.pd-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.pd-links {
  display: flex;
  gap: 18px;
}

.pd-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.pd-link:hover {
  color: var(--leaf-deep);
}

.pd-link svg {
  width: 16px;
  height: 16px;
}

.pd-promises {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 14px 16px;
  border-radius: 14px;
  background: rgba(54, 63, 78, 0.04);
  list-style: none;
}

.pd-promises li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--ink-soft);
  font-size: 12.5px;
}

.pd-promises svg {
  flex: none;
  width: 16px;
  height: 16px;
  color: var(--leaf-deep);
}

/* ── Tabs ───────────────────────────────────────────────────────── */
.pd-tabs {
  margin-top: clamp(40px, 6vw, 72px);
  scroll-margin-top: 96px;
}

.pd-tablist {
  display: flex;
  gap: 28px;
  overflow-x: auto;
  border-bottom: 1px solid var(--pd-line);
  scrollbar-width: none;
}

.pd-tablist button {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex: none;
  padding: 0 0 12px;
  border: 0;
  background: none;
  color: var(--ink-soft);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.15s ease;
}

.pd-tablist button:hover {
  color: var(--ink);
}

.pd-tablist button.on {
  color: var(--ink);
}

.pd-tablist button.on::after {
  content: "";
  position: absolute;
  right: 0;
  bottom: -1px;
  left: 0;
  height: 2px;
  border-radius: 2px;
  background: var(--ink);
}

.pd-tabcount {
  padding: 1px 7px;
  border-radius: 999px;
  background: rgba(54, 63, 78, 0.08);
  font-size: 11px;
  font-weight: 600;
}

.pd-panel {
  padding-top: 24px;
}

.pd-desc {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: clamp(24px, 4vw, 56px);
  align-items: start;
}

.pd-desc-text {
  max-width: 68ch;
  margin: 0;
  color: var(--ink);
  font-size: 15px;
  line-height: 1.75;
  white-space: pre-line;
}

.pd-feats {
  display: grid;
  gap: 14px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pd-feats li {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pd-feats li > span {
  display: grid;
  place-items: center;
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--pd-surface);
  box-shadow: inset 0 0 0 1px var(--pd-line);
  color: var(--leaf-deep);
}

.pd-feats svg {
  width: 18px;
  height: 18px;
}

.pd-feats div {
  display: grid;
}

.pd-feats strong {
  font-size: 14px;
  font-weight: 600;
}

.pd-feats small {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.pd-specs {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 40px;
  max-width: 880px;
  margin: 0;
}

.pd-specs div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid var(--pd-line);
  font-size: 14px;
}

.pd-specs dt {
  color: var(--ink-soft);
}

.pd-specs dd {
  margin: 0;
  font-weight: 500;
  text-align: right;
}

.pd-care-list {
  display: grid;
  gap: 12px;
  max-width: 68ch;
  margin: 0;
  padding-left: 20px;
  font-size: 15px;
  line-height: 1.65;
}

.pd-care-list li::marker {
  color: var(--leaf-deep);
  font-weight: 600;
}

.pd-questions {
  margin-top: 32px;
}

/* ── Product rows ───────────────────────────────────────────────── */
.pd-row {
  margin-top: clamp(44px, 6vw, 72px);
}

.pd-row-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}

.pd-row-head h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(24px, 2.4vw, 30px);
  font-weight: 500;
}

.pd-row-sub {
  margin: 4px 0 0;
  color: var(--ink-soft);
  font-size: 13px;
}

.pd-row-sub a {
  color: var(--leaf-deep);
  font-weight: 500;
}

.pd-viewall {
  color: var(--ink);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
}

.pd-viewall:hover {
  color: var(--leaf-deep);
}

.pd-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}

.pd-rel-skel {
  display: grid;
  gap: 8px;
}

.pd-rel-skel-img {
  aspect-ratio: 1;
  border-radius: 16px;
}

.pd-rel-skel-line {
  height: 12px;
  border-radius: 6px;
}

.w80 { width: 80%; }
.w70 { width: 70%; }
.w50 { width: 50%; }
.w40 { width: 40%; }
.w30 { width: 30%; }

/* Loading */
.pd-thumb-skel {
  width: 64px;
  height: 64px;
  border-radius: 12px;
}

.pd-stage-skel {
  grid-area: stage;
  aspect-ratio: 1;
  max-height: 560px;
  border-radius: 20px;
}

.pd-line {
  height: 14px;
  border-radius: 7px;
}

.pd-line.big {
  height: 34px;
  width: 85%;
}

.pd-line.block {
  height: 62px;
  border-radius: 12px;
}

.pd-line.btn {
  height: 48px;
  border-radius: 12px;
}

.pd-model-skel {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 320px;
  border-radius: inherit;
}

.pd-missing {
  display: grid;
  justify-items: center;
  gap: 10px;
  padding: 80px 0;
  text-align: center;
}

.pd-missing h1 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 32px;
  font-weight: 500;
}

.pd-missing p {
  margin: 0 0 10px;
  color: var(--ink-soft);
}

/* Phone buy bar */
.pd-bar {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 900;
  display: none;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
  border-top: 1px solid var(--pd-line);
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.pd-bar-price {
  display: grid;
}

.pd-bar-price strong {
  font-size: 18px;
  font-weight: 600;
}

.pd-bar-price s {
  color: var(--ink-soft);
  font-size: 12px;
}

.pd-bar .pd-btn {
  min-height: 44px;
}

.pd-bar-enter-active,
.pd-bar-leave-active {
  transition: transform 0.2s ease;
}

.pd-bar-enter-from,
.pd-bar-leave-to {
  transform: translateY(100%);
}

/* Focus */
.pd-page :is(button, a, input):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

/* ── Responsive ─────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .pd-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  /* 4 across here, 3 on tablets, 2 x 2 on phones */
  .pd-grid > :nth-child(n + 5) {
    display: none;
  }
}

@media (min-width: 641px) and (max-width: 900px) {
  .pd-grid > :nth-child(n + 4) {
    display: none;
  }
}

@media (max-width: 900px) {
  .pd-top {
    grid-template-columns: minmax(0, 1fr);
  }

  .pd-gallery {
    position: static;
  }

  .pd-desc {
    grid-template-columns: minmax(0, 1fr);
  }

  .pd-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .pd-bar {
    display: flex;
  }
}

@media (max-width: 640px) {
  .pd-main {
    padding-top: 88px;
  }

  /* Thumbnails under the photo, in a row */
  .pd-gallery,
  .pd-gallery:not(:has(.pd-thumbs)) {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas: "stage" "thumbs";
  }

  .pd-thumbs {
    flex-direction: row;
    max-height: none;
    overflow-x: auto;
  }

  .pd-thumb,
  .pd-thumb-skel {
    width: 56px;
    height: 56px;
  }

  .pd-stage {
    border-radius: 16px;
  }

  .pd-dates {
    grid-template-columns: repeat(6, 64px);
    overflow-x: auto;
    scrollbar-width: none;
  }

  .pd-specs {
    grid-template-columns: minmax(0, 1fr);
  }

  .pd-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .pd-page {
    padding-bottom: 72px; /* room for the buy bar */
  }
}

@media (prefers-reduced-motion: reduce) {
  .pd-photo,
  .pd-round,
  .pd-arrow,
  .pd-btn,
  .pd-bar-enter-active,
  .pd-bar-leave-active {
    transition: none;
  }
}
</style>
