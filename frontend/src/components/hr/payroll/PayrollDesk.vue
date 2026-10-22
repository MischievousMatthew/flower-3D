<template>
  <div class="pd">
    <div v-if="tabs.length > 1" class="sp-seg pd-tabs" role="tablist" aria-label="Payroll sections">
      <button v-for="t in tabs" :key="t.key" type="button" role="tab" :class="{ on: tab === t.key }" :aria-selected="tab === t.key" @click="tab = t.key">{{ t.label }}</button>
    </div>

    <!-- ── Payroll runs ── -->
    <template v-if="tab === 'runs'">
      <section class="sp-stats pd-stats" aria-label="Payroll summary">
        <template v-if="!summary">
          <div v-for="n in 4" :key="n" class="sp-stat"><div class="bc-skeleton sp-stat-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
        </template>
        <template v-else>
          <button v-for="t in statTiles" :key="t.status" type="button" class="sp-stat" :class="{ on: filters.status === t.status }" @click="filters.status = t.status">
            <span class="sp-stat-icon" :class="`tone-${t.tone}`" v-html="ICONS[t.icon]"></span>
            <div><strong>{{ t.amount === null ? t.count : php(t.amount) }}</strong><span>{{ t.amount === null ? t.label : `${t.count} ${t.label}` }}</span></div>
          </button>
        </template>
      </section>

      <section class="sp-card">
        <div class="sp-toolbar pd-toolbar">
          <div class="sp-seg" role="tablist" aria-label="Status">
            <button v-for="s in STATUS_TABS" :key="s.key" type="button" role="tab" :class="{ on: filters.status === s.key }" @click="filters.status = s.key">{{ s.label }}</button>
          </div>
          <div class="pd-filters">
            <label class="sp-search"><span v-html="ICONS.search"></span><input v-model="search" class="sp-input" type="search" placeholder="Find someone" aria-label="Find someone" /></label>
            <select v-model.number="filters.month" class="sp-select" aria-label="Month">
              <option :value="0">Every month</option>
              <option v-for="(m, i) in MONTHS" :key="m" :value="i + 1">{{ m }}</option>
            </select>
            <select v-model.number="filters.year" class="sp-select" aria-label="Year">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="!rows?.length" @click="exportRegister"><span v-html="ICONS.download"></span>Register (CSV)</button>
          </div>
        </div>

        <!-- Bulk actions -->
        <div v-if="selected.length" class="pd-bulk">
          <b>{{ selected.length }} selected · {{ php(selectedTotal) }}</b>
          <template v-if="selectedStatus === 'pending' && (perms.approve || perms.reject)">
            <input v-model="bulkNote" class="sp-input" maxlength="1000" placeholder="Note (needed to send back)" aria-label="Note" />
            <button v-if="perms.reject" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="bulk('reject')">Send back</button>
            <button v-if="perms.approve" type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy" @click="bulk('approve')">Approve</button>
          </template>
          <button v-else-if="selectedStatus === 'approved' && perms.pay" type="button" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="busy" @click="bulk('markPaid')">Mark as paid</button>
          <button type="button" class="st-link" @click="selected = []">Clear</button>
        </div>

        <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}<button type="button" @click="load">Try again</button></p>
        <ul v-else-if="!rows" class="pd-sk"><li v-for="n in 5" :key="n"><div class="bc-skeleton st-avatar"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li></ul>
        <div v-else-if="!rows.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.wallet"></span>
          <h3>No payrolls here</h3>
          <p>{{ mode === "finance" ? "Payrolls HR sends for approval show up here." : "Make payrolls for a pay period from the New payroll tab." }}</p>
          <button v-if="perms.create" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="tab = 'new'"><span v-html="ICONS.plus"></span>New payroll</button>
        </div>
        <div v-else class="st-scroll">
          <table class="st-table cards pd-table">
            <thead>
              <tr>
                <th class="pd-check"><input type="checkbox" :checked="allSelected" :disabled="!selectable.length" aria-label="Select all" @change="toggleAll" /></th>
                <th>Person</th><th>Period</th><th>Days</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net pay</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rows" :key="r.id" class="clickable" @click="openSlip(r)">
                <td class="pd-check" @click.stop>
                  <input type="checkbox" :value="r.id" :checked="selected.includes(r.id)" :disabled="!canSelect(r)" :aria-label="`Select ${r.employee}`" @change="toggle(r)" />
                </td>
                <td>
                  <div class="st-person">
                    <span class="st-avatar sm" :style="{ background: personColor(r.employee) }">{{ initialsOf(r.employee) }}</span>
                    <div><strong>{{ r.employee }}</strong><small>{{ r.number }}</small></div>
                  </div>
                </td>
                <td data-label="Period">{{ periodText(r.period_start, r.period_end) }}</td>
                <td data-label="Days">{{ r.days_worked }}/{{ r.expected_days }}<small v-if="r.overtime_hours" class="pd-sub"> · {{ r.overtime_hours }}h OT</small></td>
                <td data-label="Gross" class="num">{{ php(r.gross) }}</td>
                <td data-label="Deductions" class="num pd-muted">−{{ php(r.deductions) }}</td>
                <td data-label="Net pay" class="num"><b>{{ php(r.net) }}</b></td>
                <td data-label="Status"><span class="sp-chip" :class="PAYROLL_STATUS[r.status].tone" :title="r.finance_notes || ''">{{ PAYROLL_STATUS[r.status].short }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer v-if="page.last_page > 1" class="pd-pages">
          <span>{{ page.from }}–{{ page.to }} of {{ page.total }}</span>
          <div>
            <button type="button" class="sp-icon-btn" aria-label="Previous page" :disabled="page.current_page === 1" @click="load(page.current_page - 1)"><span v-html="ICONS.chevronLeft"></span></button>
            <button type="button" class="sp-icon-btn" aria-label="Next page" :disabled="page.current_page === page.last_page" @click="load(page.current_page + 1)"><span v-html="ICONS.chevronRight"></span></button>
          </div>
        </footer>
      </section>
    </template>

    <!-- ── New payroll ── -->
    <template v-else-if="tab === 'new'">
      <section class="sp-card pd-new">
        <div class="pd-step"><span>1</span><h3>Pay period</h3></div>
        <div class="pd-presets">
          <button v-for="p in presets" :key="p.key" type="button" class="sp-pick" :class="{ on: run.from === p.from && run.to === p.to }" @click="setPeriod(p)">{{ p.label }}</button>
        </div>
        <div class="sp-grid-2 pd-dates">
          <label class="sp-field"><span class="sp-label">From</span><input v-model="run.from" type="date" class="sp-input" @change="runPreview = null" /></label>
          <label class="sp-field"><span class="sp-label">To</span><input v-model="run.to" type="date" class="sp-input" :min="run.from" @change="runPreview = null" /></label>
        </div>
        <label class="pd-toggle">
          <span class="sp-switch"><input v-model="run.contributions" type="checkbox" @change="runPreview = null" /><span></span></span>
          <span><b>Take SSS, PhilHealth and Pag-IBIG</b><small>Only the share of a month this period covers is taken, so weekly or twice-a-month payrolls add up to one month's worth.</small></span>
        </label>

        <div class="pd-step"><span>2</span><h3>Who to pay <small>({{ run.ids.length }} chosen)</small></h3></div>
        <div v-if="!people" class="pd-sk-block"><div v-for="n in 4" :key="n" class="bc-skeleton sp-sk-line w70"></div></div>
        <template v-else>
          <div class="pd-people-bar">
            <button type="button" class="st-link" @click="run.ids = people.filter((p) => !p.problem).map((p) => p.id)">Everyone who's set up</button>
            <button type="button" class="st-link" @click="run.ids = []">No one</button>
          </div>
          <ul class="pd-people">
            <li v-for="p in people" :key="p.id">
              <label class="pd-person" :class="{ on: run.ids.includes(p.id), off: p.problem }">
                <input v-model="run.ids" type="checkbox" :value="p.id" :disabled="!!p.problem" @change="runPreview = null" />
                <span class="st-avatar sm" :style="{ background: personColor(p.name) }">{{ initialsOf(p.name) }}</span>
                <span class="pd-person-text">
                  <b>{{ p.name }}</b>
                  <small v-if="p.problem" class="pd-warn">{{ p.problem }}</small>
                  <small v-else>{{ php(p.basic_salary) }} {{ PER[p.salary_type] }}</small>
                </span>
              </label>
            </li>
          </ul>
        </template>
        <p v-if="runError" class="sp-error">{{ runError }}</p>
        <div class="pd-actions">
          <button type="button" class="sp-btn sp-btn-primary" :disabled="busy || !run.ids.length || !run.from || !run.to" @click="previewRun">{{ busy && !runPreview ? "Working it out…" : "Preview pay" }}</button>
        </div>
      </section>

      <section v-if="runPreview" class="sp-card pd-new">
        <div class="pd-step"><span>3</span><h3>Check and adjust</h3></div>
        <p class="sp-hint">Add a bonus or a one-off deduction to anyone, then recalculate. Recurring allowances and deductions are already included.</p>
        <article v-for="r in runPreview" :key="r.employee_id" class="pd-pre" :class="{ bad: !r.ok }">
          <header>
            <div class="st-person">
              <span class="st-avatar sm" :style="{ background: personColor(nameOf(r.employee_id)) }">{{ initialsOf(nameOf(r.employee_id)) }}</span>
              <div><strong>{{ nameOf(r.employee_id) }}</strong><small v-if="r.ok">{{ r.actual_work_days }} of {{ r.expected_work_days }} days · {{ r.total_hours_worked }} h{{ num(r.overtime_hours) ? ` · ${r.overtime_hours} h overtime` : "" }}{{ r.absent_days ? ` · ${r.absent_days} absent` : "" }}</small></div>
            </div>
            <div v-if="r.ok" class="pd-pre-pay"><small>Net pay</small><b>{{ php(r.net_salary) }}</b></div>
          </header>
          <p v-if="!r.ok" class="sp-error">{{ r.message }}</p>
          <p v-else-if="r.overlaps" class="sp-banner warn"><span v-html="ICONS.alert"></span>Already has a payroll covering part of this period — it will be skipped.</p>
          <template v-if="r.ok">
            <dl class="pd-pre-lines">
              <div><dt>Basic pay</dt><dd>{{ php(r.actual_work_amount) }}</dd></div>
              <div v-if="num(r.paid_leave_amount)"><dt>Paid leave</dt><dd>{{ php(r.paid_leave_amount) }}</dd></div>
              <div v-if="num(r.overtime_amount)"><dt>Overtime</dt><dd>{{ php(r.overtime_amount) }}</dd></div>
              <div v-for="(i, k) in r.items" :key="k" :class="i.kind"><dt>{{ i.label }}<em v-if="i.source === 'recurring'"> · recurring</em></dt><dd>{{ i.kind === "deduction" ? "−" : "" }}{{ php(i.amount) }}</dd></div>
              <div v-if="num(r.total_contributions)" class="deduction"><dt>SSS, PhilHealth, Pag-IBIG</dt><dd>−{{ php(r.total_contributions) }}</dd></div>
              <div class="total"><dt>Gross {{ php(r.gross_salary) }} − deductions {{ php(r.deduction_amount) }}</dt><dd>{{ php(r.net_salary) }}</dd></div>
            </dl>
            <div class="pd-extra">
              <div v-for="(x, k) in extras[r.employee_id] ?? []" :key="k" class="pd-extra-row">
                <select v-model="x.kind" class="sp-select" aria-label="Kind"><option value="bonus">Bonus</option><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select>
                <input v-model="x.label" class="sp-input" maxlength="80" placeholder="e.g. Wedding rush bonus" aria-label="Name" />
                <input v-model.number="x.amount" type="number" min="0.01" step="0.01" class="sp-input" placeholder="Amount" aria-label="Amount" />
                <button type="button" class="sp-icon-btn" aria-label="Remove line" @click="extras[r.employee_id].splice(k, 1)"><span v-html="ICONS.close"></span></button>
              </div>
              <button type="button" class="st-link" @click="(extras[r.employee_id] ??= []).push({ kind: 'bonus', label: '', amount: null })">+ Add a bonus or deduction</button>
            </div>
          </template>
        </article>
        <label class="sp-field pd-notes"><span class="sp-label">Note for Finance <small>(optional)</small></span><input v-model="run.notes" class="sp-input" maxlength="1000" /></label>
        <div class="pd-total">
          <span>{{ okRows.length }} {{ okRows.length === 1 ? "payroll" : "payrolls" }} · total net <b>{{ php(okRows.reduce((s, r) => s + num(r.net_salary), 0)) }}</b></span>
          <div>
            <button type="button" class="sp-btn sp-btn-ghost" :disabled="busy" @click="previewRun">Recalculate</button>
            <button type="button" class="sp-btn sp-btn-primary" :disabled="busy || !okRows.length" @click="createRun">{{ busy ? "Creating…" : "Create and send to Finance" }}</button>
          </div>
        </div>
      </section>
    </template>

    <!-- ── Recurring pay items & salary history ── -->
    <section v-else class="pd-items">
      <aside class="sp-card pd-pick">
        <label class="sp-search"><span v-html="ICONS.search"></span><input v-model="itemSearch" class="sp-input" type="search" placeholder="Find someone" aria-label="Find someone" /></label>
        <div v-if="!people" class="pd-sk-block"><div v-for="n in 5" :key="n" class="bc-skeleton sp-sk-line w70"></div></div>
        <ul v-else>
          <li v-for="p in people.filter((x) => !itemSearch || x.name.toLowerCase().includes(itemSearch.toLowerCase()))" :key="p.id">
            <button type="button" :class="{ on: itemPerson?.id === p.id }" @click="pickPerson(p)">
              <span class="st-avatar sm" :style="{ background: personColor(p.name) }">{{ initialsOf(p.name) }}</span>
              <span><b>{{ p.name }}</b><small>{{ p.basic_salary ? `${php(p.basic_salary)} ${PER[p.salary_type]}` : "No salary set" }}</small></span>
            </button>
          </li>
        </ul>
      </aside>
      <div class="pd-items-main">
        <div v-if="!itemPerson" class="sp-card sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.user"></span>
          <h3>Pick someone</h3>
          <p>Set allowances and deductions that go on every payroll — like a meal allowance per day worked or a monthly loan payment — and see their pay history.</p>
        </div>
        <template v-else>
          <section class="sp-card">
            <div class="sp-card-head">
              <div><h2>Recurring allowances &amp; deductions</h2><p>Added to {{ itemPerson.name.split(" ")[0] }}'s payrolls automatically.</p></div>
              <button v-if="perms.create" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="editComponent()"><span v-html="ICONS.plus"></span>Add</button>
            </div>
            <div v-if="!components" class="pd-sk-block"><div v-for="n in 2" :key="n" class="bc-skeleton sp-sk-line w70"></div></div>
            <p v-else-if="!components.length" class="sp-hint">None yet.</p>
            <ul v-else class="pd-comp">
              <li v-for="c in components" :key="c.id" :class="{ off: !c.active }">
                <span class="sp-chip" :class="c.kind === 'allowance' ? 'green' : 'red'">{{ c.kind === "allowance" ? "Allowance" : "Deduction" }}</span>
                <span class="pd-comp-text"><b>{{ c.label }}</b><small>{{ php(c.amount) }} {{ c.basis === "per_day" ? "per day worked" : "per payroll" }}{{ c.active ? "" : " · paused" }}{{ c.note ? ` · ${c.note}` : "" }}</small></span>
                <template v-if="perms.create">
                  <button type="button" class="sp-icon-btn" :aria-label="`Edit ${c.label}`" @click="editComponent(c)"><span v-html="ICONS.edit"></span></button>
                  <button type="button" class="sp-icon-btn" :aria-label="`Remove ${c.label}`" @click="removeComponent(c)"><span v-html="ICONS.trash"></span></button>
                </template>
              </li>
            </ul>
          </section>
          <section class="sp-card">
            <div class="sp-card-head"><div><h2>Pay history</h2><p>Every change to their rate. Change pay in their HR profile.</p></div></div>
            <div v-if="!history" class="pd-sk-block"><div class="bc-skeleton sp-sk-line w50"></div></div>
            <p v-else-if="!history.length" class="sp-hint">No changes recorded yet.</p>
            <ol v-else class="pd-hist">
              <li v-for="(h, k) in history" :key="k">
                <b>{{ php(h.basic_salary) }} {{ PER[h.salary_type] }}</b>
                <small>From {{ new Date(h.effective_from).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) }} · {{ h.note }} · {{ h.changed_by }}</small>
              </li>
            </ol>
          </section>
        </template>
      </div>
    </section>

    <!-- Payslip drawer -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="slipOpen" class="sp-backdrop" @click="closeSlip"></div></Transition>
      <Transition name="sp-slide">
        <aside v-if="slipOpen" class="sp-drawer pd-drawer" role="dialog" aria-modal="true" aria-labelledby="pd-slip">
          <div class="sp-drawer-head">
            <div>
              <h3 id="pd-slip">{{ slip?.employee.name ?? slipRow?.employee }}</h3>
              <p>{{ slipRow ? periodText(slipRow.period_start, slipRow.period_end) : "" }} · {{ slipRow?.number }}</p>
            </div>
            <div class="pd-head-actions">
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="!slip" @click="print"><span v-html="ICONS.print"></span>Payslip</button>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeSlip"><span v-html="ICONS.close"></span></button>
            </div>
          </div>
          <div class="sp-drawer-body">
            <div v-if="!slip" class="pd-sk-block"><div v-for="n in 6" :key="n" class="bc-skeleton sp-sk-line" :class="n % 2 ? 'w70' : 'w50'"></div></div>
            <template v-else>
              <div class="pd-net" :class="slip.status">
                <div><small>Net pay</small><strong>{{ php(slip.net) }}</strong></div>
                <span class="sp-chip" :class="PAYROLL_STATUS[slip.status].tone">{{ PAYROLL_STATUS[slip.status].label }}</span>
              </div>
              <p v-if="slip.status === 'rejected' && slip.finance_notes" class="sp-banner warn"><span v-html="ICONS.alert"></span><b>Finance:</b>&nbsp;{{ slip.finance_notes }}</p>

              <dl class="pd-facts">
                <div><dt>Days worked</dt><dd>{{ slip.attendance.days_worked }} of {{ slip.attendance.expected_days }}</dd></div>
                <div><dt>Hours</dt><dd>{{ slip.attendance.hours }} h</dd></div>
                <div><dt>Overtime</dt><dd>{{ slip.attendance.overtime_hours ? `${slip.attendance.overtime_hours} h` : "—" }}</dd></div>
                <div><dt>Paid / unpaid leave</dt><dd>{{ slip.attendance.paid_leave_days }} / {{ slip.attendance.unpaid_leave_days }}</dd></div>
                <div><dt>Absent</dt><dd>{{ slip.attendance.absent_days }}</dd></div>
                <div><dt>Late · left early</dt><dd>{{ slip.attendance.late_minutes }} · {{ slip.attendance.undertime_minutes }} min</dd></div>
              </dl>

              <div class="pd-lines">
                <section>
                  <h4>Earnings</h4>
                  <p v-for="(e, k) in slip.earnings" :key="k"><span>{{ e.label }}</span><b>{{ php(e.amount) }}</b></p>
                  <p class="total"><span>Gross pay</span><b>{{ php(slip.gross) }}</b></p>
                </section>
                <section>
                  <h4>Deductions</h4>
                  <p v-if="!slip.deductions.length" class="pd-muted"><span>None</span></p>
                  <p v-for="(d, k) in slip.deductions" :key="k"><span>{{ d.label }}</span><b>−{{ php(d.amount) }}</b></p>
                  <p class="total"><span>Total</span><b>−{{ php(slip.total_deductions) }}</b></p>
                </section>
              </div>
              <p v-if="slip.include_contributions && slip.contributions_share && slip.contributions_share < 1" class="sp-hint">Contributions: {{ Math.round(slip.contributions_share * 100) }}% of a month, for this period's length.</p>

              <!-- Revise lines -->
              <template v-if="canRevise">
                <h4 class="st-section-title">One-off lines</h4>
                <div class="pd-extra">
                  <div v-for="(x, k) in revise.items" :key="k" class="pd-extra-row">
                    <select v-model="x.kind" class="sp-select" aria-label="Kind"><option value="bonus">Bonus</option><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select>
                    <input v-model="x.label" class="sp-input" maxlength="80" aria-label="Name" />
                    <input v-model.number="x.amount" type="number" min="0.01" step="0.01" class="sp-input" aria-label="Amount" />
                    <button type="button" class="sp-icon-btn" aria-label="Remove line" @click="revise.items.splice(k, 1)"><span v-html="ICONS.close"></span></button>
                  </div>
                  <button type="button" class="st-link" @click="revise.items.push({ kind: 'bonus', label: '', amount: null })">+ Add a bonus or deduction</button>
                </div>
                <label class="pd-toggle compact">
                  <span class="sp-switch"><input v-model="revise.contributions" type="checkbox" /><span></span></span>
                  <span><b>Take SSS, PhilHealth and Pag-IBIG</b></span>
                </label>
                <label class="sp-field"><span class="sp-label">Note for Finance</span><input v-model="revise.notes" class="sp-input" maxlength="1000" /></label>
              </template>
              <p v-else-if="slip.notes" class="pd-note"><b>HR note:</b> {{ slip.notes }}</p>

              <h4 class="st-section-title">History</h4>
              <RequestHistory :history="slip.history" :labels="PAYROLL_EVENT" />

              <template v-if="canDecide">
                <h4 class="st-section-title">Your decision</h4>
                <textarea v-model="decisionNote" class="sp-textarea" maxlength="1000" placeholder="Note (needed to send it back)"></textarea>
              </template>
              <p v-if="ownPayroll && slip.status === 'pending' && (perms.approve || perms.reject)" class="sp-hint">You created this payroll, so someone else approves it.</p>
              <p v-if="slipError" class="sp-error">{{ slipError }}</p>
            </template>
          </div>
          <div v-if="slip" class="sp-drawer-foot">
            <button v-if="perms.delete && ['pending', 'rejected'].includes(slip.status)" type="button" class="sp-btn sp-btn-ghost pd-left" :disabled="busy" @click="removeSlip">{{ confirmDelete ? "Yes, delete" : "Delete" }}</button>
            <button v-if="canRevise" type="button" class="sp-btn sp-btn-ghost" :disabled="busy" @click="saveRevise">{{ slip.status === "rejected" ? "Fix and send again" : "Recalculate" }}</button>
            <template v-if="canDecide">
              <button v-if="perms.reject" type="button" class="sp-btn sp-btn-ghost" :disabled="busy" @click="decide('reject')">Send back</button>
              <button v-if="perms.approve" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="decide('approve')">Approve</button>
            </template>
            <button v-if="slip.status === 'approved' && perms.pay" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="decide('markPaid')">Mark as paid</button>
          </div>
        </aside>
      </Transition>
    </Teleport>

    <!-- Recurring item editor -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="comp" class="sp-backdrop" @click="comp = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="comp" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="pd-comp" novalidate @submit.prevent="saveComponent">
            <div class="sp-modal-head"><div><h3 id="pd-comp">{{ comp.id ? "Edit" : "Add" }} for {{ itemPerson.name }}</h3><p>Goes on every payroll from now on.</p></div></div>
            <div class="sp-grid-2">
              <label class="sp-field"><span class="sp-label">Kind</span><select v-model="comp.kind" class="sp-select"><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select></label>
              <label class="sp-field"><span class="sp-label">Name</span><input v-model="comp.label" class="sp-input" maxlength="80" :placeholder="comp.kind === 'allowance' ? 'e.g. Meal allowance' : 'e.g. SSS salary loan'" /></label>
              <label class="sp-field"><span class="sp-label">Amount</span><input v-model.number="comp.amount" type="number" min="0.01" step="0.01" class="sp-input" /></label>
              <label class="sp-field"><span class="sp-label">How often</span><select v-model="comp.basis" class="sp-select"><option value="per_payroll">Every payroll</option><option value="per_day">Per day worked</option></select></label>
              <label class="sp-field sp-span-all"><span class="sp-label">Note <small>(optional)</small></span><input v-model="comp.note" class="sp-input" maxlength="255" placeholder="e.g. Until the loan is paid off in March" /></label>
              <label class="pd-toggle compact sp-span-all"><span class="sp-switch"><input v-model="comp.active" type="checkbox" /><span></span></span><span><b>Active</b></span></label>
            </div>
            <p v-if="compError" class="sp-error">{{ compError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="comp = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="busy || !comp.label.trim() || !comp.amount">Save</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import { SHOP_ICONS as ICONS } from "../../vendor/shop/shopIcons";
import { personColor } from "../../vendor/staff/staffUi";
import { initialsOf } from "../hrUi";
import RequestHistory from "../leave/RequestHistory.vue";
import { printPayslip } from "./payslipPrint";
import { PAYROLL_EVENT, PAYROLL_STATUS, num, periodPresets, periodText, php } from "../../../services/payrollDesk";

const props = defineProps({
  api: { type: Object, required: true },
  // hr: create, revise · finance: approve, pay · owner: everything
  mode: { type: String, default: "hr" },
  perms: { type: Object, default: () => ({ view: true, create: true, delete: true, approve: true, reject: true, pay: true }) },
  // The signed-in HR employee's id, to spot payrolls they created
  employeeId: { type: Number, default: null },
  // runs · new · items
  start: { type: String, default: "runs" },
});

const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
const PER = { daily: "a day", weekly: "a week", monthly: "a month" };
const STATUS_TABS = [
  { key: "pending", label: "Waiting" },
  { key: "rejected", label: "Sent back" },
  { key: "approved", label: "To pay" },
  { key: "paid", label: "Paid" },
  { key: "all", label: "All" },
];
const now = new Date();
const years = [now.getFullYear(), now.getFullYear() - 1, now.getFullYear() - 2];
const presets = periodPresets(now);

const tab = ref(props.start === "new" && !props.perms.create ? "runs" : props.start);
const tabs = computed(() => [
  { key: "runs", label: "Payrolls" },
  ...(props.perms.create ? [{ key: "new", label: "New payroll" }] : []),
  ...(props.mode !== "finance" ? [{ key: "items", label: "Allowances, deductions & pay history" }] : []),
]);

// ── Runs ──
const rows = ref(null);
const summary = ref(null);
const error = ref("");
const busy = ref(false);
const search = ref("");
const filters = reactive({ status: props.mode === "finance" ? "pending" : "all", month: 0, year: now.getFullYear() });
const page = ref({ current_page: 1, last_page: 1 });
const selected = ref([]);
const bulkNote = ref("");

const statTiles = computed(() => {
  const s = summary.value ?? {};
  return [
    { status: "pending", label: "waiting for Finance", count: s.pending_count ?? 0, amount: s.total_pending_amount, tone: "amber", icon: "clock" },
    { status: "rejected", label: "sent back to HR to fix", count: s.rejected_count ?? 0, amount: null, tone: "red", icon: "alert" },
    { status: "approved", label: "approved, to pay", count: s.approved_count ?? 0, amount: s.total_approved_amount, tone: "blue", icon: "check" },
    { status: "paid", label: "paid", count: s.paid_count ?? 0, amount: s.total_paid_amount, tone: "green", icon: "wallet" },
  ];
});
const selectable = computed(() => (rows.value ?? []).filter(canSelect));
const allSelected = computed(() => selectable.value.length && selectable.value.every((r) => selected.value.includes(r.id)));
const selectedRows = computed(() => (rows.value ?? []).filter((r) => selected.value.includes(r.id)));
const selectedStatus = computed(() => selectedRows.value[0]?.status);
const selectedTotal = computed(() => selectedRows.value.reduce((s, r) => s + r.net, 0));

function canSelect(r) {
  if (selected.value.length && selectedStatus.value && r.status !== selectedStatus.value) return false;
  if (r.status === "pending") return (props.perms.approve || props.perms.reject) && r.created_by_employee_id !== props.employeeId;
  return r.status === "approved" && props.perms.pay;
}
function toggle(r) {
  selected.value = selected.value.includes(r.id) ? selected.value.filter((id) => id !== r.id) : [...selected.value, r.id];
}
function toggleAll() {
  selected.value = allSelected.value ? [] : selectable.value.map((r) => r.id);
}

const params = () => ({ status: filters.status, month: filters.month || undefined, year: filters.year, search: search.value.trim() || undefined });

async function load(p = 1) {
  error.value = "";
  rows.value = null;
  selected.value = [];
  try {
    const res = await props.api.list({ ...params(), page: p, per_page: 30 });
    rows.value = res.data;
    page.value = res.pagination;
  } catch (e) {
    rows.value = [];
    error.value = message(e, "Couldn't load payrolls.");
  }
}

async function loadSummary() {
  try {
    summary.value = (await props.api.summary({ month: filters.month || undefined, year: filters.year })).data;
  } catch (_) {
    summary.value = {};
  }
}

let searchTimer;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(), 350);
});
watch(() => filters.status, () => load());
watch(() => [filters.month, filters.year], () => {
  load();
  loadSummary();
});

async function bulk(action) {
  if (action === "reject" && !bulkNote.value.trim()) return toast.info("Add a note saying what needs fixing.");
  busy.value = true;
  try {
    const res = await props.api[action](selected.value, bulkNote.value.trim() || null);
    toast.success(res.message);
    bulkNote.value = "";
    await Promise.all([load(page.value.current_page), loadSummary()]);
  } catch (e) {
    toast.error(message(e, "Couldn't do that."));
  } finally {
    busy.value = false;
  }
}

function exportRegister() {
  const head = ["Payslip", "Name", "Period start", "Period end", "Days worked", "Expected days", "Hours", "Overtime hours", "Gross", "Deductions", "Net", "Status", "Created by", "Approved by"];
  const body = rows.value.map((r) => [r.number, r.employee, r.period_start, r.period_end, r.days_worked, r.expected_days, r.hours, r.overtime_hours, r.gross, r.deductions, r.net, PAYROLL_STATUS[r.status].short, r.created_by, r.approved_by]);
  const cell = (v) => {
    const s = v === null || v === undefined ? "" : String(v);
    return `"${(/^[=+\-@]/.test(s) ? `'${s}` : s).replace(/"/g, '""')}"`;
  };
  const blob = new Blob(["﻿" + [head, ...body].map((r) => r.map(cell).join(",")).join("\r\n")], { type: "text/csv;charset=utf-8" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = `payroll-register-${filters.year}${filters.month ? `-${String(filters.month).padStart(2, "0")}` : ""}.csv`;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

// ── Payslip drawer ──
const slipOpen = ref(false);
const slipRow = ref(null);
const slip = ref(null);
const slipError = ref("");
const decisionNote = ref("");
const confirmDelete = ref(false);
const revise = reactive({ items: [], notes: "", contributions: false });

const ownPayroll = computed(() => props.employeeId && slip.value?.created_by_employee_id === props.employeeId);
const canRevise = computed(() => props.perms.create && ["pending", "rejected"].includes(slip.value?.status));
const canDecide = computed(() => slip.value?.status === "pending" && (props.perms.approve || props.perms.reject) && !ownPayroll.value);

async function openSlip(r) {
  slipRow.value = r;
  slip.value = null;
  slipError.value = "";
  decisionNote.value = "";
  confirmDelete.value = false;
  slipOpen.value = true;
  try {
    slip.value = (await props.api.payslip(r.id)).data;
    revise.items = slip.value.items.filter((i) => i.source === "manual").map((i) => ({ kind: i.kind, label: i.label, amount: i.amount }));
    revise.notes = slip.value.notes ?? "";
    revise.contributions = slip.value.include_contributions;
  } catch (e) {
    slipError.value = message(e, "Couldn't open the payslip.");
  }
}

function closeSlip() {
  if (!busy.value) slipOpen.value = false;
}

function print() {
  if (!printPayslip(slip.value)) toast.info("Allow pop-ups for this site to print the payslip.");
}

async function slipAction(fn) {
  busy.value = true;
  slipError.value = "";
  try {
    const res = await fn();
    toast.success(res.message);
    await Promise.all([load(page.value.current_page), loadSummary()]);
    if (slipOpen.value && slipRow.value) await openSlip(slipRow.value);
  } catch (e) {
    slipError.value = message(e, "Couldn't do that.");
  } finally {
    busy.value = false;
  }
}

function decide(action) {
  if (action === "reject" && !decisionNote.value.trim()) {
    slipError.value = "Add a note saying what needs fixing.";
    return;
  }
  slipAction(() => props.api[action]([slip.value.id], decisionNote.value.trim() || null));
}

function saveRevise() {
  const bad = revise.items.find((x) => !x.label?.trim() || !(x.amount > 0));
  if (bad) {
    slipError.value = "Each line needs a name and an amount.";
    return;
  }
  slipAction(() => props.api.revise(slip.value.id, { items: revise.items, notes: revise.notes || null, include_contributions: revise.contributions }));
}

async function removeSlip() {
  if (!confirmDelete.value) {
    confirmDelete.value = true;
    return;
  }
  busy.value = true;
  try {
    const res = await props.api.remove(slip.value.id);
    toast.success(res.message);
    slipOpen.value = false;
    await Promise.all([load(page.value.current_page), loadSummary()]);
  } catch (e) {
    slipError.value = message(e, "Couldn't delete it.");
  } finally {
    busy.value = false;
  }
}

// ── New payroll ──
const people = ref(null);
const run = reactive({ from: presets[0].from, to: presets[0].to, contributions: false, ids: [], notes: "" });
const runPreview = ref(null);
const runError = ref("");
const extras = reactive({});
const okRows = computed(() => (runPreview.value ?? []).filter((r) => r.ok && !r.overlaps));
const nameOf = (id) => people.value?.find((p) => p.id === id)?.name ?? "";

async function loadPeople() {
  if (people.value || props.mode === "finance") return;
  try {
    people.value = (await props.api.people()).data;
  } catch (e) {
    people.value = [];
    toast.error(message(e, "Couldn't load staff."));
  }
}

function setPeriod(p) {
  run.from = p.from;
  run.to = p.to;
  runPreview.value = null;
}

function body() {
  const items = {};
  for (const [id, list] of Object.entries(extras)) {
    const clean = list.filter((x) => x.label?.trim() && x.amount > 0).map((x) => ({ kind: x.kind, label: x.label.trim(), amount: x.amount }));
    if (clean.length && run.ids.includes(Number(id))) items[id] = clean;
  }
  return { employee_ids: run.ids, period_start: run.from, period_end: run.to, include_contributions: run.contributions, items, notes: run.notes || null };
}

async function previewRun() {
  busy.value = true;
  runError.value = "";
  try {
    runPreview.value = (await props.api.batchPreview(body())).data;
  } catch (e) {
    runError.value = message(e, "Couldn't work out the pay.");
  } finally {
    busy.value = false;
  }
}

async function createRun() {
  busy.value = true;
  runError.value = "";
  try {
    const b = body();
    b.employee_ids = okRows.value.map((r) => r.employee_id);
    const res = await props.api.batch(b);
    toast.success(res.message);
    runPreview.value = null;
    run.ids = [];
    Object.keys(extras).forEach((k) => delete extras[k]);
    filters.status = "pending";
    tab.value = "runs";
    await Promise.all([load(), loadSummary()]);
  } catch (e) {
    runError.value = message(e, "Couldn't create the payrolls.");
  } finally {
    busy.value = false;
  }
}

// ── Recurring items & pay history ──
const itemSearch = ref("");
const itemPerson = ref(null);
const components = ref(null);
const history = ref(null);
const comp = ref(null);
const compError = ref("");

async function pickPerson(p) {
  itemPerson.value = p;
  components.value = null;
  history.value = null;
  try {
    const [c, h] = await Promise.all([props.api.components(p.id), props.api.salaryHistory(p.id)]);
    components.value = c.data;
    history.value = h.data;
  } catch (e) {
    components.value = [];
    history.value = [];
    toast.error(message(e, "Couldn't load their pay details."));
  }
}

function editComponent(c = null) {
  compError.value = "";
  comp.value = c ? { ...c } : { kind: "allowance", label: "", amount: null, basis: "per_payroll", active: true, note: "" };
}

async function saveComponent() {
  busy.value = true;
  compError.value = "";
  try {
    const res = await props.api.saveComponent({ ...comp.value, employee_info_id: itemPerson.value.id });
    toast.success(res.message);
    comp.value = null;
    components.value = (await props.api.components(itemPerson.value.id)).data;
  } catch (e) {
    compError.value = message(e, "Couldn't save it.");
  } finally {
    busy.value = false;
  }
}

async function removeComponent(c) {
  try {
    const res = await props.api.removeComponent(c.id);
    toast.success(res.message);
    components.value = components.value.filter((x) => x.id !== c.id);
  } catch (e) {
    toast.error(message(e, "Couldn't remove it."));
  }
}

watch(tab, (t) => {
  if (t !== "runs") loadPeople();
});

function message(e, fallback) {
  const list = e?.response?.data?.errors;
  return (list && Object.values(list).flat()[0]) || e?.response?.data?.message || fallback;
}

onMounted(() => {
  load();
  loadSummary();
  if (tab.value !== "runs") loadPeople();
});
</script>

<style scoped>
.pd-tabs {
  display: flex;
  width: fit-content;
  max-width: 100%;
  margin-bottom: 16px;
  overflow-x: auto;
}
.pd-tabs button {
  white-space: nowrap;
}
.pd-stats {
  margin-bottom: 16px;
}
.pd-stats .grow {
  flex: 1;
}
.pd-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 12px;
}
.pd-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.pd-filters .sp-select {
  width: auto;
}
.pd-filters .sp-search {
  width: 190px;
}
.pd-bulk {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--bc-accent-soft);
}
.pd-bulk b {
  margin-right: auto;
  font-size: 13px;
  color: var(--v-text);
}
.pd-bulk .sp-input {
  width: 240px;
  padding: 7px 10px;
}
.pd-sk {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.pd-sk li {
  display: flex;
  align-items: center;
  gap: 12px;
}
.pd-sk .grow {
  flex: 1;
}
.pd-sk-block {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pd-table .num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.pd-table td:nth-child(2) {
  min-width: 210px;
}
.pd-check {
  width: 36px;
}
.pd-check input {
  width: 16px;
  height: 16px;
  accent-color: var(--bc-accent);
}
.pd-muted {
  color: var(--v-muted);
}
.pd-sub {
  color: var(--v-muted);
}
.pd-pages {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 12px;
  margin-top: 6px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.pd-pages div {
  display: flex;
  gap: 6px;
}

/* New payroll */
.pd-new {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}
.pd-step {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pd-step span {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
  font-size: 12.5px;
  font-weight: 700;
}
.pd-step h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
}
.pd-step small {
  font-weight: 400;
  color: var(--v-muted);
}
.pd-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.pd-dates {
  max-width: 460px;
}
.pd-toggle {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  cursor: pointer;
}
.pd-toggle.compact {
  padding: 8px 0;
  border: 0;
}
.pd-toggle b {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.pd-toggle small {
  font-size: 12px;
  color: var(--v-muted);
}
.pd-people-bar {
  display: flex;
  gap: 14px;
}
.pd-people {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.pd-person {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  cursor: pointer;
}
.pd-person.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.pd-person.off {
  cursor: not-allowed;
  opacity: 0.75;
}
.pd-person input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.pd-person:has(input:focus-visible) {
  box-shadow: 0 0 0 2px var(--bc-accent);
}
.pd-person-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.pd-person-text b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.pd-person-text small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.pd-warn {
  color: var(--v-amber-text) !important;
}
.pd-actions {
  display: flex;
  justify-content: flex-end;
}
.pd-pre {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.pd-pre.bad {
  border-color: color-mix(in srgb, #e53e3e 40%, var(--v-border));
}
.pd-pre header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.pd-pre-pay {
  text-align: right;
}
.pd-pre-pay small {
  display: block;
  font-size: 11px;
  color: var(--v-muted);
}
.pd-pre-pay b {
  font-size: 18px;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.pd-pre-lines {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-subtle);
}
.pd-pre-lines div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12.5px;
}
.pd-pre-lines dt {
  color: var(--v-text-2);
}
.pd-pre-lines dt em {
  font-style: normal;
  color: var(--v-faint);
}
.pd-pre-lines dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
  color: var(--v-text);
}
.pd-pre-lines .deduction dd {
  color: var(--v-red-text);
}
.pd-pre-lines .bonus dd,
.pd-pre-lines .allowance dd {
  color: var(--v-green-text);
}
.pd-pre-lines .total {
  margin-top: 4px;
  padding-top: 6px;
  border-top: 1px solid var(--v-border);
  font-weight: 600;
}
.pd-extra {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}
.pd-extra-row {
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr) 120px 32px;
  gap: 6px;
  width: 100%;
}
.pd-extra-row .sp-input,
.pd-extra-row .sp-select {
  padding: 7px 9px;
}
.pd-notes {
  max-width: 520px;
}
.pd-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
  font-size: 13.5px;
  color: var(--v-text-2);
}
.pd-total div {
  display: flex;
  gap: 8px;
}

/* Recurring items */
.pd-items {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.pd-pick {
  padding: 12px;
}
.pd-pick ul {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  max-height: 60vh;
  overflow-y: auto;
}
.pd-pick button {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px;
  border: 0;
  border-radius: 10px;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.pd-pick button:hover {
  background: var(--v-subtle);
}
.pd-pick button.on {
  background: var(--bc-accent-soft);
}
.pd-pick button span:last-child {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.pd-pick b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.pd-pick small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.pd-items-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.pd-comp {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.pd-comp li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
}
.pd-comp li.off {
  opacity: 0.6;
}
.pd-comp-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}
.pd-comp-text b {
  font-size: 13px;
  color: var(--v-text);
}
.pd-comp-text small {
  font-size: 12px;
  color: var(--v-muted);
}
.pd-hist {
  margin: 0;
  padding-left: 18px;
}
.pd-hist li {
  margin-bottom: 8px;
}
.pd-hist b {
  display: block;
  font-size: 13px;
  color: var(--v-text);
}
.pd-hist small {
  font-size: 12px;
  color: var(--v-muted);
}

/* Payslip drawer */
.pd-drawer {
  width: min(680px, 100vw);
}
.pd-head-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.pd-net {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 14px;
  padding: 16px 18px;
  border-radius: 16px;
  background: linear-gradient(135deg, var(--bc-accent-soft), var(--v-subtle));
}
.pd-net small {
  font-size: 12px;
  color: var(--v-muted);
}
.pd-net strong {
  display: block;
  font-size: 28px;
  font-weight: 700;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.pd-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px 14px;
  margin: 0 0 16px;
}
.pd-facts dt {
  font-size: 11px;
  color: var(--v-faint);
}
.pd-facts dd {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.pd-lines {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.pd-lines h4 {
  margin: 0 0 6px;
  font-size: 11.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--v-muted);
}
.pd-lines p {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin: 0;
  padding: 6px 0;
  border-bottom: 1px solid var(--v-border);
  font-size: 13px;
  color: var(--v-text-2);
}
.pd-lines p b {
  font-weight: 500;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.pd-lines p.total {
  border-bottom: 0;
  font-weight: 700;
}
.pd-lines p.total b {
  font-weight: 700;
}
.pd-note {
  margin: 12px 0 0;
  font-size: 13px;
  color: var(--v-text-2);
}
.pd-left {
  margin-right: auto;
}
@media (max-width: 900px) {
  .pd-items {
    grid-template-columns: 1fr;
  }
  .pd-lines,
  .pd-facts {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 760px) {
  .pd-table .num {
    text-align: left;
  }
  .pd-table td:nth-child(2) {
    min-width: 0;
  }
}
@media (max-width: 560px) {
  .pd-lines {
    grid-template-columns: 1fr;
  }
  .pd-extra-row {
    grid-template-columns: 1fr 1fr;
  }
  .pd-filters,
  .pd-filters .sp-search {
    width: 100%;
  }
}
</style>
