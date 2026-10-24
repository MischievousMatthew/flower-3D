<template>
  <section class="pp-card" aria-labelledby="pp-title">
    <div class="pp-head">
      <div>
        <h2 id="pp-title">Flowers <em>you love</em></h2>
        <p>Tell shops what you like and the days that matter to you, so your bouquets feel personal.</p>
      </div>
    </div>

    <div v-if="loadError" class="pp-alert">{{ loadError }} <button type="button" class="bc-link" @click="load">Try again</button></div>
    <div v-else-if="!data" class="pp-sk">
      <span v-for="n in 4" :key="n" class="bc-skeleton pp-sk-line"></span>
    </div>

    <template v-else>
      <!-- Sharing -->
      <label class="pp-share" :class="{ on: form.share_with_shops }">
        <span class="pp-share-text">
          <strong>{{ form.share_with_shops ? "Shared with your shops" : "Kept private" }}</strong>
          <small v-if="form.share_with_shops">The {{ shopsText }} you've ordered from or messaged can see your preferences and special days below.</small>
          <small v-else>Only you can see what you save here. Turn this on to let the {{ shopsText }} you've ordered from or messaged see it.</small>
        </span>
        <span class="pp-switch"><input v-model="form.share_with_shops" type="checkbox" aria-label="Share with shops" @change="save(true)" /><span></span></span>
      </label>

      <!-- Preferences -->
      <div class="pp-grid">
        <div v-for="f in FIELDS" :key="f.key" class="pp-field">
          <span class="pp-label">{{ f.label }}</span>
          <small class="pp-hint">{{ f.hint }}</small>
          <TagInput v-model="form[f.key]" :suggestions="SUGGEST[f.key]" :label="f.label" :placeholder="f.placeholder" />
        </div>
        <label class="pp-field pp-wide">
          <span class="pp-label">Anything else</span>
          <small class="pp-hint">Like “no plastic wrapping” or “I'm sensitive to strong scents”.</small>
          <textarea v-model="form.note" class="pp-input" rows="2" maxlength="500"></textarea>
        </label>
      </div>
      <div class="pp-actions">
        <small v-if="savedAt" class="pp-hint">Saved</small>
        <button type="button" class="bc-btn bc-btn--dark pp-btn" :disabled="busy === 'save' || !changed" @click="save()">{{ busy === "save" ? "Saving…" : "Save preferences" }}</button>
      </div>

      <!-- Special days -->
      <div class="pp-sub">
        <h3>Special <em>days</em></h3>
        <p>Birthdays, anniversaries and other days you buy flowers for. Add only what you want to share.</p>
      </div>
      <ul v-if="data.dates.length" class="pp-dates">
        <li v-for="d in data.dates" :key="d.id">
          <span class="pp-date-icon" :class="d.kind" v-html="kindOf(d.kind).icon"></span>
          <span class="pp-grow">
            <strong>{{ d.label }}</strong>
            <small>{{ monthDay(d) }}<template v-if="d.year">, {{ d.year }}</template> · {{ whenText(d.days_away) }}<template v-if="d.note"> · {{ d.note }}</template></small>
          </span>
          <button type="button" class="pp-x" :aria-label="`Remove ${d.label}`" :disabled="busy === `d${d.id}`" @click="removeDate(d)">×</button>
        </li>
      </ul>
      <form class="pp-date-form" @submit.prevent="addDate">
        <label class="pp-field">
          <span class="pp-label">Kind</span>
          <select v-model="dateForm.kind" class="pp-input">
            <option v-for="k in DATE_KINDS" :key="k.value" :value="k.value">{{ k.label }}</option>
          </select>
        </label>
        <label class="pp-field">
          <span class="pp-label">What is it?</span>
          <input v-model="dateForm.label" class="pp-input" maxlength="80" :placeholder="dateForm.kind === 'birthday' ? 'e.g. My birthday' : dateForm.kind === 'anniversary' ? 'e.g. Our wedding anniversary' : 'e.g. Mom’s retirement'" />
        </label>
        <label class="pp-field">
          <span class="pp-label">Month</span>
          <select v-model="dateForm.month" class="pp-input">
            <option value="" disabled>Choose…</option>
            <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
          </select>
        </label>
        <label class="pp-field">
          <span class="pp-label">Day</span>
          <select v-model="dateForm.day" class="pp-input">
            <option value="" disabled>Choose…</option>
            <option v-for="n in daysIn(dateForm.month)" :key="n" :value="n">{{ n }}</option>
          </select>
        </label>
        <label class="pp-field">
          <span class="pp-label">Year <small class="pp-hint">(optional)</small></span>
          <input v-model="dateForm.year" class="pp-input" type="number" min="1900" :max="thisYear" />
        </label>
        <button type="submit" class="pp-add" :disabled="busy === 'date' || !dateForm.month || !dateForm.day">+ Add day</button>
      </form>

      <!-- Offers from shops -->
      <div id="offers" class="pp-sub">
        <h3>Offers <em>for you</em></h3>
        <p>Shops you've bought from can send you offers, like a birthday treat or a welcome-back discount. Only if you say yes.</p>
      </div>
      <label class="pp-share" :class="{ on: data.accept_offers }">
        <span class="pp-share-text">
          <strong>{{ data.accept_offers ? "You get offers from your shops" : "No offers" }}</strong>
          <small>{{ data.accept_offers ? "At most one from each shop every few days. You can stop a single shop below." : "Turn this on to get offers from shops you've ordered from." }}</small>
        </span>
        <span class="pp-switch"><input type="checkbox" :checked="data.accept_offers" aria-label="Get offers from shops" :disabled="busy === 'offers'" @change="setOffers($event.target.checked)" /><span></span></span>
      </label>
      <div v-if="offers === null" class="pp-sk"><span class="bc-skeleton pp-sk-line"></span></div>
      <p v-else-if="!offers.length" class="pp-empty">{{ data.accept_offers ? "No offers yet. They'll show up here and in your notifications." : "Offers you get will show up here." }}</p>
      <ul v-else class="pp-offers">
        <li v-for="o in offers" :key="o.id" :class="{ fresh: o.new }">
          <div class="pp-offer-top">
            <small>{{ o.shop }} · {{ agoText(o.at) }}</small>
            <span v-if="o.new" class="pp-new">New</span>
          </div>
          <strong>{{ o.title }}</strong>
          <p>{{ o.body }}</p>
          <div class="pp-offer-foot">
            <span v-if="o.promotion" class="pp-promo" :class="{ ended: !o.promotion.running }">{{ o.promotion.label }} · {{ o.promotion.name }}{{ o.promotion.running ? ` · until ${shortDay(o.promotion.ends_at)}` : " · ended" }}</span>
            <router-link v-if="o.product_id" :to="{ name: 'ProductDetails', params: { id: String(o.product_id) } }" class="bc-link">View product</router-link>
            <button v-if="!isMuted(o.vendor_id)" type="button" class="pp-mute" :disabled="busy === `m${o.vendor_id}`" @click="mute(o.vendor_id, true)">Stop offers from {{ o.shop }}</button>
          </div>
        </li>
      </ul>
      <div v-if="data.muted_shops?.length" class="pp-muted">
        <span class="pp-label">Shops you stopped</span>
        <span v-for="m in data.muted_shops" :key="m.vendor_id" class="pp-muted-shop">
          {{ m.shop }}
          <button type="button" class="pp-mute" :disabled="busy === `m${m.vendor_id}`" @click="mute(m.vendor_id, false)">Allow again</button>
        </span>
      </div>

      <!-- Favorites -->
      <div class="pp-sub">
        <h3>Your <em>favorites</em></h3>
        <p>Tap the heart on any product to keep it here.<template v-if="form.share_with_shops"> Shops see the favorites that are theirs.</template></p>
      </div>
      <p v-if="!data.favorites.length" class="pp-empty">No favorites yet. Tap the heart on any product page to save it here.</p>
      <ul v-else class="pp-favs">
        <li v-for="f in data.favorites" :key="f.product_id">
          <router-link :to="{ name: 'ProductDetails', params: { id: String(f.product_id) } }" class="pp-fav">
            <span class="pp-fav-img"><img v-if="f.image" :src="f.image" alt="" loading="lazy" /><span v-else class="pp-fav-ph" v-html="CRM_ICONS.flower"></span></span>
            <span class="pp-grow"><strong>{{ f.name }}</strong><small>{{ f.available ? peso(f.price) : "No longer sold" }}</small></span>
          </router-link>
          <button type="button" class="pp-heart" :aria-label="`Remove ${f.name} from favorites`" :disabled="busy === `f${f.product_id}`" @click="unfavorite(f)"><span v-html="CRM_ICONS.heart"></span></button>
        </li>
      </ul>

      <!-- What shops saved -->
      <div class="pp-sub">
        <h3>What shops <em>saved</em> about you</h3>
        <p>Shops can note your preferences and special days when you tell them, so they remember next time. You can see it all here and remove it anytime.</p>
      </div>
      <p v-if="!data.shops.length" class="pp-empty">No shop has saved anything about you.</p>
      <article v-for="s in data.shops" :key="s.vendor_id" class="pp-shop">
        <header>
          <strong>{{ s.shop }}</strong>
          <button type="button" class="bc-link pp-remove" :disabled="busy === `s${s.vendor_id}`" @click="forget(s)">Remove</button>
        </header>
        <dl>
          <template v-for="f in FIELDS" :key="f.key">
            <template v-if="s[f.key].length"><dt>{{ f.label }}</dt><dd>{{ s[f.key].join(", ") }}</dd></template>
          </template>
          <template v-if="s.dates.length">
            <dt>Special days</dt>
            <dd>{{ s.dates.map((d) => `${d.label} (${monthDay(d)})`).join(", ") }}</dd>
          </template>
        </dl>
      </article>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../plugins/axios";
import TagInput from "../crm/TagInput.vue";
import { CRM_ICONS, DATE_KINDS, MONTHS, SUGGEST, blankDate, datePayload, daysIn, kindOf, monthDay, whenText } from "../crm/customerPrefs";

const FIELDS = [
  { key: "flowers", label: "Favorite flowers", hint: "The ones that always make you smile.", placeholder: "e.g. Tulips" },
  { key: "colors", label: "Colors", hint: "Shades you like in a bouquet.", placeholder: "e.g. Pastel" },
  { key: "avoid", label: "Please avoid", hint: "Allergies or flowers you'd rather not get.", placeholder: "e.g. Lilies" },
  { key: "occasions", label: "Occasions", hint: "What you usually buy flowers for.", placeholder: "e.g. Anniversary" },
];

const thisYear = new Date().getFullYear();
const offers = ref(null);
const data = ref(null);
const loadError = ref("");
const busy = ref("");
const savedAt = ref(false);
const dateForm = ref(blankDate());
const form = reactive({ flowers: [], colors: [], avoid: [], occasions: [], note: "", share_with_shops: false });
let saved = "";

const changed = computed(() => JSON.stringify(form) !== saved);
const shopsText = computed(() => {
  const n = data.value?.shop_count ?? 0;
  return n === 1 ? "1 shop" : n ? `${n} shops` : "shops";
});

const peso = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const reason = (e, fallback) => {
  const errors = e?.response?.data?.errors;
  return (errors && Object.values(errors)[0]?.[0]) || e?.response?.data?.message || fallback;
};

function use(d) {
  data.value = d;
  Object.assign(form, { flowers: [...d.flowers], colors: [...d.colors], avoid: [...d.avoid], occasions: [...d.occasions], note: d.note ?? "", share_with_shops: d.share_with_shops });
  saved = JSON.stringify(form);
}

async function load() {
  loadError.value = "";
  try {
    const res = await api.get("customer/preferences");
    use(res.data.data);
  } catch (e) {
    loadError.value = reason(e, "Couldn't load your preferences.");
  }
}

async function run(key, request, message) {
  busy.value = key;
  try {
    const res = await request();
    if (res.data?.data?.flowers) use(res.data.data);
    if (message) toast.success(message);
    return true;
  } catch (e) {
    toast.error(reason(e, "Couldn't save that."));
    return false;
  } finally {
    busy.value = "";
  }
}

/** The sharing switch saves straight away; the rest waits for the button */
async function save(fromSwitch = false) {
  const message = fromSwitch ? (form.share_with_shops ? "Your shops can now see your preferences." : "Your preferences are private now.") : "Preferences saved.";
  const ok = await run("save", () => api.put("customer/preferences", { ...form }), message);
  if (ok) savedAt.value = true;
  else if (fromSwitch) form.share_with_shops = !form.share_with_shops;
}

const isMuted = (vendorId) => (data.value?.muted_shops ?? []).some((m) => m.vendor_id === vendorId);
const shortDay = (d) => (d ? new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric" }) : "");
function agoText(at) {
  const d = Math.round((Date.now() - new Date(at).getTime()) / 86400000);
  return d < 1 ? "today" : d === 1 ? "yesterday" : d < 30 ? `${d} days ago` : new Date(at).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

async function loadOffers() {
  try {
    const res = await api.get("customer/offers");
    offers.value = res.data.data;
  } catch {
    offers.value = [];
  }
}

/** The offers switch saves straight away, without touching unsaved preference edits */
async function setOffers(on) {
  const prev = data.value.accept_offers;
  data.value.accept_offers = on;
  busy.value = "offers";
  try {
    await api.put("customer/preferences", { ...JSON.parse(saved), accept_offers: on });
    toast.success(on ? "You'll get offers from shops you've ordered from." : "You won't get offers anymore.");
  } catch (e) {
    data.value.accept_offers = prev;
    toast.error(reason(e, "Couldn't save that."));
  } finally {
    busy.value = "";
  }
}

async function mute(vendorId, stop) {
  busy.value = `m${vendorId}`;
  try {
    const res = stop ? await api.post(`customer/offers/shops/${vendorId}`) : await api.delete(`customer/offers/shops/${vendorId}`);
    data.value.muted_shops = res.data.data.muted_shops;
    toast.success(res.data.message);
  } catch (e) {
    toast.error(reason(e, "Couldn't save that."));
  } finally {
    busy.value = "";
  }
}

async function addDate() {
  if (await run("date", () => api.post("customer/preferences/dates", datePayload(dateForm.value)), "Day saved.")) dateForm.value = blankDate();
}
const removeDate = (d) => run(`d${d.id}`, () => api.delete(`customer/preferences/dates/${d.id}`), "Day removed.");

async function unfavorite(f) {
  if (await run(`f${f.product_id}`, () => api.delete(`customer/favorites/${f.product_id}`))) {
    data.value.favorites = data.value.favorites.filter((x) => x.product_id !== f.product_id);
  }
}

function forget(s) {
  if (!confirm(`Remove everything ${s.shop} saved about you?`)) return;
  run(`s${s.vendor_id}`, () => api.delete(`customer/preferences/shops/${s.vendor_id}`), "Removed.");
}

onMounted(() => {
  load();
  loadOffers();
});
</script>

<style scoped>
.pp-card {
  /* Tag input colors for the customer side */
  --ti-border: var(--line);
  --ti-bg: #fff;
  --ti-accent: var(--leaf-deep);
  --ti-tag-bg: color-mix(in srgb, var(--leaf) 16%, #fff);
  --ti-tag-text: var(--leaf-deep);
  --ti-text: var(--ink);
  --ti-muted: var(--ink-soft);

  padding: clamp(28px, 3vw, 44px);
  border: 1px solid var(--line-soft);
  border-radius: 22px;
  background: var(--paper);
  color: var(--ink);
}
.pp-head h2,
.pp-sub h3 {
  margin: 0;
  font-family: var(--font-display);
  font-weight: 500;
  color: var(--ink);
}
.pp-head h2 {
  font-size: clamp(30px, 3vw, 40px);
}
.pp-head em,
.pp-sub em {
  color: var(--leaf-deep);
}
.pp-head p,
.pp-sub p {
  margin: 6px 0 0;
  max-width: 620px;
  font-size: 14px;
  line-height: 1.6;
  color: var(--ink-soft);
}
.pp-sub {
  margin: 36px 0 14px;
  padding-top: 28px;
  border-top: 1px solid var(--line-soft);
}
.pp-sub h3 {
  font-size: 26px;
}
.pp-alert {
  margin-top: 20px;
  font-size: 14px;
  color: #b42318;
}
.pp-sk {
  display: grid;
  gap: 12px;
  margin-top: 24px;
}
.pp-sk-line {
  display: block;
  height: 44px;
  border-radius: 12px;
}

/* Sharing switch */
.pp-share {
  display: flex;
  align-items: center;
  gap: 18px;
  margin: 24px 0;
  padding: 18px 20px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.pp-share.on {
  border-color: color-mix(in srgb, var(--leaf) 55%, #fff);
  background: color-mix(in srgb, var(--leaf) 8%, #fff);
}
.pp-share-text {
  flex: 1;
  display: grid;
  gap: 3px;
}
.pp-share-text strong {
  font-size: 15px;
  font-weight: 600;
}
.pp-share-text small {
  font-size: 13px;
  line-height: 1.5;
  color: var(--ink-soft);
}
.pp-switch {
  position: relative;
  flex: none;
  width: 48px;
  height: 28px;
}
.pp-switch input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.pp-switch span {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: var(--line);
  transition: background 0.2s;
  pointer-events: none;
}
.pp-switch span::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s;
}
.pp-switch input:checked + span {
  background: var(--leaf);
}
.pp-switch input:checked + span::after {
  transform: translateX(20px);
}
.pp-switch input:focus-visible + span {
  outline: 2px solid var(--ink);
  outline-offset: 2px;
}

/* Fields */
.pp-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px 24px;
}
.pp-field {
  display: grid;
  gap: 4px;
  align-content: start;
}
.pp-wide {
  grid-column: 1 / -1;
}
.pp-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}
.pp-hint {
  font-size: 12.5px;
  color: var(--ink-soft);
}
.pp-input {
  width: 100%;
  padding: 11px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
  color: var(--ink);
  font-family: var(--font-sans);
  font-size: 14px;
  box-sizing: border-box;
}
.pp-input:focus {
  outline: none;
  border-color: var(--leaf-deep);
}
textarea.pp-input {
  resize: vertical;
}
.pp-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 14px;
  margin-top: 20px;
}
.pp-btn {
  padding: 13px 26px;
}
.pp-btn:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Dates */
.pp-dates {
  list-style: none;
  margin: 0 0 14px;
  padding: 0;
  display: grid;
  gap: 8px;
}
.pp-dates li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--line-soft);
  border-radius: 14px;
  background: #fff;
}
.pp-date-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--leaf) 14%, #fff);
  color: var(--leaf-deep);
}
.pp-date-icon.birthday {
  background: #fdf3dc;
  color: #a16207;
}
.pp-date-icon.anniversary {
  background: #fdecee;
  color: #c0364a;
}
.pp-fav-ph {
  display: inline-flex;
  color: var(--leaf);
}
.pp-fav-ph :deep(svg) {
  width: 24px;
  height: 24px;
}
.pp-grow {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 2px;
}
.pp-grow strong {
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pp-grow small {
  font-size: 12.5px;
  color: var(--ink-soft);
}
.pp-x {
  flex: none;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 50%;
  background: none;
  color: var(--ink-soft);
  font-size: 20px;
  cursor: pointer;
}
.pp-x:hover {
  background: var(--ivory-deep);
  color: #b42318;
}
.pp-date-form {
  display: grid;
  grid-template-columns: 1fr 1.6fr 1fr 0.7fr 1fr auto;
  align-items: end;
  gap: 10px;
}
.pp-add {
  padding: 0 18px;
  border: 1px dashed var(--leaf-deep);
  border-radius: 12px;
  background: none;
  color: var(--leaf-deep);
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}
.pp-add:disabled {
  opacity: 0.45;
  cursor: default;
}

/* Offers */
.pp-offers {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}
.pp-offers li {
  display: grid;
  gap: 4px;
  padding: 14px 16px;
  border: 1px solid var(--line-soft);
  border-radius: 16px;
  background: #fff;
}
.pp-offers li.fresh {
  border-color: color-mix(in srgb, var(--leaf) 55%, #fff);
}
.pp-offer-top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
.pp-offer-top small {
  font-size: 12.5px;
  color: var(--ink-soft);
}
.pp-new {
  padding: 1px 9px;
  border-radius: 999px;
  background: var(--leaf);
  color: #fff;
  font-size: 11.5px;
  font-weight: 600;
}
.pp-offers strong {
  font-size: 15px;
  font-weight: 600;
}
.pp-offers p {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--ink);
}
.pp-offer-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
  margin-top: 4px;
}
.pp-promo {
  padding: 3px 10px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--leaf) 16%, #fff);
  color: var(--leaf-deep);
  font-size: 12.5px;
  font-weight: 600;
}
.pp-promo.ended {
  background: var(--ivory-deep);
  color: var(--ink-soft);
}
.pp-mute {
  min-height: 44px;
  margin-left: auto;
  padding: 0 4px;
  border: 0;
  background: none;
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 13px;
  text-decoration: underline;
  cursor: pointer;
}
.pp-muted {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
  margin-top: 12px;
}
.pp-muted-shop {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding-left: 12px;
  border: 1px solid var(--line);
  border-radius: 999px;
  font-size: 13px;
}
.pp-muted-shop .pp-mute {
  margin-left: 0;
  min-height: 36px;
  padding: 0 12px 0 4px;
}

/* Favorites */
.pp-empty {
  margin: 0;
  font-size: 14px;
  color: var(--ink-soft);
}
.pp-favs {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 10px;
}
.pp-favs li {
  position: relative;
  display: flex;
  align-items: center;
  border: 1px solid var(--line-soft);
  border-radius: 16px;
  background: #fff;
}
.pp-fav {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  color: inherit;
  text-decoration: none;
}
.pp-fav-img {
  flex: none;
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  overflow: hidden;
  border-radius: 12px;
  background: var(--ivory-deep);
  font-size: 22px;
}
.pp-fav-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pp-heart {
  flex: none;
  display: grid;
  place-items: center;
  margin-right: 6px;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 50%;
  background: none;
  color: #e0475a;
  font-size: 19px;
  cursor: pointer;
}
.pp-heart:hover {
  background: #fdecee;
}

/* Shops */
.pp-shop {
  margin-bottom: 10px;
  padding: 16px 18px;
  border: 1px solid var(--line-soft);
  border-radius: 16px;
  background: #fff;
}
.pp-shop header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}
.pp-shop strong {
  font-size: 15px;
}
.pp-remove {
  min-height: 44px;
  padding: 0 6px;
  border: 0;
  background: none;
  color: #b42318;
  font-family: var(--font-sans);
  font-size: 13px;
  cursor: pointer;
}
.pp-shop dl {
  display: grid;
  grid-template-columns: 140px minmax(0, 1fr);
  gap: 6px 12px;
  margin: 0;
  font-size: 13px;
}
.pp-shop dt {
  color: var(--ink-soft);
}
.pp-shop dd {
  margin: 0;
}

@media (max-width: 760px) {
  .pp-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .pp-date-form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .pp-date-form > :nth-child(2),
  .pp-date-form > :last-child {
    grid-column: 1 / -1;
  }
  .pp-add {
    height: 44px;
  }
  .pp-shop dl {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
