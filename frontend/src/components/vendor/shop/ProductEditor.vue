<template>
  <Teleport to="body">
    <Transition name="sp-fade"><div v-if="productId" class="sp-backdrop" @click="close"></div></Transition>
    <Transition name="sp-slide">
      <aside v-if="productId" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="pe-title">
        <header class="sp-drawer-head">
          <div>
            <h3 id="pe-title">{{ form ? `Edit ${form.product_name || "product"}` : "Edit product" }}</h3>
            <p>Changes show on your shop as soon as you save.</p>
          </div>
          <button type="button" class="sp-icon-btn" aria-label="Close" @click="close"><span v-html="ICONS.close"></span></button>
        </header>

        <div class="sp-drawer-body">
          <div v-if="loadError" class="sp-banner error">
            <span v-html="ICONS.alert"></span>{{ loadError }}
            <button type="button" @click="fetch">Try again</button>
          </div>

          <template v-else-if="!form">
            <div class="bc-skeleton sp-sk-line w50"></div>
            <div class="bc-skeleton sp-sk-block"></div>
            <div class="bc-skeleton sp-sk-line w70"></div>
            <div class="bc-skeleton sp-sk-block"></div>
          </template>

          <form v-else id="pe-form" @submit.prevent="save">
            <!-- Photos -->
            <section class="sp-section">
              <h4>Photos <span class="pe-count">{{ photoCount }}/5</span></h4>
              <div class="pe-photos">
                <figure v-for="img in keptImages" :key="`old-${img.id}`" class="pe-photo">
                  <img :src="img.image_url" :alt="form.product_name" />
                  <span v-if="img === coverImage" class="pe-cover">Cover</span>
                  <button type="button" class="pe-photo-x" aria-label="Remove photo" @click="removedImageIds.push(img.id)">
                    <span v-html="ICONS.close"></span>
                  </button>
                </figure>
                <figure v-for="(img, i) in newImages" :key="img.url" class="pe-photo">
                  <img :src="img.url" alt="New photo" />
                  <span class="pe-cover new">New</span>
                  <button type="button" class="pe-photo-x" aria-label="Remove photo" @click="dropNewImage(i)">
                    <span v-html="ICONS.close"></span>
                  </button>
                </figure>
                <label v-if="photoCount < 5" class="pe-photo add">
                  <input type="file" accept="image/*" multiple @change="addImages" />
                  <span v-html="ICONS.upload"></span>
                  <span>Add</span>
                </label>
              </div>
              <p v-if="errors.images" class="sp-error">{{ errors.images }}</p>
            </section>

            <!-- Basics -->
            <section class="sp-section">
              <h4>Basics</h4>
              <div class="sp-grid-2">
                <label class="sp-field sp-span-all">
                  <span>Name</span>
                  <input v-model.trim="form.product_name" class="sp-input" :class="{ invalid: errors.product_name }" maxlength="255" required />
                  <small v-if="errors.product_name" class="sp-error">{{ errors.product_name }}</small>
                </label>
                <label class="sp-field sp-span-all">
                  <span>Description</span>
                  <textarea v-model="form.description" class="sp-textarea" rows="3" placeholder="What makes this arrangement special?"></textarea>
                </label>
                <label class="sp-field">
                  <span>Category</span>
                  <select v-model="form.category" class="sp-select">
                    <option v-for="c in withCurrent(taxonomies.categories, form.category)" :key="c.value" :value="c.value">{{ c.label }}</option>
                  </select>
                </label>
                <label class="sp-field">
                  <span>Flower type</span>
                  <select v-model="form.flower_type" class="sp-select">
                    <option value="">Not set</option>
                    <option v-for="t in withCurrent(taxonomies.flower_types, form.flower_type)" :key="t.value" :value="t.value">{{ t.label }}</option>
                  </select>
                </label>
                <label class="sp-field">
                  <span>Colour</span>
                  <select v-model="form.color" class="sp-select">
                    <option value="">Not set</option>
                    <option v-for="c in COLORS" :key="c" :value="c">{{ titleCase(c) }}</option>
                  </select>
                </label>
                <label v-if="form.color === 'other'" class="sp-field">
                  <span>Which colour?</span>
                  <input v-model.trim="form.color_other" class="sp-input" maxlength="100" />
                </label>
                <label class="sp-field">
                  <span>Sold as</span>
                  <select v-model="form.selling_type" class="sp-select">
                    <option value="per_piece">Per piece</option>
                    <option value="per_piece_customizable">Per piece (3D customizable)</option>
                    <option value="bouquet">Bouquet</option>
                  </select>
                </label>
                <label class="sp-field">
                  <span>Season</span>
                  <select v-model="form.season" class="sp-select">
                    <option v-for="s in SEASONS" :key="s.value" :value="s.value">{{ s.label }}</option>
                  </select>
                </label>
                <label class="sp-field">
                  <span>SKU</span>
                  <input v-model.trim="form.sku" class="sp-input" maxlength="255" placeholder="Optional" />
                  <small v-if="errors.sku" class="sp-error">{{ errors.sku }}</small>
                </label>
              </div>
            </section>

            <!-- Occasions -->
            <section class="sp-section">
              <h4>Occasions <span class="pe-count">up to 2</span></h4>
              <div class="pe-picks">
                <button
                  v-for="o in withCurrentList(taxonomies.occasions, form.occasion_tags)"
                  :key="o.value"
                  type="button"
                  class="sp-pick"
                  :class="{ on: form.occasion_tags.includes(o.value) }"
                  :disabled="!form.occasion_tags.includes(o.value) && form.occasion_tags.length >= 2"
                  @click="toggleOccasion(o.value)"
                >
                  {{ o.label }}
                </button>
              </div>
            </section>

            <!-- Pricing -->
            <section class="sp-section">
              <h4>Pricing</h4>
              <div class="sp-grid-2">
                <label class="sp-field">
                  <span>Selling price (₱)</span>
                  <input v-model.number="form.selling_price" type="number" min="0" step="0.01" class="sp-input" :class="{ invalid: errors.selling_price }" required />
                  <small v-if="errors.selling_price" class="sp-error">{{ errors.selling_price }}</small>
                </label>
                <label class="sp-field">
                  <span>Your cost (₱)</span>
                  <input v-model.number="form.purchase_price" type="number" min="0" step="0.01" class="sp-input" />
                </label>
              </div>
              <p v-if="margin !== null" class="pe-margin" :class="{ bad: margin < 0 }">
                {{ margin < 0 ? "Loss" : "Profit" }} of {{ money(Math.abs(margin)) }} per item
                <template v-if="form.purchase_price > 0">({{ Math.round((margin / form.purchase_price) * 100) }}%)</template>
              </p>
              <p class="sp-hint">Sale prices and their dates are set from the product's "Sale price" option or in Promotions.</p>
            </section>

            <!-- Care & handling -->
            <section class="sp-section">
              <h4>Care &amp; handling</h4>
              <div class="sp-grid-2">
                <label class="sp-field">
                  <span>Preparation time (days)</span>
                  <input v-model.number="form.preparation_days" type="number" min="0" max="60" class="sp-input" />
                </label>
                <div class="pe-toggles">
                  <label class="pe-toggle">
                    <span class="sp-switch"><input v-model="form.is_fragile" type="checkbox" /><span></span></span>
                    Fragile
                  </label>
                  <label class="pe-toggle">
                    <span class="sp-switch"><input v-model="form.requires_refrigeration" type="checkbox" /><span></span></span>
                    Needs refrigeration
                  </label>
                </div>
                <label class="sp-field sp-span-all">
                  <span>Care instructions</span>
                  <textarea v-model="form.care_instructions" class="sp-textarea" rows="2" placeholder="Trim stems, change water every two days…"></textarea>
                </label>
              </div>
            </section>

            <!-- 3D model -->
            <section class="sp-section">
              <h4>3D model</h4>
              <div class="pe-model">
                <span class="pe-model-icon" v-html="ICONS.cube"></span>
                <div class="pe-model-text">
                  <strong>{{ modelFile ? modelFile.name : hasModel ? "3D model uploaded" : "No 3D model" }}</strong>
                  <span>{{ modelFile ? "Will replace the current model when you save" : "GLB, GLTF, OBJ or FBX, up to 50 MB" }}</span>
                </div>
                <label class="sp-btn sp-btn-ghost sp-btn-sm pe-file">
                  <input type="file" accept=".glb,.gltf,.obj,.fbx" @change="pickModel" />
                  {{ hasModel || modelFile ? "Replace" : "Upload" }}
                </label>
              </div>
              <p v-if="errors.model_file" class="sp-error">{{ errors.model_file }}</p>
            </section>

            <!-- Visibility -->
            <section class="sp-section">
              <h4>Visibility</h4>
              <div class="pe-status">
                <button
                  v-for="s in STATUS_OPTIONS"
                  :key="s.value"
                  type="button"
                  class="pe-status-opt"
                  :class="{ on: form.status === s.value }"
                  @click="form.status = s.value"
                >
                  <strong>{{ s.label }}</strong>
                  <span>{{ s.hint }}</span>
                </button>
              </div>
            </section>
          </form>
        </div>

        <footer class="sp-drawer-foot">
          <button type="button" class="sp-btn sp-btn-ghost" @click="close">Cancel</button>
          <button type="submit" form="pe-form" class="sp-btn sp-btn-primary" :disabled="!form || saving">
            {{ saving ? "Saving…" : "Save changes" }}
          </button>
        </footer>
      </aside>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import { useProductTaxonomies } from "../../../composables/useProductTaxonomies";
import { SHOP_ICONS as ICONS } from "./shopIcons";
import { apiMessage, money, titleCase } from "./useVendorCatalog";

const props = defineProps({ productId: { type: Number, default: null } });
const emit = defineEmits(["close", "saved"]);

const { taxonomies, withCurrent } = useProductTaxonomies();

const COLORS = ["white", "yellow", "red", "pink", "purple", "orange", "blue", "green", "cream", "other"];
const SEASONS = [
  { value: "all-year", label: "All year round" },
  { value: "spring", label: "Spring" },
  { value: "summer", label: "Summer" },
  { value: "autumn", label: "Autumn" },
  { value: "winter", label: "Winter" },
];
const STATUS_OPTIONS = [
  { value: "active", label: "Live", hint: "Customers can buy it" },
  { value: "draft", label: "Draft", hint: "Hidden while you finish it" },
  { value: "inactive", label: "Archived", hint: "Hidden, kept for later" },
];

const form = ref(null);
const images = ref([]);
const hasModel = ref(false);
const removedImageIds = ref([]);
const newImages = ref([]);
const modelFile = ref(null);
const saving = ref(false);
const loadError = ref("");
const errors = reactive({});

const keptImages = computed(() => images.value.filter((img) => !removedImageIds.value.includes(img.id)));
const coverImage = computed(() => keptImages.value.find((i) => i.is_primary) ?? keptImages.value[0]);
const photoCount = computed(() => keptImages.value.length + newImages.value.length);
const margin = computed(() => {
  if (!form.value || form.value.purchase_price === "" || form.value.purchase_price === null) return null;
  return Number(form.value.selling_price || 0) - Number(form.value.purchase_price || 0);
});

const withCurrentList = (list, current) =>
  current.reduce((acc, value) => withCurrent(acc, value), list);

async function fetch() {
  loadError.value = "";
  form.value = null;
  try {
    const { data } = await api.get(`vendor/products/${props.productId}`);
    const p = data.data;
    images.value = p.images ?? [];
    hasModel.value = (p.models ?? []).length > 0;
    form.value = {
      product_name: p.product_name ?? "",
      description: p.description ?? "",
      sku: p.sku ?? "",
      category: p.category ?? "",
      flower_type: p.flower_type ?? "",
      color: p.color ?? "",
      color_other: p.color_other ?? "",
      selling_type: p.selling_type ?? "per_piece",
      season: p.season ?? "all-year",
      selling_price: Number(p.selling_price ?? 0),
      purchase_price: p.purchase_price !== null && p.purchase_price !== undefined ? Number(p.purchase_price) : "",
      occasion_tags: Array.isArray(p.occasion_tags) ? [...p.occasion_tags] : [],
      preparation_days: Number(p.preparation_days ?? 0),
      is_fragile: !!p.is_fragile,
      requires_refrigeration: !!p.requires_refrigeration,
      care_instructions: p.care_instructions ?? "",
      status: ["active", "draft", "inactive"].includes(p.status) ? p.status : "active",
    };
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't open this product.");
  }
}

function reset() {
  form.value = null;
  images.value = [];
  removedImageIds.value = [];
  newImages.value.forEach((img) => URL.revokeObjectURL(img.url));
  newImages.value = [];
  modelFile.value = null;
  Object.keys(errors).forEach((k) => delete errors[k]);
}

watch(
  () => props.productId,
  (id) => {
    reset();
    if (id) fetch();
  },
  { immediate: true },
);
onBeforeUnmount(reset);

function toggleOccasion(value) {
  const tags = form.value.occasion_tags;
  const i = tags.indexOf(value);
  if (i >= 0) tags.splice(i, 1);
  else if (tags.length < 2) tags.push(value);
}
function addImages(event) {
  delete errors.images;
  const files = [...(event.target.files ?? [])];
  event.target.value = "";
  for (const file of files) {
    if (photoCount.value >= 5) break;
    if (!file.type.startsWith("image/")) continue;
    if (file.size > 10 * 1024 * 1024) {
      errors.images = `${file.name} is over 10 MB.`;
      continue;
    }
    newImages.value.push({ file, url: URL.createObjectURL(file) });
  }
}
function dropNewImage(i) {
  URL.revokeObjectURL(newImages.value[i].url);
  newImages.value.splice(i, 1);
}
function pickModel(event) {
  delete errors.model_file;
  const file = event.target.files?.[0];
  event.target.value = "";
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

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.value.product_name) errors.product_name = "Give the product a name.";
  if (!(Number(form.value.selling_price) > 0)) errors.selling_price = "Set a selling price above ₱0.";
  if (photoCount.value === 0) errors.images = "Keep at least one photo.";
  return Object.keys(errors).length === 0;
}

async function save() {
  if (saving.value || !validate()) return;
  saving.value = true;
  try {
    const fd = new FormData();
    fd.append("_method", "PUT");
    for (const [key, value] of Object.entries(form.value)) {
      if (key === "occasion_tags") continue;
      if (typeof value === "boolean") fd.append(key, value ? "1" : "0");
      else fd.append(key, value ?? "");
    }
    if (form.value.occasion_tags.length) form.value.occasion_tags.forEach((t) => fd.append("occasion_tags[]", t));
    else fd.append("occasion_tags", "");
    removedImageIds.value.forEach((id) => fd.append("removed_image_ids[]", id));
    newImages.value.forEach((img) => fd.append("images[]", img.file));
    if (modelFile.value) fd.append("model_file", modelFile.value);

    await api.post(`vendor/products/${props.productId}`, fd);
    toast.success("Product saved.");
    emit("saved", props.productId);
    emit("close");
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors;
    if (fieldErrors) {
      for (const [key, list] of Object.entries(fieldErrors)) errors[key.split(".")[0]] = [].concat(list)[0];
    }
    toast.error(apiMessage(e, "Couldn't save the product."));
  } finally {
    saving.value = false;
  }
}

function close() {
  if (!saving.value) emit("close");
}
</script>

<style scoped>
.pe-count {
  margin-left: 6px;
  font-size: 11.5px;
  font-weight: 500;
  color: #a0aec0;
}
.pe-photos {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
  gap: 10px;
}
.pe-photo {
  position: relative;
  margin: 0;
  aspect-ratio: 1;
  border-radius: 14px;
  overflow: hidden;
  background: #f7faf8;
  border: 1px solid #edf2f7;
}
.pe-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pe-cover {
  position: absolute;
  left: 6px;
  bottom: 6px;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(26, 32, 44, 0.75);
  color: #fff;
  font-size: 10.5px;
  font-weight: 600;
}
.pe-cover.new {
  background: #48bb78;
}
.pe-photo-x {
  position: absolute;
  top: 6px;
  right: 6px;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  color: #c53030;
  cursor: pointer;
}
.pe-photo-x :deep(svg) {
  width: 13px;
  height: 13px;
}
.pe-photo.add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: 1.5px dashed #9ae6b4;
  background: #f7fdf9;
  color: #2f855a;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
}
.pe-photo.add input,
.pe-file input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}
.pe-photo.add :deep(svg) {
  width: 20px;
  height: 20px;
}
.pe-photo.add:focus-within {
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.25);
}
.pe-picks {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.pe-margin {
  margin: 10px 0 4px;
  font-size: 12.5px;
  font-weight: 500;
  color: #2f855a;
}
.pe-margin.bad {
  color: #c53030;
}
.pe-toggles {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  gap: 10px;
}
.pe-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: #2d3748;
  cursor: pointer;
}
.pe-model {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border: 1px solid #edf2f7;
  border-radius: 14px;
  background: #fbfdfc;
}
.pe-model-icon {
  display: grid;
  place-items: center;
  flex: none;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #e6fffa;
  color: #2c7a7b;
}
.pe-model-icon :deep(svg) {
  width: 20px;
  height: 20px;
}
.pe-model-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.pe-model-text strong {
  font-size: 13px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pe-model-text span {
  font-size: 11.5px;
  color: #a0aec0;
}
.pe-file {
  position: relative;
}
.pe-status {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.pe-status-opt {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 13px;
  background: #fff;
  font-family: "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.pe-status-opt strong {
  font-size: 13px;
  font-weight: 600;
  color: #1a202c;
}
.pe-status-opt span {
  font-size: 11.5px;
  color: #718096;
}
.pe-status-opt.on {
  border-color: #48bb78;
  background: #f0fff4;
}
@media (max-width: 560px) {
  .pe-status {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
