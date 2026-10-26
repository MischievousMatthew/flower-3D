<template>
  <HrPage :title="s ? s.company_name : 'Supplier'" :subtitle="s ? subtitle : 'Loading the supplier…'">
    <template #actions>
      <router-link :to="{ name: 'SupplierList' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.chevronLeft"></span>All suppliers</router-link>
      <router-link v-if="s && canEdit('suppliers')" :to="{ name: 'SupplierEdit', params: { id } }" class="sp-btn sp-btn-primary"><span v-html="ICONS.edit"></span>Edit details</router-link>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Header -->
    <section class="sp-card pf-head" aria-label="Supplier">
      <template v-if="!data">
        <div class="bc-skeleton pf-logo"></div>
        <div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div>
      </template>
      <template v-else>
        <img v-if="s.logo_url && !logoFailed" class="pf-logo" :src="s.logo_url" alt="" @error="logoFailed = true" />
        <span v-else class="pf-logo" aria-hidden="true">{{ initials(s.company_name) }}</span>
        <div class="grow">
          <div class="pf-chips">
            <span v-if="kind" class="sp-chip" :class="kind.tone">{{ kind.label }}</span>
            <span v-else class="sp-chip slate">Kind not set</span>
            <span class="sp-chip" :class="STATUS[s.status]?.tone">{{ STATUS[s.status]?.label ?? s.status }}</span>
          </div>
          <div class="pf-quick">
            <span><span v-html="ICONS.user"></span>{{ s.contact_person }}</span>
            <a v-if="s.phone" :href="`tel:${s.phone}`"><span v-html="ICONS.phone"></span>{{ s.phone }}</a>
            <a v-if="s.email" :href="`mailto:${s.email}`"><span v-html="ICONS.send"></span>{{ s.email }}</a>
            <span v-if="s.address"><span v-html="ICONS.pin"></span>{{ s.address }}</span>
          </div>
        </div>
      </template>
    </section>

    <!-- Headline numbers -->
    <section class="fx-kpis" aria-label="How this supplier has done">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label">Rating</span>
          <strong class="pf-score">{{ r.score !== null ? r.score.toFixed(1) : "—" }}<Stars v-if="r.score !== null" :value="r.score" /></strong>
          <span class="fx-kpi-sub">{{ r.count ? `from ${r.count} ${r.count === 1 ? "rating" : "ratings"}` : "Not rated yet" }}</span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label">Orders that arrived</span>
          <strong>{{ p.fill_rate !== null ? `${Math.round(p.fill_rate)}%` : "—" }}</strong>
          <span class="fx-kpi-sub">{{ p.orders ? `${p.arrived} of ${p.orders} orders${p.open ? ` · ${p.open} still open` : ""}` : "No orders yet" }}</span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label">Takes, on average</span>
          <strong>{{ p.avg_lead_days !== null ? leadText(p.avg_lead_days) : "—" }}</strong>
          <span class="fx-kpi-sub">{{ s.lead_time_days !== null && s.lead_time_days !== undefined ? `promised ${leadText(s.lead_time_days)}` : "No lead time set" }}<template v-if="p.on_time_rate !== null"> · {{ Math.round(p.on_time_rate) }}% on time</template></span>
        </article>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label">Bought so far</span>
          <strong>{{ money(p.value) }}</strong>
          <span class="fx-kpi-sub">{{ p.last_order_at ? `last order ${date(p.last_order_at)}` : "No orders yet" }}<template v-if="p.problems"> · <span class="pf-bad">{{ p.problems }} failed {{ p.problems === 1 ? "delivery" : "deliveries" }}</span></template></span>
        </article>
      </template>
    </section>

    <!-- Tabs -->
    <nav class="sp-seg pf-tabs" role="tablist" aria-label="Supplier sections">
      <button v-for="t in TABS" :id="`pf-tab-${t.key}`" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" :aria-controls="`pf-panel-${t.key}`" :class="{ on: tab === t.key }" @click="setTab(t.key)">
        {{ t.label }}<span v-if="data && t.count !== undefined" class="count">{{ t.count() }}</span>
      </button>
    </nav>

    <div v-if="!data" class="sp-card"><div class="bc-skeleton sp-sk-block"></div></div>

    <!-- Overview -->
    <section v-else-if="tab === 'overview'" id="pf-panel-overview" role="tabpanel" aria-labelledby="pf-tab-overview" class="pf-grid">
      <div class="sp-card">
        <div class="sp-card-head"><div><h2>Contacts</h2><p>Who to call for orders, billing or other branches</p></div></div>
        <ul class="pf-list">
          <li class="pf-person">
            <span class="pf-avatar">{{ initials(s.contact_person) }}</span>
            <div class="grow"><b>{{ s.contact_person }} <span class="sp-chip green">Main</span></b><span>{{ s.company_name }}</span></div>
            <div class="pf-links">
              <a v-if="s.phone" :href="`tel:${s.phone}`" class="sp-icon-btn" :aria-label="`Call ${s.contact_person}`"><span v-html="ICONS.phone"></span></a>
              <a v-if="s.email" :href="`mailto:${s.email}`" class="sp-icon-btn" :aria-label="`Email ${s.contact_person}`"><span v-html="ICONS.send"></span></a>
            </div>
          </li>
          <li v-for="c in s.contacts ?? []" :key="c.id" class="pf-person">
            <span class="pf-avatar">{{ initials(c.contact_person) }}</span>
            <div class="grow"><b>{{ c.contact_person }}</b><span>{{ c.company_name }}<template v-if="c.phone"> · {{ c.phone }}</template></span></div>
            <div class="pf-links">
              <a v-if="c.phone" :href="`tel:${c.phone}`" class="sp-icon-btn" :aria-label="`Call ${c.contact_person}`"><span v-html="ICONS.phone"></span></a>
              <a v-if="c.email" :href="`mailto:${c.email}`" class="sp-icon-btn" :aria-label="`Email ${c.contact_person}`"><span v-html="ICONS.send"></span></a>
            </div>
          </li>
        </ul>
        <p v-if="!(s.contacts ?? []).length" class="sp-hint">No other contacts. <router-link v-if="canEdit('suppliers')" :to="{ name: 'SupplierEdit', params: { id } }">Add one</router-link></p>
      </div>

      <div class="pf-col">
        <div class="sp-card">
          <div class="sp-card-head"><div><h2>Terms</h2><p>What they ask for</p></div></div>
          <dl class="pf-terms">
            <div><dt>Delivers</dt><dd>{{ daysText(s.delivery_days) || "On request" }}</dd></div>
            <div><dt>Lead time</dt><dd>{{ leadText(s.lead_time_days) || "Not set" }}</dd></div>
            <div><dt>Order cutoff</dt><dd>{{ timeText(s.order_cutoff) || "None" }}</dd></div>
            <div><dt>Minimum order</dt><dd>{{ s.minimum_order ? money(s.minimum_order) : "None" }}</dd></div>
            <div><dt>Payment</dt><dd>{{ s.payment_terms || "Not set" }}</dd></div>
          </dl>
          <p v-if="s.notes" class="pf-notes">{{ s.notes }}</p>
        </div>
        <div class="sp-card">
          <div class="sp-card-head">
            <div><h2>Recent orders</h2><p>Purchase orders from this supplier</p></div>
            <router-link v-if="canView('sc_orders')" :to="{ name: 'OrderList' }" class="sp-btn sp-btn-ghost sp-btn-sm">All orders</router-link>
          </div>
          <p v-if="!p.recent_orders.length" class="sp-hint">No purchase orders yet.</p>
          <ul v-else class="pf-list">
            <li v-for="o in p.recent_orders" :key="o.id">
              <component :is="canView('sc_orders') ? 'router-link' : 'div'" :to="{ name: 'OrderDetail', params: { id: o.id } }" class="pf-order">
                <div class="grow"><b>{{ o.order_number }}</b><span>Ordered {{ date(o.ordered_at) }}<template v-if="o.received_at"> · arrived {{ date(o.received_at) }}</template></span></div>
                <strong>{{ money(o.total_amount) }}</strong>
                <span class="sp-chip" :class="PO_STATUS[o.status]?.tone ?? 'slate'">{{ PO_STATUS[o.status]?.label ?? o.status }}</span>
              </component>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Catalog -->
    <section v-else-if="tab === 'catalog'" id="pf-panel-catalog" role="tabpanel" aria-labelledby="pf-tab-catalog" class="sp-card">
      <div class="sp-card-head">
        <div><h2>Catalog &amp; prices</h2><p>What {{ s.company_name }} sells, the price per unit and the least they'll sell</p></div>
        <button v-if="canEdit('suppliers')" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openItem()"><span v-html="ICONS.plus"></span>Add item</button>
      </div>
      <div v-if="catalog.length" class="pf-cat-bar">
        <div class="sp-seg" role="group" aria-label="Category">
          <button v-for="c in catalogTabs" :key="c.value" type="button" :class="{ on: catFilter === c.value }" :aria-pressed="catFilter === c.value" @click="catFilter = c.value">{{ c.label }}<span class="count">{{ c.count }}</span></button>
        </div>
        <label class="sp-search pf-cat-search">
          <span v-html="ICONS.search"></span>
          <input v-model="catSearch" class="sp-input" type="search" placeholder="Search items" aria-label="Search the catalog" />
        </label>
      </div>
      <div v-if="!catalog.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.tag"></span>
        <h3>No items yet</h3>
        <p>Add what this supplier sells with its price, so the team can compare suppliers and order at the right price.</p>
        <button v-if="canEdit('suppliers')" type="button" class="sp-btn sp-btn-primary" @click="openItem()"><span v-html="ICONS.plus"></span>Add the first item</button>
      </div>
      <p v-else-if="!shownCatalog.length" class="sp-hint">Nothing matches. Try another category or search.</p>
      <div v-else class="pf-table-wrap">
        <table class="pf-table">
          <thead>
            <tr><th scope="col">Item</th><th scope="col">Category</th><th scope="col" class="num">Price</th><th scope="col" class="num">Minimum</th><th scope="col">Price updated</th><th scope="col"><span class="sr">Actions</span></th></tr>
          </thead>
          <tbody>
            <tr v-for="item in shownCatalog" :key="item.id" :class="{ off: !item.is_available }">
              <td>
                <b>{{ item.name }}</b>
                <small v-if="!item.is_available" class="pf-off">Not available right now</small>
                <small v-else-if="item.notes">{{ item.notes }}</small>
              </td>
              <td>{{ catLabel(item.category) }}</td>
              <td class="num"><b>{{ money(item.price) }}</b><small>{{ unitLabel(item.unit) }}</small></td>
              <td class="num">{{ item.min_qty ? `${item.min_qty.toLocaleString("en-PH")} ${item.unit}${item.min_qty === 1 ? "" : "s"}` : "—" }}</td>
              <td>{{ item.price_updated_at ? date(item.price_updated_at) : "—" }}</td>
              <td class="pf-actions">
                <template v-if="canEdit('suppliers')">
                  <button type="button" class="sp-icon-btn" :aria-label="`Edit ${item.name}`" @click="openItem(item)"><span v-html="ICONS.edit"></span></button>
                  <button type="button" class="sp-icon-btn" :aria-label="`Remove ${item.name}`" @click="askRemove('item', item)"><span v-html="ICONS.trash"></span></button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Delivery -->
    <section v-else-if="tab === 'delivery'" id="pf-panel-delivery" role="tabpanel" aria-labelledby="pf-tab-delivery" class="pf-grid">
      <div class="sp-card">
        <div class="sp-card-head">
          <div><h2>Delivery schedule</h2><p>The days {{ s.company_name }} delivers</p></div>
          <router-link v-if="canEdit('suppliers')" :to="{ name: 'SupplierEdit', params: { id } }" class="sp-btn sp-btn-ghost sp-btn-sm">Change</router-link>
        </div>
        <ul class="pf-week" aria-label="Delivery days">
          <li v-for="d in DAYS" :key="d" :class="{ on: (s.delivery_days ?? []).includes(d), today: d === todayName }">
            <span>{{ shortDay(d) }}</span>
            <span class="pf-week-mark" v-html="(s.delivery_days ?? []).includes(d) ? ICONS.truck : ''"></span>
            <small>{{ (s.delivery_days ?? []).includes(d) ? "Delivers" : "—" }}</small>
          </li>
        </ul>
        <p class="sp-hint">{{ (s.delivery_days ?? []).length ? `Delivers ${daysText(s.delivery_days)}.` : "No fixed days: they deliver on request." }}<template v-if="s.order_cutoff"> Orders after {{ timeText(s.order_cutoff) }} count as the next day.</template></p>
        <div v-if="estimate" class="pf-estimate">
          <span v-html="ICONS.calendar"></span>
          <div><b>Order today, expect it {{ estimate }}</b><small>From the promised lead time and delivery days. Actual times are on the right.</small></div>
        </div>
      </div>
      <div class="sp-card">
        <div class="sp-card-head"><div><h2>Lead times</h2><p>Promised against what really happened</p></div></div>
        <dl class="pf-terms big">
          <div><dt>Promised</dt><dd>{{ leadText(s.lead_time_days) || "Not set" }}</dd></div>
          <div><dt>Actual average</dt><dd>{{ p.avg_lead_days !== null ? leadText(p.avg_lead_days) : "—" }}</dd></div>
          <div><dt>Arrived on time</dt><dd>{{ p.on_time_rate !== null ? `${Math.round(p.on_time_rate)}%` : "—" }}</dd></div>
          <div><dt>Minimum order</dt><dd>{{ s.minimum_order ? money(s.minimum_order) : "None" }}</dd></div>
        </dl>
        <p class="sp-hint">{{ p.avg_lead_days === null ? "Actual times show once orders from this supplier are received." : s.lead_time_days === null || s.lead_time_days === undefined ? "Set a promised lead time to see how often they're on time." : leadVerdict }}</p>
      </div>
    </section>

    <!-- Ratings & history -->
    <section v-else id="pf-panel-ratings" role="tabpanel" aria-labelledby="pf-tab-ratings" class="pf-grid">
      <div class="sp-card">
        <div class="sp-card-head">
          <div><h2>Ratings</h2><p>How the team rates them, out of 5</p></div>
          <button v-if="canEdit('suppliers')" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openRating"><span v-html="ICONS.star"></span>Rate supplier</button>
        </div>
        <div v-if="r.count" class="pf-bars">
          <div v-for="c in CRITERIA" :key="c.key" class="pf-bar-row">
            <span>{{ c.label }}</span>
            <div class="pf-bar" role="img" :aria-label="`${c.label}: ${r[c.key]} out of 5`"><span :style="{ width: `${(r[c.key] / 5) * 100}%` }"></span></div>
            <b>{{ r[c.key].toFixed(1) }}</b>
          </div>
        </div>
        <p v-if="!r.count" class="sp-hint">Not rated yet. Rate them after an order arrives, so the team can compare suppliers.</p>
        <ul v-else class="pf-list">
          <li v-for="x in r.recent" :key="x.id" class="pf-rating">
            <div class="grow">
              <span class="pf-rating-top"><Stars :value="x.score" /><b>{{ x.score.toFixed(1) }}</b><small>Quality {{ x.quality }} · On time {{ x.timeliness }} · Communication {{ x.communication }}</small></span>
              <p v-if="x.comment">{{ x.comment }}</p>
              <small>{{ x.rated_by_name || "Someone on the team" }} · {{ date(x.created_at) }}<template v-if="orderLabel(x.purchase_order_id)"> · for {{ orderLabel(x.purchase_order_id) }}</template></small>
            </div>
            <button v-if="canEdit('suppliers')" type="button" class="sp-icon-btn" aria-label="Remove this rating" @click="askRemove('rating', x)"><span v-html="ICONS.trash"></span></button>
          </li>
        </ul>
      </div>
      <div class="sp-card">
        <div class="sp-card-head"><div><h2>Order history</h2><p>Purchase orders per month, last 6 months</p></div></div>
        <div class="pf-months" role="img" :aria-label="historySummary">
          <div v-for="m in p.months" :key="m.month" class="pf-month" :title="`${monthLabel(m.month)}: ${m.orders} ${m.orders === 1 ? 'order' : 'orders'}, ${m.arrived} arrived, ${money(m.value)}`">
            <b>{{ m.orders || "" }}</b>
            <div class="pf-col-track"><span :style="{ height: `${(m.orders / maxMonth) * 100}%` }"></span></div>
            <small>{{ monthLabel(m.month, true) }}</small>
          </div>
        </div>
        <table class="pf-table compact">
          <thead><tr><th scope="col">Month</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Arrived</th><th scope="col" class="num">Value</th></tr></thead>
          <tbody>
            <tr v-for="m in [...p.months].reverse()" :key="m.month"><td>{{ monthLabel(m.month) }}</td><td class="num">{{ m.orders }}</td><td class="num">{{ m.arrived }}</td><td class="num">{{ money(m.value) }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Catalog item -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="item" class="sp-backdrop" @click="item = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="item" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="pf-item-title" novalidate @submit.prevent="saveItem">
            <div class="sp-modal-head">
              <div><h3 id="pf-item-title">{{ item.id ? "Edit item" : "Add an item" }}</h3><p>The supplier's price, per unit.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="item = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span class="sp-label">Item</span>
                <input v-model.trim="item.name" class="sp-input" :class="{ invalid: item.errors.name }" maxlength="160" placeholder="e.g. Red rose, 50 cm" />
                <small v-if="item.errors.name" class="sp-error">{{ item.errors.name }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Category</span>
                <select v-model="item.category" class="sp-select">
                  <option value="">None</option>
                  <option v-for="c in CATALOG_CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span class="sp-label">Sold</span>
                <select v-model="item.unit" class="sp-select">
                  <option v-for="u in UNITS" :key="u.value" :value="u.value">{{ u.label }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span class="sp-label">Price (₱)</span>
                <input v-model="item.price" class="sp-input" :class="{ invalid: item.errors.price }" type="number" min="0" step="0.01" inputmode="decimal" />
                <small v-if="item.errors.price" class="sp-error">{{ item.errors.price }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Minimum order <small>(optional)</small></span>
                <input v-model="item.min_qty" class="sp-input" :class="{ invalid: item.errors.min_qty }" type="number" min="1" step="1" inputmode="numeric" :placeholder="`e.g. 100 ${item.unit}s`" />
                <small v-if="item.errors.min_qty" class="sp-error">{{ item.errors.min_qty }}</small>
              </label>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Notes <small>(optional)</small></span>
                <input v-model.trim="item.notes" class="sp-input" maxlength="500" placeholder="Grade, color, season…" />
              </label>
              <label class="pf-check sp-span-all">
                <input v-model="item.is_available" type="checkbox" />
                <span>Available to order right now</span>
              </label>
            </div>
            <p v-if="item.error" class="sp-error">{{ item.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="item = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="item.busy">{{ item.busy ? "Saving…" : item.id ? "Save item" : "Add item" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Remove an item or a rating -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="removing" class="sp-backdrop" @click="removing = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="removing" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="pf-rm-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="pf-rm-title">{{ removing.kind === "item" ? `Remove ${removing.target.name}?` : "Remove this rating?" }}</h3>
                <p>{{ removing.kind === "item" ? "It comes off this supplier's catalog. Past purchase orders aren't changed." : "The supplier's average is worked out again without it." }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="removing = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="removing = null">Keep it</button>
              <button type="button" class="sp-btn sp-btn-danger" @click="confirmRemove">Remove</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Rating -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="rating" class="sp-backdrop" @click="rating = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="rating" class="sp-modal-wrap">
          <form class="sp-modal pf-modal-form" role="dialog" aria-modal="true" aria-labelledby="pf-rate-title" novalidate @submit.prevent="saveRating">
            <div class="sp-modal-head">
              <div><h3 id="pf-rate-title">Rate {{ s.company_name }}</h3><p>1 is poor, 5 is excellent.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="rating = null"><span v-html="ICONS.close"></span></button>
            </div>
            <fieldset v-for="c in CRITERIA" :key="c.key" class="pf-rate">
              <legend><b>{{ c.label }}</b><small>{{ c.hint }}</small></legend>
              <div class="pf-rate-stars">
                <label v-for="n in 5" :key="n" :class="{ on: rating[c.key] >= n }" :title="`${n} out of 5`">
                  <input v-model="rating[c.key]" type="radio" :name="`pf-${c.key}`" :value="n" :aria-label="`${c.label}: ${n} out of 5`" />
                  <span v-html="ICONS.star"></span>
                </label>
              </div>
            </fieldset>
            <small v-if="rating.missing" class="sp-error">Pick a score for each one.</small>
            <label v-if="p.recent_orders.length" class="sp-field">
              <span class="sp-label">For an order <small>(optional)</small></span>
              <select v-model="rating.purchase_order_id" class="sp-select">
                <option value="">Not for one order</option>
                <option v-for="o in p.recent_orders" :key="o.id" :value="o.id">{{ o.order_number }} · {{ date(o.ordered_at) }}</option>
              </select>
            </label>
            <label class="sp-field">
              <span class="sp-label">Comment <small>(optional)</small></span>
              <textarea v-model.trim="rating.comment" class="sp-textarea" rows="3" maxlength="1000" placeholder="What went well, or what to watch next time"></textarea>
            </label>
            <p v-if="rating.error" class="sp-error">{{ rating.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="rating = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="rating.busy">{{ rating.busy ? "Saving…" : "Save rating" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../../../components/vendor/finance/financeUi";
import { STATUS, typeOf, DAYS, shortDay, daysText, leadText, timeText, initials, UNITS, unitLabel, CATALOG_CATEGORIES } from "../../../../../components/procurement/supplierUi";
import { useAssignment } from "../../../../../composables/useAssignment";
import { supplierService } from "../../../../../services/supplierService";
import { toast } from "@/utils/snackbar";
import "../../../../../components/vendor/finance/finance.css";

const props = defineProps({ id: { type: [String, Number], required: true } });
const route = useRoute();
const router = useRouter();
const { canView, canEdit } = useAssignment();

const TABS = [
  { key: "overview", label: "Overview" },
  { key: "catalog", label: "Catalog & prices", count: () => catalog.value.length },
  { key: "delivery", label: "Delivery & lead times" },
  { key: "ratings", label: "Ratings & history", count: () => r.value.count },
];
const CRITERIA = [
  { key: "quality", label: "Quality", hint: "Fresh, as ordered, well packed" },
  { key: "timeliness", label: "On time", hint: "Arrived when promised" },
  { key: "communication", label: "Communication", hint: "Easy to reach, clear about changes" },
];
const PO_STATUS = {
  pending: { label: "Waiting", tone: "amber" },
  processing: { label: "Preparing", tone: "violet" },
  shipped: { label: "On the way", tone: "blue" },
  received: { label: "Arrived", tone: "green" },
  completed: { label: "Completed", tone: "green" },
};

const data = ref(null);
const loadError = ref("");
const logoFailed = ref(false);
const tab = ref(TABS.some((t) => t.key === route.query.tab) ? route.query.tab : "overview");
const catFilter = ref("");
const catSearch = ref("");
const item = ref(null);
const rating = ref(null);
const removing = ref(null);

function askRemove(kind, target) {
  removing.value = { kind, target };
}
function confirmRemove() {
  const { kind, target } = removing.value;
  removing.value = null;
  if (kind === "item") removeItem(target);
  else removeRating(target);
}

const Stars = defineComponent({
  props: { value: Number },
  setup(p) {
    return () =>
      h("span", { class: "pf-stars", "aria-hidden": "true" }, [1, 2, 3, 4, 5].map((n) => h("span", { class: { on: p.value >= n - 0.25 }, innerHTML: ICONS.star })));
  },
});

const s = computed(() => data.value?.supplier);
const r = computed(() => data.value?.ratings ?? { count: 0 });
const p = computed(() => data.value?.performance ?? { recent_orders: [], months: [] });
const catalog = computed(() => data.value?.catalog ?? []);
const kind = computed(() => typeOf(s.value?.supplier_type));
const subtitle = computed(() => {
  const bits = [kind.value?.label, daysText(s.value.delivery_days) && `delivers ${daysText(s.value.delivery_days)}`, leadText(s.value.lead_time_days) && `${leadText(s.value.lead_time_days)} lead time`].filter(Boolean);
  return bits.length ? bits.join(" · ") : "Supplier details, catalog, delivery schedule and ratings.";
});

const catalogTabs = computed(() => [
  { value: "", label: "All", count: catalog.value.length },
  ...CATALOG_CATEGORIES.map((c) => ({ ...c, count: catalog.value.filter((i) => i.category === c.value).length })).filter((c) => c.count),
]);
const shownCatalog = computed(() => {
  const q = catSearch.value.trim().toLowerCase();
  return catalog.value.filter((i) => (!catFilter.value || i.category === catFilter.value) && (!q || i.name.toLowerCase().includes(q) || (i.notes ?? "").toLowerCase().includes(q)));
});
const catLabel = (c) => CATALOG_CATEGORIES.find((x) => x.value === c)?.label ?? "—";

const todayName = DAYS[(new Date().getDay() + 6) % 7];
// Earliest day the supplier delivers once the promised lead time has passed
const estimate = computed(() => {
  const lead = s.value?.lead_time_days;
  if (lead === null || lead === undefined) return "";
  const days = s.value.delivery_days ?? [];
  const d = new Date();
  if (s.value.order_cutoff) {
    const [hh, mm] = s.value.order_cutoff.split(":").map(Number);
    if (d.getHours() * 60 + d.getMinutes() > hh * 60 + mm) d.setDate(d.getDate() + 1);
  }
  d.setDate(d.getDate() + Number(lead));
  for (let i = 0; days.length && i < 7 && !days.includes(DAYS[(d.getDay() + 6) % 7]); i++) d.setDate(d.getDate() + 1);
  const diff = Math.round((new Date(d.toDateString()) - new Date(new Date().toDateString())) / 86400000);
  const when = d.toLocaleDateString("en-PH", { weekday: "long", month: "short", day: "numeric" });
  return diff === 0 ? "today" : diff === 1 ? `tomorrow, ${when}` : `by ${when}`;
});
const leadVerdict = computed(() => {
  const gap = p.value.avg_lead_days - s.value.lead_time_days;
  if (Math.abs(gap) < 0.5) return "They usually arrive when they say.";
  return gap > 0 ? `On average they take ${gap.toFixed(1)} days longer than promised. Order earlier, or raise it with them.` : `On average they arrive ${Math.abs(gap).toFixed(1)} days sooner than promised.`;
});

const maxMonth = computed(() => Math.max(1, ...p.value.months.map((m) => m.orders)));
const monthLabel = (ym, short = false) => new Date(`${ym}-01T00:00:00`).toLocaleDateString("en-PH", short ? { month: "short" } : { month: "long", year: "numeric" });
const historySummary = computed(() => p.value.months.map((m) => `${monthLabel(m.month)}: ${m.orders}`).join(", "));
const orderLabel = (id) => (id ? p.value.recent_orders.find((o) => o.id === id)?.order_number ?? `order #${id}` : "");
const date = (d) => (d ? new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");

function setTab(key) {
  tab.value = key;
  router.replace({ query: { ...route.query, tab: key === "overview" ? undefined : key } });
}

async function load() {
  loadError.value = "";
  try {
    const res = await supplierService.profile(props.id);
    data.value = res.data.data;
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't load this supplier.");
  }
}

// ─── Catalog ───
function openItem(existing = null) {
  item.value = existing
    ? { ...existing, category: existing.category ?? "", min_qty: existing.min_qty ?? "", notes: existing.notes ?? "", errors: {}, error: "", busy: false }
    : { id: null, name: "", category: kind.value?.value === "packaging" ? "packaging" : kind.value?.value === "supplies" ? "supplies" : "flowers", unit: "stem", price: "", min_qty: "", notes: "", is_available: true, errors: {}, error: "", busy: false };
}
async function saveItem() {
  const it = item.value;
  it.errors = {};
  it.error = "";
  if (!it.name) it.errors.name = "Name the item.";
  if (it.price === "" || Number(it.price) < 0) it.errors.price = "Enter the price, 0 or more.";
  if (it.min_qty !== "" && Number(it.min_qty) < 1) it.errors.min_qty = "Use 1 or more, or leave it empty.";
  if (Object.keys(it.errors).length) return;
  it.busy = true;
  const body = { name: it.name, category: it.category || null, unit: it.unit, price: Number(it.price), min_qty: it.min_qty === "" ? null : Number(it.min_qty), notes: it.notes || null, is_available: !!it.is_available };
  try {
    const res = it.id ? await supplierService.updateProduct(props.id, it.id, body) : await supplierService.addProduct(props.id, body);
    const saved = res.data.data;
    const list = data.value.catalog;
    const i = list.findIndex((x) => x.id === saved.id);
    if (i >= 0) list.splice(i, 1, saved);
    else list.push(saved);
    toast.success(it.id ? `${saved.name} was updated.` : `${saved.name} was added to the catalog.`);
    item.value = null;
  } catch (e) {
    it.busy = false;
    const errs = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(errs)) it.errors[k] = v[0];
    it.error = Object.keys(errs).length ? "" : apiMessage(e, "Couldn't save the item.");
  }
}
async function removeItem(x) {
  const list = data.value.catalog;
  const i = list.indexOf(x);
  list.splice(i, 1);
  try {
    await supplierService.removeProduct(props.id, x.id);
    toast.success(`${x.name} was removed.`, { toastId: `cat-${x.id}` });
  } catch (e) {
    list.splice(i, 0, x);
    toast.error(apiMessage(e, "Couldn't remove the item."));
  }
}

// ─── Ratings ───
function openRating() {
  rating.value = { quality: 0, timeliness: 0, communication: 0, comment: "", purchase_order_id: "", busy: false, error: "", missing: false };
}
async function saveRating() {
  const x = rating.value;
  x.missing = CRITERIA.some((c) => !x[c.key]);
  if (x.missing) return;
  x.busy = true;
  x.error = "";
  try {
    await supplierService.rate(props.id, { quality: x.quality, timeliness: x.timeliness, communication: x.communication, comment: x.comment || null, purchase_order_id: x.purchase_order_id || null });
    rating.value = null;
    toast.success("Thanks, your rating was saved.");
    load();
  } catch (e) {
    x.busy = false;
    x.error = apiMessage(e, "Couldn't save the rating.");
  }
}
async function removeRating(x) {
  try {
    await supplierService.removeRating(props.id, x.id);
    toast.success("Rating removed.");
    load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't remove the rating."));
  }
}

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

/* Header */
.pf-head {
  display: flex;
  align-items: center;
  gap: 18px;
  margin-bottom: 16px;
}
.pf-logo {
  flex: none;
  display: grid;
  place-items: center;
  width: 72px;
  height: 72px;
  border-radius: 20px;
  object-fit: cover;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 22px;
  font-weight: 600;
}
.pf-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.pf-quick {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 18px;
  margin-top: 8px;
  font-size: 13px;
}
.pf-quick > * {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--v-text-2);
  text-decoration: none;
}
.pf-quick a:hover {
  color: var(--bc-accent-deep);
  text-decoration: underline;
}
.pf-quick :deep(svg) {
  width: 14px;
  height: 14px;
  color: var(--v-faint);
}
.pf-score {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pf-bad {
  color: var(--v-red-text);
  font-weight: 500;
}
:deep(.pf-stars) {
  display: inline-flex;
  gap: 1px;
  color: var(--v-subtle-2);
}
:deep(.pf-stars > span) {
  display: inline-flex;
}
:deep(.pf-stars svg) {
  width: 14px;
  height: 14px;
  fill: currentColor;
}
:deep(.pf-stars .on) {
  color: #f5b301;
}

/* Tabs */
.pf-tabs {
  display: flex;
  width: fit-content;
  max-width: 100%;
  margin-bottom: 16px;
  overflow-x: auto;
}
.pf-tabs button {
  white-space: nowrap;
}

/* Layout */
.pf-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.pf-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* Lists */
.pf-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.pf-list > li + li {
  border-top: 1px solid var(--v-border);
}
.pf-person {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 0;
}
.pf-person b,
.pf-order b {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.pf-person .grow > span,
.pf-order .grow > span {
  font-size: 12px;
  color: var(--v-muted);
}
.pf-avatar {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--v-subtle);
  color: var(--v-text-2);
  font-size: 12px;
  font-weight: 600;
}
.pf-links {
  display: flex;
  gap: 4px;
}
.pf-order {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 4px;
  border-radius: 10px;
  color: inherit;
  text-decoration: none;
}
a.pf-order:hover {
  background: var(--v-subtle);
}
.pf-order > strong {
  font-size: 13px;
  color: var(--v-text);
}
.pf-terms {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.pf-terms div {
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.pf-terms dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.pf-terms dd {
  margin: 2px 0 0;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.pf-terms.big dd {
  font-size: 18px;
}
.pf-notes {
  margin: 14px 0 0;
  padding: 12px 14px;
  border-left: 3px solid var(--bc-accent);
  border-radius: 0 12px 12px 0;
  background: var(--v-subtle);
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text-2);
  white-space: pre-line;
}

/* Catalog */
.pf-cat-bar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.pf-cat-search {
  min-width: 200px;
  max-width: 280px;
  flex: 1;
}
.pf-table-wrap {
  overflow-x: auto;
}
.pf-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.pf-table th {
  padding: 10px 12px;
  border-bottom: 1px solid var(--v-border);
  color: var(--v-muted);
  font-size: 11.5px;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
}
.pf-table td {
  padding: 12px;
  border-bottom: 1px solid var(--v-border);
  color: var(--v-text-2);
  vertical-align: middle;
}
.pf-table tr:last-child td {
  border-bottom: 0;
}
.pf-table td b {
  display: block;
  color: var(--v-text);
  font-weight: 600;
}
.pf-table td small {
  display: block;
  font-size: 11.5px;
  color: var(--v-faint);
}
.pf-table .num {
  text-align: right;
  white-space: nowrap;
}
.pf-table tr.off td {
  opacity: 0.6;
}
.pf-table .pf-off {
  color: var(--v-amber-text);
}
.pf-table.compact {
  margin-top: 14px;
}
.pf-table.compact td,
.pf-table.compact th {
  padding: 8px 10px;
}
.pf-actions {
  width: 1%;
  white-space: nowrap;
}
.sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

/* Delivery */
.pf-week {
  list-style: none;
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 6px;
  margin: 0 0 12px;
  padding: 0;
}
.pf-week li {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 4px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--v-muted);
}
.pf-week li.on {
  border-color: var(--bc-accent);
  background: color-mix(in srgb, var(--bc-accent) 10%, transparent);
  color: var(--v-text);
}
.pf-week li.today {
  box-shadow: inset 0 0 0 2px var(--v-border-strong);
}
.pf-week small {
  font-size: 10.5px;
  font-weight: 500;
}
.pf-week-mark {
  display: grid;
  place-items: center;
  height: 18px;
  color: var(--bc-accent-deep);
}
.pf-week-mark :deep(svg) {
  width: 17px;
  height: 17px;
}
.pf-estimate {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--v-blue-soft);
  color: var(--v-blue-text);
}
.pf-estimate :deep(svg) {
  width: 18px;
  height: 18px;
  margin-top: 1px;
}
.pf-estimate div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.pf-estimate b {
  font-size: 13.5px;
}
.pf-estimate small {
  font-size: 12px;
  color: var(--v-text-2);
}

/* Ratings */
.pf-bars {
  display: grid;
  gap: 9px;
  margin-bottom: 14px;
  padding: 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.pf-bar-row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr) 30px;
  align-items: center;
  gap: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.pf-bar-row b {
  text-align: right;
  color: var(--v-text);
}
.pf-bar {
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.pf-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #f5b301;
}
.pf-rating {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 0;
}
.pf-rating-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.pf-rating-top b {
  font-size: 13.5px;
  color: var(--v-text);
}
.pf-rating small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.pf-rating p {
  margin: 2px 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.pf-months {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 10px;
  align-items: end;
  height: 150px;
}
.pf-month {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  height: 100%;
}
.pf-month b {
  min-height: 16px;
  font-size: 12px;
  color: var(--v-text);
}
.pf-col-track {
  position: relative;
  flex: 1;
  width: 100%;
  max-width: 38px;
  border-radius: 8px 8px 4px 4px;
  background: var(--v-subtle);
}
.pf-col-track span {
  position: absolute;
  inset: auto 0 0;
  min-height: 4px;
  border-radius: 8px 8px 4px 4px;
  background: var(--bc-accent);
}
.pf-month small {
  font-size: 11.5px;
  color: var(--v-faint);
}

/* Modals */
.pf-check {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  font-size: 13px;
  color: var(--v-text);
  cursor: pointer;
}
.pf-check input {
  width: 18px;
  height: 18px;
  accent-color: var(--bc-accent);
}
.pf-rate {
  margin: 0 0 12px;
  padding: 0;
  border: 0;
}
.pf-rate legend {
  display: flex;
  flex-direction: column;
  margin-bottom: 4px;
  font-size: 13px;
  color: var(--v-text);
}
.pf-rate legend small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.pf-rate-stars {
  display: flex;
  gap: 4px;
}
.pf-rate-stars label {
  position: relative;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 10px;
  color: var(--v-faint);
  cursor: pointer;
}
.pf-rate-stars label :deep(svg) {
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
}
.pf-rate-stars label.on :deep(svg) {
  fill: currentColor;
}
.pf-modal-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.pf-modal-form .pf-rate {
  margin: 0;
}
.pf-rate-stars label:hover {
  background: var(--v-subtle);
}
.pf-rate-stars label.on {
  color: #f5b301;
}
.pf-rate-stars label:focus-within {
  outline: 2px solid var(--bc-accent);
}
.pf-rate-stars input {
  position: absolute;
  opacity: 0;
}
.pf-rate-stars :deep(svg) {
  width: 26px;
  height: 26px;
}

@media (max-width: 1000px) {
  .pf-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .fx-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .fx-kpi {
    padding: 14px;
  }
  .pf-head {
    flex-direction: column;
    align-items: flex-start;
  }
  .pf-terms {
    grid-template-columns: minmax(0, 1fr);
  }
  .pf-week {
    gap: 4px;
  }
  .pf-week small {
    display: none;
  }
  .pf-bar-row {
    grid-template-columns: 90px minmax(0, 1fr) 30px;
  }
}
</style>
