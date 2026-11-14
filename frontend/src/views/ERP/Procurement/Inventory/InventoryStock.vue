<template>
  <HrPage title="Stock & replenishment" subtitle="Everything the shop keeps: flowers, wrapping, ribbons, vases and supplies. See what's low, what to order and how much, and every change to the count.">
    <template #actions>
      <router-link v-if="can('inventory_products', 'create')" :to="{ name: 'InventoryAddProduct' }" class="sp-btn sp-btn-primary"><span v-html="ICONS.plus"></span>Add product</router-link>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Headline numbers -->
    <section class="fx-kpis is-5" aria-label="Summary">
      <template v-if="!data">
        <div v-for="n in 5" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div></div>
      </template>
      <template v-else>
        <button v-for="k in kpis" :key="k.key" type="button" class="sp-card fx-kpi st-kpi" :class="[`t-${k.tone}`, { on: state === k.filter && k.filter }]" :disabled="!k.filter" @click="k.filter && setState(k.filter)">
          <span class="fx-kpi-label"><span class="st-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">{{ k.sub }}</span>
        </button>
      </template>
    </section>

    <!-- Filters -->
    <div class="sp-card st-bar">
      <div class="st-kinds" role="group" aria-label="Kind of stock">
        <button v-for="k in kindTabs" :key="k.value" type="button" class="st-kind" :class="{ on: kind === k.value }" :aria-pressed="kind === k.value" @click="kind = k.value">
          <span v-if="k.icon" v-html="ICONS[k.icon]"></span>{{ k.label }}<span class="count">{{ k.count }}</span>
        </button>
      </div>
      <div class="st-bar-row">
        <div class="sp-seg" role="group" aria-label="Stock level">
          <button v-for="s in STATES" :key="s.value" type="button" :class="{ on: state === s.value }" :aria-pressed="state === s.value" @click="setState(s.value)">{{ s.label }}</button>
        </div>
        <label class="sp-search st-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Search name or SKU" aria-label="Search stock" />
        </label>
      </div>
    </div>

    <!-- Reorder bar -->
    <Transition name="sp-fade">
      <div v-if="picked.length" class="sp-card st-pick" role="region" aria-label="Selected to reorder">
        <span><b>{{ picked.length }}</b> selected · {{ pickedUnits.toLocaleString("en-PH") }} units</span>
        <div class="st-pick-act">
          <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="picked = []">Clear</button>
          <button v-if="can('sc_orders', 'create')" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="orderPicked"><span v-html="ICONS.receipt"></span>Start a purchase order</button>
        </div>
      </div>
    </Transition>

    <!-- Stems to buy for the bouquets that need restocking -->
    <section v-if="data?.bouquet_flowers?.length" class="sp-card st-bf" aria-labelledby="st-bf">
      <div class="sp-card-head">
        <div>
          <h2 id="st-bf">Flowers needed for bouquets</h2>
          <p>Stems to make the bouquets that need restocking: each bouquet's order amount × the flowers in it, at what the shop pays per piece</p>
        </div>
        <div v-if="bouquetFlowersCost > 0" class="st-bf-total">
          <small>About</small>
          <b>{{ money(bouquetFlowersCost) }}</b>
          <small>to buy</small>
        </div>
      </div>
      <ul class="st-bf-list">
        <li v-for="f in data.bouquet_flowers" :key="`${f.flower}|${f.color || ''}`" class="st-bf-row">
          <span class="st-bf-name">
            <i class="st-bf-dot" :style="{ background: flowerSwatch(f.color || f.flower) }" aria-hidden="true"></i>
            <b>{{ f.color ? `${f.color} ${f.flower.toLowerCase()}` : f.flower }}</b>
          </span>
          <span class="st-bf-for">
            <small v-for="b in f.bouquets" :key="b.id">{{ b.make }} × {{ b.name }} <span class="muted">({{ b.per }} each{{ b.cost_each != null ? ` at ${money(b.cost_each)}` : "" }})</span></small>
          </span>
          <span class="st-bf-stems">
            <b>{{ f.stems.toLocaleString("en-PH") }} <small>{{ rowUnit(f.kind, f.stems) }}</small></b>
            <small v-if="f.priced_stems">{{ money(f.cost) }}<template v-if="f.priced_stems < f.stems"> · {{ f.stems - f.priced_stems }} not priced</template></small>
            <small v-else class="muted">No cost entered</small>
          </span>
        </li>
      </ul>
    </section>

    <!-- Items -->
    <section class="sp-card st-card" aria-label="Stock">
      <div v-if="!data" class="st-rows"><div v-for="n in 6" :key="n" class="st-row sk"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      <div v-else-if="!data.items.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.box"></span>
        <h3>Nothing in stock yet</h3>
        <p>Products you add appear here with their stock. Add wrapping, ribbons and vases as materials to count them too.</p>
      </div>
      <div v-else-if="!shown.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.search"></span>
        <h3>Nothing matches</h3>
        <p>Try another kind, level or search.</p>
        <button type="button" class="sp-btn sp-btn-ghost" @click="clearFilters">Clear filters</button>
      </div>
      <template v-else>
        <div class="st-head" aria-hidden="true">
          <span></span><span>Item</span><span>On hand</span><span>Pack</span><span>Minimum · target</span><span>Coming &amp; use</span><span>Order</span><span></span>
        </div>
        <ul class="st-rows">
          <li v-for="it in visible" :key="it.id" class="st-row" :class="`s-${it.state}`">
            <label class="st-sel" :title="it.suggestion.qty ? 'Select to reorder' : 'Nothing to reorder'">
              <input v-model="picked" type="checkbox" :value="it.id" :disabled="!it.suggestion.qty" :aria-label="`Select ${it.name} to reorder`" />
            </label>
            <span class="st-item">
              <span class="st-photo" :class="{ empty: !it.photo }">
                <img v-if="it.photo" :src="it.photo" :alt="it.name" loading="lazy" decoding="async" />
                <span v-else v-html="it.kind === 'flowers' ? ICONS.flower : ICONS.image" aria-hidden="true"></span>
              </span>
              <span class="st-item-text">
              <b>{{ it.name }}</b>
              <small>
                <span class="sp-chip" :class="kindOf(it.kind).tone">{{ kindOf(it.kind).label }}</span>
                <span v-if="it.material" class="sp-chip slate">Material</span>
                <span v-if="it.sku">{{ it.sku }}</span>
              </small>
              <small v-if="it.composition?.length" class="st-compo" :title="'Flowers in one bouquet'">
                {{ it.composition.map((f) => `${f.count} ${f.kind === "wrapper" ? `${rowUnit(f.kind, f.count)} of ` : f.kind === "filler" || f.kind === "ribbon" ? "of " : ""}${f.color ? `${f.color.toLowerCase()} ` : ""}${f.flower.toLowerCase()}`).join(" · ") }} per bouquet<template v-if="it.composition.some((f) => f.cost != null)"> · flowers {{ money(it.composition.reduce((s, f) => s + (f.cost != null ? f.cost * f.count : 0), 0)) }}</template>
              </small>
              </span>
            </span>
            <span class="st-onhand">
              <b>{{ it.on_hand.toLocaleString("en-PH") }} <small>{{ unitOf(it) }}</small></b>
              <small v-if="it.pack_size" class="st-packs">{{ packText(it, it.on_hand) }}</small>
              <span class="st-level" role="img" :aria-label="levelLabel(it)"><span :style="{ width: `${levelPct(it)}%` }"></span><i v-if="it.min && it.target" :style="{ left: `${(it.min / it.target) * 100}%` }"></i></span>
              <small :class="`st-state ${it.state}`">{{ STATE_TEXT[it.state] }}</small>
            </span>
            <!-- Bought in packs: how many of its unit one pack holds (e.g. 50 sheets per roll) -->
            <span class="st-pack">
              <template v-if="it.pack_size">
                <b>{{ it.pack_size.toLocaleString("en-PH") }} <small>{{ plural(unitOf(it), it.pack_size) }}</small></b>
                <small>per {{ it.pack_unit }}</small>
              </template>
              <button v-else-if="canEdit('inventory_products') && it.kind !== 'flowers'" type="button" class="st-pack-set" @click="openLevels(it, true)">
                Set {{ plural(unitOf(it), 2) }} per pack
              </button>
              <small v-else class="muted">Single {{ unitOf(it) }}</small>
            </span>
            <span class="st-levels">
              <b v-if="it.min">{{ it.min.toLocaleString("en-PH") }}<template v-if="it.target"> · {{ it.target.toLocaleString("en-PH") }}</template></b>
              <b v-else class="st-none">Not set</b>
              <small>{{ it.lead_days !== null ? `${it.lead_days}-day lead time` : "No lead time" }}</small>
            </span>
            <span class="st-flow">
              <small v-if="it.on_order"><span v-html="ICONS.truck"></span>{{ it.on_order.toLocaleString("en-PH") }} on order</small>
              <small v-if="it.used_30d">{{ it.used_30d.toLocaleString("en-PH") }} used in 30 days{{ it.days_left !== null ? ` · about ${it.days_left} ${it.days_left === 1 ? "day" : "days"} left` : "" }}</small>
              <small v-if="it.damaged_30d" class="bad">{{ it.damaged_30d }} damaged in 30 days</small>
              <small v-if="!it.on_order && !it.used_30d && !it.damaged_30d" class="muted">No movement in 30 days</small>
            </span>
            <span class="st-suggest">
              <template v-if="it.suggestion.qty">
                <b>Order {{ it.suggestion.qty.toLocaleString("en-PH") }}<template v-if="it.pack_size"> · {{ packsToOrder(it) }} {{ plural(it.pack_unit, packsToOrder(it)) }}</template></b>
                <small :title="it.suggestion.why">{{ it.last_supplier ? `${it.last_supplier.name} · ${money(it.last_supplier.price)}` : it.suggestion.why }}</small>
              </template>
              <small v-else class="muted">{{ it.suggestion.why }}</small>
            </span>
            <span class="st-act">
              <button v-if="canEdit('inventory_products')" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openAdjust(it)">Adjust</button>
              <button v-if="canEdit('inventory_products')" type="button" class="sp-icon-btn" :aria-label="`Stock levels for ${it.name}`" title="Minimum, target and lead time" @click="openLevels(it)"><span v-html="ICONS.gear"></span></button>
            </span>
          </li>
        </ul>
        <ShowMore tone="vendor" :has-more="hasMore" :shown="visible.length" :total="shown.length" @more="showMore" />
      </template>
    </section>

    <!-- Recent changes -->
    <section class="sp-card st-hist" aria-labelledby="st-hist">
      <div class="sp-card-head">
        <div><h2 id="st-hist">Stock changes</h2><p>Every manual change and everything thrown away, newest first</p></div>
        <div class="sp-seg" role="group" aria-label="Show">
          <button type="button" :class="{ on: histFilter === '' }" @click="setHist('')">All changes</button>
          <button type="button" :class="{ on: histFilter === 'damaged' }" @click="setHist('damaged')">Damaged only</button>
        </div>
      </div>
      <ul v-if="history === null" class="st-hist-list"><li v-for="n in 4" :key="n"><div class="bc-skeleton sp-sk-line w70"></div></li></ul>
      <p v-else-if="!history.length" class="sp-hint">{{ histFilter ? "Nothing damaged or thrown away yet." : "No stock changes yet." }}</p>
      <ul v-else class="st-hist-list">
        <li v-for="h in history" :key="h.key">
          <span class="st-chg" :class="h.change < 0 ? 'down' : 'up'">{{ h.change > 0 ? "+" : "" }}{{ h.change.toLocaleString("en-PH") }}</span>
          <div class="grow">
            <b>{{ h.product || "Removed product" }}</b>
            <small>{{ h.reason_label }}<template v-if="h.before !== null"> · {{ h.before }} → {{ h.after }}</template><template v-if="h.note"> · {{ h.note }}</template></small>
          </div>
          <small class="st-when">{{ h.by || (h.source === "cull" ? "Warehouse" : "") }}<br />{{ when(h.at) }}</small>
        </li>
      </ul>
    </section>

    <!-- Adjust -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="adj" class="sp-backdrop" @click="adj = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="adj" class="sp-modal-wrap">
          <form class="sp-modal st-form" role="dialog" aria-modal="true" aria-labelledby="st-adj-title" novalidate @submit.prevent="saveAdjust">
            <div class="sp-modal-head">
              <div><h3 id="st-adj-title">Adjust {{ adj.item.name }}</h3><p>{{ adj.item.on_hand.toLocaleString("en-PH") }} {{ unitOf(adj.item) }} on hand now.{{ adj.item.batch_tracked ? " Kept in warehouse batches: what you take away comes out of the batches that expire first." : "" }}</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="adj = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-seg st-modes" role="radiogroup" aria-label="Change">
              <button v-for="m in MODES" :key="m.value" type="button" role="radio" :aria-checked="adj.mode === m.value" :class="{ on: adj.mode === m.value }" @click="setMode(m.value)">{{ m.label }}</button>
            </div>
            <!-- Bought in packs: count in rolls or in single sheets -->
            <div v-if="adj.item.pack_size" class="sp-seg st-units" role="radiogroup" aria-label="Count in">
              <button type="button" role="radio" :aria-checked="adj.unit === 'piece'" :class="{ on: adj.unit === 'piece' }" @click="adj.unit = 'piece'">{{ plural(unitOf(adj.item), 2) }}</button>
              <button type="button" role="radio" :aria-checked="adj.unit === 'pack'" :class="{ on: adj.unit === 'pack' }" @click="adj.unit = 'pack'">{{ plural(adj.item.pack_unit, 2) }} of {{ adj.item.pack_size }}</button>
            </div>
            <label class="sp-field">
              <span class="sp-label">{{ adj.mode === "set" ? "Counted on hand" : adj.mode === "add" ? "How many to add" : "How many to take away" }}<template v-if="adj.item.pack_size"> ({{ plural(adj.unit === "pack" ? adj.item.pack_unit : unitOf(adj.item), 2) }})</template></span>
              <input v-model.number="adj.quantity" class="sp-input" :class="{ invalid: adj.errors.quantity }" type="number" min="0" step="1" inputmode="numeric" />
              <small v-if="adj.item.pack_size && adj.unit === 'pack' && Number(adj.quantity) > 0" class="sp-hint">= {{ (Number(adj.quantity) * adj.item.pack_size).toLocaleString("en-PH") }} {{ plural(unitOf(adj.item), Number(adj.quantity) * adj.item.pack_size) }}</small>
              <small v-if="adj.errors.quantity" class="sp-error">{{ adj.errors.quantity }}</small>
              <small v-else-if="adjAfter !== null && adjAfter < 0" class="sp-error">Only {{ adj.item.on_hand.toLocaleString("en-PH") }} on hand, so you can take away at most that.</small>
              <small v-else-if="adjAfter !== null" class="sp-hint">Stock becomes {{ adjAfter.toLocaleString("en-PH") }}{{ adj.item.min && adjAfter <= adj.item.min ? ", at or under the minimum" : "" }}.</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Why</span>
              <select v-model="adj.reason" class="sp-select" :class="{ invalid: adj.errors.reason }">
                <option value="">Pick a reason</option>
                <option v-for="r in reasonsFor(adj.mode)" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <small v-if="adj.errors.reason" class="sp-error">{{ adj.errors.reason }}</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Note <small>{{ adj.reason === "damaged" ? "(what happened)" : "(optional)" }}</small></span>
              <input v-model.trim="adj.note" class="sp-input" maxlength="500" :placeholder="adj.reason === 'damaged' ? 'e.g. Crushed in the cooler, botrytis on 6 stems' : ''" />
            </label>
            <p v-if="adj.error" class="sp-error">{{ adj.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="adj = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="adj.busy">{{ adj.busy ? "Saving…" : "Save change" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Levels -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="lv" class="sp-backdrop" @click="lv = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="lv" class="sp-modal-wrap">
          <form class="sp-modal st-form" role="dialog" aria-modal="true" aria-labelledby="st-lv-title" novalidate @submit.prevent="saveLevels">
            <div class="sp-modal-head">
              <div><h3 id="st-lv-title">Stock levels for {{ lv.item.name }}</h3><p>Below the minimum it's flagged and you get an alert. Reorders fill it back up to the target.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="lv = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span class="sp-label">Minimum</span>
                <input v-model.number="lv.min" class="sp-input" type="number" min="0" step="1" inputmode="numeric" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Target <small>(fill up to)</small></span>
                <input v-model="lv.target" class="sp-input" :class="{ invalid: lv.errors.max_stock_level }" type="number" min="0" step="1" inputmode="numeric" :placeholder="lv.min ? `${lv.min * 2} if empty` : ''" />
                <small v-if="lv.errors.max_stock_level" class="sp-error">{{ lv.errors.max_stock_level }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Lead time <small>(days to arrive)</small></span>
                <input v-model="lv.lead" class="sp-input" type="number" min="0" max="180" step="1" inputmode="numeric" :placeholder="lv.item.last_supplier ? 'Uses the supplier’s if empty' : ''" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Counted in <small>(the smallest unit you use)</small></span>
                <input v-model.trim="lv.unit" class="sp-input" maxlength="20" list="st-units" placeholder="e.g. stem, sheet, piece" />
                <datalist id="st-units"><option v-for="u in UNIT_HINTS" :key="u" :value="u" /></datalist>
              </label>
              <label class="sp-field">
                <span class="sp-label">Bought in <small>(optional pack)</small></span>
                <input v-model.trim="lv.pack_unit" class="sp-input" maxlength="20" list="st-packs" placeholder="e.g. roll, box, bundle" />
                <datalist id="st-packs"><option v-for="u in PACK_HINTS" :key="u" :value="u" /></datalist>
              </label>
              <label v-if="lv.pack_unit" class="sp-field">
                <span class="sp-label">{{ cap(plural(lv.unit || "piece", 2)) }} in one {{ lv.pack_unit }}</span>
                <input v-model="lv.pack_size" class="sp-input" :class="{ invalid: lv.errors.pack_size }" type="number" min="2" step="1" inputmode="numeric" placeholder="e.g. 50" />
                <small v-if="lv.errors.pack_size" class="sp-error">{{ lv.errors.pack_size }}</small>
                <small v-else class="sp-hint">Measure one {{ lv.pack_unit }} once: its length ÷ one {{ lv.unit || "piece" }}.</small>
              </label>
              <label v-if="lvConvert" class="st-convert sp-span-all">
                <input v-model="lv.convert" type="checkbox" />
                <span>
                  Change the <b>{{ lvConvert.from.toLocaleString("en-PH") }} {{ plural(lv.pack_unit, lvConvert.from) }}</b> on hand into
                  <b>{{ lvConvert.to.toLocaleString("en-PH") }} {{ plural(lv.unit || "piece", lvConvert.to) }}</b>
                  <small>Saved as a stock count in the history.</small>
                </span>
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Kind of stock</span>
                <select v-model="lv.kind" class="sp-select">
                  <option v-for="k in STOCK_KINDS" :key="k.value" :value="k.value">{{ k.label }}</option>
                </select>
              </label>
            </div>
            <p v-if="lv.used" class="sp-hint">Used {{ lv.item.used_30d }} in the last 30 days, about {{ (lv.item.used_30d / 30).toFixed(1) }} a day.</p>
            <p v-if="lv.error" class="sp-error">{{ lv.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="lv = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="lv.busy">{{ lv.busy ? "Saving…" : "Save levels" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Add material -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="mat" class="sp-backdrop" @click="mat = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="mat" class="sp-modal-wrap">
          <form class="sp-modal wide st-form" role="dialog" aria-modal="true" aria-labelledby="st-mat-title" novalidate @submit.prevent="saveMaterial">
            <div class="sp-modal-head">
              <div><h3 id="st-mat-title">Add a vase or supply</h3><p>Something the shop keeps but doesn't sell. Customers never see it. Flowers, bouquets, wrappers, fillers, ribbons and cards are added from Add product.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="mat = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span class="sp-label">Name</span>
                <input v-model.trim="mat.name" class="sp-input" :class="{ invalid: mat.errors.name }" maxlength="255" placeholder="e.g. Kraft paper, 50 cm roll" />
                <small v-if="mat.errors.name" class="sp-error">{{ mat.errors.name }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Kind</span>
                <select v-model="mat.stock_kind" class="sp-select">
                  <option v-for="k in STOCK_KINDS.filter((x) => x.value !== 'flowers')" :key="k.value" :value="k.value">{{ k.label }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span class="sp-label">Counted in <small>(the smallest unit you use)</small></span>
                <input v-model.trim="mat.stock_unit" class="sp-input" maxlength="20" list="st-units" placeholder="e.g. sheet" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Bought in <small>(optional pack)</small></span>
                <input v-model.trim="mat.pack_unit" class="sp-input" maxlength="20" list="st-packs" placeholder="e.g. roll, box, bundle" />
                <datalist id="st-packs"><option v-for="u in PACK_HINTS" :key="u" :value="u" /></datalist>
              </label>
              <label v-if="mat.pack_unit" class="sp-field">
                <span class="sp-label">{{ cap(plural(mat.stock_unit || "piece", 2)) }} in one {{ mat.pack_unit }}</span>
                <input v-model="mat.pack_size" class="sp-input" :class="{ invalid: mat.errors.pack_size }" type="number" min="2" step="1" inputmode="numeric" placeholder="e.g. 50" />
                <small v-if="mat.errors.pack_size" class="sp-error">{{ mat.errors.pack_size }}</small>
                <small v-else class="sp-hint">Measure one {{ mat.pack_unit }} once: its length ÷ one {{ mat.stock_unit || "piece" }}.</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">On hand now <small>({{ plural(mat.stock_unit || "piece", 2) }})</small></span>
                <input v-model.number="mat.on_hand" class="sp-input" type="number" min="0" step="1" inputmode="numeric" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Cost per unit (₱) <small>(optional)</small></span>
                <input v-model="mat.unit_cost" class="sp-input" type="number" min="0" step="0.01" inputmode="decimal" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Minimum</span>
                <input v-model.number="mat.min_stock_level" class="sp-input" type="number" min="0" step="1" inputmode="numeric" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Target <small>(optional)</small></span>
                <input v-model="mat.max_stock_level" class="sp-input" :class="{ invalid: mat.errors.max_stock_level }" type="number" min="0" step="1" inputmode="numeric" />
                <small v-if="mat.errors.max_stock_level" class="sp-error">{{ mat.errors.max_stock_level }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">SKU <small>(optional)</small></span>
                <input v-model.trim="mat.sku" class="sp-input" :class="{ invalid: mat.errors.sku }" maxlength="100" placeholder="Made for you if empty" />
                <small v-if="mat.errors.sku" class="sp-error">{{ mat.errors.sku }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Lead time <small>(days, optional)</small></span>
                <input v-model="mat.supplier_lead_time" class="sp-input" type="number" min="0" max="180" step="1" inputmode="numeric" />
              </label>
            </div>
            <p v-if="mat.error" class="sp-error">{{ mat.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="mat = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="mat.busy">{{ mat.busy ? "Adding…" : "Add" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import HrPage from "../../../../components/hr/HrPage.vue";
import ShowMore from "../../../../components/common/ShowMore.vue";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../../components/vendor/finance/financeUi";
import { useShowMoreLocal } from "../../../../composables/useShowMore";
import { useAssignment } from "../../../../composables/useAssignment";
import { inventoryStockService } from "../../../../services/inventoryStockService";
import { toast } from "@/utils/snackbar";
import { flowerSwatch } from "../../../../utils/flowerColors.js";
import "../../../../components/vendor/finance/finance.css";

const STOCK_KINDS = [
  { value: "flowers", label: "Flowers", icon: "flower", tone: "pink" },
  { value: "greenery", label: "Fillers", icon: "sparkle", tone: "green" },
  { value: "wrapping", label: "Wrapping", icon: "note", tone: "amber" },
  { value: "ribbons", label: "Ribbons", icon: "tag", tone: "violet" },
  { value: "vases", label: "Vases", icon: "box", tone: "blue" },
  { value: "supplies", label: "Supplies", icon: "gear", tone: "slate" },
];
const STATES = [
  { value: "", label: "All" },
  { value: "reorder", label: "To reorder" },
  { value: "low", label: "Low" },
  { value: "out", label: "Out" },
  { value: "no_min", label: "No minimum" },
];
const STATE_TEXT = { out: "Out of stock", low: "Running low", ok: "In stock", over: "Above target" };
const MODES = [
  { value: "remove", label: "Take away" },
  { value: "add", label: "Add" },
  { value: "set", label: "Count" },
];
const UNIT_HINTS = ["stem", "sheet", "portion", "piece", "bunch", "meter"];
const PACK_HINTS = ["roll", "sack", "box", "bundle", "pack", "bunch"];

// ─── Bought in packs: stock counts the smallest unit (sheets), a pack (roll) holds pack_size ───
const cap = (s) => String(s || "").charAt(0).toUpperCase() + String(s || "").slice(1);
/** "roll" → "rolls" (words already ending in s, like "pcs", stay as they are) */
const plural = (word, n) => (n === 1 || /s$/i.test(String(word || "")) ? word : `${word}s`);
/** What a bouquet row counts: stems of a flower, sheets of a wrapper; a filler is just a number ("2 of red fillers") */
const rowUnit = (kind, n) => (kind === "wrapper" ? plural("sheet", n) : kind === "filler" || kind === "ribbon" ? "" : plural("stem", n));
/** 95 sheets of 50 per roll → "≈ 1 roll + 45 sheets" */
function packText(it, n) {
  const packs = Math.floor(n / it.pack_size);
  const rest = n % it.pack_size;
  if (!packs) return `less than 1 ${it.pack_unit}`;
  return `≈ ${packs} ${plural(it.pack_unit, packs)}${rest ? ` + ${rest} ${plural(unitOf(it), rest)}` : ""}`;
}
/** Whole packs to order for the suggested amount */
const packsToOrder = (it) => Math.max(1, Math.ceil(it.suggestion.qty / it.pack_size));

const router = useRouter();
const route = useRoute();
const { can, canEdit } = useAssignment();
const data = ref(null);
const loadError = ref("");
const kind = ref("");
const state = ref("");
const search = ref("");
const picked = ref([]);
const history = ref(null);
const histFilter = ref("");
const adj = ref(null);
const lv = ref(null);
const mat = ref(null);

const kindOf = (k) => STOCK_KINDS.find((x) => x.value === k) ?? STOCK_KINDS[0];
const unitOf = (it) => it.unit || (["flowers", "greenery"].includes(it.kind) ? "stems" : "pcs");
const kindTabs = computed(() => [
  { value: "", label: "All", count: data.value?.counts.total ?? 0 },
  ...STOCK_KINDS.map((k) => ({ ...k, count: data.value?.counts.by_kind?.[k.value] ?? 0 })).filter((k) => k.count || kind.value === k.value),
]);
const kpis = computed(() => {
  const c = data.value.counts;
  return [
    { key: "out", label: "Out of stock", icon: "alert", tone: "red", value: c.out, sub: c.out ? "nothing left" : "nothing has run out", filter: "out" },
    { key: "low", label: "Running low", icon: "clock", tone: "amber", value: c.low, sub: "at or under the minimum", filter: "low" },
    { key: "reorder", label: "To reorder", icon: "receipt", tone: "blue", value: c.reorder, sub: "with a suggested amount", filter: "reorder" },
    { key: "damaged", label: "Damaged (30 days)", icon: "trash", tone: "pink", value: c.damaged_30d.toLocaleString("en-PH"), sub: "thrown away or written off", filter: null },
    { key: "value", label: "Stock value", icon: "wallet", tone: "green", value: money(c.stock_value), sub: c.no_min ? `${c.no_min} without a minimum` : "at last purchase price", filter: c.no_min ? "no_min" : null },
  ];
});

const shown = computed(() => {
  const q = search.value.trim().toLowerCase();
  const order = { out: 0, low: 1, ok: 2, over: 3 };
  return (data.value?.items ?? [])
    .filter((it) => !kind.value || it.kind === kind.value)
    .filter((it) => !state.value
      || (state.value === "reorder" && it.suggestion.qty > 0)
      || (state.value === "no_min" && !it.min)
      || it.state === state.value)
    .filter((it) => !q || it.name.toLowerCase().includes(q) || (it.sku ?? "").toLowerCase().includes(q))
    .sort((a, b) => order[a.state] - order[b.state] || b.suggestion.qty - a.suggestion.qty || a.name.localeCompare(b.name));
});
const { visible, hasMore, showMore } = useShowMoreLocal(shown, computed(() => `${kind.value}|${state.value}|${search.value}`));

const pickedItems = computed(() => (data.value?.items ?? []).filter((it) => picked.value.includes(it.id)));
const pickedUnits = computed(() => pickedItems.value.reduce((s, it) => s + it.suggestion.qty, 0));
// What the stems for the bouquets to restock cost to buy (flowers with a cost per piece only)
const bouquetFlowersCost = computed(() => (data.value?.bouquet_flowers || []).reduce((s, f) => s + Number(f.cost || 0), 0));

const levelPct = (it) => {
  const scale = it.target || Math.max(it.min * 2, it.on_hand, 1);
  return Math.max(it.on_hand > 0 ? 3 : 0, Math.min(100, (it.on_hand / scale) * 100));
};
const levelLabel = (it) => `${it.on_hand} on hand${it.min ? `, minimum ${it.min}` : ""}${it.target ? `, target ${it.target}` : ""}`;
const when = (d) => (d ? new Date(d).toLocaleString("en-PH", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "");

function setState(v) {
  state.value = state.value === v ? "" : v;
}
function clearFilters() {
  kind.value = "";
  state.value = "";
  search.value = "";
}

async function load() {
  loadError.value = "";
  try {
    const res = await inventoryStockService.overview();
    data.value = res.data.data;
    picked.value = picked.value.filter((id) => data.value.items.some((it) => it.id === id && it.suggestion.qty > 0));
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't load the stock.");
  }
}
async function loadHistory() {
  try {
    const res = await inventoryStockService.history({ filter: histFilter.value || undefined, limit: 30 });
    history.value = res.data.data ?? [];
  } catch {
    history.value = [];
  }
}
function setHist(v) {
  histFilter.value = v;
  history.value = null;
  loadHistory();
}

// ─── Reorder: a purchase order filled in with the suggestions ───
function orderPicked() {
  const items = pickedItems.value;
  const suppliers = [...new Set(items.map((it) => it.last_supplier?.active ? it.last_supplier.id : null).filter(Boolean))];
  const prefill = {
    supplier_id: suppliers.length === 1 ? suppliers[0] : null,
    lines: items.map((it) =>
      // Bought in packs: order whole rolls (receiving them adds their sheets)
      it.pack_size
        ? {
            product_id: it.id,
            product_name: it.name,
            quantity: packsToOrder(it),
            unit: it.pack_unit,
            price: String(it.last_supplier?.unit || "").toLowerCase() === it.pack_unit.toLowerCase() ? it.last_supplier.price : (it.unit_cost ?? 0) * it.pack_size,
          }
        : { product_id: it.id, product_name: it.name, quantity: it.suggestion.qty, unit: it.last_supplier?.unit || it.unit || (it.kind === "flowers" ? "stem" : "piece"), price: it.last_supplier?.price ?? it.unit_cost ?? 0 },
    ),
  };
  if (suppliers.length > 1) toast.info("These were last bought from different suppliers. Pick one supplier for this order, then make another for the rest.");
  router.push({ name: "OrderCreate", state: { prefill: JSON.stringify(prefill) } });
}

// ─── Adjust ───
const adjAfter = computed(() => {
  if (!adj.value || adj.value.quantity === "" || adj.value.quantity === null) return null;
  const q = (Number(adj.value.quantity) || 0) * (adj.value.unit === "pack" ? adj.value.item.pack_size || 1 : 1);
  const b = adj.value.item.on_hand;
  return adj.value.mode === "add" ? b + q : adj.value.mode === "remove" ? b - q : q;
});
const reasonsFor = (mode) => (data.value?.reasons ?? []).filter((r) => (mode === "add" ? r.value !== "damaged" && r.value !== "used" && r.value !== "sold_offline" : mode === "remove" ? r.value !== "restock" && r.value !== "returned" : true));
function openAdjust(it) {
  adj.value = { item: it, mode: "remove", unit: "piece", quantity: "", reason: "", note: "", errors: {}, error: "", busy: false };
}
function setMode(m) {
  adj.value.mode = m;
  if (!reasonsFor(m).some((r) => r.value === adj.value.reason)) adj.value.reason = m === "set" ? "count" : "";
}
async function saveAdjust() {
  const a = adj.value;
  a.errors = {};
  a.error = "";
  if (a.quantity === "" || Number(a.quantity) < 0) a.errors.quantity = "Enter a number.";
  else if (adjAfter.value < 0) a.errors.quantity = `Only ${a.item.on_hand} on hand.`;
  if (!a.reason) a.errors.reason = "Pick why the stock changed.";
  if (Object.keys(a.errors).length) return;
  a.busy = true;
  try {
    await inventoryStockService.adjust(a.item.id, { mode: a.mode, quantity: Number(a.quantity), unit: a.item.pack_size ? a.unit : null, reason: a.reason, note: a.note || null });
    toast.success(`${a.item.name}: stock is now ${adjAfter.value.toLocaleString("en-PH")}.`);
    adj.value = null;
    load();
    loadHistory();
  } catch (e) {
    a.busy = false;
    a.error = apiMessage(e, "Couldn't change the stock.");
  }
}

// ─── Levels ───
function openLevels(it, settingPack = false) {
  lv.value = { item: it, min: it.min, target: it.target ?? "", lead: it.lead_days ?? "", unit: it.unit ?? "", pack_unit: it.pack_unit ?? "", pack_size: it.pack_size ?? "", kind: it.kind, used: it.used_30d > 0, convert: true, errors: {}, error: "", busy: false };
  // Something counted in rolls so far: suggest counting sheets, bought in rolls
  if (settingPack && !it.pack_size && PACK_HINTS.includes(String(it.unit || "").toLowerCase())) {
    lv.value.pack_unit = String(it.unit).toLowerCase();
    lv.value.unit = it.kind === "wrapping" ? "sheet" : "piece";
  }
}
/**
 * Switching from counting packs to counting what's in them: the stock on hand
 * was counted in the old unit (50 rolls), so it can be changed to the new one
 * (50 × 50 = 2,500 sheets) in the same save.
 */
const lvConvert = computed(() => {
  const l = lv.value;
  if (!l || l.item.pack_size || !l.pack_unit || !(Number(l.pack_size) >= 2) || !l.item.on_hand) return null;
  if (String(l.item.unit || "").toLowerCase() !== String(l.pack_unit).toLowerCase()) return null;
  return { from: l.item.on_hand, to: l.item.on_hand * Number(l.pack_size) };
});
async function saveLevels() {
  const l = lv.value;
  l.errors = {};
  l.error = "";
  if (l.target !== "" && Number(l.target) < Number(l.min)) {
    l.errors.max_stock_level = "The target has to be at least the minimum.";
    return;
  }
  if (l.pack_unit && !(Number(l.pack_size) >= 2)) {
    l.errors.pack_size = `Enter how many ${plural(l.unit || "piece", 2)} are in one ${l.pack_unit} (2 or more).`;
    return;
  }
  l.busy = true;
  try {
    await inventoryStockService.setLevels(l.item.id, {
      min_stock_level: Number(l.min) || 0,
      max_stock_level: l.target === "" ? null : Number(l.target),
      supplier_lead_time: l.lead === "" ? null : Number(l.lead),
      stock_unit: l.unit || null,
      pack_unit: l.pack_unit || null,
      pack_size: l.pack_unit ? Number(l.pack_size) : null,
      stock_kind: l.kind,
    });
    // 50 rolls on hand become 2,500 sheets
    if (lvConvert.value && l.convert) {
      await inventoryStockService.adjust(l.item.id, {
        mode: "set",
        quantity: lvConvert.value.to,
        reason: "count",
        note: `Counted in ${plural(l.unit || "piece", 2)} now: ${lvConvert.value.from} ${plural(l.pack_unit, lvConvert.value.from)} × ${l.pack_size}`,
      });
      loadHistory();
    }
    toast.success(`Levels saved for ${l.item.name}.`);
    lv.value = null;
    load();
  } catch (e) {
    l.busy = false;
    for (const [k, v] of Object.entries(e?.response?.data?.errors ?? {})) l.errors[k] = v[0];
    l.error = Object.keys(l.errors).length ? "" : apiMessage(e, "Couldn't save the levels.");
  }
}

// ─── Add material ───
function openMaterial() {
  mat.value = { name: "", stock_kind: "vases", stock_unit: "", pack_unit: "", pack_size: "", on_hand: 0, unit_cost: "", min_stock_level: 0, max_stock_level: "", sku: "", supplier_lead_time: "", errors: {}, error: "", busy: false };
}
async function saveMaterial() {
  const m = mat.value;
  m.errors = {};
  m.error = "";
  if (!m.name) m.errors.name = "Name the material.";
  if (m.max_stock_level !== "" && Number(m.max_stock_level) < Number(m.min_stock_level)) m.errors.max_stock_level = "The target has to be at least the minimum.";
  if (m.pack_unit && !(Number(m.pack_size) >= 2)) m.errors.pack_size = `Enter how many ${plural(m.stock_unit || "piece", 2)} are in one ${m.pack_unit} (2 or more).`;
  if (Object.keys(m.errors).length) return;
  m.busy = true;
  const body = { name: m.name, stock_kind: m.stock_kind, stock_unit: m.stock_unit || null, pack_unit: m.pack_unit || null, pack_size: m.pack_unit ? Number(m.pack_size) : null, on_hand: Number(m.on_hand) || 0, unit_cost: m.unit_cost === "" ? null : Number(m.unit_cost), min_stock_level: Number(m.min_stock_level) || 0, max_stock_level: m.max_stock_level === "" ? null : Number(m.max_stock_level), sku: m.sku || null, supplier_lead_time: m.supplier_lead_time === "" ? null : Number(m.supplier_lead_time) };
  try {
    await inventoryStockService.addMaterial(body);
    toast.success(`${m.name} was added.`);
    mat.value = null;
    kind.value = body.stock_kind;
    load();
  } catch (e) {
    m.busy = false;
    for (const [k, v] of Object.entries(e?.response?.data?.errors ?? {})) m.errors[k] = v[0];
    m.error = Object.keys(m.errors).length ? "" : apiMessage(e, "Couldn't add the material.");
  }
}

watch(kind, () => (picked.value = picked.value.filter((id) => shown.value.some((it) => it.id === id))));
onMounted(() => {
  load();
  loadHistory();
  // Add product > Vase or supply comes here with ?add=material
  if (route.query.add === "material" && can("inventory_products", "create")) {
    openMaterial();
    router.replace({ query: { ...route.query, add: undefined } });
  }
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
.fx-kpis.is-5 {
  grid-template-columns: repeat(5, minmax(0, 1fr));
}
.st-kpi {
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.st-kpi:disabled {
  cursor: default;
}
.st-kpi:not(:disabled):hover {
  border-color: var(--v-border-strong);
}
.st-kpi.on {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.st-kpi:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.st-kpi.t-red strong { color: var(--v-red-text); }
.st-kpi.t-amber strong { color: var(--v-amber-text); }
.st-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.st-ic :deep(svg) {
  width: 15px;
  height: 15px;
}

/* Filters */
.st-bar {
  display: grid;
  gap: 12px;
  padding: 12px;
  margin-bottom: 14px;
}
.st-kinds {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.st-kind {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 999px;
  background: var(--v-surface);
  color: var(--v-text-2);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}
.st-kind.on {
  border-color: var(--bc-accent);
  background: color-mix(in srgb, var(--bc-accent) 10%, transparent);
  color: var(--v-text);
}
.st-kind :deep(svg) {
  width: 15px;
  height: 15px;
}
.st-kind .count {
  font-size: 11.5px;
  color: var(--v-faint);
}
.st-bar-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.st-search {
  flex: 1;
  max-width: 320px;
  min-width: 200px;
}
.st-pick {
  position: sticky;
  top: 12px;
  z-index: 6;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  margin-bottom: 12px;
  border-color: var(--bc-accent);
  font-size: 13px;
  color: var(--v-text);
}
.st-pick-act {
  display: flex;
  gap: 8px;
}

/* Rows */
.st-card {
  padding: 8px;
}
.st-head,
.st-row {
  display: grid;
  grid-template-columns: 28px minmax(0, 1.5fr) minmax(130px, 0.9fr) minmax(96px, 0.6fr) minmax(110px, 0.7fr) minmax(0, 1.1fr) minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
  padding: 12px 12px;
}
.st-head {
  padding-top: 8px;
  padding-bottom: 6px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
}
.st-rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.st-rows > li + li {
  border-top: 1px solid var(--v-border);
}
.st-row {
  border-left: 3px solid transparent;
}
.st-row.s-out { border-left-color: var(--v-red-text); }
.st-row.s-low { border-left-color: #f59e0b; }
.st-row.sk {
  grid-template-columns: 1fr 1fr;
}
.st-row > span {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.st-row b {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.st-row small {
  font-size: 12px;
  color: var(--v-muted);
}
.st-sel input {
  width: 18px;
  height: 18px;
  accent-color: var(--bc-accent);
  cursor: pointer;
}
.st-sel input:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}
/* The product's photo beside its name */
.st-row > .st-item {
  flex-direction: row;
  align-items: flex-start;
  gap: 10px;
}
.st-item-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.st-photo {
  flex: none;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  overflow: hidden;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-surface-2, var(--v-surface));
  color: var(--v-faint);
}
.st-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.st-photo :deep(svg) {
  width: 18px;
  height: 18px;
}
.st-item small {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.st-onhand b small {
  font-weight: 500;
  color: var(--v-faint);
}
.st-level {
  position: relative;
  width: 100%;
  max-width: 140px;
  height: 6px;
  border-radius: 999px;
  background: var(--v-subtle-2);
}
.st-level span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--bc-accent);
}
.s-low .st-level span { background: #f59e0b; }
.s-out .st-level span { background: var(--v-red-text); }
.st-level i {
  position: absolute;
  top: -3px;
  width: 2px;
  height: 12px;
  border-radius: 1px;
  background: var(--v-text-2);
}
.st-state.out { color: var(--v-red-text); font-weight: 600; }
.st-state.low { color: var(--v-amber-text); font-weight: 600; }
.st-none {
  color: var(--v-faint) !important;
  font-weight: 500 !important;
}
.st-flow small {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: normal;
}
.st-flow :deep(svg) {
  width: 13px;
  height: 13px;
}
.bad {
  color: var(--v-red-text) !important;
}
.muted {
  color: var(--v-faint) !important;
}
.st-suggest b {
  color: var(--v-blue-text);
}
.st-suggest small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.st-act {
  flex-direction: row !important;
  align-items: center;
  gap: 6px !important;
}

/* A bouquet's flowers, under its name */
.st-item small.st-compo {
  display: block;
  color: var(--v-muted);
  font-size: 12px;
}

/* Flowers needed for bouquets */
.st-bf {
  margin-bottom: 16px;
}
.st-bf-list {
  display: grid;
  gap: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}
.st-bf-row {
  display: grid;
  grid-template-columns: minmax(150px, 1fr) minmax(0, 2fr) auto;
  gap: 12px;
  align-items: center;
  padding: 12px 18px;
  border-top: 1px solid var(--v-border);
}
.st-bf-name {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  color: var(--v-text);
}
.st-bf-dot {
  flex: none;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
}
.st-bf-for {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  color: var(--v-text-2);
  font-size: 12.5px;
}
.st-bf-for .muted {
  color: var(--v-faint);
}
.st-bf-stems {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.st-bf-stems b {
  font-size: 15px;
}
.st-bf-stems small {
  color: var(--v-faint);
  font-size: 12px;
  font-weight: 500;
}
.st-bf-stems > small {
  color: var(--v-text-2);
}
.st-bf-stems > small.muted {
  color: var(--v-faint);
}
.st-bf-total {
  display: flex;
  align-items: baseline;
  gap: 6px;
  color: var(--v-text);
  white-space: nowrap;
}
.st-bf-total b {
  font-size: 18px;
  font-variant-numeric: tabular-nums;
}
.st-bf-total small {
  color: var(--v-muted);
  font-size: 12px;
}
@media (max-width: 640px) {
  .st-bf-row {
    grid-template-columns: minmax(0, 1fr) auto;
  }
  .st-bf-for {
    grid-column: 1 / -1;
    grid-row: 2;
  }
}

/* History */
.st-hist {
  margin-top: 16px;
}
.st-hist-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.st-hist-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
}
.st-hist-list li + li {
  border-top: 1px solid var(--v-border);
}
.st-chg {
  flex: none;
  min-width: 56px;
  padding: 4px 8px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  text-align: center;
}
.st-chg.down {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}
.st-chg.up {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.st-hist-list b {
  font-size: 13px;
  color: var(--v-text);
}
.st-hist-list small {
  font-size: 12px;
  color: var(--v-muted);
}
.st-when {
  flex: none;
  text-align: right;
  color: var(--v-faint) !important;
}

/* Modals */
.st-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.st-modes {
  display: flex;
}
.st-modes button {
  flex: 1;
  justify-content: center;
}
/* Count in sheets or in rolls */
.st-units {
  display: flex;
}
.st-units button {
  flex: 1;
  justify-content: center;
}
/* Turning the rolls on hand into sheets when the pack is first set */
.st-convert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-blue-soft, var(--v-subtle));
  color: var(--v-text-2);
  font-size: 13px;
  line-height: 1.5;
  cursor: pointer;
}
.st-convert input {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  accent-color: var(--bc-accent, var(--v-green-text));
}
.st-convert b {
  color: var(--v-text);
}
.st-convert small {
  display: block;
  color: var(--v-muted);
  font-size: 12px;
}
/* Pack column: "50 sheets per roll", or a button to set it */
.st-pack {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.st-pack b {
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.st-pack b small,
.st-pack > small {
  color: var(--v-muted);
  font-size: 12px;
  font-weight: 500;
}
.st-pack-set {
  justify-self: start;
  min-height: 32px;
  padding: 0 10px;
  border: 1px dashed var(--v-border-strong);
  border-radius: 8px;
  background: none;
  color: var(--v-text-2);
  font: 500 12px "Poppins", sans-serif;
  text-align: left;
  cursor: pointer;
}
.st-pack-set:hover {
  border-color: var(--bc-accent, var(--v-green-text));
  color: var(--v-text);
}
.st-pack-set:focus-visible {
  outline: 2px solid var(--bc-accent, var(--v-green-text));
  outline-offset: 2px;
}
/* "≈ 1 roll + 45 sheets" under the on-hand count */
.st-packs {
  color: var(--v-muted);
  font-size: 11.5px;
}

@media (max-width: 1180px) {
  .fx-kpis.is-5 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .st-head {
    display: none;
  }
  .st-row {
    grid-template-columns: 28px minmax(0, 1fr) minmax(0, 1fr);
    gap: 8px 14px;
  }
  .st-row > .st-item { grid-column: 2 / -1; }
  .st-row > .st-onhand { grid-column: 2; }
  .st-row > .st-pack { grid-column: 3; }
  .st-row > .st-levels { grid-column: 3; }
  .st-row > .st-flow { grid-column: 2; }
  .st-row > .st-suggest { grid-column: 3; }
  .st-row > .st-act { grid-column: 2 / -1; justify-content: flex-end; }
  .st-sel { grid-row: 1; }
}
@media (max-width: 640px) {
  .fx-kpis.is-5 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .st-row {
    grid-template-columns: 28px minmax(0, 1fr);
  }
  .st-row > * {
    grid-column: 2 !important;
  }
  .st-search {
    max-width: none;
  }
}
</style>
