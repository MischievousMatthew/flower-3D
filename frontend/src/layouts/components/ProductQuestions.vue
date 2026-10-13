<template>
  <div class="pq">
    <div class="pq-head">
      <p v-if="orderId" class="pq-count">Ask the shop anything about this order, like delivery time or changes.</p>
      <p v-else class="pq-count">{{ answered.length ? `${answered.length} answered ${answered.length === 1 ? "question" : "questions"}` : "No questions answered yet" }}</p>
      <button v-if="canAsk && !asking" type="button" class="pq-ask-btn" @click="asking = true">Ask the shop</button>
    </div>

    <!-- Ask -->
    <form v-if="asking" class="pq-form" @submit.prevent="ask">
      <label for="pq-question" class="pq-label">Your question</label>
      <textarea
        id="pq-question"
        v-model="question"
        rows="3"
        maxlength="1000"
        :placeholder="orderId ? 'e.g. Can you deliver before 10 AM?' : 'e.g. Can I get this in pink? Does it come with a card?'"
      ></textarea>
      <p v-if="formError" class="pq-error">{{ formError }}</p>
      <div class="pq-form-foot">
        <small>The shop is notified, and you'll get a notification when they answer.</small>
        <div>
          <button type="button" class="pq-ghost" @click="cancel">Cancel</button>
          <button type="submit" class="pq-primary" :disabled="sending || question.trim().length < 5">{{ sending ? "Sending…" : "Send question" }}</button>
        </div>
      </div>
    </form>
    <p v-else-if="!signedIn && !orderId" class="pq-hint">Sign in to ask the shop a question about this product.</p>

    <!-- Mine, still waiting or answered -->
    <ul v-if="mine.length" class="pq-list mine">
      <li v-for="q in mine" :key="`m${q.id}`">
        <p class="pq-q"><span class="pq-tag">Your question</span>{{ q.question }}</p>
        <p v-if="q.answer" class="pq-a"><b>Shop:</b>&nbsp;{{ q.answer }}</p>
        <p v-else class="pq-waiting">Waiting for the shop to answer · asked {{ ago(q.asked_at) }}</p>
      </li>
    </ul>

    <!-- Everyone's answered questions -->
    <div v-if="loading" class="pq-sk"><span></span><span></span></div>
    <ul v-else-if="!orderId && answered.length" class="pq-list">
      <li v-for="q in shown" :key="q.id">
        <p class="pq-q"><span class="pq-letter">Q</span>{{ q.question }}</p>
        <p class="pq-a"><span class="pq-letter a">A</span>{{ q.answer }}</p>
        <small class="pq-meta">Asked by {{ q.asked_by }} · answered {{ ago(q.answered_at) }}</small>
      </li>
    </ul>
    <button v-if="!orderId && answered.length > 3" type="button" class="pq-more" @click="all = !all">{{ all ? "Show fewer" : `Show all ${answered.length}` }}</button>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";

// One of the two: questions about a product (public once answered) or about one of my orders (private)
const props = defineProps({
  productId: { type: Number, default: null },
  orderId: { type: Number, default: null },
});
const subject = computed(() => (props.orderId ? { order_id: props.orderId } : { product_id: props.productId }));

const { user } = useAuth();
const answered = ref([]);
const mineAll = ref([]);
const loading = ref(true);
const asking = ref(false);
const question = ref("");
const sending = ref(false);
const formError = ref("");
const all = ref(false);

const signedIn = computed(() => !!user.value);
// Order tracking is only ever open to the signed-in customer
const canAsk = computed(() => !!props.orderId || (signedIn.value && (user.value?.role ?? "customer") === "customer"));
// My answered ones already show in the public list when the shop made them public
const mine = computed(() => mineAll.value.filter((q) => !answered.value.some((a) => a.id === q.id)));
const shown = computed(() => (all.value ? answered.value : answered.value.slice(0, 3)));

function ago(iso) {
  if (!iso) return "";
  const days = Math.floor((Date.now() - new Date(iso)) / 86_400_000);
  if (days < 1) return "today";
  if (days === 1) return "yesterday";
  if (days < 30) return `${days} days ago`;
  return new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

async function load() {
  loading.value = true;
  try {
    if (props.productId && !props.orderId) {
      const { data } = await api.get(`products/${props.productId}/questions`);
      answered.value = data.data ?? [];
    }
  } catch {
    answered.value = [];
  } finally {
    loading.value = false;
  }
  if (canAsk.value) {
    try {
      const { data } = await api.get("customer/inquiries", { params: subject.value });
      mineAll.value = data.data ?? [];
    } catch {
      mineAll.value = [];
    }
  }
}

async function ask() {
  formError.value = "";
  sending.value = true;
  try {
    await api.post("customer/inquiries", { ...subject.value, question: question.value.trim() });
    question.value = "";
    asking.value = false;
    await load();
  } catch (e) {
    const errors = e?.response?.data?.errors;
    formError.value = errors ? Object.values(errors).flat()[0] : e?.response?.data?.message || "Couldn't send your question. Please try again.";
  } finally {
    sending.value = false;
  }
}
function cancel() {
  asking.value = false;
  question.value = "";
  formError.value = "";
}

watch(() => [props.productId, props.orderId], () => {
  cancel();
  all.value = false;
  load();
});
onMounted(load);
</script>

<style scoped>
.pq {
  font-family: inherit;
}
.pq-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.pq-count {
  margin: 0;
  font-size: 13px;
  color: #718096;
}
.pq-ask-btn,
.pq-primary,
.pq-ghost {
  padding: 8px 14px;
  border-radius: 999px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}
.pq-ask-btn {
  border: 1px solid #48bb78;
  background: #fff;
  color: #2f855a;
}
.pq-ask-btn:hover {
  background: #f0fff4;
}
.pq-primary {
  border: 1px solid #48bb78;
  background: #48bb78;
  color: #fff;
}
.pq-primary:disabled {
  opacity: 0.55;
  cursor: default;
}
.pq-ghost {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #4a5568;
}
.pq-form {
  margin-top: 12px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fafcfb;
}
.pq-label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
}
.pq-form textarea {
  box-sizing: border-box;
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-family: inherit;
  font-size: 14px;
  resize: vertical;
}
.pq-form textarea:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.2);
}
.pq-form-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}
.pq-form-foot small {
  font-size: 12px;
  color: #718096;
}
.pq-form-foot div {
  display: flex;
  gap: 8px;
}
.pq-error {
  margin: 8px 0 0;
  font-size: 12.5px;
  color: #c53030;
}
.pq-hint {
  margin: 8px 0 0;
  font-size: 13px;
  color: #a0aec0;
}
.pq-list {
  margin: 14px 0 0;
  padding: 0;
  list-style: none;
}
.pq-list li {
  padding: 12px 0;
  border-top: 1px solid #edf2f7;
}
.pq-list li:first-child {
  border-top: 0;
}
.pq-list.mine li {
  padding: 12px 14px;
  border: 1px dashed #c6f6d5;
  border-radius: 12px;
  background: #f8fdf9;
}
.pq-list.mine li + li {
  margin-top: 8px;
}
.pq-q,
.pq-a {
  display: flex;
  gap: 10px;
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  color: #1a202c;
  overflow-wrap: anywhere;
}
.pq-q {
  font-weight: 600;
}
.pq-a {
  margin-top: 6px;
  color: #2d3748;
}
.pq-letter {
  flex: none;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  background: #edf2f7;
  font-size: 12px;
  font-weight: 700;
  color: #4a5568;
}
.pq-letter.a {
  background: #c6f6d5;
  color: #22543d;
}
.pq-tag {
  flex: none;
  padding: 1px 8px;
  border-radius: 999px;
  background: #c6f6d5;
  font-size: 11px;
  font-weight: 600;
  color: #22543d;
  align-self: center;
}
.pq-waiting {
  margin: 6px 0 0;
  font-size: 12.5px;
  color: #b7791f;
}
.pq-meta {
  display: block;
  margin: 6px 0 0 32px;
  font-size: 12px;
  color: #a0aec0;
}
.pq-more {
  margin-top: 6px;
  padding: 0;
  border: 0;
  background: none;
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  color: #2f855a;
  cursor: pointer;
}
.pq-sk {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 14px;
}
.pq-sk span {
  height: 42px;
  border-radius: 10px;
  background: linear-gradient(90deg, #edf2f7 25%, #f7fafc 37%, #edf2f7 63%);
  background-size: 400% 100%;
  animation: pq-shimmer 1.4s ease infinite;
}
@keyframes pq-shimmer {
  0% { background-position: 100% 50%; }
  100% { background-position: 0 50%; }
}
</style>
