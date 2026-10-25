<template>
  <ShopLayout section="account" title="Security Settings" subtitle="Your password, two-step sign-in, and where your account has been used.">
    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Checkup -->
    <section class="sp-card sc-check">
      <template v-if="!data">
        <div class="bc-skeleton sc-score"></div>
        <div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w70"></div></div>
      </template>
      <template v-else>
        <div class="sc-score" :class="`tone-${scoreTone}`" role="img" :aria-label="`${passed} of ${checks.length} checks passed`">
          <b>{{ passed }}/{{ checks.length }}</b>
        </div>
        <div class="sc-check-main">
          <h2>{{ passed === checks.length ? "Your account is well protected" : "A few things would make your account safer" }}</h2>
          <ul class="sc-checks">
            <li v-for="c in checks" :key="c.label" :class="{ ok: c.ok }">
              <span v-html="c.ok ? ICONS.check : ICONS.alert"></span>{{ c.label }}
            </li>
          </ul>
        </div>
      </template>
    </section>

    <div class="ac-cols">
      <!-- Password -->
      <section class="sp-card">
        <div class="sp-card-head">
          <div><h2>Password</h2><p>{{ data ? passwordAge : "Loading…" }}</p></div>
        </div>
        <form class="sc-form" novalidate @submit.prevent="changePassword">
          <label class="sp-field">
            <span>Current password</span>
            <div class="sc-pw">
              <input v-model="pw.current_password" :type="show.current ? 'text' : 'password'" class="sp-input" :class="{ invalid: pwErrors.current_password }" autocomplete="current-password" />
              <button type="button" class="sc-eye" :aria-label="show.current ? 'Hide password' : 'Show password'" @click="show.current = !show.current" v-html="ICONS.eye"></button>
            </div>
            <small v-if="pwErrors.current_password" class="sp-error">{{ pwErrors.current_password }}</small>
          </label>
          <label class="sp-field">
            <span>New password</span>
            <div class="sc-pw">
              <input v-model="pw.password" :type="show.new ? 'text' : 'password'" class="sp-input" :class="{ invalid: pwErrors.password }" autocomplete="new-password" />
              <button type="button" class="sc-eye" :aria-label="show.new ? 'Hide password' : 'Show password'" @click="show.new = !show.new" v-html="ICONS.eye"></button>
            </div>
            <div v-if="pw.password" class="sc-strength" :class="strength.tone">
              <div class="ac-meter"><i :style="{ width: `${strength.pct}%` }"></i></div>
              <small>{{ strength.label }}</small>
            </div>
            <!-- The rules the server checks, ticked off while typing -->
            <ul class="sc-rules" aria-label="Password rules">
              <li v-for="r in rules" :key="r.label" :class="{ met: r.met }">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path v-if="r.met" d="m6 12.5 4 4 8-9" /><circle v-else cx="12" cy="12" r="3.2" /></svg>
                {{ r.label }}<span class="sc-sr">{{ r.met ? " — done" : " — not yet" }}</span>
              </li>
            </ul>
            <small v-if="pwErrors.password" class="sp-error">{{ pwErrors.password }}</small>
          </label>
          <label class="sp-field">
            <span>Confirm new password</span>
            <input v-model="pw.password_confirmation" :type="show.new ? 'text' : 'password'" class="sp-input" :class="{ invalid: pw.password_confirmation && !matches }" autocomplete="new-password" />
            <small v-if="pw.password_confirmation && !matches" class="sp-error">Passwords don't match.</small>
          </label>
          <div class="sc-form-foot">
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="pwSaving || !pw.current_password || !passwordOk(pw.password) || !matches">
              <span v-html="ICONS.lock"></span>{{ pwSaving ? "Updating…" : "Update password" }}
            </button>
          </div>
        </form>
      </section>

      <!-- Two-step sign-in -->
      <section class="sp-card">
        <div class="sp-card-head">
          <div><h2>Two-step sign-in</h2><p>After your password, you'll also enter a 6-digit passkey you choose (or a code we email you).</p></div>
          <span v-if="data" class="sp-chip" :class="data.two_factor_enabled ? 'green' : 'slate'">{{ data.two_factor_enabled ? "On" : "Off" }}</span>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <template v-else>
          <div class="sc-2fa" :class="{ on: data.two_factor_enabled }">
            <span class="sp-stat-icon" :class="data.two_factor_enabled ? 'tone-green' : 'tone-amber'" v-html="ICONS.shield"></span>
            <p>{{ data.two_factor_enabled ? "Someone who learns your password still can't get in without your passkey." : "Anyone with your password can sign in. Turning this on takes a minute." }}</p>
          </div>
          <button type="button" class="sp-btn" :class="data.two_factor_enabled ? 'sp-btn-ghost' : 'sp-btn-primary'" :disabled="tf.busy" @click="startTwoFactor(data.two_factor_enabled ? 'disable' : 'enable')">
            {{ tf.busy && !tf.step ? "Sending code…" : data.two_factor_enabled ? "Turn off" : "Turn on two-step sign-in" }}
          </button>
          <p class="sp-hint sc-email">Codes go to {{ data.account.email }}.</p>
        </template>
      </section>
    </div>

    <!-- Sessions -->
    <section class="sp-card sc-sessions">
      <div class="sp-card-head">
        <div><h2>Sign-ins</h2><p>Your account stays signed in on one device at a time. Signing in somewhere new signs the old one out.</p></div>
        <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="confirmSignOut = true"><span v-html="ICONS.lock"></span>Sign out everywhere else</button>
      </div>
      <div v-if="!data" class="sc-sk"><div v-for="n in 4" :key="n" class="bc-skeleton sp-sk-line"></div></div>
      <p v-else-if="!data.sign_ins.length" class="sp-hint">No sign-ins recorded yet.</p>
      <ul v-else class="sc-list">
        <li v-for="(s, i) in data.sign_ins" :key="s.id">
          <span class="sp-stat-icon" :class="i === 0 ? 'tone-green' : 'tone-slate'" v-html="ICONS.globe"></span>
          <span class="sc-list-main">
            <b>{{ device(s) }}</b>
            <small>{{ [s.location, s.ip].filter(Boolean).join(" · ") || "Location unknown" }}</small>
          </span>
          <span v-if="i === 0" class="sp-chip green">Latest</span>
          <time class="st-sub" :datetime="s.at">{{ dateTime(s.at) }}</time>
        </li>
      </ul>

      <h3 class="st-section-title">Failed sign-in attempts · last 30 days</h3>
      <div v-if="!data" class="bc-skeleton sp-sk-line w50"></div>
      <p v-else-if="!data.failed_attempts.count" class="sc-clean"><span v-html="ICONS.check"></span>None. Nobody has tried a wrong password on your account.</p>
      <template v-else>
        <p class="sp-hint">{{ data.failed_attempts.count }} failed attempt{{ data.failed_attempts.count === 1 ? "" : "s" }}. If these weren't you, change your password and turn on two-step sign-in.</p>
        <ul class="sc-list">
          <li v-for="(a, i) in data.failed_attempts.recent" :key="i">
            <span class="sp-stat-icon tone-red" v-html="ICONS.alert"></span>
            <span class="sc-list-main"><b>{{ REASONS[a.reason] ?? "Sign-in refused" }}</b><small>{{ a.ip || "Unknown address" }}</small></span>
            <time class="st-sub" :datetime="a.at">{{ dateTime(a.at) }}</time>
          </li>
        </ul>
      </template>
    </section>

    <!-- Sign out everywhere else -->
    <Transition name="sp-fade"><div v-if="confirmSignOut" class="sp-backdrop" @click="confirmSignOut = false"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="confirmSignOut" class="sp-modal-wrap">
        <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="so-title">
          <div class="sp-modal-head">
            <div>
              <h3 id="so-title">Sign out everywhere else?</h3>
              <p>Any other browser or phone signed in to your account is signed out right away. You stay signed in here.</p>
            </div>
          </div>
          <div class="sp-modal-foot">
            <button type="button" class="sp-btn sp-btn-ghost" @click="confirmSignOut = false">Cancel</button>
            <button type="button" class="sp-btn sp-btn-dark" :disabled="signingOut" @click="signOutOthers">{{ signingOut ? "Signing out…" : "Sign them out" }}</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Two-step sign-in: email code, then passkey -->
    <Transition name="sp-fade"><div v-if="tf.step" class="sp-backdrop" @click="closeTwoFactor"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="tf.step" class="sp-modal-wrap">
        <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="tf-title" @submit.prevent="tf.step === 'otp' ? verifyOtp() : savePasskey()">
          <div class="sp-modal-head">
            <div>
              <h3 id="tf-title">{{ tf.step === "otp" ? "Check your email" : "Choose your passkey" }}</h3>
              <p v-if="tf.step === 'otp'">We sent a 6-digit code to {{ data.account.email }}. It's valid for a few minutes.</p>
              <p v-else>Six digits you'll enter after your password each time you sign in. Don't reuse your PIN.</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeTwoFactor"><span v-html="ICONS.close"></span></button>
          </div>
          <input
            :key="tf.step"
            v-model="tf.code"
            class="sp-input sc-code"
            :type="tf.step === 'otp' ? 'text' : 'password'"
            inputmode="numeric"
            maxlength="6"
            :autocomplete="tf.step === 'otp' ? 'one-time-code' : 'new-password'"
            :aria-label="tf.step === 'otp' ? 'Email code' : 'Passkey'"
            autofocus
            @input="tf.code = tf.code.replace(/\D/g, '').slice(0, 6)"
          />
          <p v-if="tf.error" class="sp-error">{{ tf.error }}</p>
          <div class="sp-modal-foot">
            <button v-if="tf.step === 'otp'" type="button" class="sp-btn sp-btn-ghost" :disabled="tf.busy" @click="startTwoFactor(tf.action)">Send a new code</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="tf.busy || tf.code.length !== 6">
              {{ tf.busy ? "Checking…" : tf.step === "otp" ? (tf.action === "disable" ? "Turn off" : "Continue") : "Turn on" }}
            </button>
          </div>
        </form>
      </div>
    </Transition>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { dateTime, longDate } from "../../../components/vendor/account/accountUi";
import { passwordOk, passwordRules } from "../../../utils/passwordRules";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";
import "../../../components/vendor/account/account.css";

const REASONS = {
  wrong_password: "Wrong password",
  locked: "Blocked after too many tries",
  suspended: "Refused: account suspended",
  unverified: "Refused: account not verified",
  unknown_account: "Unknown account",
};

const data = ref(null);
const error = ref("");

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/account/security");
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your security settings.");
  }
}

// ── Checkup ──
const daysSince = (iso) => (iso ? Math.floor((Date.now() - new Date(iso)) / 86400000) : null);
const checks = computed(() => {
  const d = data.value;
  const age = daysSince(d.password_changed_at);
  return [
    { label: "Two-step sign-in is on", ok: d.two_factor_enabled },
    { label: age === null ? "Password changed through BloomCraft" : "Password changed in the last 6 months", ok: age !== null && age <= 180 },
    { label: "No failed sign-ins in the last 30 days", ok: !d.failed_attempts.count },
  ];
});
const passed = computed(() => checks.value.filter((c) => c.ok).length);
const scoreTone = computed(() => (passed.value === checks.value.length ? "green" : passed.value >= 2 ? "amber" : "red"));

// ── Password ──
const pw = reactive({ current_password: "", password: "", password_confirmation: "" });
const pwErrors = reactive({});
const pwSaving = ref(false);
const show = reactive({ current: false, new: false });
const matches = computed(() => pw.password === pw.password_confirmation);
const rules = computed(() => passwordRules(pw.password));
const passwordAge = computed(() => {
  const age = daysSince(data.value.password_changed_at);
  if (age === null) return "We don't have a record of you changing it here yet.";
  if (age === 0) return "Changed today.";
  return `Last changed ${longDate(data.value.password_changed_at)} (${age} day${age === 1 ? "" : "s"} ago).`;
});
const strength = computed(() => {
  const p = pw.password;
  let score = 0;
  if (p.length >= 8) score++;
  if (p.length >= 12) score++;
  if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
  if (/\d/.test(p)) score++;
  if (/[^A-Za-z0-9]/.test(p)) score++;
  if (p.length < 8) return { pct: 15, label: "Too short", tone: "weak" };
  if (score <= 2) return { pct: 35, label: "Weak", tone: "weak" };
  if (score <= 3) return { pct: 65, label: "Okay", tone: "fair" };
  return { pct: 100, label: "Strong", tone: "strong" };
});

async function changePassword() {
  Object.keys(pwErrors).forEach((k) => delete pwErrors[k]);
  pwSaving.value = true;
  try {
    const { data: res } = await api.put("vendor/profile/change-password", { ...pw });
    toast.success(res.message || "Password updated.");
    Object.assign(pw, { current_password: "", password: "", password_confirmation: "" });
    load();
  } catch (e) {
    for (const [key, list] of Object.entries(e?.response?.data?.errors ?? {})) pwErrors[key] = [].concat(list)[0];
    toast.error(apiMessage(e, "Couldn't update your password."));
  } finally {
    pwSaving.value = false;
  }
}

// ── Two-step sign-in ──
const tf = reactive({ step: null, action: null, code: "", busy: false, error: "" });

async function startTwoFactor(action) {
  tf.busy = true;
  tf.error = "";
  try {
    await api.post("/profile/two-factor/manage/start", { action });
    Object.assign(tf, { step: "otp", action, code: "" });
  } catch (e) {
    const message = apiMessage(e, "Couldn't send the code.");
    if (tf.step) tf.error = message;
    else toast.error(message);
  } finally {
    tf.busy = false;
  }
}
async function verifyOtp() {
  tf.busy = true;
  tf.error = "";
  try {
    await api.post("/profile/two-factor/manage/verify-email-otp", { otp: tf.code });
    if (tf.action === "disable") {
      toast.success("Two-step sign-in is off.");
      closeTwoFactor();
      load();
    } else {
      Object.assign(tf, { step: "passkey", code: "" });
    }
  } catch (e) {
    tf.error = apiMessage(e, "That code didn't work.");
  } finally {
    tf.busy = false;
  }
}
async function savePasskey() {
  tf.busy = true;
  tf.error = "";
  try {
    await api.post("/profile/two-factor/manage/passkey", { passkey: tf.code });
    toast.success("Two-step sign-in is on.");
    closeTwoFactor();
    load();
  } catch (e) {
    tf.error = apiMessage(e, "Couldn't save your passkey.");
  } finally {
    tf.busy = false;
  }
}
function closeTwoFactor() {
  Object.assign(tf, { step: null, action: null, code: "", error: "" });
}

// ── Sessions ──
const confirmSignOut = ref(false);
const signingOut = ref(false);
const device = (s) => {
  const what = [s.browser, s.platform && `on ${s.platform}`].filter(Boolean).join(" ");
  return what || s.device || "Unknown device";
};

async function signOutOthers() {
  signingOut.value = true;
  try {
    const { data: res } = await api.post("vendor/account/security/sign-out-others");
    // The old token no longer works; keep this browser signed in with the new one
    if (res.token) localStorage.setItem("auth_token", res.token);
    toast.success(res.message);
    confirmSignOut.value = false;
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't sign the other devices out."));
  } finally {
    signingOut.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
}
.sc-check {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 16px;
}
.sc-score {
  flex: none;
  display: grid;
  place-items: center;
  width: 72px;
  height: 72px;
  border-radius: 50%;
}
.sc-score b {
  font-size: 20px;
  font-weight: 700;
}
.sc-check-main {
  flex: 1;
  min-width: 0;
}
.sc-check-main h2 {
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 600;
}
.sc-checks {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 18px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 12.5px;
  color: var(--v-amber-text);
}
.sc-checks li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sc-checks li.ok {
  color: var(--v-green-text);
}
.sc-checks :deep(svg) {
  width: 14px;
  height: 14px;
}
.sc-form {
  display: grid;
  gap: 12px;
}
.sc-pw {
  position: relative;
}
.sc-pw .sp-input {
  width: 100%;
  padding-right: 40px;
}
.sc-eye {
  position: absolute;
  top: 50%;
  right: 8px;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 8px;
  background: none;
  color: var(--v-faint);
  cursor: pointer;
  transform: translateY(-50%);
}
.sc-eye :deep(svg) {
  width: 16px;
  height: 16px;
}
.sc-rules {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 14px;
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  font-size: 12.5px;
  color: var(--v-muted);
}
.sc-rules li {
  display: flex;
  align-items: center;
  gap: 6px;
}
.sc-rules svg {
  width: 15px;
  height: 15px;
  flex: none;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.sc-rules li.met {
  color: var(--v-green-text);
  font-weight: 500;
}
.sc-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
.sc-strength {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
}
.sc-strength .ac-meter {
  flex: 1;
}
.sc-strength.weak .ac-meter > i { background: #e53e3e; }
.sc-strength.fair .ac-meter > i { background: #ed8936; }
.sc-strength small {
  width: 60px;
  font-size: 12px;
  font-weight: 600;
}
.sc-strength.weak small { color: var(--v-red-text); }
.sc-strength.fair small { color: var(--v-amber-text); }
.sc-strength.strong small { color: var(--v-green-text); }
.sc-form-foot {
  display: flex;
  justify-content: flex-end;
}
.sc-2fa {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
  padding: 14px;
  border-radius: 14px;
  background: var(--v-amber-soft);
}
.sc-2fa.on {
  background: var(--v-green-soft);
}
.sc-2fa p {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--v-text-2);
}
.sc-email {
  margin: 10px 0 0;
}
.sc-sessions {
  margin-top: 16px;
}
.sc-sk {
  display: grid;
  gap: 12px;
}
.sc-list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.sc-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-top: 1px solid var(--v-border);
}
.sc-list li:first-child {
  border-top: 0;
}
.sc-list .sp-stat-icon {
  width: 34px;
  height: 34px;
}
.sc-list .sp-stat-icon :deep(svg) {
  width: 16px;
  height: 16px;
}
.sc-list-main {
  flex: 1;
  min-width: 0;
}
.sc-list-main b {
  display: block;
  font-size: 13px;
  font-weight: 600;
}
.sc-list-main small {
  display: block;
  font-size: 12px;
  color: var(--v-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sc-list time {
  flex: none;
  white-space: nowrap;
}
.sc-clean {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 13px;
  color: var(--v-green-text);
}
.sc-clean :deep(svg) {
  width: 16px;
  height: 16px;
}
.sc-code {
  width: 100%;
  margin-bottom: 8px;
  font-size: 24px;
  letter-spacing: 0.5em;
  text-align: center;
  font-variant-numeric: tabular-nums;
}
@media (max-width: 640px) {
  .sc-check {
    align-items: flex-start;
  }
  .sc-list li {
    flex-wrap: wrap;
  }
  .sc-list time {
    width: 100%;
    padding-left: 46px;
  }
}
</style>
