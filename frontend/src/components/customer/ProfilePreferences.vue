<template>
  <section class="pp-card" aria-labelledby="pp-title">
    <header class="pp-head">
      <h2 id="pp-title">Flowers <em>you love</em></h2>
      <p>Tell shops what you like, so the bouquets they make for you feel personal. Everything here is optional.</p>
    </header>

    <div v-if="loadError" class="pp-alert" role="alert">
      {{ loadError }}
      <button type="button" class="pp-link" @click="load">Try again</button>
    </div>
    <div v-else-if="!data" class="pp-sk" aria-label="Loading">
      <span v-for="n in 4" :key="n" class="bc-skeleton pp-sk-line"></span>
    </div>

    <template v-else>
      <!-- ── 1. Taste ─────────────────────────────────────────────────── -->
      <div class="pp-block">
        <div class="pp-block__head">
          <span class="pp-num" aria-hidden="true">1</span>
          <div>
            <h3>Your flower taste</h3>
            <p>Tap the ones that fit you. Tap again to remove.</p>
          </div>
        </div>

        <fieldset v-for="q in QUESTIONS" :key="q.key" class="pp-q">
          <legend>
            {{ q.question }}
            <small v-if="q.hint">{{ q.hint }}</small>
          </legend>
          <div class="pp-chips">
            <button
              v-for="option in optionsFor(q.key)"
              :key="option"
              type="button"
              class="pp-chip"
              :class="{ on: picked(q.key, option), avoid: q.key === 'avoid' }"
              :aria-pressed="picked(q.key, option)"
              @click="toggle(q.key, option)"
            >
              <svg v-if="picked(q.key, option)" viewBox="0 0 24 24" aria-hidden="true">
                <path v-if="q.key === 'avoid'" d="M6 6l12 12M18 6 6 18" />
                <path v-else d="m5 12.5 4.5 4.5L19 7.5" />
              </svg>
              {{ option }}
            </button>
            <form class="pp-own" @submit.prevent="addOwn(q.key)">
              <input
                v-model="drafts[q.key]"
                type="text"
                maxlength="40"
                :placeholder="q.own"
                :aria-label="`Add your own: ${q.question}`"
              />
              <button type="submit" :disabled="!drafts[q.key].trim()" aria-label="Add">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
              </button>
            </form>
          </div>
        </fieldset>

        <label class="pp-q pp-note">
          <span class="pp-legend">Anything else shops should know? <small>Like "no plastic wrapping" or "I'm sensitive to strong scents".</small></span>
          <textarea v-model="form.note" rows="2" maxlength="500" placeholder="Write a note for shops (optional)"></textarea>
        </label>

        <!-- Who sees it, and saving -->
        <div class="pp-save">
          <div class="pp-who" role="radiogroup" aria-label="Who can see your taste and special days">
            <span class="pp-who__label">Who can see this?</span>
            <div class="pp-seg">
              <button type="button" role="radio" :aria-checked="!form.share_with_shops" :class="{ on: !form.share_with_shops }" :disabled="busy === 'save'" @click="setSharing(false)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                Only me
              </button>
              <button type="button" role="radio" :aria-checked="form.share_with_shops" :class="{ on: form.share_with_shops }" :disabled="busy === 'save'" @click="setSharing(true)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9l2-5h12l2 5M4 9h16v11H4zM9 20v-5h6v5" /></svg>
                My shops
              </button>
            </div>
            <small>{{ form.share_with_shops ? `The ${shopsText} you've ordered from or messaged can see it.` : "Nobody else can see it until you choose My shops." }}</small>
          </div>
          <div class="pp-save__btn">
            <span v-if="changed" class="pp-unsaved" role="status">Unsaved changes</span>
            <span v-else-if="savedAt" class="pp-saved" role="status">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
              Saved
            </span>
            <button type="button" class="pp-btn" :disabled="busy === 'save' || !changed" @click="save()">
              {{ busy === "save" ? "Saving…" : "Save my taste" }}
            </button>
          </div>
        </div>
      </div>

      <!-- ── 2. Special days ──────────────────────────────────────────── -->
      <div class="pp-block">
        <div class="pp-block__head">
          <span class="pp-num" aria-hidden="true">2</span>
          <div>
            <h3>Special days</h3>
            <p>Birthdays, anniversaries and other days you buy flowers for. You'll see how many days are left.</p>
          </div>
        </div>

        <ul v-if="data.dates.length" class="pp-days">
          <li v-for="d in data.dates" :key="d.id" class="pp-day">
            <span class="pp-day__ico" :class="d.kind" aria-hidden="true" v-html="kindOf(d.kind).icon"></span>
            <span class="pp-day__body">
              <strong>{{ d.label }}</strong>
              <small>{{ monthDay(d) }}<template v-if="d.year">, {{ d.year }}</template><template v-if="d.note"> · {{ d.note }}</template></small>
            </span>
            <span class="pp-day__when">{{ whenText(d.days_away) }}</span>
            <button type="button" class="pp-x" :aria-label="`Remove ${d.label}`" :disabled="busy === `d${d.id}`" @click="removeDate(d)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
          </li>
        </ul>
        <p v-else-if="!addingDay" class="pp-empty">No special days yet.</p>

        <button v-if="!addingDay" type="button" class="pp-add" @click="openDayForm">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
          Add a special day
        </button>

        <form v-else class="pp-dayform" @submit.prevent="addDate">
          <div class="pp-kinds" role="radiogroup" aria-label="Kind of day">
            <button
              v-for="k in DATE_KINDS"
              :key="k.value"
              type="button"
              role="radio"
              class="pp-kind"
              :class="{ on: dateForm.kind === k.value }"
              :aria-checked="dateForm.kind === k.value"
              @click="dateForm.kind = k.value"
            >
              <span aria-hidden="true" v-html="k.icon"></span>
              {{ k.label }}
            </button>
          </div>
          <label class="pp-field pp-field--wide">
            <span>What is it?</span>
            <input ref="dayLabel" v-model="dateForm.label" maxlength="80" :placeholder="dateForm.kind === 'birthday' ? 'e.g. Mom\'s birthday' : dateForm.kind === 'anniversary' ? 'e.g. Our wedding anniversary' : 'e.g. Graduation day'" />
          </label>
          <label class="pp-field">
            <span>Month</span>
            <select v-model="dateForm.month">
              <option value="" disabled>Choose…</option>
              <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
            </select>
          </label>
          <label class="pp-field">
            <span>Day</span>
            <select v-model="dateForm.day">
              <option value="" disabled>Choose…</option>
              <option v-for="n in daysIn(dateForm.month)" :key="n" :value="n">{{ n }}</option>
            </select>
          </label>
          <label class="pp-field">
            <span>Year <small>(optional)</small></span>
            <input v-model="dateForm.year" type="number" min="1900" :max="thisYear" placeholder="e.g. 2018" />
          </label>
          <div class="pp-dayform__actions">
            <button type="button" class="pp-btn pp-btn--quiet" @click="addingDay = false">Cancel</button>
            <button type="submit" class="pp-btn" :disabled="busy === 'date' || !dateForm.month || !dateForm.day">
              {{ busy === "date" ? "Saving…" : "Save this day" }}
            </button>
          </div>
        </form>
      </div>

      <!-- ── 3. Offers ────────────────────────────────────────────────── -->
      <div id="offers" class="pp-block">
        <div class="pp-block__head">
          <span class="pp-num" aria-hidden="true">3</span>
          <div>
            <h3>Offers from shops</h3>
            <p>Shops you've bought from can send you a birthday treat or a welcome-back discount. Only if you say yes.</p>
          </div>
        </div>

        <label class="pp-toggle" :class="{ on: data.accept_offers }">
          <span>
            <strong>{{ data.accept_offers ? "You get offers" : "Offers are off" }}</strong>
            <small>{{ data.accept_offers ? "At most one from each shop every few days." : "Turn on to get offers from shops you've ordered from." }}</small>
          </span>
          <span class="pp-switch">
            <input type="checkbox" role="switch" :checked="data.accept_offers" aria-label="Get offers from shops" :disabled="busy === 'offers'" @change="setOffers($event.target.checked)" />
            <span aria-hidden="true"></span>
          </span>
        </label>

        <div v-if="offers === null" class="pp-sk"><span class="bc-skeleton pp-sk-line"></span></div>
        <p v-else-if="!offers.length" class="pp-empty">{{ data.accept_offers ? "No offers yet. They'll show up here and in your notifications." : "Offers you get will show up here." }}</p>
        <ul v-else class="pp-offers">
          <li v-for="o in offers" :key="o.id" class="pp-offer" :class="{ fresh: o.new }">
            <div class="pp-offer__top">
              <small>{{ o.shop }} · {{ agoText(o.at) }}</small>
              <span v-if="o.new" class="pp-new">New</span>
            </div>
            <strong>{{ o.title }}</strong>
            <p>{{ o.body }}</p>
            <div class="pp-offer__foot">
              <span v-if="o.promotion" class="pp-promo" :class="{ ended: !o.promotion.running }">{{ o.promotion.label }} · {{ o.promotion.name }}{{ o.promotion.running ? ` · until ${shortDay(o.promotion.ends_at)}` : " · ended" }}</span>
              <router-link v-if="o.product_id" :to="{ name: 'ProductDetails', params: { id: String(o.product_id) } }" class="pp-link">View product</router-link>
              <button v-if="!isMuted(o.vendor_id)" type="button" class="pp-link muted" :disabled="busy === `m${o.vendor_id}`" @click="mute(o.vendor_id, true)">Stop offers from {{ o.shop }}</button>
            </div>
          </li>
        </ul>
        <div v-if="data.muted_shops?.length" class="pp-muted">
          <span>Shops you stopped:</span>
          <span v-for="m in data.muted_shops" :key="m.vendor_id" class="pp-muted__shop">
            {{ m.shop }}
            <button type="button" class="pp-link" :disabled="busy === `m${m.vendor_id}`" @click="mute(m.vendor_id, false)">Allow again</button>
          </span>
        </div>
      </div>

      <!-- ── What shops saved (privacy) ───────────────────────────────── -->
      <details class="pp-privacy">
        <summary>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z" /></svg>
          What shops saved about you
          <span class="pp-count">{{ data.shops.length }}</span>
          <svg class="pp-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
        </summary>
        <p class="pp-privacy__lede">When you tell a shop your preferences, it can note them to remember next time. You can see it here and remove it anytime.</p>
        <p v-if="!data.shops.length" class="pp-empty">No shop has saved anything about you.</p>
        <article v-for="s in data.shops" :key="s.vendor_id" class="pp-shop">
          <header>
            <strong>{{ s.shop }}</strong>
            <button type="button" class="pp-link danger" :disabled="busy === `s${s.vendor_id}`" @click="forget(s)">Remove</button>
          </header>
          <dl>
            <template v-for="q in QUESTIONS" :key="q.key">
              <template v-if="s[q.key].length"><dt>{{ q.short }}</dt><dd>{{ s[q.key].join(", ") }}</dd></template>
            </template>
            <template v-if="s.dates.length">
              <dt>Special days</dt>
              <dd>{{ s.dates.map((d) => `${d.label} (${monthDay(d)})`).join(", ") }}</dd>
            </template>
          </dl>
        </article>
      </details>
    </template>
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import { DATE_KINDS, MONTHS, SUGGEST, blankDate, datePayload, daysIn, kindOf, monthDay, whenText } from "../crm/customerPrefs";

// The taste questions, asked plainly; answers are tappable chips
const QUESTIONS = [
  { key: "flowers", short: "Favorite flowers", question: "Which flowers do you love?", own: "Another flower" },
  { key: "colors", short: "Colors", question: "Which colors do you like?", own: "Another color" },
  { key: "avoid", short: "Please avoid", question: "Anything shops should avoid?", hint: "Allergies or flowers you'd rather not get.", own: "Something else" },
  { key: "occasions", short: "Occasions", question: "What do you usually buy flowers for?", own: "Another occasion" },
];
const MAX_PER_QUESTION = 12;

const thisYear = new Date().getFullYear();
const offers = ref(null);
const data = ref(null);
const loadError = ref("");
const busy = ref("");
const savedAt = ref(false);
const dateForm = ref(blankDate());
const addingDay = ref(false);
const dayLabel = ref(null);
const form = reactive({ flowers: [], colors: [], avoid: [], occasions: [], note: "", share_with_shops: false });
const drafts = reactive({ flowers: "", colors: "", avoid: "", occasions: "" });
let saved = "";

const changed = computed(() => JSON.stringify(form) !== saved);
const shopsText = computed(() => {
  const n = data.value?.shop_count ?? 0;
  return n === 1 ? "1 shop" : n ? `${n} shops` : "shops";
});

const reason = (e, fallback) => {
  const errors = e?.response?.data?.errors;
  return (errors && Object.values(errors)[0]?.[0]) || e?.response?.data?.message || fallback;
};

// ── Chips ─────────────────────────────────────────────────────────────
const same = (a, b) => a.trim().toLowerCase() === b.trim().toLowerCase();
/** The suggestions, plus anything they added that isn't one of them */
const optionsFor = (key) => [...SUGGEST[key], ...form[key].filter((v) => !SUGGEST[key].some((s) => same(s, v)))];
const picked = (key, option) => form[key].some((v) => same(v, option));
function toggle(key, option) {
  if (picked(key, option)) {
    form[key] = form[key].filter((v) => !same(v, option));
  } else if (form[key].length < MAX_PER_QUESTION) {
    form[key] = [...form[key], option];
  } else {
    toast.info(`Up to ${MAX_PER_QUESTION} for each question.`);
  }
  savedAt.value = false;
}
function addOwn(key) {
  const value = drafts[key].trim();
  if (!value) return;
  const existing = optionsFor(key).find((o) => same(o, value));
  if (!existing || !picked(key, existing)) toggle(key, existing || value);
  drafts[key] = "";
}

// ── Loading and saving ────────────────────────────────────────────────
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

/** Taste waits for the button; who can see it saves straight away (with any taste edits) */
async function save(fromSharing = false) {
  const message = fromSharing ? (form.share_with_shops ? "Your shops can now see your taste and special days." : "Only you can see them now.") : "Your taste is saved.";
  const ok = await run("save", () => api.put("customer/preferences", { ...form }), message);
  if (ok) savedAt.value = true;
  else if (fromSharing) form.share_with_shops = !form.share_with_shops;
}
function setSharing(on) {
  if (form.share_with_shops === on) return;
  form.share_with_shops = on;
  save(true);
}

// ── Special days ──────────────────────────────────────────────────────
function openDayForm() {
  dateForm.value = blankDate();
  addingDay.value = true;
  nextTick(() => dayLabel.value?.focus());
}
async function addDate() {
  if (await run("date", () => api.post("customer/preferences/dates", datePayload(dateForm.value)), "Day saved.")) {
    dateForm.value = blankDate();
    addingDay.value = false;
  }
}
const removeDate = (d) => run(`d${d.id}`, () => api.delete(`customer/preferences/dates/${d.id}`), "Day removed.");

// ── Offers ────────────────────────────────────────────────────────────
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

/** The offers switch saves straight away, without touching unsaved taste edits */
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
  --pp-green-bg: #e4f3ea;
  --pp-red: #b4332a;
  --pp-red-bg: #fbe9e7;
  display: grid;
  gap: 18px;
  padding: clamp(20px, 2.6vw, 30px);
  border: 1px solid var(--line);
  border-radius: 22px;
  background: #ffffff;
  color: var(--ink);
  font-family: var(--font-sans);
}

.pp-card :is(p, h2, h3, dl, dd, ul) {
  margin: 0;
}

.pp-head h2 {
  font-family: var(--font-display);
  font-size: clamp(28px, 2.6vw, 34px);
  font-weight: 500;
  line-height: 1.05;
}

.pp-head h2 em {
  color: var(--leaf-deep);
  font-style: italic;
}

.pp-head p {
  max-width: 600px;
  margin-top: 6px;
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

/* Blocks: numbered steps */
.pp-block {
  display: grid;
  gap: 16px;
  padding: 20px;
  border-radius: 18px;
  background: var(--ivory);
}

.pp-block__head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.pp-num {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--ink);
  color: var(--paper);
  font-size: 13px;
  font-weight: 600;
}

.pp-block__head h3 {
  font-size: 16px;
  font-weight: 600;
  line-height: 1.3;
}

.pp-block__head p {
  margin-top: 2px;
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.5;
}

/* Questions with chips */
.pp-q {
  display: grid;
  gap: 10px;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.pp-q legend,
.pp-legend {
  display: grid;
  gap: 2px;
  padding: 0;
  margin-bottom: 10px;
  color: var(--ink);
  font-size: 14px;
  font-weight: 600;
}

.pp-q legend small,
.pp-legend small,
.pp-field small {
  color: var(--ink-soft);
  font-size: 12.5px;
  font-weight: 400;
}

.pp-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pp-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #ffffff;
  color: var(--ink);
  font: 500 13.5px var(--font-sans);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.pp-chip:hover {
  border-color: var(--leaf);
}

.pp-chip svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pp-chip.on {
  border-color: var(--leaf);
  background: var(--pp-green-bg);
  color: var(--leaf-deep);
}

.pp-chip.avoid.on {
  border-color: #e7b4ae;
  background: var(--pp-red-bg);
  color: var(--pp-red);
}

/* "Add your own" as a chip-shaped box */
.pp-own {
  display: inline-flex;
  align-items: center;
  min-height: 38px;
  padding: 0 4px 0 14px;
  border: 1px dashed #c3cbc6;
  border-radius: 999px;
  background: #ffffff;
}

.pp-own:focus-within {
  border-style: solid;
  border-color: var(--leaf);
}

.pp-own input {
  width: 130px;
  border: 0;
  background: none;
  color: var(--ink);
  font: 400 13.5px var(--font-sans);
  outline: none;
}

.pp-own button {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border: 0;
  border-radius: 50%;
  background: var(--ink);
  color: var(--paper);
  cursor: pointer;
}

.pp-own button:disabled {
  background: var(--ivory-deep);
  color: var(--ink-soft);
  cursor: default;
}

.pp-own button svg,
.pp-add svg,
.pp-x svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}

.pp-note textarea,
.pp-field :is(input, select) {
  width: 100%;
  padding: 11px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #ffffff;
  color: var(--ink);
  font: 400 14px var(--font-sans);
  resize: vertical;
}

.pp-note textarea:focus,
.pp-field :is(input, select):focus {
  border-color: var(--leaf);
  outline: none;
  box-shadow: 0 0 0 4px rgba(77, 177, 115, 0.14);
}

/* Who sees it + save */
.pp-save {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

.pp-who {
  display: grid;
  gap: 8px;
}

.pp-who__label {
  font-size: 13.5px;
  font-weight: 600;
}

.pp-who small {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.pp-seg {
  display: inline-flex;
  gap: 4px;
  padding: 4px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #ffffff;
}

.pp-seg button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 14px;
  border: 0;
  border-radius: 999px;
  background: none;
  color: var(--ink-soft);
  font: 500 13px var(--font-sans);
  cursor: pointer;
}

.pp-seg button.on {
  background: var(--ink);
  color: var(--paper);
}

.pp-seg svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pp-save__btn {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pp-unsaved {
  color: #9a5b0b;
  font-size: 12.5px;
  font-weight: 600;
}

.pp-saved {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--leaf-deep);
  font-size: 12.5px;
  font-weight: 600;
}

.pp-saved svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
}

.pp-btn {
  min-height: 44px;
  padding: 0 20px;
  border: 0;
  border-radius: 999px;
  background: var(--ink);
  color: var(--paper);
  font: 500 14px var(--font-sans);
  cursor: pointer;
  transition: background 0.2s ease;
}

.pp-btn:hover:not(:disabled) {
  background: var(--leaf-deep);
}

.pp-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.pp-btn--quiet {
  background: none;
  color: var(--ink-soft);
}

.pp-btn--quiet:hover:not(:disabled) {
  background: var(--ivory-deep);
  color: var(--ink);
}

/* Special days */
.pp-days {
  display: grid;
  gap: 8px;
  padding: 0;
  list-style: none;
}

.pp-day {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 12px;
  padding: 10px 10px 10px 12px;
  border-radius: 14px;
  background: #ffffff;
}

.pp-day__ico {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--pp-green-bg);
  color: var(--leaf-deep);
}

.pp-day__ico.birthday {
  background: #fdf1df;
  color: #9a5b0b;
}

.pp-day__ico.anniversary {
  background: #fbe9ef;
  color: #a33a5e;
}

.pp-day__ico :deep(svg),
.pp-kind :deep(svg) {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pp-day__body {
  display: grid;
  min-width: 0;
}

.pp-day__body strong {
  font-size: 14px;
  font-weight: 600;
}

.pp-day__body small {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.pp-day__when {
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--ivory);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.pp-x {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 50%;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
}

.pp-x:hover:not(:disabled) {
  background: var(--pp-red-bg);
  color: var(--pp-red);
}

.pp-add {
  display: inline-flex;
  align-items: center;
  justify-self: start;
  gap: 8px;
  min-height: 42px;
  padding: 0 16px;
  border: 1px dashed #c3cbc6;
  border-radius: 999px;
  background: #ffffff;
  color: var(--ink);
  font: 500 13.5px var(--font-sans);
  cursor: pointer;
}

.pp-add:hover {
  border-style: solid;
  border-color: var(--leaf);
  color: var(--leaf-deep);
}

.pp-dayform {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  padding: 16px;
  border-radius: 14px;
  background: #ffffff;
}

.pp-kinds {
  display: flex;
  flex-wrap: wrap;
  grid-column: 1 / -1;
  gap: 8px;
}

.pp-kind {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #ffffff;
  color: var(--ink);
  font: 500 13.5px var(--font-sans);
  cursor: pointer;
}

.pp-kind.on {
  border-color: var(--leaf);
  background: var(--pp-green-bg);
  color: var(--leaf-deep);
}

.pp-field {
  display: grid;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
}

.pp-field--wide {
  grid-column: 1 / -1;
}

.pp-dayform__actions {
  display: flex;
  grid-column: 1 / -1;
  justify-content: flex-end;
  gap: 8px;
}

/* Offers */
.pp-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border-radius: 14px;
  background: #ffffff;
  cursor: pointer;
}

.pp-toggle span:first-child {
  display: grid;
  gap: 2px;
}

.pp-toggle strong {
  font-size: 14px;
  font-weight: 600;
}

.pp-toggle small {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.pp-switch {
  position: relative;
  flex-shrink: 0;
  width: 52px;
  height: 30px;
}

.pp-switch input {
  position: absolute;
  inset: 0;
  z-index: 1;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: pointer;
}

.pp-switch span {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: #cfd5d1;
  transition: background 0.2s ease;
}

.pp-switch span::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 2px 6px rgba(38, 45, 57, 0.25);
  transition: transform 0.2s ease;
}

.pp-switch input:checked + span {
  background: var(--leaf);
}

.pp-switch input:checked + span::after {
  transform: translateX(22px);
}

.pp-switch input:focus-visible + span {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

.pp-offers {
  display: grid;
  gap: 8px;
  padding: 0;
  list-style: none;
}

.pp-offer {
  display: grid;
  gap: 4px;
  padding: 14px 16px;
  border: 1px solid transparent;
  border-radius: 14px;
  background: #ffffff;
}

.pp-offer.fresh {
  border-color: rgba(77, 177, 115, 0.4);
}

.pp-offer__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.pp-offer__top small {
  color: var(--ink-soft);
  font-size: 12px;
}

.pp-new {
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--leaf);
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
}

.pp-offer strong {
  font-size: 14.5px;
  font-weight: 600;
}

.pp-offer p {
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.5;
}

.pp-offer__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
  margin-top: 6px;
}

.pp-promo {
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--pp-green-bg);
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 500;
}

.pp-promo.ended {
  background: var(--ivory-deep);
  color: var(--ink-soft);
}

.pp-muted {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
  color: var(--ink-soft);
  font-size: 13px;
}

.pp-muted__shop {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 4px 6px 4px 12px;
  border-radius: 999px;
  background: #ffffff;
  color: var(--ink);
}

/* Links */
.pp-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--leaf-deep);
  font: 600 13px var(--font-sans);
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.pp-link.muted {
  color: var(--ink-soft);
}

.pp-link.danger {
  color: var(--pp-red);
}

.pp-link:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Privacy panel */
.pp-privacy {
  border: 1px solid var(--line);
  border-radius: 16px;
}

.pp-privacy summary {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 52px;
  padding: 0 16px;
  font-size: 14px;
  font-weight: 600;
  list-style: none;
  cursor: pointer;
}

.pp-privacy summary::-webkit-details-marker {
  display: none;
}

.pp-privacy summary svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pp-count {
  padding: 1px 8px;
  border-radius: 999px;
  background: var(--ivory-deep);
  color: var(--ink-soft);
  font-size: 12px;
}

.pp-chev {
  margin-left: auto;
  stroke: var(--ink-soft) !important;
  transition: transform 0.2s ease;
}

.pp-privacy[open] .pp-chev {
  transform: rotate(180deg);
}

.pp-privacy[open] {
  padding-bottom: 16px;
}

.pp-privacy__lede,
.pp-privacy > .pp-empty {
  margin: 0 16px 12px !important;
  color: var(--ink-soft);
  font-size: 13px;
  line-height: 1.55;
}

.pp-shop {
  margin: 0 16px 10px;
  padding: 14px;
  border-radius: 14px;
  background: var(--ivory);
}

.pp-shop header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.pp-shop dl {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 4px 14px;
  font-size: 13px;
}

.pp-shop dt {
  color: var(--ink-soft);
}

/* Misc */
.pp-empty {
  color: var(--ink-soft);
  font-size: 13.5px;
}

.pp-alert {
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--pp-red-bg);
  color: var(--pp-red);
  font-size: 13.5px;
}

.pp-sk {
  display: grid;
  gap: 10px;
}

.pp-sk-line {
  display: block;
  height: 14px;
  border-radius: 7px;
}

.pp-sk-line:nth-child(2n) {
  width: 70%;
}

:is(.pp-chip, .pp-own button, .pp-seg button, .pp-btn, .pp-add, .pp-x, .pp-kind, .pp-link, .pp-privacy summary):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (max-width: 640px) {
  .pp-block {
    padding: 16px;
  }

  .pp-dayform {
    grid-template-columns: 1fr 1fr;
  }

  .pp-day {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  .pp-day__when {
    grid-column: 2;
    justify-self: start;
  }

  .pp-day .pp-x {
    grid-row: 1;
    grid-column: 3;
  }

  .pp-save {
    align-items: stretch;
  }

  .pp-save__btn {
    width: 100%;
    justify-content: space-between;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pp-chip,
  .pp-switch span,
  .pp-switch span::after,
  .pp-chev {
    transition: none;
  }
}
</style>
