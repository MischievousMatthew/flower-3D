<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
          </button>
          <div>
            <h1 class="page-title">{{ SECTIONS[sectionKey].title }}</h1>
            <p class="page-subtitle">{{ SECTIONS[sectionKey].subtitle }}</p>
          </div>
        </div>
        <span v-if="readOnly" class="readonly-chip">View only · your role can't change these</span>
      </header>

      <div class="page-body">
        <nav v-if="visibleTabs.length > 1" class="tabs" role="tablist">
          <button v-for="t in visibleTabs" :key="t.key" type="button" role="tab" class="tab" :class="{ active: tab === t.key }" :aria-selected="tab === t.key" @click="setTab(t.key)">
            {{ t.label }}
            <span v-if="t.key === 'health' && health && health.overall !== 'ok'" class="dot" :class="health.overall"></span>
          </button>
        </nav>

        <!-- ═══ Administrators ═══ -->
        <section v-if="tab === 'admins'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Administrators</h2>
              <p class="panel-sub">Everyone who can sign in to this admin panel, and what their role allows.</p>
            </div>
            <button v-if="canManage" type="button" class="primary-btn" @click="openAdminDialog()">+ Add administrator</button>
          </div>
          <div v-if="loading.admins && !admins.length" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-row"></span></div>
          <div v-else class="table-scroll">
            <table class="data-table">
              <thead>
                <tr><th>Administrator</th><th>Role</th><th>Status</th><th>Last sign-in</th><th class="num">Actions (30d)</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="a in admins" :key="a.id" :class="{ off: !a.active }">
                  <td>
                    <div class="person">
                      <span class="avatar">{{ initials(a.name) }}</span>
                      <div>
                        <span class="strong">{{ a.name }} <span v-if="a.id === access.id" class="you">You</span></span>
                        <span class="sub">@{{ a.username }} · {{ a.email }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <select v-if="canManage && a.id !== access.id" :value="a.role_id" class="input small" @change="changeRole(a, $event.target.value)">
                      <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                    <span v-else class="pill" :class="{ super: a.is_super_admin }">{{ a.role || "No role" }}</span>
                  </td>
                  <td><span class="pill" :class="a.active ? 'ok' : 'bad'">{{ a.active ? "Active" : "Turned off" }}</span></td>
                  <td class="muted">{{ a.last_login_at ? timeAgo(a.last_login_at) : "Never" }}</td>
                  <td class="num">{{ a.actions_30d }}</td>
                  <td class="right">
                    <div v-if="canManage" class="row-actions">
                      <button type="button" class="mini-btn" @click="openPasswordDialog(a)">{{ a.id === access.id ? "Change my password" : "Set password" }}</button>
                      <template v-if="a.id !== access.id">
                        <button v-if="a.active" type="button" class="mini-btn danger" @click="setActive(a, false)">Turn off</button>
                        <button v-else type="button" class="mini-btn ok" @click="setActive(a, true)">Turn on</button>
                      </template>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ═══ Roles ═══ -->
        <section v-else-if="tab === 'roles'">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Roles &amp; permissions</h2>
              <p class="panel-sub">A role is a set of permissions. Every admin page and action checks them on the server.</p>
            </div>
            <button v-if="canManage" type="button" class="primary-btn" @click="openRoleDialog()">+ New role</button>
          </div>
          <div v-if="loading.roles && !roles.length" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-row tall"></span></div>
          <div v-else class="role-grid">
            <article v-for="r in roles" :key="r.id" class="panel role-card" :class="{ system: r.is_system }">
              <div class="role-head">
                <div>
                  <h3>{{ r.name }} <span v-if="r.is_system" class="pill super">Built in</span></h3>
                  <p class="sub">{{ r.description || "No description" }}</p>
                </div>
                <span class="count">{{ r.admins }} admin{{ r.admins === 1 ? "" : "s" }}</span>
              </div>
              <div class="perm-groups">
                <div v-for="(perms, group) in catalog" :key="group" class="perm-group">
                  <span class="group-label">{{ group }}</span>
                  <span v-for="(label, key) in perms" :key="key" class="perm" :class="{ on: r.permissions.includes(key) }">
                    {{ r.permissions.includes(key) ? "✓" : "–" }} {{ label }}
                  </span>
                </div>
              </div>
              <div v-if="canManage && !r.is_system" class="role-actions">
                <button type="button" class="mini-btn" @click="openRoleDialog(r)">Edit</button>
                <button type="button" class="mini-btn danger" :disabled="r.admins > 0" :title="r.admins ? 'Move its administrators first' : ''" @click="deleteRole(r)">Delete</button>
              </div>
            </article>
          </div>
        </section>

        <!-- ═══ Audit log ═══ -->
        <section v-else-if="tab === 'audit'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Audit log</h2>
              <p class="panel-sub">Every change an admin made, and every export, including ones that were refused.</p>
            </div>
          </div>
          <div class="toolbar">
            <input v-model="auditFilters.search" type="search" class="input grow" placeholder="Search by admin or by who / what was changed" />
            <select v-model="auditFilters.admin_id" class="input"><option value="">All admins</option><option v-for="a in auditAdmins" :key="a.id" :value="a.id">{{ a.name }}</option></select>
            <select v-model="auditFilters.area" class="input"><option value="">All areas</option><option v-for="(label, key) in AREAS" :key="key" :value="key">{{ label }}</option></select>
            <select v-model="auditFilters.outcome" class="input"><option value="">Any outcome</option><option value="done">Done</option><option value="refused">Refused / failed</option></select>
            <input v-model="auditFilters.from" type="date" class="input" aria-label="From" />
            <input v-model="auditFilters.to" type="date" class="input" aria-label="To" />
          </div>
          <div v-if="loading.audit && !audit.length" class="sk-list"><span v-for="n in 6" :key="n" class="sk sk-row"></span></div>
          <p v-else-if="!audit.length" class="empty">Nothing recorded for these filters yet.</p>
          <div v-else class="table-scroll" :class="{ dim: loading.audit }">
            <table class="data-table">
              <thead><tr><th>No.</th><th>When</th><th>Admin</th><th>What</th><th>Details</th><th>Result</th><th>From</th></tr></thead>
              <tbody>
                <tr v-for="(l, i) in audit" :key="l.id">
                  <td class="muted mono">{{ rowNumber(auditMeta, i) }}</td>
                  <td class="muted">{{ formatDateTime(l.created_at) }}</td>
                  <td class="strong">{{ l.admin_name || "—" }}</td>
                  <td>
                    <span class="strong">{{ actionLabel(l) }}</span>
                    <span v-if="l.target" class="sub">{{ l.target }}</span>
                    <span v-else-if="l.had_target" class="sub">A record that no longer exists</span>
                  </td>
                  <td>
                    <div class="chips">
                      <span v-for="(v, k) in l.details || {}" :key="k" class="chip">{{ k.replace(/_/g, " ") }}: {{ v }}</span>
                    </div>
                  </td>
                  <td><span class="pill" :class="l.status_code < 400 ? 'ok' : 'bad'">{{ l.status_code < 400 ? "Done" : l.status_code === 403 ? "Not allowed" : `Failed (${l.status_code})` }}</span></td>
                  <td class="muted">{{ l.ip_address || "—" }}<span class="sub">{{ l.device }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="auditMeta.total > auditMeta.per_page" class="pager">
            <span class="sub">{{ auditMeta.total }} entries</span>
            <div class="row-actions">
              <button type="button" class="mini-btn" :disabled="auditMeta.current_page <= 1" @click="fetchAudit(auditMeta.current_page - 1)">Previous</button>
              <span class="sub">{{ auditMeta.current_page }} / {{ auditMeta.last_page }}</span>
              <button type="button" class="mini-btn" :disabled="auditMeta.current_page >= auditMeta.last_page" @click="fetchAudit(auditMeta.current_page + 1)">Next</button>
            </div>
          </div>
        </section>

        <!-- ═══ Sign-ins ═══ -->
        <template v-else-if="tab === 'signins'">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Sign-in monitoring</h2>
              <p class="panel-sub" v-if="signins">After {{ signins.lockout.max_attempts }} wrong passwords an account is locked for {{ signins.lockout.minutes }} minutes (change this in Platform settings).</p>
            </div>
            <select v-model.number="signinHours" class="input"><option :value="1">Last hour</option><option :value="24">Last 24 hours</option><option :value="168">Last 7 days</option><option :value="720">Last 30 days</option></select>
          </div>
          <div v-if="!signins" class="sk-list"><span v-for="n in 3" :key="n" class="sk sk-row tall"></span></div>
          <template v-else>
            <section class="stat-grid">
              <div class="stat" :class="{ hot: signins.failed >= 20 }"><span class="stat-label">Failed sign-ins</span><span class="stat-value">{{ signins.failed }}</span></div>
              <div class="stat"><span class="stat-label">Successful sign-ins</span><span class="stat-value">{{ signins.successful }}</span></div>
              <div class="stat" :class="{ hot: signins.locked_now.length }"><span class="stat-label">Locked right now</span><span class="stat-value">{{ signins.locked_now.length }}</span></div>
              <div class="stat"><span class="stat-label">Why they failed</span>
                <span class="reasons"><span v-for="(n, reason) in signins.by_reason" :key="reason">{{ REASON[reason] ?? reason }} <b>{{ n }}</b></span><span v-if="!Object.keys(signins.by_reason).length" class="sub">No failures</span></span>
              </div>
            </section>
            <section class="split">
              <div class="panel">
                <h3 class="panel-title">Where failures come from</h3>
                <p v-if="!signins.top_ips.length" class="empty small">No failures.</p>
                <ul v-else class="mini-list">
                  <li v-for="ip in signins.top_ips" :key="ip.ip" :class="{ warn: ip.suspicious }">
                    <span class="mono strong">{{ ip.ip }}</span>
                    <span class="sub">{{ ip.failures }} failures · {{ ip.accounts }} account{{ ip.accounts === 1 ? "" : "s" }}</span>
                    <span v-if="ip.suspicious" class="pill bad">Looks like guessing</span>
                  </li>
                </ul>
                <h3 class="panel-title spaced">Most-targeted accounts</h3>
                <p v-if="!signins.top_accounts.length" class="empty small">None.</p>
                <ul v-else class="mini-list">
                  <li v-for="acc in signins.top_accounts" :key="acc.identifier">
                    <span class="mono strong">{{ acc.identifier }}</span>
                    <span class="sub">{{ acc.failures }} failures</span>
                    <span v-if="signins.locked_now.some((l) => l.identifier === acc.identifier)" class="pill bad">Locked</span>
                  </li>
                </ul>
              </div>
              <div class="panel">
                <h3 class="panel-title">Recent admin sign-ins</h3>
                <p v-if="!signins.admin_sign_ins.length" class="empty small">None recorded.</p>
                <ul v-else class="mini-list">
                  <li v-for="(s, i) in signins.admin_sign_ins" :key="i">
                    <span class="strong">{{ s.name }}</span>
                    <span class="sub">{{ s.device }}<template v-if="s.location"> · {{ s.location }}</template> · {{ s.ip }}</span>
                    <span class="sub">{{ formatDateTime(s.at) }}</span>
                  </li>
                </ul>
                <button type="button" class="link-btn" @click="setTab('history')">All sign-ins →</button>
              </div>
            </section>
            <section class="panel">
              <h3 class="panel-title">Failed attempts</h3>
              <p v-if="!signins.recent.length" class="empty small">No failed sign-ins in this period.</p>
              <div v-else class="table-scroll">
                <table class="data-table">
                  <thead><tr><th>When</th><th>Account typed</th><th>Reason</th><th>Who</th><th>From</th></tr></thead>
                  <tbody>
                    <tr v-for="a in signins.recent" :key="a.id">
                      <td class="muted">{{ formatDateTime(a.at) }}</td>
                      <td class="mono">{{ a.identifier }}</td>
                      <td><span class="pill" :class="a.reason === 'locked' ? 'bad' : 'warn'">{{ REASON[a.reason] ?? a.reason }}</span></td>
                      <td class="muted">{{ a.actor_type === "employee" ? "Staff login" : "Account login" }}</td>
                      <td class="muted">{{ a.ip }}<span class="sub">{{ a.device }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </template>
        </template>

        <!-- ═══ Platform settings ═══ -->
        <form v-else-if="tab === 'settings'" class="panel settings" @submit.prevent="saveSettings">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Platform settings</h2>
              <p class="panel-sub">Switches save the moment you flip them. Use Save settings for the message and numbers.</p>
            </div>
          </div>
          <div v-if="!settings" class="sk-list"><span v-for="n in 4" :key="n" class="sk sk-row"></span></div>
          <template v-else>
            <fieldset :disabled="!canManage">
              <h3 class="set-group">Sign-ups &amp; ordering</h3>
              <label class="toggle-row">
                <div><span class="strong">Customer sign-ups</span><span class="sub">When off, new customers can't create accounts. Existing customers still sign in.</span></div>
                <input v-model="settings.registrations_open" type="checkbox" class="switch" :disabled="switchSaving" @change="saveSwitch('registrations_open')" />
              </label>
              <label class="toggle-row">
                <div><span class="strong">Vendor applications</span><span class="sub">When off, the vendor registration form is refused.</span></div>
                <input v-model="settings.vendor_applications_open" type="checkbox" class="switch" :disabled="switchSaving" @change="saveSwitch('vendor_applications_open')" />
              </label>
              <label class="toggle-row" :class="{ alarm: settings.checkout_paused }">
                <div><span class="strong">Pause all checkout</span><span class="sub">Stops every new order across all stores, e.g. during an incident. Payments already made aren't affected.</span></div>
                <input v-model="settings.checkout_paused" type="checkbox" class="switch danger" :disabled="switchSaving" @change="saveSwitch('checkout_paused')" />
              </label>
              <label class="field">
                <span>Message customers see while checkout is paused</span>
                <input v-model="settings.checkout_pause_message" class="input" maxlength="200" />
              </label>

              <h3 class="set-group">Sign-in protection</h3>
              <div class="two">
                <label class="field"><span>Wrong passwords before a lock</span><input v-model.number="settings.login_max_attempts" type="number" min="3" max="20" class="input" /></label>
                <label class="field"><span>Lock lasts (minutes)</span><input v-model.number="settings.login_lockout_minutes" type="number" min="1" max="1440" class="input" /></label>
              </div>

              <h3 class="set-group">Monitoring</h3>
              <label class="field narrow"><span>An order counts as stuck after this many days pending</span><input v-model.number="settings.stale_pending_days" type="number" min="1" max="30" class="input" /></label>
            </fieldset>
            <p v-if="settingsError" class="form-error">{{ settingsError }}</p>
            <div v-if="canManage" class="form-actions">
              <button type="submit" class="primary-btn" :disabled="busy">{{ busy ? "Saving…" : "Save settings" }}</button>
            </div>
          </template>
        </form>

        <!-- ═══ System health ═══ -->
        <template v-else-if="tab === 'health'">
          <div v-if="!health" class="sk-list"><span v-for="n in 4" :key="n" class="sk sk-row tall"></span></div>
          <template v-else>
            <div class="health-banner" :class="health.overall">
              <strong>{{ { ok: "Everything looks healthy", warn: "Working, with some warnings", fail: "Something needs fixing" }[health.overall] }}</strong>
              <span class="sub">Checked {{ formatDateTime(health.checked_at) }} · PHP {{ health.versions.php }} · Laravel {{ health.versions.laravel }} · {{ health.timezone }}</span>
              <button type="button" class="mini-btn" @click="fetchHealth">Check again</button>
            </div>
            <section class="split">
              <div v-for="(checks, group) in healthGroups" :key="group" class="panel">
                <h3 class="panel-title">{{ group }}</h3>
                <ul class="checks">
                  <li v-for="c in checks" :key="c.name">
                    <span class="check-dot" :class="c.status">{{ { ok: "✓", warn: "!", fail: "✕" }[c.status] }}</span>
                    <span class="strong">{{ c.name }}</span>
                    <span class="sub">{{ c.detail }}</span>
                  </li>
                </ul>
              </div>
            </section>

            <section class="panel">
              <div class="panel-head">
                <div>
                  <h3 class="panel-title">API errors</h3>
                  <p class="panel-sub">Actions that failed because of a server error. Full technical details stay in the server logs.</p>
                </div>
                <select v-model.number="errorHours" class="input"><option :value="1">Last hour</option><option :value="24">Last 24 hours</option><option :value="168">Last 7 days</option><option :value="720">Last 30 days</option></select>
              </div>
              <div v-if="!apiErrors" class="sk sk-row tall"></div>
              <template v-else>
                <p v-if="!apiErrors.total" class="calm">✓ No server errors in this period.</p>
                <template v-else>
                  <div class="bars" :aria-label="`${apiErrors.total} errors`">
                    <div v-for="(count, label) in apiErrors.series" :key="label" class="bar-col" :title="`${label}: ${count}`">
                      <span class="bar" :style="{ height: barHeight(count) }"></span>
                    </div>
                  </div>
                  <div class="split tight">
                    <div>
                      <h4 class="mini-title">Most affected features</h4>
                      <ul class="mini-list">
                        <li v-for="e in apiErrors.by_feature" :key="e.feature">
                          <span class="strong">{{ e.feature }}</span>
                          <span class="sub">{{ e.total }}× · last {{ timeAgo(e.last_at) }}</span>
                        </li>
                      </ul>
                    </div>
                    <div>
                      <h4 class="mini-title">Latest</h4>
                      <ul class="mini-list">
                        <li v-for="e in apiErrors.recent.slice(0, 12)" :key="e.id">
                          <span class="strong">{{ e.feature }} <span class="pill bad">Error {{ e.status }}</span></span>
                          <span class="sub">{{ e.exception ? e.exception + ": " : "" }}{{ e.message || "No message" }}</span>
                          <span class="sub">{{ formatDateTime(e.at) }}<template v-if="e.duration_ms"> · {{ e.duration_ms }} ms</template></span>
                        </li>
                      </ul>
                    </div>
                  </div>
                </template>
              </template>
            </section>
          </template>
        </template>

        <!-- ═══ Sign-in history ═══ -->
        <section v-else-if="tab === 'history'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Sign-in history</h2>
              <p class="panel-sub">Every successful sign-in by admins, vendors, customers and staff.</p>
            </div>
          </div>
          <div class="toolbar">
            <input v-model="historyFilters.search" type="search" class="input grow" placeholder="Search name, username, device, location or IP" />
            <select v-model="historyFilters.actor_type" class="input">
              <option value="">All accounts</option>
              <option value="admin">Admins</option>
              <option value="vendor">Vendors</option>
              <option value="customer">Customers</option>
              <option value="employee">Staff</option>
            </select>
          </div>
          <div v-if="loading.history && !history.length" class="sk-list"><span v-for="n in 6" :key="n" class="sk sk-row"></span></div>
          <p v-else-if="!history.length" class="empty">No sign-ins match these filters.</p>
          <div v-else class="table-scroll" :class="{ dim: loading.history }">
            <table class="data-table">
              <thead><tr><th>No.</th><th>Account</th><th>Type</th><th>When</th><th>Device</th><th>Location</th><th>IP address</th></tr></thead>
              <tbody>
                <tr v-for="(l, i) in history" :key="l.id">
                  <td class="muted mono">{{ rowNumber(historyMeta, i) }}</td>
                  <td>
                    <span class="strong">{{ l.actor_name || "Unknown" }}</span>
                    <span v-if="l.username" class="sub">@{{ l.username }}</span>
                  </td>
                  <td><span class="pill" :class="ACTOR_TONE[l.actor_type]">{{ ACTOR_LABEL[l.actor_type] ?? l.actor_type }}</span></td>
                  <td class="muted">{{ formatDateTime(l.logged_in_at) }}<span class="sub">{{ timeAgo(l.logged_in_at) }}</span></td>
                  <td>{{ l.device_name || "Unknown device" }}<span class="sub">{{ [l.browser, l.platform].filter(Boolean).join(" · ") }}</span></td>
                  <td class="muted">{{ l.location_label || "—" }}</td>
                  <td class="mono muted">{{ l.ip_address || "—" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="historyMeta.last_page > 1" class="pager">
            <span class="sub">{{ historyMeta.total }} sign-ins</span>
            <div class="row-actions">
              <button type="button" class="mini-btn" :disabled="historyMeta.current_page <= 1" @click="fetchHistory(historyMeta.current_page - 1)">Previous</button>
              <span class="sub">{{ historyMeta.current_page }} / {{ historyMeta.last_page }}</span>
              <button type="button" class="mini-btn" :disabled="historyMeta.current_page >= historyMeta.last_page" @click="fetchHistory(historyMeta.current_page + 1)">Next</button>
            </div>
          </div>
        </section>

        <!-- ═══ Website info ═══ -->
        <section v-else-if="tab === 'website'" class="split website">
          <form class="panel" @submit.prevent="saveWebsite">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">Website information</h2>
                <p class="panel-sub">Shown in the shop and landing page footers and on the journal.</p>
              </div>
            </div>
            <div v-if="!website" class="sk-list"><span v-for="n in 4" :key="n" class="sk sk-row"></span></div>
            <fieldset v-else class="form-grid" :disabled="!canEditWebsite">
              <label class="field"><span>Site name *</span><input v-model="website.site_name" class="input" maxlength="60" required /></label>
              <label class="field"><span>Tagline</span><input v-model="website.tagline" class="input" maxlength="140" /></label>
              <label class="field full"><span>About (footer text)</span><textarea v-model="website.about" class="input" rows="3" maxlength="600"></textarea></label>
              <label class="field"><span>Contact email</span><input v-model="website.contact_email" type="email" class="input" /></label>
              <label class="field"><span>Contact phone</span><input v-model="website.contact_phone" class="input" maxlength="40" /></label>
              <label class="field full"><span>Address</span><input v-model="website.address" class="input" maxlength="255" /></label>
              <label class="field"><span>Business hours</span><input v-model="website.business_hours" class="input" placeholder="Mon–Sat, 8 AM–6 PM" maxlength="120" /></label>
              <label class="field"><span>Footer note</span><input v-model="website.footer_note" class="input" placeholder="All rights reserved." maxlength="200" /></label>
              <label v-for="so in SOCIALS" :key="so.key" class="field"><span>{{ so.label }}</span><input v-model="website.socials[so.key]" type="url" class="input" :placeholder="so.placeholder" /></label>
            </fieldset>
            <p v-if="websiteError" class="form-error">{{ websiteError }}</p>
            <div v-if="canEditWebsite" class="form-actions">
              <button type="submit" class="primary-btn" :disabled="busy || !website">{{ busy ? "Saving…" : "Save changes" }}</button>
            </div>
          </form>
          <div class="panel preview-panel">
            <h2 class="panel-title">Footer preview</h2>
            <div v-if="website" class="footer-preview">
              <span class="fp-name">{{ website.site_name || "BloomCraft" }}</span>
              <p>{{ website.about || website.tagline || "Your marketplace for custom flower arrangements." }}</p>
              <ul>
                <li v-if="website.contact_email">✉ {{ website.contact_email }}</li>
                <li v-if="website.contact_phone">☏ {{ website.contact_phone }}</li>
                <li v-if="website.address">⌂ {{ website.address }}</li>
                <li v-if="website.business_hours">◷ {{ website.business_hours }}</li>
              </ul>
              <div class="fp-socials">
                <span v-for="so in SOCIALS.filter((x) => website.socials?.[x.key])" :key="so.key">{{ so.label }}</span>
              </div>
              <small>© {{ new Date().getFullYear() }} {{ website.site_name || "BloomCraft" }}. {{ website.footer_note || "All rights reserved." }}</small>
            </div>
          </div>
        </section>
      </div>
    </main>

    <!-- ═══ Dialogs ═══ -->
    <Transition name="fade">
      <div v-if="dialog.type" class="dialog-backdrop" @click.self="closeDialog">
        <form class="dialog" :class="{ wide: dialog.type === 'role' }" @submit.prevent="submitDialog">
          <template v-if="dialog.type === 'admin'">
            <h3>Add an administrator</h3>
            <p class="sub">They sign in at the normal login page with the username or email and password you set here.</p>
            <div class="two">
              <label class="field"><span>First name</span><input v-model="form.name" class="input" required /></label>
              <label class="field"><span>Last name</span><input v-model="form.surname" class="input" required /></label>
            </div>
            <div class="two">
              <label class="field"><span>Username</span><input v-model="form.username" class="input" required autocomplete="off" /></label>
              <label class="field"><span>Email</span><input v-model="form.email" type="email" class="input" required autocomplete="off" /></label>
            </div>
            <label class="field"><span>Role</span>
              <select v-model="form.admin_role_id" class="input" required><option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option></select>
            </label>
            <label class="field"><span>Temporary password</span>
              <div class="pw-row">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input mono" required autocomplete="new-password" />
                <button type="button" class="mini-btn" @click="showPassword = !showPassword">{{ showPassword ? "Hide" : "Show" }}</button>
                <button type="button" class="mini-btn" @click="generatePassword">Generate</button>
              </div>
              <small class="sub">At least 10 characters with upper- and lowercase letters and a number. Share it privately.</small>
            </label>
          </template>

          <template v-else-if="dialog.type === 'password'">
            <h3>{{ dialog.target.id === access.id ? "Change your password" : `Set a new password for ${dialog.target.name}` }}</h3>
            <p class="sub">{{ dialog.target.id === access.id ? "You'll stay signed in." : "They'll be signed out and need the new password." }}</p>
            <label class="field"><span>New password</span>
              <div class="pw-row">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input mono" required autocomplete="new-password" />
                <button type="button" class="mini-btn" @click="showPassword = !showPassword">{{ showPassword ? "Hide" : "Show" }}</button>
                <button type="button" class="mini-btn" @click="generatePassword">Generate</button>
              </div>
            </label>
          </template>

          <template v-else-if="dialog.type === 'role'">
            <h3>{{ dialog.target ? `Edit “${dialog.target.name}”` : "New role" }}</h3>
            <div class="two">
              <label class="field"><span>Name</span><input v-model="form.name" class="input" maxlength="60" required /></label>
              <label class="field"><span>Description</span><input v-model="form.description" class="input" maxlength="255" /></label>
            </div>
            <div class="perm-editor">
              <div v-for="(perms, group) in catalog" :key="group" class="perm-group">
                <span class="group-label">{{ group }}</span>
                <label v-for="(label, key) in perms" :key="key" class="perm-check">
                  <input v-model="form.permissions" type="checkbox" :value="key" />
                  <span>{{ label }}</span>
                </label>
              </div>
            </div>
          </template>

          <p v-if="dialog.error" class="form-error">{{ dialog.error }}</p>
          <div class="dialog-actions">
            <button type="button" class="ghost-btn" :disabled="busy" @click="closeDialog">Cancel</button>
            <button type="submit" class="primary-btn" :disabled="busy">{{ busy ? "Saving…" : "Save" }}</button>
          </div>
        </form>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useSidebarState } from "../../composables/useSidebarState";
import { useAdminAccess } from "../../composables/useAdminAccess";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import api from "../../plugins/axios";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();
const adminAccess = useAdminAccess();

const props = defineProps({ section: { type: String, default: "access" } });

// One component, four sidebar pages
const SECTIONS = {
  access: { title: "Admin Users & Permissions", subtitle: "Who can sign in to the admin console, and what each role allows", tabs: ["admins", "roles"] },
  audit: { title: "Audit Logs", subtitle: "Every change and export an admin made, and every sign-in", tabs: ["audit", "signins", "history"] },
  settings: { title: "System Settings", subtitle: "Platform switches, sign-in protection and the website's public information", tabs: ["settings", "website"] },
  health: { title: "System Health", subtitle: "Database, storage, integrations and server errors", tabs: ["health"] },
};
const TABS = [
  { key: "admins", label: "Administrators" },
  { key: "roles", label: "Roles & permissions" },
  { key: "audit", label: "Admin actions" },
  { key: "signins", label: "Sign-in monitoring" },
  { key: "history", label: "Sign-in history" },
  { key: "settings", label: "Platform" },
  { key: "website", label: "Website info" },
  { key: "health", label: "System health" },
];
// Tabs that need something other than security.view
const TAB_PERMISSION = { website: "content.manage" };
const ACTOR_LABEL = { admin: "Admin", vendor: "Vendor", customer: "Customer", employee: "Staff" };
const ACTOR_TONE = { admin: "super", vendor: "ok", customer: "", employee: "warn" };
const SOCIALS = [
  { key: "facebook", label: "Facebook", placeholder: "https://facebook.com/…" },
  { key: "instagram", label: "Instagram", placeholder: "https://instagram.com/…" },
  { key: "tiktok", label: "TikTok", placeholder: "https://tiktok.com/@…" },
  { key: "x", label: "X", placeholder: "https://x.com/…" },
];
const REASON = { unknown_account: "Unknown account", wrong_password: "Wrong password", locked: "Blocked while locked", suspended: "Suspended account", unverified: "Unverified account" };
const AREAS = {
  AdminVendorController: "Vendors",
  VendorApplication: "Vendor applications",
  AdminCustomerController: "Customers",
  AdminProductController: "Products & categories",
  ProductReportController: "Product reports",
  AdminReviewController: "Customer reviews",
  AdminOrderController: "Orders",
  AdminContentController: "Website content",
  AdminSupportController: "Support & announcements",
  FinanceController: "Finance exports",
  SubscriptionRevenueController: "Subscription exports",
  AdminReportsController: "Report downloads",
  SecurityController: "Security",
};
const ACTIONS = {
  "AdminVendorController@suspend": "Suspended a vendor",
  "AdminVendorController@reactivate": "Reactivated a vendor",
  "VendorApplicationController@approve": "Approved a vendor application",
  "VendorApplicationController@reject": "Rejected a vendor application",
  "VendorApplicationController@updateStatus": "Changed an application's status",
  "VendorApplicationController@export": "Exported vendor applications",
  "VendorApplicationResubmissionController@requestResubmission": "Asked a vendor for corrections",
  "VendorApplicationResubmissionController@approveItem": "Accepted a resubmitted item",
  "VendorApplicationResubmissionController@requestAgain": "Asked again for a resubmitted item",
  "AdminCustomerController@reveal": "Viewed a customer's personal details",
  "AdminCustomerController@suspend": "Suspended a customer",
  "AdminCustomerController@reactivate": "Reactivated a customer",
  "AdminProductController@moderate": "Moderated a listing",
  "AdminProductController@storeTaxonomy": "Added a category / type / occasion",
  "AdminProductController@updateTaxonomy": "Edited a category / type / occasion",
  "AdminProductController@destroyTaxonomy": "Deleted a category / type / occasion",
  "AdminProductController@reorderTaxonomies": "Reordered a list",
  "ProductReportController@review": "Reviewed a product report",
  "AdminReviewController@hide": "Hid a customer review",
  "AdminReviewController@restore": "Restored a customer review",
  "AdminOrderController@addNote": "Added an order note",
  "AdminContentController@saveBanner": "Saved a banner",
  "AdminContentController@deleteBanner": "Deleted a banner",
  "AdminContentController@addFeatured": "Featured a product",
  "AdminContentController@removeFeatured": "Unfeatured a product",
  "AdminContentController@saveArticle": "Saved an article",
  "AdminContentController@publishArticle": "Published an article",
  "AdminContentController@unpublishArticle": "Unpublished an article",
  "AdminContentController@deleteArticle": "Deleted an article",
  "AdminContentController@saveAnnouncement": "Saved an announcement / promotion",
  "AdminContentController@deleteAnnouncement": "Deleted an announcement / promotion",
  "AdminContentController@reorder": "Reordered website content",
  "AdminContentController@saveSettings": "Changed website information",
  "AdminSupportController@reply": "Replied to a support request",
  "AdminSupportController@setStatus": "Updated a support request",
  "AdminSupportController@sendBroadcast": "Sent an announcement",
  "AdminSupportController@retractBroadcast": "Withdrew an announcement",
  "FinanceController@exportTransactions": "Exported transactions",
  "FinanceController@exportReport": "Exported a financial report",
  "SubscriptionRevenueController@export": "Exported subscription payments",
  "AdminReportsController@export": "Downloaded an analytics report",
  "SecurityController@createAdmin": "Added an administrator",
  "SecurityController@updateAdmin": "Edited an administrator",
  "SecurityController@setAdminActive": "Turned an administrator on/off",
  "SecurityController@resetAdminPassword": "Set an administrator's password",
  "SecurityController@saveRole": "Saved a role",
  "SecurityController@deleteRole": "Deleted a role",
  "SecurityController@saveSettings": "Changed platform settings",
};

const access = adminAccess.access;
const canManage = computed(() => adminAccess.can("security.manage"));
const canEditWebsite = computed(() => adminAccess.can("content.manage"));
const sectionKey = computed(() => (SECTIONS[props.section] ? props.section : "access"));
const visibleTabs = computed(() =>
  TABS.filter((t) => SECTIONS[sectionKey.value].tabs.includes(t.key) && adminAccess.can(TAB_PERMISSION[t.key] ?? "security.view")),
);
const readOnly = computed(() =>
  tab.value === "website" ? !canEditWebsite.value : ["admins", "roles", "settings"].includes(tab.value) && !canManage.value,
);
const tab = ref(null);
const loading = reactive({ admins: false, roles: false, audit: false, history: false });
const busy = ref(false);

// ── Admins & roles ───────────────────────────────────────────────
const admins = ref([]);
const roles = ref([]);
const catalog = ref({});
const fetchAdmins = async () => {
  loading.admins = true;
  try {
    admins.value = (await api.get("/admin/security/admins")).data.data;
  } catch {
    toast.error("Administrators couldn't be loaded.");
  } finally {
    loading.admins = false;
  }
};
const fetchRoles = async () => {
  loading.roles = true;
  try {
    const { data } = await api.get("/admin/security/roles");
    roles.value = data.data;
    catalog.value = data.catalog;
  } catch {
    toast.error("Roles couldn't be loaded.");
  } finally {
    loading.roles = false;
  }
};
const changeRole = async (admin, roleId) => {
  try {
    await api.put(`/admin/security/admins/${admin.id}`, { admin_role_id: Number(roleId) });
    toast.success("Role changed.");
  } catch (e) {
    toast.error(e.response?.data?.message || "The role couldn't be changed.");
  }
  fetchAdmins();
  fetchRoles();
};
const setActive = async (admin, active) => {
  try {
    const { data } = await api.post(`/admin/security/admins/${admin.id}/active`, { active });
    toast.success(data.message);
    fetchAdmins();
  } catch (e) {
    toast.error(e.response?.data?.message || "That change couldn't be saved.");
  }
};
const deleteRole = async (role) => {
  try {
    const { data } = await api.delete(`/admin/security/roles/${role.id}`);
    toast.success(data.message);
    fetchRoles();
  } catch (e) {
    toast.error(e.response?.data?.message || "The role couldn't be deleted.");
  }
};

// ── Audit ────────────────────────────────────────────────────────
const audit = ref([]);
const auditAdmins = ref([]);
const auditMeta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const auditFilters = reactive({ search: "", admin_id: "", area: "", outcome: "", from: "", to: "" });
const fetchAudit = async (page = 1) => {
  loading.audit = true;
  try {
    const params = Object.fromEntries(Object.entries({ ...auditFilters, page }).filter(([, v]) => v !== ""));
    const { data } = await api.get("/admin/security/audit", { params });
    audit.value = data.data;
    auditMeta.value = data.meta;
    auditAdmins.value = data.admins;
  } catch {
    toast.error("The audit log couldn't be loaded.");
  } finally {
    loading.audit = false;
  }
};
let auditTimer = null;
watch(
  () => ({ ...auditFilters }),
  () => {
    clearTimeout(auditTimer);
    auditTimer = setTimeout(() => fetchAudit(1), 300);
  },
);
// "SomethingController@doThing" → "Do thing" when there's no friendlier label
const humanize = (action) => {
  const method = (action.split("@")[1] || "Made a change").replace(/([a-z])([A-Z])/g, "$1 $2").toLowerCase();
  return method.charAt(0).toUpperCase() + method.slice(1);
};
const actionLabel = (l) => {
  const base = ACTIONS[l.action] ?? humanize(l.action);
  return l.action === "AdminProductController@moderate" && l.details?.action ? `${base} (${l.details.action})` : base;
};
// Lists number their rows 001, 002… instead of showing record IDs
const rowNumber = (meta, index) => String((meta.current_page - 1) * meta.per_page + index + 1).padStart(3, "0");

// ── Sign-ins ─────────────────────────────────────────────────────
const signins = ref(null);
const signinHours = ref(24);
const fetchSignins = async () => {
  try {
    signins.value = (await api.get("/admin/security/sign-ins", { params: { hours: signinHours.value } })).data.data;
  } catch {
    toast.error("Sign-ins couldn't be loaded.");
  }
};
watch(signinHours, fetchSignins);

// ── Settings ─────────────────────────────────────────────────────
const settings = ref(null);
const settingsError = ref("");
const fetchSettings = async () => {
  try {
    settings.value = (await api.get("/admin/security/settings")).data.data;
  } catch {
    toast.error("Settings couldn't be loaded.");
  }
};
const saveSettings = async () => {
  busy.value = true;
  settingsError.value = "";
  try {
    const { data } = await api.put("/admin/security/settings", settings.value);
    settings.value = data.data;
    toast.success(data.message);
  } catch (e) {
    const errors = e.response?.data?.errors;
    settingsError.value = errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "Settings couldn't be saved.";
  } finally {
    busy.value = false;
  }
};

// Sign-up and checkout switches apply as soon as they're flipped; a failed save flips them back
const SWITCH_TOASTS = {
  registrations_open: (on) => (on ? "Customer sign-ups are open." : "Customer sign-ups are closed."),
  vendor_applications_open: (on) => (on ? "Vendor applications are open." : "Vendor applications are closed."),
  checkout_paused: (on) => (on ? "Checkout is paused for every store." : "Checkout is open again."),
};
const switchSaving = ref(false);
const saveSwitch = async (key) => {
  const value = settings.value[key];
  switchSaving.value = true;
  settingsError.value = "";
  try {
    // Only the switch itself; unsaved edits to other fields stay as they are
    const saved = (await api.get("/admin/security/settings")).data.data;
    const { data } = await api.put("/admin/security/settings", { ...saved, [key]: value });
    settings.value = { ...settings.value, [key]: data.data[key] };
    toast.success(SWITCH_TOASTS[key](data.data[key]));
  } catch (e) {
    settings.value[key] = !value;
    const errors = e.response?.data?.errors;
    settingsError.value = errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "That change couldn't be saved.";
  } finally {
    switchSaving.value = false;
  }
};

// ── Health & errors ──────────────────────────────────────────────
const health = ref(null);
const apiErrors = ref(null);
const errorHours = ref(24);
const healthGroups = computed(() => {
  const groups = {};
  for (const c of health.value?.checks ?? []) (groups[c.group] ??= []).push(c);
  return groups;
});
const fetchHealth = async () => {
  try {
    health.value = (await api.get("/admin/security/health")).data.data;
  } catch {
    toast.error("Health checks couldn't run.");
  }
};
const fetchErrors = async () => {
  try {
    apiErrors.value = (await api.get("/admin/security/api-errors", { params: { hours: errorHours.value } })).data.data;
  } catch {
    toast.error("API errors couldn't be loaded.");
  }
};
watch(errorHours, fetchErrors);
const barHeight = (count) => {
  const max = Math.max(1, ...Object.values(apiErrors.value?.series ?? {}));
  return `${Math.max(6, Math.round((count / max) * 100))}%`;
};

// ── Dialogs ──────────────────────────────────────────────────────
const dialog = reactive({ type: null, target: null, error: "" });
const form = reactive({});
const showPassword = ref(false);
const openDialog = (type, target, values) => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, values);
  Object.assign(dialog, { type, target, error: "" });
  showPassword.value = false;
};
const openAdminDialog = () =>
  openDialog("admin", null, { name: "", surname: "", username: "", email: "", password: "", admin_role_id: roles.value.find((r) => !r.is_system)?.id ?? roles.value[0]?.id });
const openPasswordDialog = (admin) => openDialog("password", admin, { password: "" });
const openRoleDialog = (role = null) =>
  openDialog("role", role, { name: role?.name ?? "", description: role?.description ?? "", permissions: [...(role?.permissions ?? ["dashboard.view"])] });
const closeDialog = () => {
  if (!busy.value) dialog.type = null;
};
const generatePassword = () => {
  const sets = ["ABCDEFGHJKLMNPQRSTUVWXYZ", "abcdefghijkmnopqrstuvwxyz", "23456789", "!@#$%*?"];
  const pick = (chars) => chars[crypto.getRandomValues(new Uint32Array(1))[0] % chars.length];
  const chars = [...sets.map(pick), ...Array.from({ length: 10 }, () => pick(sets.join("")))];
  for (let i = chars.length - 1; i > 0; i--) {
    const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1);
    [chars[i], chars[j]] = [chars[j], chars[i]];
  }
  form.password = chars.join("");
  showPassword.value = true;
};
const submitDialog = async () => {
  busy.value = true;
  dialog.error = "";
  try {
    let message;
    if (dialog.type === "admin") {
      ({ data: { message } } = await api.post("/admin/security/admins", form));
      fetchAdmins();
      fetchRoles();
    } else if (dialog.type === "password") {
      ({ data: { message } } = await api.post(`/admin/security/admins/${dialog.target.id}/password`, { password: form.password }));
    } else {
      ({ data: { message } } = dialog.target ? await api.put(`/admin/security/roles/${dialog.target.id}`, form) : await api.post("/admin/security/roles", form));
      fetchRoles();
      adminAccess.load(true).catch(() => {});
    }
    toast.success(message);
    busy.value = false;
    dialog.type = null;
  } catch (e) {
    busy.value = false;
    const errors = e.response?.data?.errors;
    dialog.error = errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "It couldn't be saved.";
  }
};

// ── Tabs ─────────────────────────────────────────────────────────
const loadTab = (key) => {
  if (key === "admins") {
    fetchAdmins();
    if (!roles.value.length) fetchRoles();
  }
  if (key === "roles") fetchRoles();
  if (key === "audit") fetchAudit(1);
  if (key === "signins") fetchSignins();
  if (key === "settings") fetchSettings();
  if (key === "history") fetchHistory(1);
  if (key === "website" && !website.value) fetchWebsite();
  if (key === "health") {
    fetchHealth();
    fetchErrors();
  }
};
const setTab = (key) => {
  tab.value = key;
  router.replace({ query: { ...route.query, tab: key } });
  loadTab(key);
};

// ── Sign-in history ──────────────────────────────────────────────
const history = ref([]);
const historyMeta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const historyFilters = reactive({ search: "", actor_type: "" });
const fetchHistory = async (page = 1) => {
  loading.history = true;
  try {
    const params = { page, per_page: 25, search: historyFilters.search || undefined, actor_type: historyFilters.actor_type || undefined };
    const page = (await api.get("/admin/login-logs", { params })).data.data;
    history.value = page.data;
    historyMeta.value = { current_page: page.current_page, last_page: page.last_page, per_page: page.per_page, total: page.total };
  } catch {
    toast.error("Sign-in history couldn't be loaded.");
  } finally {
    loading.history = false;
  }
};
let historyTimer = null;
watch(
  () => ({ ...historyFilters }),
  () => {
    clearTimeout(historyTimer);
    historyTimer = setTimeout(() => fetchHistory(1), 300);
  },
);

// ── Website info ─────────────────────────────────────────────────
const website = ref(null);
const websiteError = ref("");
const fetchWebsite = async () => {
  try {
    const { data } = await api.get("/admin/content/settings");
    website.value = { ...data.data, socials: data.data.socials ?? {} };
  } catch {
    toast.error("Website information couldn't be loaded.");
  }
};
const saveWebsite = async () => {
  busy.value = true;
  websiteError.value = "";
  try {
    const socials = Object.fromEntries(SOCIALS.map((so) => [so.key, website.value.socials?.[so.key]?.trim() || null]));
    const { data } = await api.put("/admin/content/settings", { ...website.value, socials });
    website.value = { ...data.data, socials: data.data.socials ?? {} };
    toast.success(data.message);
  } catch (e) {
    const errors = e.response?.data?.errors;
    websiteError.value = errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "Website information couldn't be saved.";
  } finally {
    busy.value = false;
  }
};

// Pick the tab from ?tab= when this page allows it, else the first one
const openSection = () => {
  const keys = visibleTabs.value.map((t) => t.key);
  tab.value = keys.includes(route.query.tab) ? route.query.tab : keys[0] ?? null;
  if (tab.value) loadTab(tab.value);
};
// The four pages reuse this component, so switching between them changes the prop
watch(() => props.section, openSection);

// ── Formatting ───────────────────────────────────────────────────
const initials = (name) => (name || "?").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join("");
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
const timeAgo = (v) => {
  const minutes = Math.floor((Date.now() - new Date(v)) / 60_000);
  if (minutes < 60) return minutes < 1 ? "just now" : `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? "" : "s"} ago`;
};

onMounted(async () => {
  await adminAccess.load().catch(() => {});
  openSection();
});
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
}
.page-body {
  padding: 18px 28px 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 20px 28px;
  background: #fff;
  border-bottom: 1px solid #e9edf3;
  position: sticky;
  top: 0;
  z-index: 100;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 4px;
}
.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #1e2739;
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}
.readonly-chip {
  font-size: 12px;
  padding: 6px 11px;
  border-radius: 20px;
  background: #f1f5f9;
  color: #475569;
}

/* Buttons */
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}
.ghost-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
}
.primary-btn {
  border: none;
  background: #7c6ff7;
  color: #fff;
}
.primary-btn:hover:not(:disabled) {
  background: #5b4fc9;
}
.primary-btn:disabled,
.ghost-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.mini-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  white-space: nowrap;
}
.mini-btn:hover:not(:disabled) {
  border-color: #c7c2fb;
  color: #5b4fc9;
}
.mini-btn:disabled {
  opacity: 0.45;
  cursor: default;
}
.mini-btn.danger { color: #b91c1c; }
.mini-btn.ok { color: #15803d; }
.link-btn {
  display: inline-block;
  margin-top: 10px;
  color: #6d5ff0;
  font-size: 12.5px;
  font-weight: 500;
  text-decoration: none;
}

/* Skeleton */
.sk {
  display: block;
  border-radius: 10px;
  background: linear-gradient(100deg, #eef1f6 30%, #f8fafc 50%, #eef1f6 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sk-row {
  height: 52px;
}
.sk-row.tall {
  height: 120px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Tabs */
.tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
}
.tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid #e9edf3;
  background: #fff;
  color: #475569;
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
}
.tab.active {
  background: #ede9fe;
  border-color: #c7c2fb;
  color: #4c3fd0;
}
.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.dot.warn { background: #f59e0b; }
.dot.fail { background: #dc2626; }

/* Panels */
.panel {
  background: #fff;
  border: 1px solid #e9edf3;
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e2739;
  margin: 0 0 4px;
}
.panel-title.spaced {
  margin-top: 18px;
}
.panel-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
  max-width: 720px;
}
.split {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}
.split.tight {
  margin-top: 16px;
}
.empty {
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
  padding: 24px 0;
  margin: 0;
}
.empty.small {
  padding: 10px 0;
  text-align: left;
}
.calm {
  margin: 0;
  padding: 12px 14px;
  border-radius: 10px;
  background: #f0fdf4;
  color: #15803d;
  font-size: 13px;
  font-weight: 500;
}
.strong {
  font-weight: 600;
  color: #1e2739;
  font-size: 13px;
}
.sub {
  display: block;
  font-size: 11.5px;
  color: #94a3b8;
}
.muted {
  color: #64748b;
}
.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 12px;
}
.pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  background: #f1f5f9;
  color: #475569;
}
.pill.ok { background: #dcfce7; color: #15803d; }
.pill.bad { background: #fee2e2; color: #b91c1c; }
.pill.warn { background: #fef3c7; color: #b45309; }
.pill.super { background: #ede9fe; color: #5b4fc9; }
.you {
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 6px;
  background: #e0f2fe;
  color: #0369a1;
  margin-left: 4px;
}

/* Tables */
.table-scroll {
  overflow-x: auto;
  margin: 0 -6px;
}
.table-scroll.dim {
  opacity: 0.55;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.data-table th {
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 9px 10px;
  background: #f8fafc;
  white-space: nowrap;
}
.data-table td {
  padding: 11px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
  color: #1e2739;
}
.data-table tr.off td {
  opacity: 0.6;
}
.num {
  text-align: right !important;
}
.right {
  text-align: right;
}
.person {
  display: flex;
  align-items: center;
  gap: 10px;
}
.avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, #7c6ff7, #5b4fc9);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.row-actions {
  display: inline-flex;
  gap: 6px;
  align-items: center;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.chip {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
  background: #f5f3ff;
  color: #5b4fc9;
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.input {
  padding: 8px 11px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 12.5px;
  color: #1e2739;
  background: #fff;
}
.input.small {
  padding: 6px 8px;
  font-size: 12px;
}
.input:focus {
  outline: none;
  border-color: #7c6ff7;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.toolbar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.grow {
  flex: 1 1 220px;
}
.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
}

/* Roles */
.role-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 16px;
}
.role-card.system {
  border-color: #c7c2fb;
  background: linear-gradient(160deg, #faf9ff, #fff 60%);
}
.role-head {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.role-head h3 {
  margin: 0 0 4px;
  font-size: 15px;
  color: #1e2739;
}
.count {
  font-size: 12px;
  color: #64748b;
  white-space: nowrap;
}
.perm-groups,
.perm-editor {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.perm-editor {
  max-height: 52vh;
  overflow-y: auto;
  padding: 4px 2px;
}
.perm-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.group-label {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #94a3b8;
}
.perm {
  font-size: 12.5px;
  color: #cbd5e1;
}
.perm.on {
  color: #1e2739;
}
.perm-check {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 13px;
  color: #334155;
  cursor: pointer;
}
.perm-check input {
  accent-color: #7c6ff7;
}
.role-actions {
  display: flex;
  gap: 6px;
  margin-top: 14px;
}

/* Sign-ins */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid #e9edf3;
  background: #fff;
}
.stat.hot {
  border-color: #fecaca;
  background: #fff7f7;
}
.stat-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}
.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #1e2739;
}
.reasons {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  color: #475569;
}
.mini-list {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.mini-list li {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f1f5f9;
}
.mini-list li.warn .strong {
  color: #b91c1c;
}
.mini-title {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin: 0;
}

/* Settings */
.settings fieldset {
  border: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.set-group {
  margin: 14px 0 2px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #94a3b8;
}
.toggle-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 12px 14px;
  border: 1px solid #eef1f6;
  border-radius: 12px;
  cursor: pointer;
}
.toggle-row.alarm {
  border-color: #fecaca;
  background: #fff7f7;
}
.toggle-row > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.switch {
  appearance: none;
  width: 40px;
  height: 22px;
  border-radius: 22px;
  background: #cbd5e1;
  position: relative;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s;
}
.switch::after {
  content: "";
  position: absolute;
  top: 3px;
  left: 3px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s;
}
.switch:checked {
  background: #16a34a;
}
.switch.danger:checked {
  background: #dc2626;
}
.switch:checked::after {
  transform: translateX(18px);
}
.switch:focus-visible {
  outline: 2px solid #7c6ff7;
  outline-offset: 2px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}
.field.narrow {
  max-width: 420px;
}
.two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.form-error {
  color: #b91c1c;
  font-size: 12.5px;
  margin: 10px 0 0;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

/* Health */
.health-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px 18px;
  border-radius: 14px;
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
  color: #166534;
}
.health-banner strong {
  font-size: 15px;
}
.health-banner .sub {
  flex: 1;
  display: inline;
}
.health-banner.warn { border-color: #fde68a; background: #fffbeb; color: #92400e; }
.health-banner.fail { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
.checks {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.checks li {
  display: grid;
  grid-template-columns: 24px 1fr;
  column-gap: 10px;
  align-items: center;
}
.checks li .sub {
  grid-column: 2;
}
.check-dot {
  grid-row: span 2;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  color: #fff;
}
.check-dot.ok { background: #16a34a; }
.check-dot.warn { background: #f59e0b; }
.check-dot.fail { background: #dc2626; }
.bars {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 90px;
  padding: 6px 0;
  border-bottom: 1px solid #eef1f6;
}
.bar-col {
  flex: 1;
  height: 100%;
  display: flex;
  align-items: flex-end;
}
.bar {
  display: block;
  width: 100%;
  border-radius: 4px 4px 0 0;
  background: #f87171;
}

/* Dialog */
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.38);
  backdrop-filter: blur(2px);
  z-index: 400;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.dialog {
  width: min(520px, 100%);
  max-height: calc(100vh - 32px);
  overflow-y: auto;
  background: #fff;
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.dialog.wide {
  width: min(640px, 100%);
}
.dialog h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #1e2739;
}
.dialog .input {
  width: 100%;
}
.pw-row {
  display: flex;
  gap: 6px;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 6px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  border: none;
  margin: 0;
  padding: 0;
}
.field.full {
  grid-column: 1 / -1;
}
textarea.input {
  resize: vertical;
  font-family: inherit;
}
.split.website {
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
}
.preview-panel {
  position: sticky;
  top: 96px;
}
.footer-preview {
  margin-top: 12px;
  padding: 22px;
  border-radius: 12px;
  background: #1b1612;
  color: rgba(250, 248, 244, 0.78);
  font-size: 13px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.fp-name {
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 28px;
  color: #faf8f4;
}
.footer-preview p {
  margin: 0;
}
.footer-preview ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.fp-socials {
  display: flex;
  gap: 12px;
  color: #d4b27a;
}
.footer-preview small {
  border-top: 1px solid rgba(250, 248, 244, 0.12);
  padding-top: 10px;
  font-size: 11px;
}
.link-btn {
  border: none;
  background: none;
  padding: 0;
  cursor: pointer;
}

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
}
@media (max-width: 1100px) {
  .split {
    grid-template-columns: minmax(0, 1fr);
  }
  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .mobile-toggle {
    display: flex;
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 14px 16px 32px;
  }
  .two {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
}
</style>
