<template>
  <vendorHeader />
  <div class="cz-page">
    <VendorSidebar />

    <main class="cz-main">
      <ShopLayout embedded />
      <header class="cz-intro">
        <div>
          <p class="cz-kicker">Flower customizer</p>
          <h1>3D Bouquets</h1>
          <p class="cz-lede">
            Choose what customers can use when they build a bouquet in 3D: wrappers, flowers,
            fillers and ribbons.
          </p>
        </div>
      </header>

      <nav class="cz-tabs" role="tablist" aria-label="Customization items">
        <button
          v-for="tab in TABS"
          :key="tab.key"
          :id="`cz-tab-${tab.key}`"
          class="cz-tab"
          :class="{ active: activeTab === tab.key }"
          role="tab"
          type="button"
          :aria-selected="activeTab === tab.key"
          :aria-controls="`cz-panel-${tab.key}`"
          @click="switchTab(tab.key)"
        >
          <span>{{ tab.label }}</span>
          <span class="cz-count">{{ tabCount(tab.key) }}</span>
        </button>
      </nav>

      <!-- Flowers: customizable products live in Products, so this tab lists them. -->
      <section
        v-if="activeTab === 'flowers'"
        id="cz-panel-flowers"
        class="cz-panel"
        role="tabpanel"
        aria-labelledby="cz-tab-flowers"
      >
        <div class="cz-panel-head">
          <div>
            <h2>Flowers</h2>
            <p>
              Products sold as <strong>Per Piece (Customizable)</strong> with a 3D model appear in the
              customizer. Stock, price and description are managed in Products and Inventory.
            </p>
          </div>
          <router-link
            class="cz-btn cz-btn-primary"
            :to="{ path: '/vendor/add-product', query: { selling_type: 'per_piece_customizable' } }"
          >
            + Add flower
          </router-link>
        </div>

        <div v-if="loadingFlowers" class="cz-grid" aria-hidden="true">
          <div v-for="n in 3" :key="n" class="cz-item">
            <span class="bc-skeleton cz-sk-thumb"></span>
            <div class="cz-item-body">
              <span class="bc-skeleton cz-sk-line cz-sk-line--title"></span>
              <span class="bc-skeleton cz-sk-line"></span>
              <span class="bc-skeleton cz-sk-line cz-sk-line--short"></span>
            </div>
          </div>
        </div>

        <div v-else-if="!flowers.length" class="cz-empty">
          <strong>No customizable flowers yet.</strong>
          <span>Add a flower with the selling type "Per Piece (Customizable)" and a 3D model.</span>
        </div>

        <div v-else class="cz-grid">
          <article v-for="flower in flowers" :key="flower.id" class="cz-item">
            <img v-if="flower.image" :src="flower.image" :alt="flower.product_name" class="cz-thumb" />
            <div v-else class="cz-thumb cz-thumb--empty" aria-hidden="true">🌸</div>
            <div class="cz-item-body">
              <div class="cz-item-title">
                <h3>{{ flower.product_name }}</h3>
                <span class="cz-price">{{ formatPrice(flower.price) }}</span>
              </div>
              <p class="cz-desc" :class="{ muted: !flower.description }">
                {{ flower.description || "No description yet." }}
              </p>
              <div class="cz-meta">
                <span class="cz-chip">{{ flower.stock }} in stock</span>
                <span v-if="flower.hasModel" class="cz-chip ok">3D model ready</span>
                <span v-else class="cz-chip warn">No 3D model, hidden from the customizer</span>
              </div>
              <div class="cz-actions">
                <router-link :to="{ name: 'VendorProducts' }" class="cz-link">Edit in Products</router-link>
              </div>
            </div>
          </article>
        </div>
      </section>

      <!-- Wrappers, fillers and ribbons share one form and one list. -->
      <section
        v-else
        :id="`cz-panel-${activeTab}`"
        class="cz-panel cz-split"
        role="tabpanel"
        :aria-labelledby="`cz-tab-${activeTab}`"
      >
        <form class="cz-card cz-form" novalidate @submit.prevent="submitForm">
          <div class="cz-form-head">
            <h2>{{ editingId ? `Edit ${current.singular}` : `Add ${current.singular}` }}</h2>
            <p>{{ current.hint }}</p>
          </div>

          <label class="cz-field">
            <span class="cz-label">Name</span>
            <input
              v-model.trim="form.name"
              class="cz-input"
              :class="{ invalid: errors.name }"
              type="text"
              maxlength="120"
              :placeholder="current.namePlaceholder"
            />
            <span v-if="errors.name" class="cz-error">{{ errors.name }}</span>
          </label>

          <!-- Card: photo, message allowance, and a price per size -->
          <template v-if="current.isCard">
            <div class="cz-field">
              <span class="cz-label">Card photo <small>(what customers see when choosing)</small></span>
              <label class="cz-upload cz-upload--image" :class="{ invalid: errors.image_file, filled: form.imagePreview }">
                <input type="file" accept="image/jpeg,image/png,image/webp" class="bc-sr-only" @change="onImageSelected" />
                <img v-if="form.imagePreview" :src="form.imagePreview" alt="" class="cz-upload-preview" />
                <span v-else class="cz-upload-icon" aria-hidden="true">⬆</span>
                <span class="cz-upload-text">
                  <strong>{{ form.imagePreview ? (form.imageFile ? form.imageFile.name : "Current photo kept") : "Upload a photo of the card" }}</strong>
                  <small>{{ form.imagePreview ? "Click to replace" : "JPG, PNG or WEBP · Max 5 MB" }}</small>
                </span>
              </label>
              <span v-if="errors.image_file" class="cz-error">{{ errors.image_file }}</span>
            </div>

            <div class="cz-field">
              <span class="cz-label">Message allowance</span>
              <div class="cz-allowance">
                <div class="cz-segment" role="radiogroup" aria-label="Count the message by">
                  <button
                    v-for="mode in LIMIT_TYPES"
                    :key="mode.value"
                    type="button"
                    role="radio"
                    :aria-checked="form.limitType === mode.value"
                    :class="{ active: form.limitType === mode.value }"
                    @click="form.limitType = mode.value"
                  >
                    {{ mode.label }}
                  </button>
                </div>
                <label class="cz-allowance-count">
                  <span>Up to</span>
                  <input
                    v-model="form.limit"
                    class="cz-input"
                    :class="{ invalid: errors.card_limit }"
                    type="number"
                    min="1"
                    max="1000"
                    step="1"
                    inputmode="numeric"
                    placeholder="50"
                    :aria-label="`Maximum ${form.limitType}`"
                  />
                  <span>{{ form.limitType }}</span>
                </label>
              </div>
              <span class="cz-hint">
                {{ form.limitType === "letters" ? "Letters and symbols count; spaces don't." : "Words are counted by the spaces between them." }}
                Customers can't write past this.
              </span>
              <span v-if="errors.card_limit" class="cz-error">{{ errors.card_limit }}</span>
            </div>

            <div class="cz-field">
              <span class="cz-label">Sizes and prices</span>
              <div class="cz-sizes">
                <div v-for="(size, index) in form.sizes" :key="index" class="cz-size-row">
                  <select v-model="size.label" class="cz-input" :aria-label="`Size ${index + 1}`">
                    <option v-for="label in CARD_SIZES" :key="label" :value="label" :disabled="isSizeTaken(label, index)">
                      {{ label }}
                    </option>
                  </select>
                  <div class="cz-money">
                    <span aria-hidden="true">₱</span>
                    <input
                      v-model="size.price"
                      class="cz-input"
                      :class="{ invalid: errors[`card_sizes.${index}.price`] }"
                      type="number"
                      min="0"
                      step="0.01"
                      inputmode="decimal"
                      placeholder="0.00"
                      :aria-label="`${size.label} price`"
                    />
                  </div>
                  <button
                    type="button"
                    class="cz-icon-btn"
                    :disabled="form.sizes.length === 1"
                    :aria-label="`Remove ${size.label}`"
                    title="Remove size"
                    @click="form.sizes.splice(index, 1)"
                  >
                    ✕
                  </button>
                </div>
              </div>
              <button v-if="form.sizes.length < CARD_SIZES.length" type="button" class="cz-link cz-add-size" @click="addSize">
                + Add size
              </button>
              <span v-if="errors.card_sizes" class="cz-error">{{ errors.card_sizes }}</span>
            </div>
          </template>

          <!-- Colour: picker, hex code and sliders all edit the same value. -->
          <div v-if="current.hasColor" class="cz-field">
            <span class="cz-label">Colour</span>
            <div class="cz-color-row">
              <label class="cz-swatch-picker" :style="{ background: form.color }">
                <span class="bc-sr-only">Pick a colour</span>
                <input type="color" :value="form.color" @input="setColorFromHex($event.target.value)" />
              </label>
              <div class="cz-hex">
                <span aria-hidden="true">#</span>
                <input
                  v-model="hexInput"
                  class="cz-input"
                  :class="{ invalid: errors.color_hex }"
                  type="text"
                  maxlength="7"
                  spellcheck="false"
                  aria-label="Hex colour code"
                  placeholder="C8A27A"
                  @input="onHexInput"
                  @blur="hexInput = form.color.slice(1).toUpperCase()"
                />
              </div>
            </div>
            <span v-if="errors.color_hex" class="cz-error">{{ errors.color_hex }}</span>

            <div class="cz-sliders">
              <label v-for="slider in SLIDERS" :key="slider.key" class="cz-slider">
                <span class="cz-slider-head">
                  <span>{{ slider.label }}</span>
                  <strong>{{ hsl[slider.key] }}{{ slider.unit }}</strong>
                </span>
                <input
                  type="range"
                  :min="0"
                  :max="slider.max"
                  :value="hsl[slider.key]"
                  :style="{ '--track': sliderTrack(slider.key) }"
                  @input="setHslPart(slider.key, Number($event.target.value))"
                />
              </label>
            </div>
          </div>

          <!-- Filler 3D model -->
          <div v-if="current.hasModel" class="cz-field">
            <span class="cz-label">3D model</span>
            <label class="cz-upload" :class="{ invalid: errors.model_file, filled: form.modelFile || form.modelUrl }">
              <input type="file" accept=".glb,.gltf" class="bc-sr-only" @change="onModelSelected" />
              <span class="cz-upload-icon" aria-hidden="true">⬆</span>
              <span v-if="form.modelFile" class="cz-upload-text">
                <strong>{{ form.modelFile.name }}</strong>
                <small>{{ formatSize(form.modelFile.size) }} · click to replace</small>
              </span>
              <span v-else-if="form.modelUrl" class="cz-upload-text">
                <strong>Current model kept</strong>
                <small>Click to upload a replacement</small>
              </span>
              <span v-else class="cz-upload-text">
                <strong>Upload a GLB or GLTF file</strong>
                <small>Max 50 MB</small>
              </span>
            </label>
            <span v-if="errors.model_file" class="cz-error">{{ errors.model_file }}</span>
          </div>

          <label v-if="!current.isCard" class="cz-field">
            <span class="cz-label">Price <small>(added to the bouquet, optional)</small></span>
            <div class="cz-money">
              <span aria-hidden="true">₱</span>
              <input
                v-model="form.price"
                class="cz-input"
                :class="{ invalid: errors.price }"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                placeholder="0.00"
              />
            </div>
            <span v-if="errors.price" class="cz-error">{{ errors.price }}</span>
          </label>

          <label class="cz-field">
            <span class="cz-label">Description</span>
            <textarea
              v-model="form.description"
              class="cz-input cz-textarea"
              rows="3"
              maxlength="500"
              :placeholder="current.descriptionPlaceholder"
            ></textarea>
            <span class="cz-counter">{{ form.description.length }}/500</span>
          </label>

          <div class="cz-form-actions">
            <button v-if="editingId" type="button" class="cz-btn cz-btn-ghost" :disabled="saving" @click="resetForm">
              Cancel
            </button>
            <button type="submit" class="cz-btn cz-btn-primary" :disabled="saving">
              {{ saving ? savingLabel : editingId ? "Save changes" : `Add ${current.singular}` }}
            </button>
          </div>
        </form>

        <div class="cz-list">
          <div class="cz-list-head">
            <h2>{{ current.label }}</h2>
            <span v-if="activeTab === 'wrapper' && options.wrapper.length" class="cz-list-note">
              {{ activeWrapperCount }} of {{ options.wrapper.length }} shown in the customizer
            </span>
          </div>

          <div v-if="loadingOptions" class="cz-stack" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="cz-row">
              <span class="bc-skeleton cz-sk-swatch"></span>
              <div class="cz-item-body">
                <span class="bc-skeleton cz-sk-line cz-sk-line--title"></span>
                <span class="bc-skeleton cz-sk-line"></span>
              </div>
            </div>
          </div>

          <div v-else-if="!currentItems.length" class="cz-empty">
            <strong>No {{ current.label.toLowerCase() }} yet.</strong>
            <span>Add your first {{ current.singular }} with the form.</span>
          </div>

          <ul v-else class="cz-stack">
            <li
              v-for="item in currentItems"
              :key="item.id"
              class="cz-row"
              :class="{ editing: editingId === item.id, off: item.type === 'wrapper' && !item.is_active }"
            >
              <img v-if="item.type === 'card' && item.image_url" :src="item.image_url" alt="" class="cz-card-thumb" />
              <span
                v-else-if="item.color_hex"
                class="cz-dot"
                :style="{ background: item.color_hex }"
                aria-hidden="true"
              ></span>
              <span v-else class="cz-dot cz-dot--model" aria-hidden="true">{{ item.type === "card" ? "✉" : "3D" }}</span>

              <div class="cz-item-body">
                <div class="cz-item-title">
                  <h3>{{ item.name }}</h3>
                  <span class="cz-price">{{ itemPriceLabel(item) }}</span>
                </div>
                <p v-if="item.color_hex" class="cz-hexline">{{ item.color_hex.toUpperCase() }}</p>
                <div v-if="item.type === 'card'" class="cz-meta">
                  <span class="cz-chip">Up to {{ item.card_limit }} {{ item.card_limit_type }}</span>
                  <span v-for="size in item.card_sizes || []" :key="size.label" class="cz-chip">
                    {{ size.label }} · {{ Number(size.price) ? formatPrice(size.price) : "Free" }}
                  </span>
                </div>
                <p class="cz-desc" :class="{ muted: !item.description }">
                  {{ item.description || "No description." }}
                </p>

                <div class="cz-row-foot">
                  <label v-if="item.type === 'wrapper'" class="cz-switch">
                    <input
                      type="checkbox"
                      :checked="item.is_active"
                      :disabled="togglingId === item.id"
                      @change="toggleWrapper(item, $event.target.checked)"
                    />
                    <span class="cz-switch-track" aria-hidden="true"></span>
                    <span class="cz-switch-text">{{ item.is_active ? "Shown in customizer" : "Hidden" }}</span>
                  </label>

                  <div class="cz-actions">
                    <template v-if="confirmingId === item.id">
                      <span class="cz-confirm-text">Delete this {{ current.singular }}?</span>
                      <button type="button" class="cz-link danger" :disabled="deletingId === item.id" @click="deleteItem(item)">
                        {{ deletingId === item.id ? "Deleting…" : "Delete" }}
                      </button>
                      <button type="button" class="cz-link" :disabled="deletingId === item.id" @click="confirmingId = null">
                        Keep
                      </button>
                    </template>
                    <template v-else>
                      <button type="button" class="cz-link" @click="startEdit(item)">Edit</button>
                      <button type="button" class="cz-link danger" @click="confirmingId = item.id">Delete</button>
                    </template>
                  </div>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import api from "../../plugins/axios";
import vendorHeader from "../../layouts/vendorHeader.vue";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import ShopLayout from "../../components/vendor/shop/ShopLayout.vue";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import "../../styles/bloomcraft.css";

const TABS = [
  { key: "wrapper", label: "Wrappers" },
  { key: "flowers", label: "Flowers" },
  { key: "filler", label: "Fillers" },
  { key: "ribbon", label: "Ribbons" },
  { key: "card", label: "Cards" },
];

const CARD_SIZES = ["Small", "Medium", "Large"];
const LIMIT_TYPES = [
  { value: "words", label: "Words" },
  { value: "letters", label: "Letters" },
];
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

const TYPE_CONFIG = {
  wrapper: {
    label: "Wrappers",
    singular: "wrapper",
    hasColor: true,
    hint: "Wrapping paper customers can choose. Switch a wrapper off to hide it without deleting it.",
    namePlaceholder: "e.g. Kraft paper",
    descriptionPlaceholder: "e.g. Matte recycled kraft paper, water resistant",
    defaultColor: "#d3b18f",
  },
  filler: {
    label: "Fillers",
    singular: "filler",
    hasModel: true,
    hint: "Greenery and small blooms customers can place around the flowers.",
    namePlaceholder: "e.g. Baby's breath",
    descriptionPlaceholder: "e.g. Fresh white baby's breath sprigs",
  },
  ribbon: {
    label: "Ribbons",
    singular: "ribbon",
    hasColor: true,
    hint: "Ribbon colours tied around the bouquet.",
    namePlaceholder: "e.g. Satin blush",
    descriptionPlaceholder: "e.g. 1-inch satin ribbon",
    defaultColor: "#caa6d9",
  },
  card: {
    label: "Cards",
    singular: "card",
    isCard: true,
    hint: "Message cards customers can add to a bouquet and write on. Customers see your photo when choosing; on the 3D bouquet the card is plain white.",
    namePlaceholder: "e.g. Folded greeting card",
    descriptionPlaceholder: "e.g. Thick matte card stock with an envelope",
  },
};

const SLIDERS = [
  { key: "h", label: "Hue", max: 360, unit: "°" },
  { key: "s", label: "Saturation", max: 100, unit: "%" },
  { key: "l", label: "Lightness", max: 100, unit: "%" },
];

const MAX_MODEL_BYTES = 50 * 1024 * 1024;

const activeTab = ref("wrapper");
const options = reactive({ wrapper: [], filler: [], ribbon: [], card: [] });
const flowers = ref([]);
const loadingOptions = ref(true);
const loadingFlowers = ref(true);
const saving = ref(false);
const uploadProgress = ref(0);
const editingId = ref(null);
const togglingId = ref(null);
const confirmingId = ref(null);
const deletingId = ref(null);
const errors = reactive({});

const form = reactive({
  name: "",
  description: "",
  price: "",
  color: "#d3b18f",
  modelFile: null,
  modelUrl: null,
  // Cards
  imageFile: null,
  imagePreview: null,
  limitType: "words",
  limit: "",
  sizes: [{ label: "Small", price: "" }],
});
const hexInput = ref("D3B18F");
const hsl = reactive({ h: 0, s: 0, l: 0 });

const current = computed(() => TYPE_CONFIG[activeTab.value] || TYPE_CONFIG.wrapper);
const currentItems = computed(() => options[activeTab.value] || []);
const activeWrapperCount = computed(() => options.wrapper.filter((item) => item.is_active).length);
const savingLabel = computed(() =>
  form.modelFile && uploadProgress.value > 0 && uploadProgress.value < 100
    ? `Uploading… ${uploadProgress.value}%`
    : "Saving…",
);

const tabCount = (key) => (key === "flowers" ? flowers.value.length : options[key].length);

onMounted(() => {
  resetForm();
  loadOptions();
  loadFlowers();
});

async function loadOptions() {
  loadingOptions.value = true;
  try {
    const { data } = await api.get("/vendor/customization-options");
    const items = Array.isArray(data?.data) ? data.data : [];
    for (const type of Object.keys(options)) {
      options[type] = items.filter((item) => item.type === type);
    }
  } catch (error) {
    console.error("Failed to load customization options:", error);
    toast.error("Could not load your customization items.");
  } finally {
    loadingOptions.value = false;
  }
}

async function loadFlowers() {
  loadingFlowers.value = true;
  try {
    const { data } = await api.get("/vendor/my-products");
    const products = Array.isArray(data?.data) ? data.data : [];
    flowers.value = products
      .filter((product) => product.selling_type === "per_piece_customizable")
      .map((product) => ({
        id: product.id,
        product_name: product.product_name,
        description: product.description,
        price: Number(product.discount_price || product.selling_price || 0),
        stock: Number(product.quantity_in_stock || 0),
        image: product.primary_image?.image_url || product.images?.[0]?.image_url || null,
        hasModel: (product.models || []).some((model) => model.model_url || model.model_path),
      }));
  } catch (error) {
    console.error("Failed to load customizable flowers:", error);
    flowers.value = [];
  } finally {
    loadingFlowers.value = false;
  }
}

function switchTab(key) {
  if (activeTab.value === key) return;
  activeTab.value = key;
  confirmingId.value = null;
  resetForm();
}

// ---- Colour helpers ----
function hexToHsl(hex) {
  const value = hex.replace("#", "");
  const r = parseInt(value.slice(0, 2), 16) / 255;
  const g = parseInt(value.slice(2, 4), 16) / 255;
  const b = parseInt(value.slice(4, 6), 16) / 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const l = (max + min) / 2;
  let h = 0;
  let s = 0;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
    else if (max === g) h = (b - r) / d + 2;
    else h = (r - g) / d + 4;
    h *= 60;
  }
  return { h: Math.round(h), s: Math.round(s * 100), l: Math.round(l * 100) };
}

function hslToHex(h, s, l) {
  const sat = s / 100;
  const light = l / 100;
  const k = (n) => (n + h / 30) % 12;
  const a = sat * Math.min(light, 1 - light);
  const channel = (n) => {
    const value = light - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return Math.round(value * 255).toString(16).padStart(2, "0");
  };
  return `#${channel(0)}${channel(8)}${channel(4)}`;
}

function setColorFromHex(hex) {
  const normalized = hex.toLowerCase();
  form.color = normalized;
  hexInput.value = normalized.slice(1).toUpperCase();
  Object.assign(hsl, hexToHsl(normalized));
  delete errors.color_hex;
}

function onHexInput() {
  const value = hexInput.value.replace(/[^0-9a-f]/gi, "").slice(0, 6);
  hexInput.value = value.toUpperCase();
  if (value.length === 6) {
    form.color = `#${value.toLowerCase()}`;
    Object.assign(hsl, hexToHsl(form.color));
    delete errors.color_hex;
  } else if (value.length === 3) {
    // Accept shorthand like F80 the same way CSS does.
    setColorFromHex(`#${value.split("").map((c) => c + c).join("")}`);
    hexInput.value = value.toUpperCase();
  }
}

function setHslPart(key, value) {
  hsl[key] = value;
  form.color = hslToHex(hsl.h, hsl.s, hsl.l);
  hexInput.value = form.color.slice(1).toUpperCase();
  delete errors.color_hex;
}

function sliderTrack(key) {
  if (key === "h") {
    return `linear-gradient(90deg, ${[0, 60, 120, 180, 240, 300, 360].map((h) => hslToHex(h, hsl.s || 70, hsl.l || 50)).join(", ")})`;
  }
  if (key === "s") return `linear-gradient(90deg, ${hslToHex(hsl.h, 0, hsl.l)}, ${hslToHex(hsl.h, 100, hsl.l)})`;
  return `linear-gradient(90deg, #000000, ${hslToHex(hsl.h, hsl.s, 50)}, #ffffff)`;
}

// ---- Form ----
function resetForm() {
  editingId.value = null;
  form.name = "";
  form.description = "";
  form.price = "";
  form.modelFile = null;
  form.modelUrl = null;
  form.imageFile = null;
  form.imagePreview = null;
  form.limitType = "words";
  form.limit = "";
  form.sizes = [{ label: "Small", price: "" }];
  uploadProgress.value = 0;
  Object.keys(errors).forEach((key) => delete errors[key]);
  setColorFromHex(current.value.defaultColor || "#d3b18f");
}

function startEdit(item) {
  resetForm();
  editingId.value = item.id;
  confirmingId.value = null;
  form.name = item.name;
  form.description = item.description || "";
  form.price = Number(item.price) ? String(item.price) : "";
  form.modelUrl = item.model_url || null;
  if (item.color_hex) setColorFromHex(item.color_hex);
  if (item.type === "card") {
    form.imagePreview = item.image_url || null;
    form.limitType = item.card_limit_type || "words";
    form.limit = item.card_limit ? String(item.card_limit) : "";
    form.sizes = (item.card_sizes || []).map((size) => ({ label: size.label, price: String(size.price ?? "") }));
    if (!form.sizes.length) form.sizes = [{ label: "Small", price: "" }];
  }
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function onModelSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file) return;
  const extension = file.name.split(".").pop().toLowerCase();
  if (!["glb", "gltf"].includes(extension)) {
    errors.model_file = "Choose a GLB or GLTF file.";
    return;
  }
  if (file.size > MAX_MODEL_BYTES) {
    errors.model_file = "The file is larger than 50 MB.";
    return;
  }
  delete errors.model_file;
  form.modelFile = file;
}

// ---- Cards ----
function onImageSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file) return;
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
    errors.image_file = "Choose a JPG, PNG or WEBP image.";
    return;
  }
  if (file.size > MAX_IMAGE_BYTES) {
    errors.image_file = "The image is larger than 5 MB.";
    return;
  }
  delete errors.image_file;
  form.imageFile = file;
  const reader = new FileReader();
  reader.onload = (e) => (form.imagePreview = e.target.result);
  reader.readAsDataURL(file);
}

const isSizeTaken = (label, index) => form.sizes.some((size, i) => i !== index && size.label === label);

function addSize() {
  const next = CARD_SIZES.find((label) => !form.sizes.some((size) => size.label === label));
  if (next) form.sizes.push({ label: next, price: "" });
}

function itemPriceLabel(item) {
  if (item.type === "card") {
    const prices = (item.card_sizes || []).map((size) => Number(size.price || 0));
    const lowest = prices.length ? Math.min(...prices) : 0;
    if (!lowest) return prices.some(Boolean) ? "From free" : "Free";
    return prices.length > 1 ? `From ${formatPrice(lowest)}` : `+ ${formatPrice(lowest)}`;
  }
  return Number(item.price) ? `+ ${formatPrice(item.price)}` : "Free";
}

function validate() {
  Object.keys(errors).forEach((key) => delete errors[key]);
  if (!form.name) errors.name = "Enter a name.";
  if (current.value.isCard) {
    if (!form.imageFile && !form.imagePreview) errors.image_file = "Upload a photo of the card.";
    const limit = Number(form.limit);
    if (!Number.isInteger(limit) || limit < 1 || limit > 1000) {
      errors.card_limit = `Enter how many ${form.limitType} customers can write (1 to 1000).`;
    }
    form.sizes.forEach((size, index) => {
      if (size.price === "" || Number.isNaN(Number(size.price)) || Number(size.price) < 0) {
        errors[`card_sizes.${index}.price`] = "Enter 0 or a positive amount.";
        errors.card_sizes = "Give every size a price (0 for free).";
      }
    });
  }
  if (current.value.hasColor && !/^#[0-9a-f]{6}$/i.test(form.color)) errors.color_hex = "Enter a 6-digit hex code.";
  if (current.value.hasModel && !form.modelFile && !form.modelUrl) errors.model_file = "Upload a 3D model for this filler.";
  if (form.price !== "" && (Number.isNaN(Number(form.price)) || Number(form.price) < 0)) {
    errors.price = "Enter 0 or a positive amount.";
  }
  return !Object.keys(errors).length;
}

async function submitForm() {
  if (saving.value || !validate()) return;

  const type = activeTab.value;
  const payload = new FormData();
  if (!editingId.value) payload.append("type", type);
  payload.append("name", form.name);
  payload.append("description", form.description.trim());
  if (current.value.isCard) {
    if (form.imageFile) payload.append("image_file", form.imageFile);
    payload.append("card_limit_type", form.limitType);
    payload.append("card_limit", String(Number(form.limit)));
    form.sizes.forEach((size, index) => {
      payload.append(`card_sizes[${index}][label]`, size.label);
      payload.append(`card_sizes[${index}][price]`, String(Number(size.price)));
    });
  } else {
    payload.append("price", form.price === "" ? "0" : String(Number(form.price)));
  }
  if (current.value.hasColor) payload.append("color_hex", form.color);
  if (form.modelFile) payload.append("model_file", form.modelFile);

  saving.value = true;
  uploadProgress.value = 0;
  try {
    const url = editingId.value
      ? `/vendor/customization-options/${editingId.value}`
      : "/vendor/customization-options";
    const { data } = await api.post(url, payload, {
      onUploadProgress: (event) => {
        if (event.total) uploadProgress.value = Math.round((event.loaded / event.total) * 100);
      },
    });
    const saved = data.data;
    const list = options[type];
    const index = list.findIndex((item) => item.id === saved.id);
    if (index === -1) list.push(saved);
    else list.splice(index, 1, saved);
    toast.success(editingId.value ? "Changes saved." : `${capitalize(current.value.singular)} added.`);
    resetForm();
  } catch (error) {
    const serverErrors = error?.response?.data?.errors;
    if (serverErrors) {
      for (const [key, messages] of Object.entries(serverErrors)) {
        errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
      }
    } else {
      toast.error(error?.response?.data?.message || "Could not save. Please try again.");
    }
  } finally {
    saving.value = false;
  }
}

async function toggleWrapper(item, isActive) {
  togglingId.value = item.id;
  const previous = item.is_active;
  item.is_active = isActive;
  try {
    await api.patch(`/vendor/customization-options/${item.id}/active`, { is_active: isActive });
    toast.success(isActive ? `${item.name} is shown in the customizer.` : `${item.name} is hidden from the customizer.`);
  } catch (error) {
    item.is_active = previous;
    toast.error("Could not update the wrapper.");
  } finally {
    togglingId.value = null;
  }
}

async function deleteItem(item) {
  deletingId.value = item.id;
  try {
    await api.delete(`/vendor/customization-options/${item.id}`);
    options[item.type] = options[item.type].filter((entry) => entry.id !== item.id);
    if (editingId.value === item.id) resetForm();
    toast.success(`${item.name} deleted.`);
  } catch (error) {
    toast.error("Could not delete this item.");
  } finally {
    deletingId.value = null;
    confirmingId.value = null;
  }
}

const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);
const formatPrice = (value) =>
  `₱${Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const formatSize = (bytes) =>
  bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<style scoped>
.cz-page {
  /* Skeleton shimmer colours in the vendor palette. */
  --ivory-deep: #edf0f3;
  --paper: #f8f9fa;
  margin-left: 260px;
  min-height: 100vh;
  background: #f8f9fa;
  color: #2d3748;
  font-family: "Poppins", sans-serif;
  transition: margin-left 0.3s ease;
}

.cz-page *,
.cz-page *::before,
.cz-page *::after {
  box-sizing: border-box;
}

.cz-main {
  max-width: 1280px;
  margin: 0 auto;
  padding: 28px 24px 56px;
}

.cz-intro h1 {
  margin: 4px 0 6px;
  font-size: 28px;
  font-weight: 600;
}

.cz-kicker {
  margin: 0;
  color: #48bb78;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.cz-lede {
  max-width: 640px;
  margin: 0;
  color: #718096;
  font-size: 14px;
  line-height: 1.6;
}

/* ---- Tabs ---- */
.cz-tabs {
  display: flex;
  gap: 4px;
  margin: 24px 0 20px;
  border-bottom: 2px solid #e2e8f0;
  overflow-x: auto;
  scrollbar-width: none;
}

.cz-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: -2px;
  padding: 12px 18px;
  border: none;
  border-bottom: 3px solid transparent;
  background: transparent;
  color: #9ca3af;
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
  transition: color 0.2s, border-color 0.2s;
}

.cz-tab:hover {
  color: #4a5568;
}

.cz-tab.active {
  border-bottom-color: #48bb78;
  color: #2d3748;
}

.cz-count {
  min-width: 22px;
  padding: 1px 7px;
  border-radius: 999px;
  background: #edf2f7;
  color: #4a5568;
  font-size: 12px;
  text-align: center;
}

.cz-tab.active .cz-count {
  background: #c6f6d5;
  color: #22543d;
}

/* ---- Panels and cards ---- */
.cz-split {
  display: grid;
  grid-template-columns: minmax(320px, 400px) minmax(0, 1fr);
  gap: 20px;
  align-items: start;
}

.cz-card,
.cz-list,
.cz-item,
.cz-empty {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
}

.cz-form {
  position: sticky;
  top: 88px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 22px;
}

.cz-form-head h2,
.cz-list-head h2,
.cz-panel-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.cz-form-head p,
.cz-panel-head p {
  margin: 4px 0 0;
  color: #718096;
  font-size: 13px;
  line-height: 1.55;
}

.cz-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cz-label {
  color: #4a5568;
  font-size: 13px;
  font-weight: 500;
}

.cz-label small {
  color: #a0aec0;
  font-weight: 400;
}

.cz-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #2d3748;
  font: inherit;
  font-size: 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.cz-input:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.15);
}

.cz-input.invalid,
.cz-upload.invalid {
  border-color: #e53e3e;
}

.cz-textarea {
  resize: vertical;
  min-height: 84px;
  line-height: 1.5;
}

.cz-counter {
  align-self: flex-end;
  color: #a0aec0;
  font-size: 12px;
}

.cz-error {
  color: #e53e3e;
  font-size: 12px;
}

/* ---- Colour ---- */
.cz-color-row {
  display: flex;
  gap: 10px;
}

.cz-swatch-picker {
  position: relative;
  flex-shrink: 0;
  width: 46px;
  height: 42px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 8px;
  box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.6);
  cursor: pointer;
}

.cz-swatch-picker input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.cz-swatch-picker:focus-within {
  outline: 2px solid #48bb78;
  outline-offset: 2px;
}

.cz-hex {
  position: relative;
  flex: 1;
}

.cz-hex > span,
.cz-money > span {
  position: absolute;
  top: 50%;
  left: 12px;
  color: #a0aec0;
  font-size: 14px;
  transform: translateY(-50%);
  pointer-events: none;
}

.cz-hex .cz-input {
  padding-left: 26px;
  font-family: ui-monospace, "SFMono-Regular", Menlo, monospace;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.cz-money {
  position: relative;
}

.cz-money .cz-input {
  padding-left: 28px;
}

.cz-sliders {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 6px;
  padding: 12px;
  border-radius: 10px;
  background: #f7fafc;
}

.cz-slider {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cz-slider-head {
  display: flex;
  justify-content: space-between;
  color: #718096;
  font-size: 12px;
}

.cz-slider-head strong {
  color: #2d3748;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.cz-slider input[type="range"] {
  width: 100%;
  height: 10px;
  margin: 0;
  border-radius: 999px;
  background: var(--track);
  appearance: none;
  -webkit-appearance: none;
  cursor: pointer;
}

.cz-slider input[type="range"]::-webkit-slider-thumb {
  width: 18px;
  height: 18px;
  border: 3px solid #fff;
  border-radius: 50%;
  background: transparent;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.25), 0 2px 6px rgba(0, 0, 0, 0.2);
  -webkit-appearance: none;
}

.cz-slider input[type="range"]::-moz-range-thumb {
  width: 14px;
  height: 14px;
  border: 3px solid #fff;
  border-radius: 50%;
  background: transparent;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.25), 0 2px 6px rgba(0, 0, 0, 0.2);
}

.cz-slider input[type="range"]:focus-visible {
  outline: 2px solid #48bb78;
  outline-offset: 3px;
}

/* ---- Upload ---- */
.cz-upload {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border: 1.5px dashed #cbd5e0;
  border-radius: 10px;
  background: #f7fafc;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.cz-upload:hover,
.cz-upload:focus-within {
  border-color: #48bb78;
  background: #f0fff4;
}

.cz-upload.filled {
  border-style: solid;
}

.cz-upload-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #fff;
  color: #48bb78;
  font-size: 16px;
}

.cz-upload-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  font-size: 13px;
}

.cz-upload-text strong {
  overflow: hidden;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cz-upload-text small {
  color: #a0aec0;
  font-size: 12px;
}

/* ---- Buttons ---- */
.cz-form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.cz-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 18px;
  border: 1px solid transparent;
  border-radius: 8px;
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
}

.cz-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.cz-btn-primary {
  background: #48bb78;
  color: #fff;
}

.cz-btn-primary:hover:not(:disabled) {
  background: #38a169;
}

.cz-btn-ghost {
  border-color: #e2e8f0;
  background: #fff;
  color: #4a5568;
}

.cz-btn-ghost:hover:not(:disabled) {
  border-color: #cbd5e0;
}

.cz-btn:focus-visible,
.cz-link:focus-visible,
.cz-tab:focus-visible {
  outline: 2px solid #48bb78;
  outline-offset: 2px;
}

.cz-link {
  padding: 0;
  border: none;
  background: none;
  color: #2f855a;
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
}

.cz-link:hover {
  text-decoration: underline;
}

.cz-link.danger {
  color: #c53030;
}

.cz-link:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* ---- List ---- */
.cz-list {
  padding: 22px;
}

.cz-list-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.cz-list-note {
  color: #718096;
  font-size: 13px;
}

.cz-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.cz-row {
  display: flex;
  gap: 14px;
  padding: 14px;
  border: 1px solid #edf2f7;
  border-radius: 10px;
  transition: border-color 0.2s, background 0.2s, opacity 0.2s;
}

.cz-row.editing {
  border-color: #48bb78;
  background: #f0fff4;
}

.cz-row.off .cz-dot,
.cz-row.off h3 {
  opacity: 0.5;
}

.cz-dot {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 10px;
}

.cz-dot--model {
  background: #ebf8ff;
  color: #2b6cb0;
  font-size: 12px;
  font-weight: 600;
}

/* ── Cards ─────────────────────────────────────────────────────────── */
.cz-card-thumb {
  flex-shrink: 0;
  width: 44px;
  height: 56px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 8px;
  object-fit: cover;
}

.cz-upload-preview {
  flex-shrink: 0;
  width: 56px;
  height: 72px;
  border-radius: 8px;
  object-fit: cover;
  background: #fff;
}

.cz-allowance {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.cz-segment {
  display: inline-flex;
  padding: 3px;
  border-radius: 10px;
  background: #edf2f7;
}

.cz-segment button {
  padding: 7px 14px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #4a5568;
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.cz-segment button.active {
  background: #fff;
  color: #2d3748;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.cz-segment button:focus-visible,
.cz-icon-btn:focus-visible {
  outline: 2px solid #48bb78;
  outline-offset: 2px;
}

.cz-allowance-count {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #4a5568;
  font-size: 13px;
}

.cz-allowance-count .cz-input {
  width: 84px;
}

.cz-hint {
  color: #718096;
  font-size: 12px;
}

.cz-sizes {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cz-size-row {
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr) 36px;
  gap: 8px;
  align-items: center;
}

.cz-icon-btn {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #718096;
  cursor: pointer;
}

.cz-icon-btn:hover:not(:disabled) {
  border-color: #e53e3e;
  color: #e53e3e;
}

.cz-icon-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.cz-add-size {
  align-self: flex-start;
  margin-top: 4px;
}

.cz-item-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.cz-item-title {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 12px;
}

.cz-item-title h3 {
  margin: 0;
  overflow: hidden;
  font-size: 15px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cz-price {
  flex-shrink: 0;
  color: #2f855a;
  font-size: 13px;
  font-weight: 600;
}

.cz-hexline {
  margin: 0;
  color: #a0aec0;
  font-family: ui-monospace, "SFMono-Regular", Menlo, monospace;
  font-size: 12px;
}

.cz-desc {
  margin: 2px 0 0;
  color: #4a5568;
  font-size: 13px;
  line-height: 1.55;
  word-break: break-word;
}

.cz-desc.muted {
  color: #a0aec0;
  font-style: italic;
}

.cz-row-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 8px;
}

.cz-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  margin-left: auto;
}

.cz-confirm-text {
  color: #c53030;
  font-size: 13px;
}

/* ---- Switch ---- */
.cz-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.cz-switch input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}

.cz-switch-track {
  position: relative;
  flex-shrink: 0;
  width: 40px;
  height: 22px;
  border-radius: 999px;
  background: #cbd5e0;
  transition: background 0.25s;
}

.cz-switch-track::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  transition: transform 0.25s ease;
}

.cz-switch input:checked + .cz-switch-track {
  background: #48bb78;
}

.cz-switch input:checked + .cz-switch-track::after {
  transform: translateX(18px);
}

.cz-switch input:focus-visible + .cz-switch-track {
  outline: 2px solid #48bb78;
  outline-offset: 2px;
}

.cz-switch input:disabled + .cz-switch-track {
  opacity: 0.6;
}

.cz-switch-text {
  color: #4a5568;
  font-size: 13px;
}

/* ---- Flowers tab ---- */
.cz-panel-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 16px;
}

.cz-panel-head p {
  max-width: 620px;
}

.cz-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.cz-item {
  display: flex;
  gap: 14px;
  padding: 14px;
}

.cz-thumb {
  flex-shrink: 0;
  width: 84px;
  height: 84px;
  border-radius: 10px;
  object-fit: cover;
  background: #f7fafc;
}

.cz-thumb--empty {
  display: grid;
  place-items: center;
  font-size: 28px;
}

.cz-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}

.cz-chip {
  padding: 3px 9px;
  border-radius: 999px;
  background: #edf2f7;
  color: #4a5568;
  font-size: 11.5px;
}

.cz-chip.ok {
  background: #c6f6d5;
  color: #22543d;
}

.cz-chip.warn {
  background: #fefcbf;
  color: #744210;
}

.cz-item .cz-actions {
  margin-top: 8px;
  margin-left: 0;
}

/* ---- Empty and skeleton ---- */
.cz-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 36px 20px;
  color: #718096;
  font-size: 13px;
  text-align: center;
}

.cz-list .cz-empty {
  border-style: dashed;
}

.cz-empty strong {
  color: #2d3748;
  font-size: 14px;
  font-weight: 600;
}

.cz-sk-thumb {
  flex-shrink: 0;
  width: 84px;
  height: 84px;
}

.cz-sk-swatch {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
}

.cz-sk-line {
  display: block;
  width: 100%;
  height: 12px;
}

.cz-sk-line--title {
  width: 55%;
  height: 16px;
}

.cz-sk-line--short {
  width: 35%;
}

/* ---- Responsive ---- */
@media (max-width: 1100px) {
  .cz-split {
    grid-template-columns: 1fr;
  }

  .cz-form {
    position: static;
  }
}

@media (max-width: 968px) {
  .cz-page {
    margin-left: 0 !important;
  }

  .cz-main {
    padding: 20px 16px 48px;
  }
}

@media (max-width: 560px) {
  .cz-tabs {
    gap: 0;
  }

  .cz-tab {
    gap: 6px;
    padding: 12px 10px;
    font-size: 13px;
  }

  .cz-panel-head {
    flex-direction: column;
  }

  .cz-panel-head .cz-btn {
    width: 100%;
  }

  .cz-form,
  .cz-list {
    padding: 16px;
  }

  .cz-form-actions .cz-btn {
    flex: 1;
  }
}
</style>
