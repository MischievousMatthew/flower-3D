<template>
  <HrPage title="Receiving & inspection" subtitle="Check each delivery against its purchase order. Only what passes inspection goes into stock; rejected and missing stems stay owed.">
    <template #actions>
      <router-link :to="{ name: 'WarehouseFloor' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.chevronLeft"></span>Floor view</router-link>
    </template>

    <div class="sp-seg rv-mode" role="tablist" aria-label="How it arrived">
      <button type="button" role="tab" :aria-selected="mode === 'order'" :class="{ on: mode === 'order' }" @click="mode = 'order'"><span v-html="ICONS.receipt"></span>Against a purchase order</button>
      <button type="button" role="tab" :aria-selected="mode === 'single'" :class="{ on: mode === 'single' }" @click="mode = 'single'"><span v-html="ICONS.box"></span>Without an order</button>
    </div>

    <!-- ═══ Against a purchase order ═══ -->
    <template v-if="mode === 'order'">
      <!-- 1. Which order -->
      <section class="sp-card" aria-labelledby="rv-pick">
        <div class="sp-card-head">
          <div><h2 id="rv-pick">1. Which order is this delivery for?</h2><p>Approved orders that still have stems owed</p></div>
          <button v-if="order" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="clearOrder">Pick another</button>
        </div>
        <div v-if="openOrders === null" class="rv-orders"><div v-for="n in 3" :key="n" class="bc-skeleton rv-sk-order"></div></div>
        <div v-else-if="!openOrders.length && !order" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
          <h3>Nothing to receive</h3>
          <p>Approved purchase orders that are still owed show up here. To receive something you didn't order, use “Without an order”.</p>
        </div>
        <div v-else-if="order" class="rv-chosen">
          <span class="rv-logo">{{ initials(order.supplier?.company_name) }}</span>
          <div class="grow">
            <b>{{ order.order_number }} · {{ order.supplier?.company_name }}</b>
            <span>{{ owedUnits(order).toLocaleString("en-PH") }} units still owed<template v-if="order.expected_date"> · expected {{ poDate(order.expected_date) }}</template></span>
          </div>
          <span class="sp-chip" :class="stageOf(order.status).tone">{{ statusText(order.status) }}</span>
        </div>
        <template v-else>
          <label class="sp-search rv-find">
            <span v-html="ICONS.search"></span>
            <input v-model="orderSearch" class="sp-input" type="search" placeholder="Order number or supplier" aria-label="Find an order" />
          </label>
          <div class="rv-orders" role="list">
            <button v-for="o in shownOrders" :key="o.id" type="button" role="listitem" class="rv-order" @click="pickOrder(o)">
              <span class="rv-logo">{{ initials(o.supplier?.company_name) }}</span>
              <span class="grow">
                <b>{{ o.order_number }}</b>
                <small>{{ o.supplier?.company_name }} · {{ o.items.length }} {{ o.items.length === 1 ? "line" : "lines" }} · {{ owedUnits(o).toLocaleString("en-PH") }} units owed</small>
              </span>
              <span class="rv-order-due" :class="{ late: daysUntil(o.expected_date) < 0 }">{{ o.expected_date ? dueText(o.expected_date, o.status) : statusText(o.status) }}</span>
            </button>
          </div>
          <p v-if="!shownOrders.length" class="sp-hint">No order matches.</p>
        </template>
      </section>

      <template v-if="order">
        <!-- 2. Inspection -->
        <section class="sp-card" aria-labelledby="rv-inspect">
          <div class="sp-card-head">
            <div><h2 id="rv-inspect">2. Count and inspect each line</h2><p>Accepted stems go into stock. Rejected and missing ones stay owed unless you close them.</p></div>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="allArrived">Everything arrived and passed</button>
          </div>
          <div class="rv-lines">
            <article v-for="(l, i) in lines" :key="l.item_id" class="rv-line" :class="{ off: l.owed === 0 }">
              <header class="rv-line-head">
                <div class="grow">
                  <b>{{ l.name }}</b>
                  <small>{{ l.owed ? `${l.owed.toLocaleString("en-PH")} ${l.unit || "units"} still owed` : "Nothing owed on this line" }}<template v-if="l.received"> · {{ l.received.toLocaleString("en-PH") }} already in stock</template></small>
                </div>
                <span v-if="!l.product_id" class="sp-chip amber">Not linked to stock</span>
              </header>

              <div class="rv-counts">
                <label class="sp-field">
                  <span class="sp-label">Delivered</span>
                  <input v-model.number="l.delivered" class="sp-input" :class="{ invalid: lineErr(i, 'delivered_qty') }" type="number" min="0" step="1" inputmode="numeric" :disabled="l.owed === 0" @input="syncAccepted(l)" />
                </label>
                <label class="sp-field">
                  <span class="sp-label">Accepted</span>
                  <input v-model.number="l.accepted" class="sp-input" :class="{ invalid: lineErr(i, 'accepted_qty') }" type="number" min="0" :max="Math.min(l.delivered || 0, l.owed)" step="1" inputmode="numeric" :disabled="!l.delivered" />
                </label>
                <div class="rv-stat" :class="{ bad: rejected(l) > 0 }"><span class="sp-label">Rejected</span><b>{{ rejected(l) }}</b></div>
                <div class="rv-stat" :class="{ warn: missing(l) > 0 }"><span class="sp-label">Missing</span><b>{{ missing(l) }}</b></div>
              </div>
              <small v-if="lineErr(i, 'accepted_qty')" class="sp-error">{{ lineErr(i, "accepted_qty") }}</small>
              <small v-else-if="l.delivered > l.owed" class="sp-hint">{{ l.delivered - l.owed }} more than ordered came. Only {{ l.owed }} can be accepted on this order.</small>

              <template v-if="l.delivered > 0">
                <fieldset class="rv-cond">
                  <legend class="sp-label">How did it look?</legend>
                  <label v-for="c in CONDITIONS" :key="c.value" class="rv-cond-opt" :class="[`t-${c.tone}`, { on: l.condition === c.value }]">
                    <input v-model="l.condition" type="radio" :name="`rv-cond-${l.item_id}`" :value="c.value" />
                    <b>{{ c.label }}</b><small>{{ c.hint }}</small>
                  </label>
                </fieldset>
                <small v-if="lineErr(i, 'condition')" class="sp-error">{{ lineErr(i, "condition") }}</small>

                <fieldset class="rv-checks">
                  <legend class="sp-label">Quality checks</legend>
                  <label v-for="c in CHECKS" :key="c.value" class="rv-check" :class="{ on: l.checks.includes(c.value) }">
                    <input v-model="l.checks" type="checkbox" :value="c.value" />
                    <span class="rv-tick" v-html="l.checks.includes(c.value) ? ICONS.check : ''"></span>{{ c.label }}
                  </label>
                </fieldset>
              </template>

              <div v-if="rejected(l) > 0" class="rv-reject">
                <label class="sp-field">
                  <span class="sp-label">Why were {{ rejected(l) }} turned away?</span>
                  <select v-model="l.reject_reason" class="sp-select" :class="{ invalid: lineErr(i, 'reject_reason') }">
                    <option value="">Pick a reason</option>
                    <option v-for="r in REJECT_REASONS" :key="r.value" :value="r.value">{{ r.label }}</option>
                  </select>
                  <small v-if="lineErr(i, 'reject_reason')" class="sp-error">{{ lineErr(i, "reject_reason") }}</small>
                </label>
              </div>

              <fieldset v-if="stillOwed(l) > 0 && (l.delivered > 0 || missing(l) > 0)" class="rv-rest">
                <legend class="sp-label">The other {{ stillOwed(l) }}</legend>
                <label class="rv-rest-opt" :class="{ on: !l.close_short }"><input v-model="l.close_short" type="radio" :name="`rv-rest-${l.item_id}`" :value="false" />Still coming: keep them owed</label>
                <label class="rv-rest-opt" :class="{ on: l.close_short }"><input v-model="l.close_short" type="radio" :name="`rv-rest-${l.item_id}`" :value="true" />Not coming: close the rest</label>
              </fieldset>

              <details v-if="l.accepted > 0" class="rv-store">
                <summary>Where it goes and how long it lasts <small>(optional)</small></summary>
                <div class="sp-grid-3">
                  <label class="sp-field">
                    <span class="sp-label">Storage location</span>
                    <select v-model="l.warehouse_location_id" class="sp-select">
                      <option value="">Not set</option>
                      <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}{{ loc.is_refrigerated ? " (cold)" : "" }}</option>
                    </select>
                  </label>
                  <label class="sp-field">
                    <span class="sp-label">Harvested</span>
                    <input v-model="l.harvest_date" type="date" class="sp-input" :max="receivedDate" />
                  </label>
                  <label class="sp-field">
                    <span class="sp-label">Use by</span>
                    <input v-model="l.expiration_date" type="date" class="sp-input" :class="{ invalid: lineErr(i, 'expiration_date') }" :min="receivedDate" />
                  </label>
                </div>
              </details>

              <input v-model.trim="l.notes" class="sp-input rv-note" maxlength="500" :placeholder="`Note about ${l.name} (optional)`" :aria-label="`Note about ${l.name}`" />
            </article>
          </div>
        </section>

        <!-- 3. Paperwork -->
        <section class="sp-card" aria-labelledby="rv-papers">
          <div class="sp-card-head"><div><h2 id="rv-papers">3. Delivery receipt and photos</h2><p>Keep the supplier's receipt and photos of anything wrong, in case you need to claim</p></div></div>
          <div class="sp-grid-2">
            <label class="sp-field">
              <span class="sp-label">Delivered on</span>
              <input v-model="receivedDate" type="date" class="sp-input" :max="todayYmd()" />
            </label>
            <label class="sp-field">
              <span class="sp-label">Delivery receipt or invoice number <small>(optional)</small></span>
              <input v-model.trim="deliveryRef" class="sp-input" maxlength="80" placeholder="e.g. DR-5521" />
            </label>
          </div>
          <div class="rv-files">
            <div class="rv-drop" :class="{ has: receiptFile }">
              <span class="sp-label">Delivery receipt</span>
              <div v-if="receiptFile" class="rv-file">
                <img v-if="receiptPreview" :src="receiptPreview" alt="Delivery receipt" />
                <span v-else class="rv-file-ic" v-html="ICONS.note"></span>
                <div class="grow"><b>{{ receiptFile.name }}</b><small>{{ fileSize(receiptFile.size) }}</small></div>
                <button type="button" class="sp-icon-btn" aria-label="Remove the receipt" @click="setReceipt(null)"><span v-html="ICONS.close"></span></button>
              </div>
              <button v-else type="button" class="rv-add" @click="receiptInput.click()"><span v-html="ICONS.upload"></span>Add the receipt<small>Photo or PDF, up to 10 MB</small></button>
              <input ref="receiptInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" hidden @change="setReceipt($event.target.files[0]); $event.target.value = ''" />
            </div>
            <div class="rv-drop">
              <span class="sp-label">Photos <small>({{ photos.length }}/8)</small></span>
              <div class="rv-thumbs">
                <figure v-for="(p, i) in photos" :key="p.key" class="rv-thumb">
                  <img :src="p.url" :alt="`Photo ${i + 1}`" />
                  <button type="button" class="rv-thumb-x" :aria-label="`Remove photo ${i + 1}`" @click="removePhoto(i)"><span v-html="ICONS.close"></span></button>
                </figure>
                <button v-if="photos.length < 8" type="button" class="rv-add small" @click="photoInput.click()"><span v-html="ICONS.image"></span>Add photos</button>
              </div>
              <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden @change="addPhotos($event.target.files); $event.target.value = ''" />
            </div>
          </div>
          <label class="sp-field">
            <span class="sp-label">Notes <small>(optional)</small></span>
            <textarea v-model.trim="notes" class="sp-textarea" rows="2" maxlength="2000" placeholder="e.g. Driver arrived 2 hours late; two boxes were wet"></textarea>
          </label>
        </section>

        <!-- Summary -->
        <section class="sp-card rv-sum" aria-label="Summary">
          <dl>
            <div><dt>Delivered</dt><dd>{{ totals.delivered.toLocaleString("en-PH") }}</dd></div>
            <div class="good"><dt>Into stock</dt><dd>{{ totals.accepted.toLocaleString("en-PH") }}</dd></div>
            <div :class="{ bad: totals.rejected }"><dt>Rejected</dt><dd>{{ totals.rejected.toLocaleString("en-PH") }}</dd></div>
            <div :class="{ warn: totals.missing }"><dt>Missing</dt><dd>{{ totals.missing.toLocaleString("en-PH") }}</dd></div>
          </dl>
          <div class="rv-sum-act">
            <p v-if="formError" class="sp-error" role="alert">{{ formError }}</p>
            <small class="sp-hint">{{ afterText }}</small>
            <button type="button" class="sp-btn sp-btn-primary" :disabled="saving" @click="saveInspection">{{ saving ? "Saving…" : "Save delivery" }}</button>
          </div>
        </section>
      </template>
    </template>

    <!-- ═══ Without an order (the existing single-batch receiving) ═══ -->
    <section v-else class="sp-card" aria-labelledby="rv-single">
      <div class="sp-card-head"><div><h2 id="rv-single">Receive a batch</h2><p>For stock that didn't come from a purchase order</p></div></div>
      <div class="sp-grid-2">
        <div class="sp-field sp-span-all rv-prod">
          <span class="sp-label">Product</span>
          <div v-if="selectedProduct" class="rv-chosen">
            <span class="rv-logo">{{ initials(selectedProduct.product_name) }}</span>
            <div class="grow"><b>{{ selectedProduct.product_name }}</b><span>{{ selectedProduct.sku || "No SKU" }} · {{ selectedProduct.stock_count ?? 0 }} in stock</span></div>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="clearProduct">Change</button>
          </div>
          <template v-else>
            <label class="sp-search">
              <span v-html="ICONS.search"></span>
              <input v-model="productSearch" class="sp-input" :class="{ invalid: errors.product_id }" type="search" placeholder="Search by name or SKU" aria-label="Find a product" autocomplete="off" @input="onProductInput" />
            </label>
            <ul v-if="productResults.length || productsLoading" class="rv-results">
              <li v-if="productsLoading"><div class="bc-skeleton sp-sk-line w50"></div></li>
              <li v-for="p in productResults" :key="p.id"><button type="button" @click="selectProduct(p)"><b>{{ p.product_name }}</b><small>{{ p.sku || "No SKU" }} · {{ p.stock_count }} in stock</small></button></li>
            </ul>
            <small v-if="errors.product_id" class="sp-error">{{ errors.product_id }}</small>
          </template>
        </div>
        <label class="sp-field">
          <span class="sp-label">Quantity received</span>
          <input v-model.number="form.qty_received" class="sp-input" :class="{ invalid: errors.qty_received }" type="number" min="1" step="1" inputmode="numeric" />
          <small v-if="errors.qty_received" class="sp-error">{{ errors.qty_received }}</small>
        </label>
        <label class="sp-field">
          <span class="sp-label">Received on</span>
          <input v-model="form.received_date" class="sp-input" :class="{ invalid: errors.received_date }" type="date" :max="todayYmd()" @change="calcFreshnessDays" />
          <small v-if="errors.received_date" class="sp-error">{{ errors.received_date }}</small>
        </label>
        <label class="sp-field">
          <span class="sp-label">Harvested <small>(optional)</small></span>
          <input v-model="form.harvest_date" class="sp-input" type="date" :max="form.received_date" />
        </label>
        <label class="sp-field">
          <span class="sp-label">Use by <small>(optional)</small></span>
          <input v-model="form.expiration_date" class="sp-input" type="date" :min="form.received_date" @change="calcFreshnessDays" />
        </label>
        <label class="sp-field">
          <span class="sp-label">Stays fresh for <small>(days, optional)</small></span>
          <input v-model.number="form.freshness_days" class="sp-input" type="number" min="1" max="365" step="1" inputmode="numeric" />
        </label>
        <label class="sp-field">
          <span class="sp-label">Storage location <small>(optional)</small></span>
          <select v-model="form.warehouse_location_id" class="sp-select">
            <option value="">{{ locationsLoading ? "Loading…" : "Not set" }}</option>
            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}{{ loc.is_refrigerated ? " (cold)" : "" }}</option>
          </select>
        </label>
        <label class="sp-field">
          <span class="sp-label">Lot number <small>(optional)</small></span>
          <input v-model.trim="form.lot_number" class="sp-input" maxlength="100" />
        </label>
        <label class="sp-field sp-span-all">
          <span class="sp-label">Notes <small>(optional)</small></span>
          <textarea v-model.trim="form.notes" class="sp-textarea" rows="2" maxlength="500"></textarea>
        </label>
      </div>
      <div class="rv-single-foot">
        <button type="button" class="sp-btn sp-btn-primary" :disabled="submitting || submitted" @click="submit">{{ submitting ? "Saving…" : "Receive batch" }}</button>
      </div>
    </section>

    <!-- Recent deliveries -->
    <section class="sp-card rv-recent" aria-labelledby="rv-recent">
      <div class="sp-card-head">
        <div><h2 id="rv-recent">Recent deliveries</h2><p>What was received against orders, with what was rejected or missing</p></div>
        <label class="rv-toggle"><input v-model="issuesOnly" type="checkbox" @change="loadRecent" />Only with problems</label>
      </div>
      <ul v-if="recent === null" class="rv-recent-list"><li v-for="n in 3" :key="n"><div class="bc-skeleton sp-sk-line w70"></div></li></ul>
      <p v-else-if="!recent.length" class="sp-hint">{{ issuesOnly ? "No deliveries with rejected or missing stems." : "No deliveries received against orders yet." }}</p>
      <ul v-else class="rv-recent-list">
        <li v-for="r in recent" :key="r.id">
          <button type="button" class="rv-rec" :aria-expanded="openRec === r.id" @click="toggleRec(r)">
            <span class="grow">
              <b>{{ r.order?.order_number }} · {{ r.order?.supplier?.company_name }}</b>
              <small>{{ poDate(r.received_date) }} · {{ r.received_by_name || "Someone on the team" }}<template v-if="r.delivery_ref"> · {{ r.delivery_ref }}</template></small>
            </span>
            <span class="rv-rec-nums">
              <span class="sp-chip green">{{ r.accepted_total }} in</span>
              <span v-if="r.rejected_total" class="sp-chip red">{{ r.rejected_total }} rejected</span>
              <span v-if="r.missing_total" class="sp-chip amber">{{ r.missing_total }} missing</span>
              <span v-if="r.files?.length" class="rv-clip" :title="`${r.files.length} files`"><span v-html="ICONS.clip"></span>{{ r.files.length }}</span>
            </span>
          </button>
          <div v-if="openRec === r.id" class="rv-rec-body">
            <div v-if="!details[r.id]" class="bc-skeleton sp-sk-block"></div>
            <ReceiptLines v-else :receipt="details[r.id]" />
          </div>
        </li>
      </ul>
    </section>
  </HrPage>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import ReceiptLines from "../../../../../components/procurement/ReceiptLines.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { initials } from "../../../../../components/procurement/supplierUi";
import { stageOf, statusText, poDate, dueText, daysUntil, todayYmd } from "../../../../../components/procurement/poUi";
import { CONDITIONS, CHECKS, REJECT_REASONS } from "../../../../../components/procurement/receivingUi";
import { receivingService } from "../../../../../services/receivingService";
import { warehouseBatchService, warehouseLocationService } from "../../../../../services/warehouseBatchService";
import { notifyWarehouseInventoryChanged, warehouseService } from "../../../../../services/warehouseService";
import { toast } from "@/utils/snackbar";
import { linkedId } from "@/utils/hiddenIds";

const router = useRouter();

const mode = ref("order");
const locations = ref([]);
const locationsLoading = ref(false);

// ─── Against a purchase order ───
const openOrders = ref(null);
const orderSearch = ref("");
const order = ref(null);
const lines = ref([]);
const receivedDate = ref(todayYmd());
const deliveryRef = ref("");
const notes = ref("");
const receiptInput = ref(null);
const photoInput = ref(null);
const receiptFile = ref(null);
const receiptPreview = ref("");
const photos = ref([]);
const saving = ref(false);
const formError = ref("");
const serverErr = ref({});

const shownOrders = computed(() => {
  const q = orderSearch.value.trim().toLowerCase();
  return (openOrders.value ?? []).filter((o) => !q || o.order_number?.toLowerCase().includes(q) || o.supplier?.company_name?.toLowerCase().includes(q));
});
const owedUnits = (o) => (o.items ?? []).reduce((s, i) => s + Number(i.outstanding ?? 0), 0);

const rejected = (l) => Math.max(0, (Number(l.delivered) || 0) - (Number(l.accepted) || 0));
const missing = (l) => Math.max(0, l.owed - (Number(l.delivered) || 0));
// Still owed after this delivery: what wasn't accepted
const stillOwed = (l) => Math.max(0, l.owed - (Number(l.accepted) || 0));
const lineErr = (i, field) => serverErr.value[`lines.${i}.${field}`]?.[0] ?? "";
const totals = computed(() => lines.value.reduce((t, l) => ({
  delivered: t.delivered + (Number(l.delivered) || 0),
  accepted: t.accepted + (Number(l.accepted) || 0),
  rejected: t.rejected + rejected(l),
  missing: t.missing + missing(l),
}), { delivered: 0, accepted: 0, rejected: 0, missing: 0 }));
const afterText = computed(() => {
  if (!order.value) return "";
  const open = lines.value.reduce((s, l) => s + (l.close_short ? 0 : stillOwed(l)), 0);
  return open ? `After this, ${open.toLocaleString("en-PH")} stay owed and the order is partly received.` : "After this, nothing is owed and the order is completed.";
});

function pickOrder(o) {
  order.value = o;
  serverErr.value = {};
  formError.value = "";
  lines.value = o.items.map((i) => ({
    item_id: i.id,
    name: i.product_name,
    unit: i.unit,
    product_id: i.product_id,
    owed: Number(i.outstanding ?? 0),
    received: Number(i.received_qty ?? 0),
    delivered: 0,
    accepted: 0,
    condition: "",
    checks: [],
    reject_reason: "",
    close_short: false,
    warehouse_location_id: "",
    harvest_date: "",
    expiration_date: "",
    notes: "",
  }));
}
function clearOrder() {
  order.value = null;
  lines.value = [];
}
// Accepted follows delivered until it's changed by hand
function syncAccepted(l) {
  const d = Math.max(0, Number(l.delivered) || 0);
  if (!l.touched) l.accepted = Math.min(d, l.owed);
  if (d > 0 && !l.condition) l.condition = "fresh";
}
watch(lines, (all) => all.forEach((l) => {
  if (l.accepted !== Math.min(Number(l.delivered) || 0, l.owed)) l.touched = true;
}), { deep: true });
function allArrived() {
  lines.value.forEach((l) => {
    if (!l.owed) return;
    Object.assign(l, { delivered: l.owed, accepted: l.owed, condition: "fresh", checks: CHECKS.map((c) => c.value), close_short: false, touched: false });
  });
}

function setReceipt(file) {
  if (receiptPreview.value) URL.revokeObjectURL(receiptPreview.value);
  receiptPreview.value = "";
  receiptFile.value = null;
  if (!file) return;
  if (file.size > 10 * 1024 * 1024) return toast.error("The receipt can be up to 10 MB.");
  if (!/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(file.type)) return toast.error("Use a photo (JPG, PNG, WebP) or a PDF for the receipt.");
  receiptFile.value = file;
  if (file.type.startsWith("image/")) receiptPreview.value = URL.createObjectURL(file);
}
let photoKey = 0;
function addPhotos(fileList) {
  for (const f of fileList) {
    if (photos.value.length >= 8) {
      toast.info("Up to 8 photos per delivery.");
      break;
    }
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
      toast.error(`${f.name} isn't a photo (JPG, PNG or WebP).`);
      continue;
    }
    if (f.size > 8 * 1024 * 1024) {
      toast.error(`${f.name} is over 8 MB.`);
      continue;
    }
    photos.value.push({ key: ++photoKey, file: f, url: URL.createObjectURL(f) });
  }
}
function removePhoto(i) {
  URL.revokeObjectURL(photos.value[i].url);
  photos.value.splice(i, 1);
}
const fileSize = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

async function saveInspection() {
  formError.value = "";
  serverErr.value = {};
  const local = {};
  lines.value.forEach((l, i) => {
    if (Number(l.accepted) > Number(l.delivered)) local[`lines.${i}.accepted_qty`] = ["You can't accept more than was delivered."];
    if (Number(l.delivered) > 0 && !l.condition) local[`lines.${i}.condition`] = ["Say how the delivery looked."];
    if (rejected(l) > 0 && !l.reject_reason) local[`lines.${i}.reject_reason`] = ["Pick why they were turned away."];
  });
  if (!lines.value.some((l) => Number(l.delivered) > 0 || l.close_short)) {
    formError.value = "Enter what was delivered for at least one line.";
    return;
  }
  if (Object.keys(local).length) {
    serverErr.value = local;
    formError.value = "A few lines need a look.";
    return;
  }

  saving.value = true;
  const fd = new FormData();
  fd.append("purchase_order_id", order.value.id);
  fd.append("received_date", receivedDate.value);
  if (deliveryRef.value) fd.append("delivery_ref", deliveryRef.value);
  if (notes.value) fd.append("notes", notes.value);
  fd.append("lines", JSON.stringify(lines.value.map((l) => ({
    item_id: l.item_id,
    delivered_qty: Number(l.delivered) || 0,
    accepted_qty: Number(l.accepted) || 0,
    condition: l.condition || null,
    checks: l.checks,
    reject_reason: rejected(l) > 0 ? l.reject_reason : null,
    close_short: !!l.close_short,
    notes: l.notes || null,
    warehouse_location_id: l.warehouse_location_id || null,
    harvest_date: l.harvest_date || null,
    expiration_date: l.expiration_date || null,
  }))));
  if (receiptFile.value) fd.append("receipt", receiptFile.value);
  photos.value.forEach((p) => fd.append("photos[]", p.file));

  try {
    const res = await receivingService.inspect(fd);
    const r = res.data.data;
    notifyWarehouseInventoryChanged({ reason: "delivery-received" });
    toast.success(`${r.receipt_number}: ${r.accepted_total} into stock${r.rejected_total ? `, ${r.rejected_total} rejected` : ""}${r.missing_total ? `, ${r.missing_total} missing` : ""}.`, {
      action: { label: "View order", to: { name: "OrderDetail", params: { id: r.purchase_order_id } } },
    });
    resetDelivery();
    loadOpen();
    loadRecent();
  } catch (e) {
    serverErr.value = e?.response?.data?.errors ?? {};
    formError.value = apiMessage(e, "Couldn't save the delivery.");
  } finally {
    saving.value = false;
  }
}
function resetDelivery() {
  clearOrder();
  setReceipt(null);
  photos.value.forEach((p) => URL.revokeObjectURL(p.url));
  photos.value = [];
  deliveryRef.value = "";
  notes.value = "";
  receivedDate.value = todayYmd();
}

async function loadOpen() {
  try {
    const res = await receivingService.openOrders();
    openOrders.value = res.data.data ?? [];
  } catch (e) {
    openOrders.value = [];
    toast.error(apiMessage(e, "Couldn't load the open orders."));
  }
}

// ─── Recent deliveries ───
const recent = ref(null);
const issuesOnly = ref(false);
const openRec = ref(null);
const details = reactive({});
async function loadRecent() {
  try {
    const res = await receivingService.list({ issues: issuesOnly.value ? 1 : undefined, per_page: 10 });
    recent.value = res.data.data ?? [];
  } catch {
    recent.value = [];
  }
}
async function toggleRec(r) {
  openRec.value = openRec.value === r.id ? null : r.id;
  if (openRec.value && !details[r.id]) {
    try {
      details[r.id] = (await receivingService.find(r.id)).data.data;
    } catch (e) {
      openRec.value = null;
      toast.error(apiMessage(e, "Couldn't open that delivery."));
    }
  }
}

// ─── Without an order (unchanged steps: search, fill in, receive) ───
const submitting = ref(false);
const submitted = ref(false);
const errors = reactive({});
const productSearch = ref("");
const productResults = ref([]);
const productsLoading = ref(false);
const selectedProduct = ref(null);
const form = reactive({
  product_id: null,
  qty_received: null,
  received_date: todayYmd(),
  harvest_date: "",
  expiration_date: "",
  freshness_days: null,
  warehouse_location_id: "",
  lot_number: "",
  notes: "",
  source_order_id: null,
});

let searchTimer = null;
function onProductInput() {
  selectedProduct.value = null;
  form.product_id = null;
  clearTimeout(searchTimer);
  if (!productSearch.value.trim()) {
    productResults.value = [];
    return;
  }
  searchTimer = setTimeout(doSearch, 300);
}
async function doSearch() {
  productsLoading.value = true;
  try {
    const res = await warehouseService.searchProducts({ search: productSearch.value, per_page: 10 });
    const body = res.data;
    const raw = body?.data?.data ?? body?.data ?? body ?? [];
    productResults.value = raw.map((p) => ({ ...p, stock_count: p.stock_count ?? p.quantity_in_stock ?? 0 }));
  } catch {
    productResults.value = [];
  } finally {
    productsLoading.value = false;
  }
}
function selectProduct(p) {
  selectedProduct.value = p;
  form.product_id = p.id;
  productSearch.value = p.product_name;
  productResults.value = [];
  if (p.freshness_days) form.freshness_days = p.freshness_days;
}
function clearProduct() {
  selectedProduct.value = null;
  form.product_id = null;
  productSearch.value = "";
}
function calcFreshnessDays() {
  if (form.expiration_date && form.received_date) {
    const diff = Math.ceil((new Date(form.expiration_date) - new Date(form.received_date)) / 86400000);
    if (diff > 0) form.freshness_days = diff;
  }
}
function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!form.product_id) errors.product_id = "Pick the product.";
  if (!form.qty_received || form.qty_received < 1) errors.qty_received = "1 or more.";
  if (!form.received_date) errors.received_date = "Pick the date.";
  return !Object.keys(errors).length;
}
async function submit() {
  if (!validate() || submitted.value) return;
  submitting.value = true;
  try {
    const payload = { ...form };
    for (const k of ["warehouse_location_id", "harvest_date", "expiration_date", "freshness_days", "lot_number", "notes", "source_order_id"]) {
      if (!payload[k]) delete payload[k];
    }
    await warehouseBatchService.receive(payload);
    notifyWarehouseInventoryChanged({ warehouseLocationId: payload.warehouse_location_id ?? null, productId: payload.product_id, reason: "batch-received" });
    submitted.value = true;
    toast.success("Batch received.");
    setTimeout(() => router.push("/erp/procurement/supply-chain/warehouse/floor"), 1000);
  } catch (e) {
    for (const [k, v] of Object.entries(e?.response?.data?.errors ?? {})) errors[k] = v[0];
    toast.error(apiMessage(e, "Couldn't receive the batch."));
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  locationsLoading.value = true;
  warehouseLocationService.list()
    .then((res) => (locations.value = res.data?.data ?? res.data ?? []))
    .catch(() => (locations.value = []))
    .finally(() => (locationsLoading.value = false));
  loadRecent();
  await loadOpen();

  // From an order's page (the router keeps the order ID out of the address bar),
  // or the older prefill the order page passed along
  let wanted = linkedId("order");
  try {
    const prefill = history.state?.prefill ? JSON.parse(history.state.prefill) : null;
    if (!wanted && prefill?.order_id) wanted = Number(prefill.order_id);
  } catch {
    // A broken prefill just means picking the order by hand
  }
  if (wanted) {
    const o = openOrders.value?.find((x) => x.id === wanted);
    if (o) pickOrder(o);
    else toast.info("That order has nothing left to receive.");
  }
});
onBeforeUnmount(() => {
  clearTimeout(searchTimer);
  setReceipt(null);
  photos.value.forEach((p) => URL.revokeObjectURL(p.url));
});
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.rv-mode {
  display: inline-flex;
  margin-bottom: 16px;
}
.rv-mode button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}
.rv-mode :deep(svg) {
  width: 15px;
  height: 15px;
}
.sp-card + .sp-card {
  margin-top: 16px;
}

/* Orders */
.rv-find {
  max-width: 360px;
  margin-bottom: 12px;
}
.rv-orders {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 10px;
}
.rv-sk-order {
  height: 66px;
  border-radius: 14px;
}
.rv-order {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 14px;
  background: var(--v-surface);
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s;
}
.rv-order:hover,
.rv-order:focus-visible {
  border-color: var(--bc-accent);
  outline: none;
}
.rv-order b,
.rv-chosen b {
  font-size: 13.5px;
  color: var(--v-text);
}
.rv-order small,
.rv-chosen .grow > span {
  font-size: 12px;
  color: var(--v-muted);
}
.rv-order-due {
  flex: none;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
}
.rv-order-due.late {
  color: var(--v-red-text);
}
.rv-logo {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12.5px;
  font-weight: 600;
}
.rv-chosen {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}

/* Lines */
.rv-lines {
  display: grid;
  gap: 12px;
}
.rv-line {
  display: grid;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--v-border);
  border-radius: 16px;
}
.rv-line.off {
  opacity: 0.55;
}
.rv-line-head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.rv-line-head b {
  font-size: 14.5px;
  color: var(--v-text);
}
.rv-line-head small {
  font-size: 12px;
  color: var(--v-muted);
}
.rv-counts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  align-items: end;
}
.rv-stat {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.rv-stat b {
  display: flex;
  align-items: center;
  min-height: 42px;
  padding: 0 12px;
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 15px;
  color: var(--v-text);
}
.rv-stat.bad b {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.rv-stat.warn b {
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.rv-cond,
.rv-checks,
.rv-rest {
  margin: 0;
  padding: 0;
  border: 0;
}
.rv-cond legend,
.rv-checks legend,
.rv-rest legend {
  margin-bottom: 6px;
}
.rv-cond {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}
.rv-cond-opt {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border: 1.5px solid var(--v-border);
  border-radius: 12px;
  cursor: pointer;
}
.rv-cond-opt input,
.rv-check input,
.rv-rest-opt input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.rv-cond-opt b {
  font-size: 13px;
  color: var(--v-text);
}
.rv-cond-opt small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.rv-cond-opt.on.t-green { border-color: #10b981; background: var(--v-green-soft); }
.rv-cond-opt.on.t-teal { border-color: #14b8a6; background: var(--v-teal-soft); }
.rv-cond-opt.on.t-amber { border-color: #f59e0b; background: var(--v-amber-soft); }
.rv-cond-opt.on.t-red { border-color: var(--v-red-text); background: var(--v-red-soft); }
.rv-cond-opt:focus-within,
.rv-check:focus-within,
.rv-rest-opt:focus-within {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.rv-checks {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rv-checks legend {
  width: 100%;
}
.rv-check {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 12px;
  border: 1.5px solid var(--v-border-strong);
  border-radius: 999px;
  font-size: 12.5px;
  color: var(--v-text-2);
  cursor: pointer;
  user-select: none;
}
.rv-check.on {
  border-color: var(--bc-accent);
  background: color-mix(in srgb, var(--bc-accent) 10%, transparent);
  color: var(--v-text);
}
.rv-tick {
  display: grid;
  place-items: center;
  width: 16px;
  height: 16px;
  border: 1.5px solid var(--v-border-strong);
  border-radius: 5px;
  color: var(--bc-accent-ink);
}
.rv-check.on .rv-tick {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
}
.rv-tick :deep(svg) {
  width: 11px;
  height: 11px;
}
.rv-reject {
  max-width: 360px;
}
.rv-rest {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rv-rest legend {
  width: 100%;
}
.rv-rest-opt {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  padding: 0 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 12px;
  font-size: 13px;
  color: var(--v-text);
  cursor: pointer;
}
.rv-rest-opt.on {
  border-color: var(--bc-accent);
  background: color-mix(in srgb, var(--bc-accent) 8%, transparent);
  font-weight: 600;
}
.rv-store summary {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text-2);
  cursor: pointer;
}
.rv-store summary small {
  font-weight: 400;
  color: var(--v-faint);
}
.rv-store .sp-grid-3 {
  margin-top: 10px;
}
.rv-note {
  font-size: 13px;
}

/* Files */
.rv-files {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
  gap: 14px;
  margin: 14px 0;
}
.rv-drop {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.rv-add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 108px;
  padding: 12px;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 14px;
  background: var(--v-subtle);
  color: var(--v-text-2);
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.rv-add:hover,
.rv-add:focus-visible {
  border-color: var(--bc-accent);
  outline: none;
}
.rv-add small {
  font-size: 11.5px;
  font-weight: 400;
  color: var(--v-faint);
}
.rv-add :deep(svg) {
  width: 20px;
  height: 20px;
  color: var(--v-muted);
}
.rv-add.small {
  width: 96px;
  min-height: 96px;
  font-size: 12px;
}
.rv-file {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.rv-file img,
.rv-file-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 10px;
  object-fit: cover;
  background: var(--v-subtle);
  color: var(--v-muted);
}
.rv-file b {
  overflow: hidden;
  font-size: 13px;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rv-file small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.rv-thumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rv-thumb {
  position: relative;
  width: 96px;
  height: 96px;
  margin: 0;
  overflow: hidden;
  border-radius: 12px;
}
.rv-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.rv-thumb-x {
  position: absolute;
  top: 4px;
  right: 4px;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.7);
  color: #fff;
  cursor: pointer;
}
.rv-thumb-x :deep(svg) {
  width: 14px;
  height: 14px;
}

/* Summary */
.rv-sum {
  position: sticky;
  bottom: 12px;
  z-index: 5;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  box-shadow: 0 -8px 24px -16px rgba(15, 23, 42, 0.4);
}
.rv-sum dl {
  display: flex;
  flex-wrap: wrap;
  gap: 22px;
  margin: 0;
}
.rv-sum dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.rv-sum dd {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
}
.rv-sum .good dd { color: var(--v-green-text); }
.rv-sum .bad dd { color: var(--v-red-text); }
.rv-sum .warn dd { color: var(--v-amber-text); }
.rv-sum-act {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  max-width: 560px;
}

/* Without an order */
.rv-results {
  list-style: none;
  margin: 6px 0 0;
  padding: 6px;
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  background: var(--v-raised);
}
.rv-results button {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding: 8px 10px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.rv-results button:hover,
.rv-results button:focus-visible {
  background: var(--v-subtle);
  outline: none;
}
.rv-results b {
  font-size: 13px;
}
.rv-results small {
  font-size: 12px;
  color: var(--v-muted);
}
.rv-single-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

/* Recent */
.rv-recent {
  margin-top: 16px;
}
.rv-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: var(--v-text-2);
  cursor: pointer;
}
.rv-toggle input {
  width: 16px;
  height: 16px;
  accent-color: var(--bc-accent);
}
.rv-recent-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.rv-recent-list > li + li {
  border-top: 1px solid var(--v-border);
}
.rv-rec {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 4px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.rv-rec:hover,
.rv-rec:focus-visible {
  background: var(--v-subtle);
  outline: none;
}
.rv-rec b {
  font-size: 13.5px;
  color: var(--v-text);
}
.rv-rec small {
  font-size: 12px;
  color: var(--v-muted);
}
.rv-rec-nums {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.rv-clip {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 12px;
  color: var(--v-muted);
}
.rv-clip :deep(svg) {
  width: 14px;
  height: 14px;
}
.rv-rec-body {
  padding: 4px 4px 14px;
}

@media (max-width: 900px) {
  .rv-cond {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .rv-files {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .rv-mode {
    display: flex;
  }
  .rv-mode button {
    flex: 1;
    justify-content: center;
  }
  .rv-counts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .rv-sum {
    position: static;
  }
  .rv-sum-act {
    justify-content: stretch;
  }
  .rv-sum-act .sp-btn {
    flex: 1;
  }
}
</style>
