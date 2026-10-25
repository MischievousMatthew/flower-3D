<template>
  <div class="sb">
    <!-- Toolbar -->
    <div class="sb-toolbar">
      <div class="sp-seg" role="tablist" aria-label="View">
        <button type="button" role="tab" :class="{ on: view === 'week' }" :aria-selected="view === 'week'" @click="setView('week')">Week</button>
        <button type="button" role="tab" :class="{ on: view === 'month' }" :aria-selected="view === 'month'" @click="setView('month')">Month</button>
      </div>
      <div class="sb-nav">
        <button type="button" class="sp-icon-btn" :aria-label="view === 'week' ? 'Previous week' : 'Previous month'" @click="shift(-1)"><span v-html="ICONS.chevronLeft"></span></button>
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="goToday">Today</button>
        <button type="button" class="sp-icon-btn" :aria-label="view === 'week' ? 'Next week' : 'Next month'" @click="shift(1)"><span v-html="ICONS.chevronRight"></span></button>
        <strong class="sb-range">{{ rangeLabel }}</strong>
      </div>
      <div class="sb-actions">
        <button v-if="perms.create && view === 'week'" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" :disabled="busy" @click="copyWeek"><span v-html="ICONS.history"></span>Copy last week</button>
        <button v-if="perms.edit" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openTemplate()"><span v-html="ICONS.plus"></span>New shift</button>
      </div>
    </div>

    <p v-if="picked" class="sp-banner info sb-pick">
      <span v-html="ICONS.user"></span>Tap a shift to put <b>{{ picked.name }}</b> on it, or tap someone's name to have {{ picked.name.split(" ")[0] }} cover them.
      <button type="button" @click="picked = null">Cancel</button>
    </p>
    <p v-if="error" class="sp-banner error"><span v-html="ICONS.alert"></span>{{ error }}<button type="button" @click="load">Try again</button></p>

    <div class="sb-layout">
      <!-- Board -->
      <section class="sp-card sb-board" :class="{ dragging: !!dragging }">
        <div v-if="!board" class="sb-sk">
          <div v-for="n in 4" :key="n" class="bc-skeleton sb-sk-row"></div>
        </div>

        <div v-else-if="!board.templates.length && view === 'week' && !board.assignments.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.calendar"></span>
          <h3>Create your first shift</h3>
          <p>A shift is a block of hours people work, like "Morning counter, 8 AM–5 PM, needs 2 people, Monday to Saturday". Then link people to it or drag them on from the right.</p>
          <button v-if="perms.edit" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openTemplate()"><span v-html="ICONS.plus"></span>New shift</button>
        </div>

        <!-- Week -->
        <div v-else-if="view === 'week'" class="sb-scroll">
          <div class="sb-grid" role="grid" :aria-label="`Shifts for ${rangeLabel}`">
            <div class="sb-corner" role="columnheader">Shifts</div>
            <button
              v-for="d in board.days"
              :key="d"
              type="button"
              role="columnheader"
              class="sb-dayhead"
              :class="{ today: d === today, on: d === selectedDay }"
              :aria-pressed="d === selectedDay"
              @click="selectedDay = d"
            >
              <span>{{ dayShort(d) }}</span>
              <b>{{ dayNum(d) }}</b>
            </button>

            <template v-for="row in rows" :key="row.key">
              <div class="sb-rowhead" role="rowheader" :style="row.color ? { '--row': row.color } : null">
                <i v-if="row.color" aria-hidden="true"></i>
                <div>
                  <strong>{{ row.name }}</strong>
                  <small>{{ row.sub }}</small>
                </div>
                <button v-if="row.template && perms.edit" type="button" class="sp-icon-btn sb-mini" :aria-label="`Edit ${row.name}`" @click="openTemplate(row.template)"><span v-html="ICONS.edit"></span></button>
                <button v-if="row.template && perms.create" type="button" class="sp-icon-btn sb-mini" :aria-label="`Link people to ${row.name}`" title="Link people" @click="openLink(row.template)"><span v-html="ICONS.users"></span></button>
              </div>
              <div
                v-for="d in board.days"
                :key="`${row.key}-${d}`"
                role="gridcell"
                class="sb-cell"
                :class="[{ on: d === selectedDay, past: d < today, idle: row.template && !row.template.days.includes(weekdayName(d)), drop: dropTarget === `${row.key}|${d}`, pickable: picked && perms.create }]"
                @dragover.prevent="dropTarget = `${row.key}|${d}`"
                @dragleave="dropTarget = dropTarget === `${row.key}|${d}` ? null : dropTarget"
                @drop.prevent="onDrop($event, row, d)"
                @click="picked && placePicked(row, d)"
              >
                <span v-if="row.template" class="sb-cover" :class="cov(row, d).state" :title="coverTitle(row, d)">
                  <template v-if="cov(row, d).state === 'idle'">Not running</template>
                  <template v-else>{{ cov(row, d).on }}/{{ cov(row, d).need }}</template>
                </span>
                <div
                  v-for="a in cellMap[`${row.key}|${d}`] ?? []"
                  :key="a.id"
                  class="sb-chip"
                  :class="{ replaced: a.status === 'replaced', cover: a.covers, menu: menuId === a.id }"
                  :draggable="perms.create && a.status !== 'replaced'"
                  :style="row.color ? { '--row': row.color } : null"
                  tabindex="0"
                  role="button"
                  :aria-label="chipLabel(a)"
                  @dragstart="startDrag($event, { type: 'chip', employeeId: a.employee_id, name: a.employee, date: a.date, shiftId: a.id })"
                  @dragend="endDrag"
                  @dragover.prevent.stop="dropTarget = `chip${a.id}`"
                  @drop.prevent.stop="onDropChip($event, a)"
                  @click.stop="picked ? pickOnChip(a) : (menuId = menuId === a.id ? null : a.id)"
                  @keydown.enter.prevent="menuId = menuId === a.id ? null : a.id"
                >
                  <span class="sb-av" :style="{ background: personColor(a.employee) }">{{ initialsOf(a.employee) }}</span>
                  <span class="sb-chip-text">
                    <b>{{ a.employee }}</b>
                    <small v-if="a.status === 'replaced'">→ {{ a.replaced_by }} · {{ REASON_LABEL[a.replaced_reason] ?? "covered" }}</small>
                    <small v-else-if="a.covers">covering {{ a.covers }}</small>
                    <small v-else-if="a.kind === 'custom'">{{ time12(a.start) }}–{{ time12(a.end) }}</small>
                  </span>
                  <div v-if="menuId === a.id" class="sb-menu" role="menu" @click.stop>
                    <template v-if="a.status === 'replaced'">
                      <button v-if="perms.edit" type="button" role="menuitem" @click="undoCover(a)"><span v-html="ICONS.restore"></span>Put {{ first(a.employee) }} back on</button>
                    </template>
                    <template v-else-if="a.kind === 'off'">
                      <button v-if="perms.delete" type="button" role="menuitem" @click="removeShift(a)"><span v-html="ICONS.close"></span>Remove day off</button>
                    </template>
                    <template v-else>
                      <button v-if="perms.edit" type="button" role="menuitem" @click="openReplace(a)"><span v-html="ICONS.users"></span>Someone else covers…</button>
                      <button v-if="perms.create" type="button" role="menuitem" @click="dayOff(a)"><span v-html="ICONS.pause"></span>Give {{ first(a.employee) }} the day off</button>
                      <button v-if="perms.delete" type="button" role="menuitem" class="danger" @click="removeShift(a)"><span v-html="ICONS.trash"></span>Take off this shift</button>
                    </template>
                    <small v-if="a.assigned_by" class="sb-menu-note">Set by {{ a.assigned_by }}</small>
                  </div>
                </div>
                <span v-if="dragging && perms.create" class="sb-drop-hint" aria-hidden="true">Drop here</span>
              </div>
            </template>
          </div>
        </div>

        <!-- Month -->
        <div v-else class="sb-month">
          <div v-for="w in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="w" class="sb-mhead">{{ w }}</div>
          <button
            v-for="d in board.days"
            :key="d"
            type="button"
            class="sb-mday"
            :class="{ today: d === today, out: d.slice(0, 7) !== anchor.slice(0, 7) }"
            @click="openDay(d)"
          >
            <b>{{ dayNum(d) }}</b>
            <span v-for="t in board.templates.filter((t) => cov({ template: t, key: `t${t.id}` }, d).state !== 'idle')" :key="t.id" class="sb-mline" :class="cov({ template: t, key: `t${t.id}` }, d).state">
              <i :style="{ background: t.color }"></i>{{ t.name }} {{ cov({ template: t, key: `t${t.id}` }, d).on }}/{{ cov({ template: t, key: `t${t.id}` }, d).need }}
            </span>
            <span v-if="coveredOn(d)" class="sb-mline covered">{{ coveredOn(d) }} covered</span>
          </button>
        </div>
      </section>

      <!-- Staff availability -->
      <aside class="sp-card sb-side" aria-label="Staff availability">
        <div class="sb-side-head">
          <div>
            <h2>Staff availability</h2>
            <p>{{ selectedDay ? longDay(selectedDay) : "" }}</p>
          </div>
          <div class="sb-side-nav">
            <button type="button" class="sp-icon-btn" aria-label="Previous day" @click="moveDay(-1)"><span v-html="ICONS.chevronLeft"></span></button>
            <button type="button" class="sp-icon-btn" aria-label="Next day" @click="moveDay(1)"><span v-html="ICONS.chevronRight"></span></button>
          </div>
        </div>
        <label class="sp-search sb-search">
          <span v-html="ICONS.search"></span>
          <input v-model="query" class="sp-input" type="search" placeholder="Find someone" aria-label="Find someone" />
        </label>
        <p v-if="perms.create" class="sp-hint sb-side-tip">Drag someone onto a shift — or tap them, then tap the shift.</p>

        <div v-if="!board" class="sb-side-sk">
          <div v-for="n in 5" :key="n" class="bc-skeleton sp-sk-line w70"></div>
        </div>
        <template v-else>
          <p v-if="!groups.length" class="sp-hint">{{ query ? "Nobody matches." : "No active staff with an HR profile yet." }}</p>
          <section v-for="g in groups" :key="g.key" class="sb-group">
            <h3>{{ g.label }} <span>{{ g.people.length }}</span></h3>
            <small>{{ g.hint }}</small>
            <ul>
              <li
                v-for="p in g.people"
                :key="p.id"
                class="sb-person"
                :class="{ picked: picked?.employeeId === p.id, locked: !g.draggable || !perms.create }"
                :draggable="g.draggable && perms.create"
                :tabindex="g.draggable && perms.create ? 0 : -1"
                @dragstart="startDrag($event, { type: 'person', employeeId: p.id, name: p.name })"
                @dragend="endDrag"
                @click="g.draggable && perms.create && togglePick(p)"
                @keydown.enter.prevent="g.draggable && perms.create && togglePick(p)"
              >
                <span class="sb-av lg" :style="{ background: personColor(p.name) }">
                  <img v-if="p.avatar" :src="p.avatar" alt="" />
                  <template v-else>{{ initialsOf(p.name) }}</template>
                </span>
                <div class="sb-person-text">
                  <b>{{ p.name }}</b>
                  <small>{{ personNote(p) }}</small>
                </div>
                <span v-if="p.weekHours" class="sb-hours" :class="{ over: p.weekHours > board.max_week_hours }" :title="`${p.weekHours} hours scheduled this week`">{{ p.weekHours }}h</span>
              </li>
            </ul>
          </section>
        </template>
      </aside>
    </div>

    <!-- Confirm a conflict -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="conflict" class="sp-backdrop" @click="conflict = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="conflict" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="sb-conflict">
            <div class="sp-modal-head"><div><h3 id="sb-conflict">Are you sure?</h3><p>{{ conflict.message }}</p></div></div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="conflict = null">No</button>
              <button type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="confirmConflict">{{ CONFIRM_VERB[conflict.confirm] ?? "Yes" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Custom hours -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="hours" class="sp-backdrop" @click="hours = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="hours" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="sb-hours" novalidate @submit.prevent="saveHours">
            <div class="sp-modal-head"><div><h3 id="sb-hours">Hours for {{ hours.name }}</h3><p>{{ longDay(hours.date) }}, outside the usual shifts.</p></div></div>
            <div class="sp-grid-2">
              <label class="sp-field"><span class="sp-label">Starts</span><input v-model="hours.start" type="time" class="sp-input" /></label>
              <label class="sp-field"><span class="sp-label">Ends</span><input v-model="hours.end" type="time" class="sp-input" /></label>
              <label class="sp-field sp-span-all"><span class="sp-label">Label <small>(optional)</small></span><input v-model="hours.label" class="sp-input" maxlength="40" placeholder="e.g. Wedding setup" /></label>
            </div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="hours = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="!hours.start || !hours.end || hours.end <= hours.start || busy">Schedule</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Someone else covers -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="cover" class="sp-backdrop" @click="cover = null"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="cover" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="sb-cover" novalidate @submit.prevent="saveCover">
          <div class="sp-drawer-head">
            <div>
              <h3 id="sb-cover">Cover {{ cover.shift.employee }}'s shift</h3>
              <p>{{ longDay(cover.shift.date) }} · {{ shiftName(cover.shift) }} · {{ time12(cover.shift.start) }}–{{ time12(cover.shift.end) }}</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="cover = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <h4 class="st-section-title first">Why</h4>
            <div class="sb-reasons" role="radiogroup" aria-label="Why">
              <label v-for="r in REASONS" :key="r.key" class="sb-reason" :class="{ on: cover.reason === r.key }">
                <input v-model="cover.reason" type="radio" name="reason" :value="r.key" />
                <b>{{ r.label }}</b><small>{{ r.hint }}</small>
              </label>
            </div>
            <label class="sp-field sb-note">
              <span class="sp-label">Note <small>(optional)</small></span>
              <input v-model="cover.note" class="sp-input" maxlength="255" placeholder="e.g. Called in sick at 7 AM" />
            </label>

            <h4 class="st-section-title">Who covers</h4>
            <p v-if="!coverOptions.length" class="sp-hint">Nobody else is available that day.</p>
            <ul class="sb-pick-list">
              <li v-for="p in coverOptions" :key="p.id">
                <label class="sb-pick-person" :class="{ on: cover.employeeId === p.id }">
                  <input v-model="cover.employeeId" type="radio" name="cover" :value="p.id" />
                  <span class="sb-av lg" :style="{ background: personColor(p.name) }">{{ initialsOf(p.name) }}</span>
                  <span class="sb-person-text"><b>{{ p.name }}</b><small>{{ p.groupLabel }} · {{ personNote(p) }}</small></span>
                  <span v-if="p.weekHours" class="sb-hours" :class="{ over: p.weekHours > board.max_week_hours }">{{ p.weekHours }}h</span>
                </label>
              </li>
            </ul>
          </div>
          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="cover = null">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="!cover.employeeId || busy">{{ busy ? "Saving…" : "Cover the shift" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>

    <!-- Shift (template) editor -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="tpl" class="sp-backdrop" @click="tpl = null"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="tpl" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="sb-tpl" novalidate @submit.prevent="saveTemplate">
          <div class="sp-drawer-head">
            <div>
              <h3 id="sb-tpl">{{ tpl.id ? `Edit ${tpl.original}` : "New shift" }}</h3>
              <p>A block of hours people work. After saving, link people to it.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="tpl = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span class="sp-label">Name</span>
                <input v-model="tpl.name" class="sp-input" maxlength="60" placeholder="e.g. Morning counter" :class="{ invalid: tplErrors.name }" />
                <small v-if="tplErrors.name" class="sp-error">{{ tplErrors.name }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Starts</span>
                <input v-model="tpl.start_time" type="time" class="sp-input" :class="{ invalid: tplErrors.start_time }" />
              </label>
              <label class="sp-field">
                <span class="sp-label">Ends</span>
                <input v-model="tpl.end_time" type="time" class="sp-input" :class="{ invalid: tplErrors.end_time }" />
                <small v-if="tplErrors.end_time" class="sp-error">{{ tplErrors.end_time }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">People needed</span>
                <input v-model.number="tpl.headcount" type="number" min="1" max="50" class="sp-input" />
              </label>
              <div class="sp-field">
                <span class="sp-label">Color</span>
                <div class="sb-swatches" role="radiogroup" aria-label="Color">
                  <label v-for="c in ROLE_COLORS" :key="c" class="sb-swatch" :class="{ on: tpl.color === c }" :style="{ background: c }">
                    <input v-model="tpl.color" type="radio" name="color" :value="c" :aria-label="c" />
                  </label>
                </div>
              </div>
              <div class="sp-field sp-span-all">
                <span class="sp-label">Runs on</span>
                <div class="sb-days" role="group" aria-label="Runs on">
                  <label v-for="d in WEEKDAYS" :key="d" class="sb-day" :class="{ on: tpl.days.includes(d) }">
                    <input v-model="tpl.days" type="checkbox" :value="d" />{{ d.slice(0, 3) }}
                  </label>
                </div>
                <small v-if="tplErrors.days" class="sp-error">{{ tplErrors.days }}</small>
              </div>
              <label class="sp-field sp-span-all">
                <span class="sp-label">Note <small>(optional)</small></span>
                <input v-model="tpl.note" class="sp-input" maxlength="255" placeholder="e.g. Opens the shop and receives deliveries" />
              </label>
              <label v-if="tpl.id" class="sb-check sp-span-all">
                <input v-model="tpl.update_future" type="checkbox" />
                Also change the hours for upcoming days people are already on this shift
              </label>
            </div>
            <p v-if="tplError" class="sp-error">{{ tplError }}</p>
            <button v-if="tpl.id && perms.delete" type="button" class="sp-btn sp-btn-ghost sp-btn-sm sb-delete" @click="deleteTemplate">
              <span v-html="ICONS.trash"></span>{{ confirmDelete ? "Yes, remove it and clear its upcoming days" : "Remove this shift" }}
            </button>
          </div>
          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="tpl = null">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="busy">{{ busy ? "Saving…" : tpl.id ? "Save" : "Create shift" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>

    <!-- Link people -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="linking" class="sp-backdrop" @click="linking = null"></div></Transition>
      <Transition name="sp-slide">
        <form v-if="linking" class="sp-drawer" role="dialog" aria-modal="true" aria-labelledby="sb-link" novalidate @submit.prevent="saveLink">
          <div class="sp-drawer-head">
            <div>
              <h3 id="sb-link">Link people to {{ linking.template.name }}</h3>
              <p>{{ time12(linking.template.start) }}–{{ time12(linking.template.end) }} · {{ linking.template.days.map((d) => d.slice(0, 3)).join(", ") }}. Rest days, leave and days they're already scheduled are skipped.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="linking = null"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="sp-drawer-body">
            <h4 class="st-section-title first">For</h4>
            <div class="sp-seg sb-ranges" role="tablist" aria-label="For">
              <button v-for="r in LINK_RANGES" :key="r.key" type="button" role="tab" :class="{ on: linking.range === r.key }" @click="setLinkRange(r.key)">{{ r.label }}</button>
            </div>
            <div class="sp-grid-2 sb-link-dates">
              <label class="sp-field"><span class="sp-label">From</span><input v-model="linking.from" type="date" class="sp-input" @change="linking.range = 'custom'" /></label>
              <label class="sp-field"><span class="sp-label">To</span><input v-model="linking.to" type="date" class="sp-input" :min="linking.from" @change="linking.range = 'custom'" /></label>
            </div>

            <h4 class="st-section-title">People <small>({{ linking.ids.length }} chosen)</small></h4>
            <label class="sp-search sb-search">
              <span v-html="ICONS.search"></span>
              <input v-model="linking.query" class="sp-input" type="search" placeholder="Find someone" aria-label="Find someone" />
            </label>
            <ul class="sb-pick-list">
              <li v-for="p in linkPeople" :key="p.id">
                <label class="sb-pick-person" :class="{ on: linking.ids.includes(p.id) }">
                  <input v-model="linking.ids" type="checkbox" :value="p.id" />
                  <span class="sb-av lg" :style="{ background: personColor(p.name) }">{{ initialsOf(p.name) }}</span>
                  <span class="sb-person-text"><b>{{ p.name }}</b><small>{{ p.position || "—" }} · rests {{ p.rest_days.length ? p.rest_days.map((d) => d.slice(0, 3)).join(", ") : "never" }}</small></span>
                </label>
              </li>
            </ul>

            <div v-if="linking.result" class="sb-result">
              <b>{{ linking.result.message }}</b>
              <ul v-if="linking.result.skipped.length">
                <li v-for="(s, i) in linking.result.skipped.slice(0, 12)" :key="i">{{ s.employee }} · {{ shortDay(s.date) }} — {{ s.why }}</li>
                <li v-if="linking.result.skipped.length > 12">and {{ linking.result.skipped.length - 12 }} more</li>
              </ul>
            </div>
            <p v-if="linkError" class="sp-error">{{ linkError }}</p>
          </div>
          <div class="sp-drawer-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="linking = null">{{ linking.result ? "Done" : "Cancel" }}</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="!linking.ids.length || busy">{{ busy ? "Scheduling…" : "Schedule them" }}</button>
          </div>
        </form>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { toast } from "@/utils/snackbar";
import { SHOP_ICONS as ICONS } from "../../vendor/shop/shopIcons";
import { ROLE_COLORS, personColor } from "../../vendor/staff/staffUi";
import { initialsOf } from "../hrUi";
import {
  REASONS, REASON_LABEL, WEEKDAYS, addDays, availability, cells, coverage, rangeFor, time12, weekStart, weekdayName, ymd,
} from "./scheduleMath";

const props = defineProps({
  // { board, assign, replace, undoReplace, remove, createTemplate, updateTemplate, deleteTemplate, link, copyWeek }
  api: { type: Object, required: true },
  perms: { type: Object, default: () => ({ view: true, create: true, edit: true, delete: true }) },
});

const CONFIRM_VERB = { move: "Yes, move them", allow_rest_day: "Yes, schedule them", off_day: "Yes, add it" };
const LINK_RANGES = [
  { key: "week", label: "This week" },
  { key: "next", label: "Next week" },
  { key: "month", label: "This month" },
  { key: "next_month", label: "Next month" },
  { key: "custom", label: "Custom" },
];

const today = ymd(new Date());
const view = ref("week");
const anchor = ref(today);
const board = ref(null);
const error = ref("");
const busy = ref(false);
const selectedDay = ref(today);
const query = ref("");
const dragging = ref(null);
const dropTarget = ref(null);
const picked = ref(null);
const menuId = ref(null);
const conflict = ref(null);
const hours = ref(null);
const cover = ref(null);
const tpl = ref(null);
const tplErrors = reactive({});
const tplError = ref("");
const confirmDelete = ref(false);
const linking = ref(null);
const linkError = ref("");

const cellMap = computed(() => cells(board.value?.assignments ?? []));
const rows = computed(() => {
  if (!board.value) return [];
  return [
    ...board.value.templates.map((t) => ({
      key: `t${t.id}`, template: t, name: t.name, color: t.color,
      sub: `${time12(t.start)}–${time12(t.end)} · needs ${t.headcount}`,
    })),
    { key: "custom", name: "Other hours", sub: "Custom times for one day" },
    { key: "off", name: "Day off", sub: "Not working that day" },
  ];
});
const groups = computed(() => (board.value && selectedDay.value ? availability(board.value.staff, selectedDay.value, query.value) : []));
const rangeLabel = computed(() => {
  if (!board.value) return "";
  if (view.value === "month") return new Date(`${anchor.value}T00:00:00`).toLocaleDateString("en-PH", { month: "long", year: "numeric" });
  return `${shortDay(board.value.from)} – ${shortDay(board.value.to)}`;
});
const coverOptions = computed(() => {
  if (!cover.value || !board.value) return [];
  const date = cover.value.shift.date;
  return availability(board.value.staff, date)
    .filter((g) => g.draggable)
    .flatMap((g) => g.people.map((p) => ({ ...p, groupLabel: g.label })))
    .filter((p) => p.id !== cover.value.shift.employee_id);
});
const linkPeople = computed(() => {
  const q = (linking.value?.query ?? "").trim().toLowerCase();
  return (board.value?.staff ?? []).filter((p) => !q || [p.name, p.position].some((v) => v && v.toLowerCase().includes(q)));
});

const first = (name) => String(name).split(" ")[0];
const dayShort = (d) => new Date(`${d}T00:00:00`).toLocaleDateString("en-PH", { weekday: "short" });
const dayNum = (d) => Number(d.slice(8));
const shortDay = (d) => new Date(`${d}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric" });
const longDay = (d) => new Date(`${d}T00:00:00`).toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric" });
const cov = (row, d) => coverage(row.template, cellMap.value[`${row.key}|${d}`], d);
const coverTitle = (row, d) => {
  const c = cov(row, d);
  return c.state === "short" ? `${c.short} more needed` : c.state === "over" ? "More people than needed" : c.state === "ok" ? "Fully staffed" : "This shift doesn't run today";
};
const coveredOn = (d) => (board.value?.assignments ?? []).filter((a) => a.date === d && a.status === "replaced").length;
const shiftName = (a) => (a.template_id ? board.value.templates.find((t) => t.id === a.template_id)?.name ?? a.label : a.label || "Custom hours");
const chipLabel = (a) => `${a.employee}${a.status === "replaced" ? `, covered by ${a.replaced_by}` : ""}${a.covers ? `, covering ${a.covers}` : ""}`;

function personNote(p) {
  const d = p.day;
  switch (d?.status) {
    case "usual": return `Usually ${time12(d.start)}–${time12(d.end)}`;
    case "shift": return `On ${board.value.templates.find((t) => t.id === d.template_id)?.name ?? "a shift"}`;
    case "custom": return `${time12(d.start)}–${time12(d.end)}`;
    case "rest": return "Rest day";
    case "off": return "Day off";
    case "leave": return "Approved leave";
    case "replaced": return "Someone covered their shift";
    default: return p.position || "No hours set";
  }
}

// ── Loading & navigation ──

async function load() {
  error.value = "";
  const { from, to } = rangeFor(view.value, anchor.value);
  try {
    board.value = await props.api.board(from, to);
    if (!board.value.days.includes(selectedDay.value)) {
      selectedDay.value = board.value.days.includes(today) ? today : board.value.days[0];
    }
  } catch (e) {
    error.value = message(e, "Couldn't load the schedule.");
  }
}

function setView(v) {
  view.value = v;
  board.value = null;
  load();
}

function shift(n) {
  if (view.value === "week") anchor.value = addDays(anchor.value, n * 7);
  else {
    const d = new Date(`${anchor.value}T00:00:00`);
    anchor.value = ymd(new Date(d.getFullYear(), d.getMonth() + n, 1));
  }
  load();
}

function goToday() {
  anchor.value = today;
  selectedDay.value = today;
  load();
}

function openDay(d) {
  anchor.value = d;
  selectedDay.value = d;
  setView("week");
}

function moveDay(n) {
  const next = addDays(selectedDay.value, n);
  selectedDay.value = next;
  if (!board.value.days.includes(next)) {
    anchor.value = next;
    load();
  }
}

// ── Drag, drop and tap ──

function startDrag(e, payload) {
  menuId.value = null;
  dragging.value = payload;
  e.dataTransfer.effectAllowed = "move";
  e.dataTransfer.setData("text/plain", JSON.stringify(payload));
}

function endDrag() {
  dragging.value = null;
  dropTarget.value = null;
}

function readDrop(e) {
  try {
    return JSON.parse(e.dataTransfer.getData("text/plain"));
  } catch (_) {
    return dragging.value;
  }
}

function onDrop(e, row, date) {
  const payload = readDrop(e);
  endDrag();
  if (payload) place(payload, row, date);
}

function onDropChip(e, target) {
  const payload = readDrop(e);
  endDrag();
  if (!payload || payload.employeeId === target.employee_id) return;
  if (target.status === "replaced" || target.kind === "off") return;
  if (payload.type === "chip" && payload.date !== target.date) return toast.info("To cover a shift on another day, drag from Staff availability.");
  openReplace(target, payload.employeeId);
}

function togglePick(p) {
  picked.value = picked.value?.employeeId === p.id ? null : { type: "person", employeeId: p.id, name: p.name };
}

function placePicked(row, date) {
  const p = picked.value;
  picked.value = null;
  place(p, row, date);
}

function pickOnChip(a) {
  const p = picked.value;
  picked.value = null;
  if (p.employeeId !== a.employee_id && a.status !== "replaced" && a.kind !== "off") openReplace(a, p.employeeId);
}

function place(payload, row, date) {
  if (!props.perms.create) return;
  if (payload.type === "chip" && payload.date !== date) {
    toast.info("Drag within the same day to move someone. To add another day, drag them from Staff availability.");
    return;
  }
  const confirm = payload.type === "chip" ? ["move"] : [];
  if (row.key === "custom") {
    hours.value = { employeeId: payload.employeeId, name: payload.name, date, start: "08:00", end: "17:00", label: "", confirm };
    return;
  }
  const body = row.key === "off" ? { day_off: true } : { template_id: row.template.id };
  run(() => props.api.assign({ employee_id: payload.employeeId, date, ...body, confirm }), confirm, (c) => props.api.assign({ employee_id: payload.employeeId, date, ...body, confirm: c }));
}

function saveHours() {
  const h = hours.value;
  hours.value = null;
  const body = { employee_id: h.employeeId, date: h.date, start: h.start, end: h.end, label: h.label || null };
  run(() => props.api.assign({ ...body, confirm: h.confirm }), h.confirm, (c) => props.api.assign({ ...body, confirm: c }));
}

// ── Chip actions ──

function openReplace(a, employeeId = null) {
  menuId.value = null;
  if (!props.perms.edit) return toast.info("Your role can't change who covers a shift.");
  cover.value = { shift: a, employeeId, reason: "no_show", note: "" };
}

function saveCover() {
  const c = cover.value;
  const body = { employee_id: c.employeeId, reason: c.reason, note: c.note || null };
  cover.value = null;
  run(() => props.api.replace(c.shift.id, { ...body, confirm: [] }), [], (confirm) => props.api.replace(c.shift.id, { ...body, confirm }));
}

function dayOff(a) {
  menuId.value = null;
  run(() => props.api.assign({ employee_id: a.employee_id, date: a.date, day_off: true, confirm: ["move"] }), ["move"]);
}

function removeShift(a) {
  menuId.value = null;
  run(() => props.api.remove(a.id), []);
}

function undoCover(a) {
  menuId.value = null;
  run(() => props.api.undoReplace(a.id), []);
}

async function copyWeek() {
  await run(() => props.api.copyWeek(weekStart(anchor.value)), []);
}

/**
 * Do a change; on a confirmable clash (409) ask, then retry with that confirmation added.
 * retry(confirms) re-sends the same request with the confirmations so far.
 */
async function run(action, confirms, retry = null) {
  busy.value = true;
  try {
    const res = await action();
    if (res?.message) toast.success(res.message);
    await load();
  } catch (e) {
    const data = e?.response?.data;
    if (e?.response?.status === 409 && data?.code === "schedule_conflict" && retry) {
      conflict.value = { message: data.message, confirm: data.confirm, retry, confirms: [...confirms, data.confirm] };
    } else {
      toast.error(message(e, "Couldn't change the schedule."));
    }
  } finally {
    busy.value = false;
  }
}

function confirmConflict() {
  const c = conflict.value;
  conflict.value = null;
  run(() => c.retry(c.confirms), c.confirms, c.retry);
}

// ── Shift templates ──

function openTemplate(t = null) {
  Object.keys(tplErrors).forEach((k) => delete tplErrors[k]);
  tplError.value = "";
  confirmDelete.value = false;
  tpl.value = t
    ? { id: t.id, original: t.name, name: t.name, start_time: t.start, end_time: t.end, headcount: t.headcount, color: t.color, days: [...t.days], note: t.note ?? "", update_future: true }
    : { id: null, name: "", start_time: "08:00", end_time: "17:00", headcount: 1, color: ROLE_COLORS[(board.value?.templates.length ?? 0) % ROLE_COLORS.length], days: WEEKDAYS.slice(0, 6), note: "" };
}

async function saveTemplate() {
  const t = tpl.value;
  Object.keys(tplErrors).forEach((k) => delete tplErrors[k]);
  if (!t.name.trim()) tplErrors.name = "Name the shift.";
  if (t.end_time <= t.start_time) tplErrors.end_time = "It must end after it starts.";
  if (!t.days.length) tplErrors.days = "Pick at least one day.";
  if (Object.keys(tplErrors).length) return;
  busy.value = true;
  tplError.value = "";
  const body = { name: t.name.trim(), start_time: t.start_time, end_time: t.end_time, headcount: t.headcount, color: t.color, days: t.days, note: t.note || null, update_future: !!t.update_future };
  try {
    const res = t.id ? await props.api.updateTemplate(t.id, body) : await props.api.createTemplate(body);
    toast.success(res.message);
    tpl.value = null;
    await load();
    if (!t.id && res.data && props.perms.create) openLink(res.data);
  } catch (e) {
    for (const [k, v] of Object.entries(e?.response?.data?.errors ?? {})) tplErrors[k] = [].concat(v)[0];
    tplError.value = Object.keys(tplErrors).length ? "" : message(e, "Couldn't save the shift.");
  } finally {
    busy.value = false;
  }
}

async function deleteTemplate() {
  if (!confirmDelete.value) {
    confirmDelete.value = true;
    return;
  }
  const id = tpl.value.id;
  tpl.value = null;
  await run(() => props.api.deleteTemplate(id), []);
}

// ── Link people ──

function openLink(template) {
  linkError.value = "";
  linking.value = { template, ids: [], query: "", range: "week", from: "", to: "", result: null };
  setLinkRange("week");
}

function setLinkRange(key) {
  const l = linking.value;
  l.range = key;
  const base = view.value === "week" ? weekStart(anchor.value) : weekStart(today);
  const now = new Date(`${today}T00:00:00`);
  if (key === "week") [l.from, l.to] = [base, addDays(base, 6)];
  if (key === "next") [l.from, l.to] = [addDays(base, 7), addDays(base, 13)];
  if (key === "month") [l.from, l.to] = [ymd(new Date(now.getFullYear(), now.getMonth(), 1)), ymd(new Date(now.getFullYear(), now.getMonth() + 1, 0))];
  if (key === "next_month") [l.from, l.to] = [ymd(new Date(now.getFullYear(), now.getMonth() + 1, 1)), ymd(new Date(now.getFullYear(), now.getMonth() + 2, 0))];
}

async function saveLink() {
  const l = linking.value;
  busy.value = true;
  linkError.value = "";
  try {
    const res = await props.api.link(l.template.id, { employee_ids: l.ids, from: l.from, to: l.to });
    l.result = { message: res.message, skipped: res.data?.skipped ?? [] };
    toast.success(res.message);
    await load();
  } catch (e) {
    linkError.value = message(e, "Couldn't schedule them.");
  } finally {
    busy.value = false;
  }
}

function message(e, fallback) {
  const errors = e?.response?.data?.errors;
  return (errors && Object.values(errors).flat()[0]) || e?.response?.data?.message || fallback;
}

const closeMenu = (e) => {
  if (!e.target.closest?.(".sb-chip")) menuId.value = null;
};
const onKey = (e) => {
  if (e.key === "Escape") {
    menuId.value = null;
    picked.value = null;
  }
};

onMounted(() => {
  document.addEventListener("click", closeMenu);
  document.addEventListener("keydown", onKey);
  load();
});
onBeforeUnmount(() => {
  document.removeEventListener("click", closeMenu);
  document.removeEventListener("keydown", onKey);
});
</script>

<style scoped>
.sb-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
}
.sb-nav {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1 1 auto;
}
.sb-range {
  margin-left: 6px;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-actions {
  display: flex;
  gap: 8px;
}
.sb-pick {
  margin-bottom: 12px;
}
.sb-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}
.sb-board {
  padding: 14px;
  min-width: 0;
}
.sb-sk {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sb-sk-row {
  height: 74px;
  border-radius: 12px;
}
.sb-scroll {
  overflow-x: auto;
}
.sb-grid {
  display: grid;
  grid-template-columns: 190px repeat(7, minmax(118px, 1fr));
  min-width: 1020px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  overflow: hidden;
}
.sb-corner,
.sb-dayhead {
  padding: 10px;
  border-bottom: 1px solid var(--v-border);
  background: var(--v-subtle);
  font-size: 12px;
  color: var(--v-muted);
}
.sb-corner {
  font-weight: 600;
  display: flex;
  align-items: flex-end;
}
.sb-dayhead {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border: 0;
  border-bottom: 1px solid var(--v-border);
  border-left: 1px solid var(--v-border);
  font-family: inherit;
  cursor: pointer;
}
.sb-dayhead b {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  font-size: 14px;
  color: var(--v-text);
}
.sb-dayhead.today b {
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.sb-dayhead.on {
  background: var(--bc-accent-soft);
}
.sb-rowhead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px;
  border-bottom: 1px solid var(--v-border);
  background: var(--v-surface);
}
.sb-rowhead i {
  flex: none;
  width: 4px;
  align-self: stretch;
  border-radius: 4px;
  background: var(--row);
}
.sb-rowhead div {
  flex: 1;
  min-width: 0;
}
.sb-rowhead strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.sb-rowhead small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.sb-mini {
  width: 26px;
  height: 26px;
}
.sb-mini :deep(svg) {
  width: 13px;
  height: 13px;
}
.sb-cell {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-height: 74px;
  padding: 8px 7px;
  border-bottom: 1px solid var(--v-border);
  border-left: 1px solid var(--v-border);
  background: var(--v-surface);
  transition: background 0.12s, box-shadow 0.12s;
}
.sb-cell.on {
  background: color-mix(in srgb, var(--bc-accent) 5%, var(--v-surface));
}
.sb-cell.past {
  background: var(--v-subtle);
}
.sb-cell.idle {
  background-image: repeating-linear-gradient(135deg, transparent 0 8px, color-mix(in srgb, var(--v-border) 55%, transparent) 8px 9px);
}
.sb-cell.drop {
  box-shadow: inset 0 0 0 2px var(--bc-accent);
  background: var(--bc-accent-soft);
}
.sb-cell.pickable {
  cursor: copy;
}
.sb-cell.pickable:hover {
  box-shadow: inset 0 0 0 2px var(--bc-accent);
}
.sb-drop-hint {
  margin-top: auto;
  font-size: 11px;
  color: var(--v-faint);
  text-align: center;
}
.sb-cover {
  align-self: flex-end;
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
}
.sb-cover.ok { background: var(--v-green-soft); color: var(--v-green-text); }
.sb-cover.short { background: var(--v-red-soft); color: var(--v-red-text); }
.sb-cover.over { background: var(--v-blue-soft); color: var(--v-blue-text); }
.sb-cover.idle { background: none; color: var(--v-faint); font-weight: 400; }
.sb-chip {
  position: relative;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 6px;
  border: 1px solid var(--v-border);
  border-left: 3px solid var(--row, var(--v-border-strong));
  border-radius: 9px;
  background: var(--v-raised);
  cursor: grab;
  outline: none;
  transition: box-shadow 0.12s, transform 0.12s;
}
.sb-chip:hover,
.sb-chip:focus-visible,
.sb-chip.menu {
  box-shadow: 0 3px 10px var(--v-shadow);
}
.sb-chip:focus-visible {
  box-shadow: 0 0 0 2px var(--bc-accent);
}
.sb-chip:active {
  cursor: grabbing;
}
.sb-chip.replaced {
  opacity: 0.7;
  cursor: pointer;
  background: var(--v-subtle);
}
.sb-chip.replaced b {
  text-decoration: line-through;
}
.sb-chip.cover {
  border-style: dashed;
  border-left-style: solid;
}
.sb-chip-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.2;
}
/* Day columns are narrow: names wrap to two lines rather than being cut off */
.sb-chip-text b,
.sb-chip-text small {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  overflow-wrap: anywhere;
}
.sb-chip-text b {
  font-size: 12px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-chip-text small {
  font-size: 10.5px;
  color: var(--v-muted);
}
.sb-av {
  flex: none;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  overflow: hidden;
  border-radius: 7px;
  color: #fff;
  font-size: 9.5px;
  font-weight: 700;
}
.sb-av.lg {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  font-size: 11.5px;
}
.sb-av img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.sb-menu {
  position: absolute;
  left: 0;
  top: calc(100% + 4px);
  z-index: 30;
  display: flex;
  flex-direction: column;
  min-width: 220px;
  padding: 6px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-raised);
  box-shadow: 0 12px 32px var(--v-shadow);
  cursor: default;
}
.sb-menu button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-text);
  font: inherit;
  font-size: 12.5px;
  text-align: left;
  white-space: nowrap;
  cursor: pointer;
}
.sb-menu button:hover {
  background: var(--v-subtle);
}
.sb-menu button :deep(svg) {
  width: 14px;
  height: 14px;
  color: var(--v-muted);
}
.sb-menu button.danger,
.sb-menu button.danger :deep(svg) {
  color: var(--v-red-text);
}
.sb-menu-note {
  padding: 6px 10px 2px;
  font-size: 11px;
  color: var(--v-faint);
}

/* Month */
.sb-month {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 6px;
}
.sb-mhead {
  padding: 4px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--v-muted);
  text-align: center;
}
.sb-mday {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 3px;
  min-height: 96px;
  padding: 7px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}
.sb-mday:hover {
  border-color: var(--bc-accent);
}
.sb-mday.out {
  opacity: 0.5;
}
.sb-mday b {
  font-size: 13px;
  color: var(--v-text);
}
.sb-mday.today b {
  color: var(--bc-accent);
}
.sb-mline {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
  font-size: 10.5px;
  color: var(--v-text-2);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sb-mline i {
  flex: none;
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.sb-mline.short {
  color: var(--v-red-text);
  font-weight: 600;
}
.sb-mline.covered {
  color: var(--v-amber-text);
}

/* Availability */
.sb-side {
  position: sticky;
  top: 16px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  padding: 16px;
}
.sb-side-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}
.sb-side-head h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-side-head p {
  margin: 2px 0 0;
  font-size: 12.5px;
  color: var(--v-muted);
}
.sb-side-nav {
  display: flex;
  gap: 4px;
}
.sb-search {
  margin: 12px 0 6px;
}
.sb-side-tip {
  margin: 0 0 8px;
}
.sb-side-sk {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 10px;
}
.sb-group {
  margin-top: 14px;
}
.sb-group h3 {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-group h3 span {
  padding: 0 7px;
  border-radius: 999px;
  background: var(--v-subtle);
  font-size: 11px;
  color: var(--v-muted);
}
.sb-group > small {
  display: block;
  margin-bottom: 6px;
  font-size: 11px;
  color: var(--v-faint);
}
.sb-group ul,
.sb-pick-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.sb-person,
.sb-pick-person {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 8px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-raised);
  cursor: grab;
  outline: none;
  transition: border-color 0.12s, box-shadow 0.12s;
}
.sb-person:hover,
.sb-person:focus-visible {
  border-color: var(--bc-accent);
}
.sb-person.picked {
  border-color: var(--bc-accent);
  box-shadow: 0 0 0 2px var(--bc-accent);
}
.sb-person.locked {
  cursor: default;
  opacity: 0.6;
}
.sb-pick-person {
  cursor: pointer;
}
.sb-pick-person.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.sb-pick-person input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.sb-pick-person:has(input:focus-visible) {
  box-shadow: 0 0 0 2px var(--bc-accent);
}
.sb-person-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}
.sb-person-text b {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sb-person-text small {
  font-size: 11px;
  color: var(--v-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sb-hours {
  flex: none;
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--v-subtle);
  font-size: 10.5px;
  font-weight: 600;
  color: var(--v-muted);
}
.sb-hours.over {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}

/* Drawers */
.st-section-title.first {
  margin-top: 0;
}
.st-section-title small {
  font-weight: 400;
  text-transform: none;
  letter-spacing: 0;
}
.sb-reasons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.sb-reason {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 10px 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  cursor: pointer;
}
.sb-reason.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  box-shadow: 0 0 0 1px var(--bc-accent);
}
.sb-reason input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.sb-reason:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.sb-reason b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.sb-reason small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.sb-note {
  margin-top: 12px;
}
.sb-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.sb-swatch {
  position: relative;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  cursor: pointer;
}
.sb-swatch.on {
  box-shadow: 0 0 0 2px var(--v-surface), 0 0 0 4px var(--v-text);
}
.sb-swatch input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.sb-swatch:has(input:focus-visible) {
  box-shadow: 0 0 0 2px var(--v-surface), 0 0 0 4px var(--bc-accent);
}
.sb-days {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.sb-day {
  position: relative;
  padding: 7px 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-muted);
  cursor: pointer;
}
.sb-day.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--v-text);
}
.sb-day input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.sb-day:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent) 30%, transparent);
}
.sb-check {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  color: var(--v-text-2);
}
.sb-check input {
  margin-top: 3px;
  accent-color: var(--bc-accent);
}
.sb-delete {
  margin-top: 18px;
  color: var(--v-red-text);
}
.sb-ranges {
  display: flex;
  flex-wrap: wrap;
}
.sb-link-dates {
  margin-top: 10px;
}
.sb-result {
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-text-2);
}
.sb-result ul {
  margin: 6px 0 0;
  padding-left: 18px;
}
@media (max-width: 1100px) {
  /* minmax(0, …) so the wide week grid scrolls inside its card instead of widening the page */
  .sb-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .sb-nav {
    flex-wrap: wrap;
  }
  .sb-side {
    position: static;
    max-height: none;
    order: -1;
  }
}
@media (max-width: 560px) {
  .sb-reasons {
    grid-template-columns: 1fr;
  }
  .sb-month {
    gap: 3px;
  }
  .sb-mday {
    min-height: 64px;
    padding: 4px;
  }
  .sb-mline {
    display: none;
  }
}
</style>
