<template>
  <ShopLayout title="Add product" subtitle="Fill in the steps on the left; the preview shows how customers will see it.">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-primary" :disabled="submitting" @click="submit('active')">
        <span v-html="ICONS.check"></span>{{ submitting === "active" ? "Publishing…" : "Publish" }}
      </button>
    </template>

    <div class="ap-layout">
      <form class="ap-form" novalidate @submit.prevent="submit('active')">
        <!-- 1. Photos -->
        <section id="ap-photos" class="sp-card ap-step" :class="{ done: steps.photos }">
          <header class="ap-step-head">
            <span class="ap-num">{{ steps.photos ? "✓" : 1 }}</span>
            <div><h2>Photos</h2><p>Up to 5. The first one is the cover; drag to reorder.</p></div>
          </header>
          <div
            class="ap-photos"
            :class="{ dragging: dropping }"
            @dragover.prevent="dropping = true"
            @dragleave.prevent="dropping = false"
            @drop.prevent="onDrop"
          >
            <figure
              v-for="(img, i) in images"
              :key="img.url"
              class="ap-photo"
              draggable="true"
              @dragstart="dragFrom = i"
              @dragover.prevent
              @drop.stop.prevent="moveImage(i)"
            >
              <img :src="img.url" :alt="`Photo ${i + 1}`" />
              <span v-if="img.small" class="ap-lowres" :title="`${img.size}: under 800px, it may look blurry`">Low resolution</span>
              <span v-if="i === 0" class="ap-cover">Cover</span>
              <button v-else type="button" class="ap-make-cover" @click="makeCover(i)">Make cover</button>
              <button type="button" class="ap-x" aria-label="Remove photo" @click="removeImage(i)"><span v-html="ICONS.close"></span></button>
            </figure>
            <label v-if="images.length < 5" class="ap-photo ap-add" :class="{ big: !images.length }">
              <input type="file" accept="image/*" multiple @change="onPick" />
              <span v-html="ICONS.upload"></span>
              <strong>{{ images.length ? "Add more" : "Add photos" }}</strong>
              <small v-if="!images.length">Click or drop images here · up to 10 MB each</small>
            </label>
          </div>
          <p v-if="errors.images" class="sp-error">{{ errors.images }}</p>
          <p v-else-if="images.some((img) => img.small)" class="ap-lowres-note">
            Photos marked “Low resolution” are under 800px and may look blurry on the product page. A bigger photo (at least 800px) looks sharper.
          </p>
        </section>

        <!-- 2. Basics -->
        <section id="ap-basics" class="sp-card ap-step" :class="{ done: steps.basics }">
          <header class="ap-step-head">
            <span class="ap-num">{{ steps.basics ? "✓" : 2 }}</span>
            <div><h2>Basics</h2><p>What it is and what makes it special.</p></div>
          </header>
          <div class="sp-grid-2">
            <label data-change="product_name" class="sp-field sp-span-all">
              <span>Product name</span>
              <input v-model="form.product_name" class="sp-input" :class="{ invalid: errors.product_name }" maxlength="255" placeholder="e.g. Sunny Day Tulip Bouquet" @input="clear('product_name')" />
              <small v-if="errors.product_name" class="sp-error">{{ errors.product_name }}</small>
            </label>
            <label data-change="description" class="sp-field sp-span-all">
              <span>Description</span>
              <textarea v-model="form.description" class="sp-textarea" :class="{ invalid: errors.description }" rows="4" maxlength="3000" placeholder="Flowers used, size, the feeling it gives…" @input="clear('description')"></textarea>
              <small v-if="errors.description" class="sp-error">{{ errors.description }}</small>
              <small v-else>{{ form.description.length }}/3000</small>
            </label>
            <div data-change="colors" class="sp-field sp-span-all">
              <FlowerColorPicker :model-value="form.colors" :error="errors.color || ''" @update:model-value="setColors" />
            </div>
            <label data-change="sku" class="sp-field">
              <span>SKU</span>
              <div class="ap-inline">
                <input v-model="form.sku" class="sp-input" :class="{ invalid: errors.sku }" maxlength="255" placeholder="e.g. TULIP-YEL-001" @input="clear('sku')" />
                <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="generateSku">Generate</button>
              </div>
              <small v-if="errors.sku" class="sp-error">{{ errors.sku }}</small>
              <small v-else>Your own code to tell products apart</small>
            </label>
          </div>
        </section>

        <!-- 3. Category & occasions -->
        <section id="ap-organize" class="sp-card ap-step" :class="{ done: steps.organize }">
          <header class="ap-step-head">
            <span class="ap-num">{{ steps.organize ? "✓" : 3 }}</span>
            <div><h2>Category &amp; occasions</h2><p>How customers find it when they browse or shop for an event.</p></div>
          </header>
          <div data-change="category" class="sp-field">
            <span class="sp-label">Category</span>
            <div class="ap-chips">
              <button
                v-for="c in withCurrent(taxonomies.categories, form.category)"
                :key="c.value"
                type="button"
                class="sp-pick"
                :class="{ on: form.category === c.value }"
                :aria-pressed="form.category === c.value"
                @click="form.category = c.value; clear('category')"
              >{{ c.label }}</button>
            </div>
            <small v-if="errors.category" class="sp-error">{{ errors.category }}</small>
          </div>
          <div data-change="flower_type" class="sp-field ap-gap">
            <span class="sp-label">Flower type</span>
            <div class="ap-type-grid">
              <button
                v-for="t in withCurrent(taxonomies.flower_types, form.flower_type)"
                :key="t.value"
                type="button"
                class="ap-type"
                :class="{ on: form.flower_type === t.value }"
                :aria-pressed="form.flower_type === t.value"
                @click="form.flower_type = t.value; clear('flower_type')"
              >
                <strong>{{ t.label }}</strong>
                <span v-if="t.description">{{ t.description }}</span>
              </button>
            </div>
            <small v-if="errors.flower_type" class="sp-error">{{ errors.flower_type }}</small>
          </div>
          <div data-change="occasion_tags" class="sp-field ap-gap">
            <span class="sp-label">
              Occasions <em v-if="form.occasion_tags.length" class="ap-count">{{ form.occasion_tags.length }} picked</em>
            </span>
            <div class="ap-chips">
              <button
                v-for="o in taxonomies.occasions"
                :key="o.value"
                type="button"
                class="sp-pick ap-occasion"
                :class="{ on: form.occasion_tags.includes(o.value) }"
                :aria-pressed="form.occasion_tags.includes(o.value)"
                @click="toggleOccasion(o.value)"
              >
                <span v-if="form.occasion_tags.includes(o.value)" v-html="ICONS.check"></span>{{ o.label }}
              </button>
            </div>
            <small v-if="errors.occasion_tags" class="sp-error">{{ errors.occasion_tags }}</small>
            <small v-else>Pick every occasion it suits. Customers use “Shop by occasion”, and products without one don't show up there.</small>
          </div>
        </section>

        <!-- 4. Price -->
        <section id="ap-price" class="sp-card ap-step" :class="{ done: steps.price }">
          <header class="ap-step-head">
            <span class="ap-num">{{ steps.price ? "✓" : 4 }}</span>
            <div><h2>Price</h2><p>What it costs you and what customers pay.</p></div>
          </header>
          <div class="sp-grid-2">
            <label data-change="selling_price" class="sp-field">
              <span>Selling price</span>
              <div class="ap-money"><i>₱</i><input v-model.number="form.selling_price" type="number" min="0" step="0.01" class="sp-input" :class="{ invalid: errors.selling_price }" placeholder="0.00" @input="clear('selling_price')" /></div>
              <small v-if="errors.selling_price" class="sp-error">{{ errors.selling_price }}</small>
            </label>
            <label data-change="purchase_price" class="sp-field">
              <span>Your cost</span>
              <div class="ap-money"><i>₱</i><input v-model.number="form.purchase_price" type="number" min="0" step="0.01" class="sp-input" :class="{ invalid: errors.purchase_price }" placeholder="0.00" @input="clear('purchase_price')" /></div>
              <small v-if="errors.purchase_price" class="sp-error">{{ errors.purchase_price }}</small>
              <small v-else>Only you see this</small>
            </label>
          </div>
          <div v-if="form.selling_price > 0" class="ap-profit" :class="{ bad: profit <= 0 }">
            <span>{{ profit > 0 ? "You earn" : "You lose" }} <strong>{{ money(Math.abs(profit)) }}</strong> per sale</span>
            <span v-if="form.purchase_price > 0">{{ Math.round((profit / form.selling_price) * 100) }}% margin</span>
          </div>

          <label data-change="has_discount" class="ap-switch-row">
            <span class="sp-switch"><input v-model="form.has_discount" type="checkbox" @change="!form.has_discount && (form.discount_price = null)" /><span></span></span>
            <span><strong>Put it on sale</strong><small>Shows the regular price crossed out. You can schedule dates later from Products or Promotions.</small></span>
          </label>
          <div v-if="form.has_discount" class="sp-grid-2 ap-gap">
            <label data-change="discount_price" class="sp-field">
              <span>Sale price</span>
              <div class="ap-money"><i>₱</i><input v-model.number="form.discount_price" type="number" min="0" step="0.01" class="sp-input" :class="{ invalid: errors.discount_price }" placeholder="0.00" @input="clear('discount_price')" /></div>
              <small v-if="errors.discount_price" class="sp-error">{{ errors.discount_price }}</small>
            </label>
            <div class="sp-field">
              <span class="sp-label">Customers save</span>
              <div class="ap-save">{{ salePercent ? `${salePercent}% off` : "—" }}</div>
            </div>
          </div>
        </section>

        <!-- 5. Stock & selling -->
        <section id="ap-stock" class="sp-card ap-step" :class="{ done: steps.stock }">
          <header class="ap-step-head">
            <span class="ap-num">{{ steps.stock ? "✓" : 5 }}</span>
            <div><h2>Stock &amp; how it's sold</h2><p>How many you have and how customers buy it.</p></div>
          </header>
          <div data-change="selling_type" class="ap-sell-grid" role="radiogroup" aria-label="How it's sold">
            <button
              v-for="s in SELLING"
              :key="s.value"
              type="button"
              role="radio"
              class="ap-sell"
              :class="{ on: form.selling_type === s.value }"
              :aria-checked="form.selling_type === s.value"
              @click="form.selling_type = s.value"
            >
              <span class="ap-sell-icon" v-html="ICONS[s.icon]"></span>
              <strong>{{ s.label }}</strong>
              <span>{{ s.hint }}</span>
            </button>
          </div>
          <div v-if="form.selling_type === 'bouquet'" data-change="composition" class="ap-gap">
            <BouquetFlowersInput v-model="form.composition" :flowers="linkableFlowers" :error="errors.composition || ''" :cost-value="form.purchase_price" @update:model-value="clear('composition')" @use-cost="(v) => { form.purchase_price = v; clear('purchase_price'); }" />
          </div>
          <div v-if="colorCounts" data-change="colors" class="ap-gap">
            <ColorStockInputs :model-value="form.colors" :error="errors.color_stock || ''" @update:model-value="setColors" />
          </div>
          <div class="sp-grid-3 ap-gap">
            <label data-change="quantity_in_stock" class="sp-field">
              <span>In stock</span>
              <input
                v-model.number="form.quantity_in_stock"
                type="number"
                min="0"
                step="1"
                class="sp-input"
                :class="{ invalid: errors.quantity_in_stock }"
                :readonly="colorCounts || fromLinks !== null"
                @input="clear('quantity_in_stock')"
              />
              <small v-if="errors.quantity_in_stock" class="sp-error">{{ errors.quantity_in_stock }}</small>
              <small v-else-if="fromLinks !== null">From the linked flowers</small>
              <small v-else-if="colorCounts">The total of the colours above</small>
            </label>
            <label data-change="min_stock_level" class="sp-field">
              <span>Warn me at</span>
              <input v-model.number="form.min_stock_level" type="number" min="0" step="1" class="sp-input" :class="{ invalid: errors.min_stock_level }" @input="clear('min_stock_level')" />
              <small>Low-stock alert</small>
            </label>
            <label data-change="max_stock_level" class="sp-field">
              <span>Maximum</span>
              <input
                v-model.number="form.max_stock_level"
                type="number"
                :min="Math.max(1, form.min_stock_level || 0)"
                step="1"
                required
                class="sp-input"
                :class="{ invalid: errors.max_stock_level }"
                @input="clear('max_stock_level')"
              />
              <small v-if="errors.max_stock_level" class="sp-error">{{ errors.max_stock_level }}</small>
              <small v-else>The most you keep. Restocking fills up to this.</small>
            </label>
            <label data-change="season" class="sp-field">
              <span>Season</span>
              <select v-model="form.season" class="sp-select">
                <option v-for="s in SEASONS" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </label>
            <label data-change="preparation_days" class="sp-field">
              <span>Preparation time</span>
              <select v-model.number="form.preparation_days" class="sp-select">
                <option :value="0">Ready the same day</option>
                <option v-for="d in [1, 2, 3, 5, 7]" :key="d" :value="d">{{ d }} {{ d === 1 ? "day" : "days" }}</option>
              </select>
            </label>
          </div>
        </section>

        <!-- 6. Care & extras -->
        <section id="ap-extra" class="sp-card ap-step done-optional">
          <header class="ap-step-head">
            <span class="ap-num">6</span>
            <div><h2>Care &amp; extras <em class="ap-optional">optional</em></h2><p>Handling, a 3D model, and where you source it.</p></div>
          </header>
          <div class="ap-toggles">
            <label data-change="is_fragile" class="ap-switch-row">
              <span class="sp-switch"><input v-model="form.is_fragile" type="checkbox" /><span></span></span>
              <span><strong>Fragile</strong><small>Couriers are told to handle with care</small></span>
            </label>
            <label data-change="requires_refrigeration" class="ap-switch-row">
              <span class="sp-switch"><input v-model="form.requires_refrigeration" type="checkbox" /><span></span></span>
              <span><strong>Keep cool</strong><small>Needs refrigeration until delivery</small></span>
            </label>
          </div>
          <div class="sp-grid-2 ap-gap">
            <label data-change="care_instructions" class="sp-field sp-span-all">
              <span>Care instructions</span>
              <textarea v-model="form.care_instructions" class="sp-textarea" rows="2" placeholder="Trim stems at an angle, change water every two days…"></textarea>
            </label>
          </div>

          <div data-change="model_file" class="ap-model" :class="{ has: modelFile }" @dragover.prevent @drop.prevent="onModelDrop">
            <span class="ap-model-icon" v-html="ICONS.cube"></span>
            <div class="ap-model-text">
              <strong>{{ modelFile ? modelFile.name : "3D model" }}</strong>
              <span>{{ modelFile ? fileSize(modelFile.size) : form.selling_type === "per_piece_customizable" ? "Needed for the flower to appear in the 3D customizer" : "GLB (best), GLTF, OBJ or FBX · up to 50 MB" }}</span>
            </div>
            <button v-if="modelFile" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="modelFile = null">Remove</button>
            <label v-else class="sp-btn sp-btn-ghost sp-btn-sm ap-file">
              <input type="file" accept=".glb,.gltf,.obj,.fbx" @change="onModelPick" />Upload
            </label>
          </div>
          <p v-if="errors.model_file" class="sp-error">{{ errors.model_file }}</p>
          <!-- New tab, so this unsaved form stays as it is -->
          <p v-else-if="!modelFile && canPhotoTo3D" class="sp-hint">
            No 3D file? <router-link :to="{ name: 'VendorPhotoTo3D' }" target="_blank" rel="noopener">Make one from a photo</router-link>, download it, then upload it here.
          </p>

          <details class="ap-more">
            <summary>Supplier &amp; notes</summary>
            <div class="sp-grid-3 ap-gap">
              <label data-change="supplier_name" class="sp-field"><span>Supplier</span><input v-model="form.supplier_name" class="sp-input" maxlength="255" placeholder="e.g. Dangwa Wholesale" /></label>
              <label data-change="supplier_contact" class="sp-field"><span>Supplier contact</span><input v-model="form.supplier_contact" class="sp-input" maxlength="100" placeholder="Phone or email" /></label>
              <label data-change="supplier_sku" class="sp-field"><span>Supplier's code</span><input v-model="form.supplier_sku" class="sp-input" maxlength="255" /></label>
              <label data-change="notes" class="sp-field sp-span-all"><span>Private notes</span><textarea v-model="form.notes" class="sp-textarea" rows="2" placeholder="Only you and your staff see this"></textarea></label>
            </div>
          </details>
        </section>
      </form>

      <!-- Preview + checklist -->
      <aside class="ap-side">
        <div class="sp-card ap-preview">
          <p class="ap-side-label">Customer preview</p>
          <div class="ap-card">
            <div class="ap-card-media">
              <img v-if="images[0]" :src="images[0].url" alt="" />
              <span v-else class="ap-card-blank" v-html="ICONS.image"></span>
              <span v-if="salePercent" class="sp-chip pink ap-card-badge">−{{ salePercent }}%</span>
            </div>
            <div class="ap-card-body">
              <span class="ap-card-cat">{{ categoryLabel || "Category" }}</span>
              <strong>{{ form.product_name || "Product name" }}</strong>
              <div class="ap-card-price">
                <b>{{ money(form.has_discount && salePercent ? form.discount_price : form.selling_price) }}</b>
                <s v-if="form.has_discount && salePercent">{{ money(form.selling_price) }}</s>
              </div>
              <div v-if="form.occasion_tags.length" class="ap-card-tags">
                <span v-for="t in form.occasion_tags" :key="t">{{ t }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="sp-card ap-checklist">
          <p class="ap-side-label">Ready to publish?</p>
          <ul>
            <li v-for="s in CHECKLIST" :key="s.key" :class="{ done: steps[s.key] }">
              <a :href="`#${s.anchor}`" @click.prevent="jump(s.anchor)">
                <span class="ap-tick" v-html="steps[s.key] ? ICONS.check : ''"></span>{{ s.label }}
              </a>
            </li>
          </ul>
          <div class="ap-progress"><span :style="{ width: `${progress}%` }"></span></div>
          <button type="button" class="sp-btn sp-btn-primary ap-publish" :disabled="submitting" @click="submit('active')">
            {{ submitting === "active" ? "Publishing…" : "Publish product" }}
          </button>
          <button type="button" class="sp-btn sp-btn-ghost ap-publish" :disabled="submitting" @click="submit('draft')">
            {{ submitting === "draft" ? "Saving…" : "Save as draft" }}
          </button>
          <p class="sp-hint">Drafts stay hidden from customers until you publish them from Products.</p>
        </div>
      </aside>
    </div>
  </ShopLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import ShopLayout from "../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../components/vendor/shop/shopIcons";
import { apiMessage, money, useVendorCatalog } from "../../components/vendor/shop/useVendorCatalog";
import { useProductTaxonomies } from "../../composables/useProductTaxonomies";
import { useSubscriptionAccess } from "../../composables/useSubscriptionAccess";
import { SUBSCRIPTION_MODULES } from "../../constants/subscriptionModules";
import { useUnsavedChanges } from "../../composables/useUnsavedChanges";
import "../../components/vendor/shop/shop.css";
import FlowerColorPicker from "../../components/vendor/shop/FlowerColorPicker.vue";
import ColorStockInputs from "../../components/vendor/shop/ColorStockInputs.vue";
import { colorFields, colorProblem, countsPerColor, totalOf } from "../../utils/flowerColors.js";
import BouquetFlowersInput from "../../components/vendor/shop/BouquetFlowersInput.vue";
import { compositionField, compositionProblem, stockFromLinks } from "../../utils/bouquetComposition.js";

const route = useRoute();
const router = useRouter();
const { taxonomies, withCurrent } = useProductTaxonomies();
const { load: reloadCatalog } = useVendorCatalog();

const SEASONS = [
  { value: "all-year", label: "All year round" },
  { value: "spring", label: "Spring" },
  { value: "summer", label: "Summer" },
  { value: "autumn", label: "Autumn" },
  { value: "winter", label: "Winter" },
];
const SELLING = [
  { value: "bouquet", label: "Bouquet", hint: "Arranged and ready to give", icon: "flower" },
  { value: "per_piece", label: "Per piece", hint: "Single stems or items", icon: "tag" },
  { value: "per_piece_customizable", label: "3D customizable", hint: "Customers add it to bouquets they design", icon: "cube" },
];
const CHECKLIST = [
  { key: "photos", label: "At least one photo", anchor: "ap-photos" },
  { key: "basics", label: "Name, description, colour & SKU", anchor: "ap-basics" },
  { key: "organize", label: "Category, flower type & an occasion", anchor: "ap-organize" },
  { key: "price", label: "Selling price above your cost", anchor: "ap-price" },
  { key: "stock", label: "Stock count & maximum", anchor: "ap-stock" },
];

const blank = () => ({
  product_name: "",
  description: "",
  sku: "",
  category: "",
  flower_type: "",
  color: "",
  color_other: "",
  colors: [], // [{ value, name, quantity }]: every flower colour, the first is the main one
  composition: [], // a bouquet's flowers: [{ flower, color, count }]
  purchase_price: null,
  selling_price: null,
  has_discount: false,
  discount_price: null,
  quantity_in_stock: 0,
  min_stock_level: 5,
  max_stock_level: null,
  selling_type: "bouquet",
  season: "all-year",
  preparation_days: 0,
  supplier_name: "",
  supplier_contact: "",
  supplier_sku: "",
  care_instructions: "",
  occasion_tags: [],
  notes: "",
  is_fragile: false,
  requires_refrigeration: false,
});

const form = reactive(blank());
const images = ref([]);
const modelFile = ref(null);
// "Make one from a photo" is a Business-and-up feature
const { subscriptionAccess, subscriptionAllowsModule } = useSubscriptionAccess();
const canPhotoTo3D = computed(() => subscriptionAllowsModule(subscriptionAccess.value, SUBSCRIPTION_MODULES.PHOTO_TO_3D));
const errors = reactive({});
const submitting = ref(null);
const dropping = ref(false);
const dragFrom = ref(null);
const saved = ref(false);

// ── Derived ───────────────────────────────────────────────────────────────
const profit = computed(() => (Number(form.selling_price) || 0) - (Number(form.purchase_price) || 0));
const salePercent = computed(() => {
  const price = Number(form.selling_price) || 0;
  const sale = Number(form.discount_price) || 0;
  return form.has_discount && price > 0 && sale > 0 && sale < price ? Math.round((1 - sale / price) * 100) : 0;
});
// 3D-customizable flowers are used inside custom bouquets, not listed by occasion
const needsOccasion = computed(() => form.selling_type !== "per_piece_customizable");
const categoryLabel = computed(() => taxonomies.categories.find((c) => c.value === form.category)?.label);
const steps = computed(() => ({
  photos: images.value.length > 0,
  basics: !!(form.product_name.trim() && form.description.trim() && form.sku.trim() && !colorProblem(form.colors.slice(0, 1)) && !form.colors.some((c) => c.value === "other" && !String(c.name || "").trim())),
  organize: !!(form.category && form.flower_type && (form.occasion_tags.length || !needsOccasion.value)),
  price: Number(form.selling_price) > 0 && Number(form.purchase_price) >= 0 && form.purchase_price !== null && profit.value > 0 && (!form.has_discount || salePercent.value > 0),
  stock:
    Number.isInteger(Number(form.quantity_in_stock)) && Number(form.quantity_in_stock) >= 0 &&
    // The maximum (what restocking fills up to) is needed too
    form.max_stock_level !== null && form.max_stock_level !== "" && Number.isInteger(Number(form.max_stock_level)) &&
    Number(form.max_stock_level) >= Math.max(1, Number(form.min_stock_level || 0)),
}));
const progress = computed(() => (Object.values(steps.value).filter(Boolean).length / CHECKLIST.length) * 100);

// What View changes steps through, in page order; each falls back to its step card
const FIELD_LABELS = [
  ["ap-basics", { product_name: "Product name", description: "Description", colors: "Flower colours", sku: "SKU" }],
  ["ap-organize", { category: "Category", flower_type: "Flower type", occasion_tags: "Occasions" }],
  ["ap-price", { selling_price: "Selling price", purchase_price: "Your cost", has_discount: "On sale", discount_price: "Sale price" }],
  ["ap-stock", { selling_type: "How it's sold", composition: "Bouquet flowers", quantity_in_stock: "In stock", min_stock_level: "Warn me at", max_stock_level: "Maximum stock", season: "Season", preparation_days: "Preparation time" }],
  ["ap-extra", { is_fragile: "Fragile", requires_refrigeration: "Keep refrigerated", care_instructions: "Care instructions", supplier_name: "Supplier", supplier_contact: "Supplier contact", supplier_sku: "Supplier's code", notes: "Private notes" }],
];
const { markClean } = useUnsavedChanges({
  fields: () => [
    { key: "images", label: "Photos", value: images.value.map((img) => img.url), target: "#ap-photos" },
    ...FIELD_LABELS.flatMap(([section, labels]) =>
      Object.entries(labels).map(([key, label]) => ({ key, label, value: form[key], fallback: `#${section}` })),
    ),
    { key: "model_file", label: "3D model", value: modelFile.value?.name ?? null, fallback: "#ap-extra" },
  ],
  // A new product is kept as a draft from the bar; Publish stays at the top
  save: () => submit("draft"),
  discard: () => discardAll(),
  saving: () => !!submitting.value,
  saveLabel: "Save as draft",
  enabled: () => !saved.value,
});

function discardAll() {
  images.value.forEach((img) => URL.revokeObjectURL(img.url));
  images.value = [];
  modelFile.value = null;
  Object.assign(form, blank(), { selling_type: form.selling_type, occasion_tags: [] });
  Object.keys(errors).forEach((k) => delete errors[k]);
}

// ── Inputs ────────────────────────────────────────────────────────────────
const clear = (key) => delete errors[key];
// Two or more flower colours: each has its own stock, and the total is their sum
const multiColor = computed(() => countsPerColor(form.colors));

// A bouquet whose flowers are linked to single flowers in stock: its stock is
// what those flowers make, so colour counts and the In stock box don't apply
const linkableFlowers = ref([]);
api
  .get("vendor/products/linkable-flowers")
  .then(({ data }) => (linkableFlowers.value = data.data ?? []))
  .catch(() => {}); // linking just isn't offered then
const fromLinks = computed(() =>
  form.selling_type === "bouquet" ? stockFromLinks(form.composition, linkableFlowers.value) : null,
);
const colorCounts = computed(() => multiColor.value && fromLinks.value === null);
watch(fromLinks, (make) => {
  if (make !== null) form.quantity_in_stock = make;
  else if (multiColor.value) form.quantity_in_stock = totalOf(form.colors);
});
function setColors(list) {
  // Going from one colour to two: the stock entered so far belongs to the first
  if (countsPerColor(list) && !countsPerColor(form.colors) && list[0]) {
    list = list.map((c, i) => (i === 0 ? { ...c, quantity: Number(form.quantity_in_stock) || 0 } : c));
  }
  // Back to one colour: its count becomes the product's stock
  if (list.length === 1 && countsPerColor(form.colors)) {
    form.quantity_in_stock = Number(list[0].quantity) || 0;
  }
  form.colors = list;
  form.color = list[0]?.value || "";
  form.color_other = list[0]?.value === "other" ? String(list[0].name || "") : "";
  if (countsPerColor(list) && fromLinks.value === null) form.quantity_in_stock = totalOf(list);
  clear("color");
  clear("color_stock");
  clear("quantity_in_stock");
}
function toggleOccasion(value) {
  const i = form.occasion_tags.indexOf(value);
  if (i >= 0) form.occasion_tags.splice(i, 1);
  else form.occasion_tags.push(value);
  clear("occasion_tags");
}
function generateSku() {
  const part = (s, n) => (s || "").normalize("NFD").replace(/[^A-Za-z0-9]/g, "").slice(0, n).toUpperCase();
  const head = part(form.product_name.split(/\s+/)[0] || form.category, 5) || "ITEM";
  const color = part(form.color === "other" ? form.color_other : form.color, 3);
  const tail = Math.random().toString(36).slice(2, 6).toUpperCase();
  form.sku = [head, color, tail].filter(Boolean).join("-");
  clear("sku");
}

function addFiles(files) {
  clear("images");
  for (const file of files) {
    if (images.value.length >= 5) {
      toast.info("You can add up to 5 photos.");
      break;
    }
    if (!file.type.startsWith("image/")) continue;
    if (file.size > 10 * 1024 * 1024) {
      toast.error(`${file.name} is over 10 MB.`);
      continue;
    }
    const entry = reactive({ file, url: URL.createObjectURL(file), small: false, size: "" });
    images.value.push(entry);
    checkPhotoSize(entry);
  }
}

// Photos under 800px on the short side look blurry on the product page
const MIN_PHOTO_SIDE = 800;
function checkPhotoSize(entry) {
  const img = new Image();
  img.onload = () => {
    entry.small = Math.min(img.naturalWidth, img.naturalHeight) < MIN_PHOTO_SIDE;
    entry.size = `${img.naturalWidth}×${img.naturalHeight}`;
  };
  img.src = entry.url;
}

function onPick(e) {
  addFiles([...(e.target.files ?? [])]);
  e.target.value = "";
}
function onDrop(e) {
  dropping.value = false;
  if (dragFrom.value !== null) return;
  addFiles([...(e.dataTransfer?.files ?? [])]);
}
function moveImage(to) {
  const from = dragFrom.value;
  dragFrom.value = null;
  dropping.value = false;
  if (from === null || from === to) return;
  const [img] = images.value.splice(from, 1);
  images.value.splice(to, 0, img);
}
function makeCover(i) {
  const [img] = images.value.splice(i, 1);
  images.value.unshift(img);
}
function removeImage(i) {
  URL.revokeObjectURL(images.value[i].url);
  images.value.splice(i, 1);
}

function setModel(file) {
  clear("model_file");
  if (!file) return;
  if (!/\.(glb|gltf|obj|fbx)$/i.test(file.name)) {
    errors.model_file = "Use a GLB, GLTF, OBJ or FBX file.";
    return;
  }
  if (file.size > 50 * 1024 * 1024) {
    errors.model_file = "The 3D model must be 50 MB or smaller.";
    return;
  }
  modelFile.value = file;
}
const onModelPick = (e) => {
  setModel(e.target.files?.[0]);
  e.target.value = "";
};
const onModelDrop = (e) => setModel(e.dataTransfer?.files?.[0]);
const fileSize = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.ceil(b / 1024)} KB`);

function jump(anchor) {
  document.getElementById(anchor)?.scrollIntoView({ behavior: "smooth", block: "start" });
}

// ── Validation & submit ───────────────────────────────────────────────────
function validate(status) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  const e = errors;
  if (!form.product_name.trim()) e.product_name = "Give your product a name.";
  if (!images.value.length) e.images = "Add at least one photo.";

  // The server needs these for drafts too, so check them either way
  if (!form.description.trim()) e.description = "Add a short description.";
  if (!form.sku.trim()) e.sku = "Add a SKU, or press Generate.";
  const colorIssue = colorProblem(form.colors);
  if (colorIssue) {
    // Colour counts don't apply to a bouquet whose stock comes from linked flowers
    if (colorIssue.startsWith("Enter how many")) { if (fromLinks.value === null) e.color_stock = colorIssue; }
    else e.color = colorIssue;
  }
  if (!form.category) e.category = "Pick a category.";
  if (!form.flower_type) e.flower_type = "Pick a flower type.";
  if (!(Number(form.selling_price) > 0)) e.selling_price = "Set a selling price.";
  if (form.purchase_price === null || form.purchase_price === "" || Number(form.purchase_price) < 0) e.purchase_price = "Enter your cost (0 if none).";
  else if (Number(form.selling_price) > 0 && profit.value <= 0) e.selling_price = "The selling price must be higher than your cost.";
  if (form.has_discount && !salePercent.value) e.discount_price = "The sale price must be above ₱0 and below the selling price.";
  if (!Number.isInteger(Number(form.quantity_in_stock)) || Number(form.quantity_in_stock) < 0) e.quantity_in_stock = "Enter how many you have.";
  // The maximum is what restocking fills up to, so every product needs one
  const max = form.max_stock_level;
  if (max === null || max === "" || !Number.isInteger(Number(max)) || Number(max) < 1) e.max_stock_level = "Enter the most you keep in stock (1 or more).";
  else if (Number(max) < Number(form.min_stock_level || 0)) e.max_stock_level = "The maximum can't be lower than “Warn me at”.";
  const flowersIssue = form.selling_type === "bouquet" ? compositionProblem(form.composition) : "";
  if (flowersIssue) e.composition = flowersIssue;
  if (status === "active" && needsOccasion.value && !form.occasion_tags.length) e.occasion_tags = "Pick at least one occasion so customers can find it.";

  const first = Object.keys(e)[0];
  if (first) {
    toast.error(e[first]);
    const anchor = {
      images: "ap-photos", product_name: "ap-basics", description: "ap-basics", sku: "ap-basics", color: "ap-basics", color_stock: "ap-stock",
      category: "ap-organize", flower_type: "ap-organize", occasion_tags: "ap-organize",
      selling_price: "ap-price", purchase_price: "ap-price", discount_price: "ap-price", quantity_in_stock: "ap-stock", composition: "ap-stock", max_stock_level: "ap-stock",
    }[first];
    if (anchor) jump(anchor);
  }
  return !first;
}

async function submit(status) {
  if (submitting.value || !validate(status)) return;
  submitting.value = status;
  try {
    const fd = new FormData();
    const fields = {
      ...form,
      status,
      ...colorFields(form.colors),
      // The bouquet's flowers (only bouquets keep them)
      composition: form.selling_type === "bouquet" ? compositionField(form.composition) : "[]",
      discount_price: form.has_discount ? form.discount_price : "",
    };
    for (const [key, value] of Object.entries(fields)) {
      if (key === "occasion_tags" || key === "colors") continue;
      if (typeof value === "boolean") fd.append(key, value ? "1" : "0");
      else if (value !== null && value !== undefined && value !== "") fd.append(key, String(value).trim());
    }
    form.occasion_tags.forEach((t) => fd.append("occasion_tags[]", t));
    images.value.forEach((img) => fd.append("images[]", img.file, img.file.name));
    if (modelFile.value) fd.append("model_file", modelFile.value, modelFile.value.name);

    const { data } = await api.post("vendor/products", fd);
    saved.value = true;
    toast.success(status === "draft" ? "Saved as a draft." : `${form.product_name} is live in your shop.`);
    reloadCatalog();
    router.push({ name: "VendorProducts" });
    return data;
  } catch (err) {
    const serverErrors = err?.response?.data?.errors;
    if (serverErrors) {
      for (const [key, list] of Object.entries(serverErrors)) errors[key.split(".")[0]] = [].concat(list)[0];
    }
    toast.error(apiMessage(err, "Couldn't save the product. Please try again."));
  } finally {
    submitting.value = null;
  }
}


onMounted(() => {
  // "Add flower" on the 3D Bouquets page opens this form preset for the customizer
  if (["per_piece_customizable", "per_piece", "bouquet"].includes(route.query.selling_type)) {
    form.selling_type = route.query.selling_type;
  }
  // The preset type isn't an edit
  markClean();
});
onBeforeUnmount(() => {
  images.value.forEach((img) => URL.revokeObjectURL(img.url));
});
</script>

<style scoped>
.ap-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 18px;
  align-items: start;
}
.ap-form {
  display: grid;
  gap: 16px;
}
.ap-step {
  scroll-margin-top: 16px;
}
.ap-step-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
}
.ap-step-head h2 {
  margin: 0;
  font-size: 15.5px;
  font-weight: 600;
  color: var(--v-text);
}
.ap-step-head p {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--v-muted);
}
.ap-num {
  flex: none;
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 10px;
  background: var(--v-subtle-2);
  color: var(--v-text-2);
  font-size: 13px;
  font-weight: 600;
  transition: background 0.2s, color 0.2s;
}
.ap-step.done .ap-num {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.ap-optional {
  margin-left: 6px;
  font-size: 11px;
  font-style: normal;
  font-weight: 500;
  color: var(--v-faint);
}
.ap-gap {
  margin-top: 16px;
}

/* Photos */
.ap-photos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
  gap: 10px;
  padding: 4px;
  border-radius: 16px;
  transition: background 0.15s;
}
.ap-photos.dragging {
  background: var(--bc-accent-soft);
}
.ap-photo {
  position: relative;
  margin: 0;
  aspect-ratio: 1;
  border-radius: 14px;
  overflow: hidden;
  background: var(--v-subtle);
  border: 1px solid var(--v-border);
  cursor: grab;
}
.ap-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ap-cover {
  position: absolute;
  left: 8px;
  bottom: 8px;
  padding: 2px 9px;
  border-radius: 999px;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  font-size: 11px;
  font-weight: 600;
}
.ap-make-cover {
  position: absolute;
  left: 8px;
  bottom: 8px;
  padding: 3px 9px;
  border: 0;
  border-radius: 999px;
  background: rgba(26, 32, 44, 0.75);
  color: #fff;
  font-family: "Poppins", sans-serif;
  font-size: 11px;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s;
}
.ap-photo:hover .ap-make-cover,
.ap-make-cover:focus-visible {
  opacity: 1;
}
.ap-x {
  position: absolute;
  top: 7px;
  right: 7px;
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--v-glass);
  color: var(--v-red-text);
  cursor: pointer;
}
.ap-x :deep(svg) {
  width: 13px;
  height: 13px;
}
.ap-add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 10px;
  border: 1.5px dashed var(--bc-accent-border);
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  text-align: center;
  cursor: pointer;
}
.ap-add.big {
  grid-column: 1 / -1;
  aspect-ratio: auto;
  min-height: 170px;
}
.ap-add strong {
  font-size: 13px;
  font-weight: 600;
}
.ap-add small {
  font-size: 11.5px;
  color: var(--v-green-text);
}
.ap-add :deep(svg) {
  width: 24px;
  height: 24px;
}
.ap-add input,
.ap-file input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}
.ap-add:focus-within {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 25%, transparent);
}

/* Basics */
.ap-colors {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ap-swatch {
  width: 30px;
  height: 30px;
  padding: 0;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  background: var(--sw);
  box-shadow: 0 0 0 1px var(--v-border-strong);
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;
}
.ap-swatch:hover {
  transform: scale(1.08);
}
.ap-swatch.on {
  box-shadow: 0 0 0 2px var(--bc-accent);
  transform: scale(1.1);
}
.ap-inline {
  display: flex;
  gap: 8px;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

/* Organize */
.ap-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ap-occasion :deep(svg) {
  width: 13px;
  height: 13px;
}
.ap-count {
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  color: var(--v-text-2);
  font-size: 11px;
  font-style: normal;
  font-weight: 600;
}
.ap-count.full {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.ap-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 8px;
}
.ap-type {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 13px;
  background: var(--v-surface);
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.ap-type strong {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.ap-type span {
  font-size: 11.5px;
  color: var(--v-muted);
}
.ap-type.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}

/* Price */
.ap-money {
  position: relative;
}
.ap-money i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-style: normal;
  color: var(--v-faint);
  font-size: 13.5px;
}
.ap-money .sp-input {
  padding-left: 28px;
}
.ap-profit {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
  padding: 11px 14px;
  border-radius: 12px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
  font-size: 13px;
}
.ap-profit.bad {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.ap-switch-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 16px;
  cursor: pointer;
}
.ap-switch-row strong {
  display: block;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.ap-switch-row small {
  display: block;
  font-size: 12px;
  color: var(--v-muted);
}
.ap-switch-row .sp-switch {
  margin-top: 2px;
}
.ap-save {
  padding: 10px 12px;
  border-radius: 11px;
  background: var(--v-pink-soft);
  color: var(--v-pink-text);
  font-weight: 600;
  font-size: 14px;
}

/* Stock & selling */
.ap-sell-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.ap-sell {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
  padding: 14px;
  border: 1px solid var(--v-border-strong);
  border-radius: 14px;
  background: var(--v-surface);
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.ap-sell strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.ap-sell span:last-child {
  font-size: 11.5px;
  color: var(--v-muted);
}
.ap-sell.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.ap-sell-icon {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  margin-bottom: 4px;
  border-radius: 10px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-strong);
}
.ap-sell-icon :deep(svg) {
  width: 17px;
  height: 17px;
}

/* Extras */
.ap-toggles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 16px;
}
.ap-toggles .ap-switch-row {
  margin-top: 0;
}
.ap-model {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
  padding: 12px;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 14px;
  background: var(--v-subtle);
}
.ap-model.has {
  border-style: solid;
  border-color: var(--bc-accent-border);
}
.ap-model-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--v-teal-soft);
  color: var(--v-teal-text);
}
.ap-model-icon :deep(svg) {
  width: 20px;
  height: 20px;
}
.ap-model-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.ap-model-text strong {
  font-size: 13px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ap-model-text span {
  font-size: 11.5px;
  color: var(--v-faint);
}
.ap-file {
  position: relative;
}
.ap-more {
  margin-top: 16px;
  border-top: 1px solid var(--v-border);
  padding-top: 12px;
}
.ap-more summary {
  font-size: 13px;
  font-weight: 600;
  color: var(--bc-accent-deep);
  cursor: pointer;
}

/* Side */
.ap-side {
  position: sticky;
  top: 16px;
  display: grid;
  gap: 14px;
}
.ap-side-label {
  margin: 0 0 12px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--v-faint);
}
.ap-card {
  border: 1px solid var(--v-border);
  border-radius: 16px;
  overflow: hidden;
}
.ap-card-media {
  position: relative;
  aspect-ratio: 4 / 3;
  background: var(--bc-accent-soft);
}
.ap-card-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ap-card-blank {
  display: grid;
  place-items: center;
  height: 100%;
  color: var(--bc-accent-border);
}
.ap-card-blank :deep(svg) {
  width: 36px;
  height: 36px;
}
.ap-card-badge {
  position: absolute;
  top: 10px;
  right: 10px;
}
.ap-card-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 12px 14px 14px;
}
.ap-card-cat {
  font-size: 11.5px;
  color: var(--v-faint);
}
.ap-card-body strong {
  font-size: 14.5px;
  font-weight: 600;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.ap-card-price b {
  font-size: 15px;
  color: var(--v-text);
}
.ap-card-price s {
  margin-left: 6px;
  font-size: 12px;
  color: var(--v-faint);
}
.ap-card-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 6px;
}
.ap-card-tags span {
  padding: 2px 9px;
  border-radius: 999px;
  background: var(--v-pink-soft);
  color: var(--v-pink-text);
  font-size: 11px;
  font-weight: 500;
}
.ap-checklist ul {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
  display: grid;
  gap: 4px;
}
.ap-checklist a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 4px;
  border-radius: 9px;
  font-size: 13px;
  color: var(--v-text-2);
  text-decoration: none;
}
.ap-checklist a:hover {
  background: var(--v-subtle);
}
.ap-checklist li.done a {
  color: var(--bc-accent-strong);
}
.ap-tick {
  flex: none;
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border: 1.5px solid var(--v-border-strong);
  border-radius: 50%;
  color: #fff;
}
.ap-tick :deep(svg) {
  width: 12px;
  height: 12px;
}
.ap-checklist li.done .ap-tick {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
}
.ap-progress {
  height: 6px;
  margin-bottom: 14px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  overflow: hidden;
}
.ap-progress span {
  display: block;
  height: 100%;
  background: var(--bc-accent);
  transition: width 0.3s;
}
.ap-publish {
  width: 100%;
  margin-bottom: 8px;
}
@media (max-width: 1100px) {
  .ap-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .ap-side {
    position: static;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .ap-side {
    grid-template-columns: minmax(0, 1fr);
  }
  .ap-sell-grid,
  .ap-toggles {
    grid-template-columns: minmax(0, 1fr);
  }
}

.ap-lowres {
  position: absolute;
  left: 6px;
  bottom: 6px;
  padding: 3px 8px;
  border-radius: 999px;
  background: #8a5a12;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
}
.ap-lowres-note {
  margin: 10px 0 0;
  font-size: 13px;
  color: var(--v-text-2, #5f6776);
}
</style>
