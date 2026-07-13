<template>
  <main class="resubmission-page">
    <section class="resubmission-card">
      <p class="eyebrow">BloomCraft Vendor Review</p>
      <h1>Vendor Requirement Resubmission</h1>

      <div v-if="loading" class="state-message">Loading your requested requirements…</div>
      <div v-else-if="loadError" class="state-message error">{{ loadError }}</div>

      <template v-else>
        <p class="intro">Hello {{ vendorName }}. Please update only the requirements listed below.</p>
        <form @submit.prevent="submit" enctype="multipart/form-data">
          <article v-for="item in items" :key="item.id" class="requirement-card">
            <div class="requirement-heading">
              <h2>{{ item.label }}</h2>
              <span class="status" :class="item.status">{{ statusLabel(item.status) }}</span>
            </div>
            <p v-if="item.rejection_reason" class="reason"><strong>Reason:</strong> {{ item.rejection_reason }}</p>

            <template v-if="item.status === 'pending_resubmission'">
              <label class="input-label" :for="`field-${item.id}`">{{ item.type === 'file' ? 'Upload corrected file' : 'Provide corrected information' }}</label>
              <input v-if="item.type === 'file'" :id="`field-${item.id}`" type="file" :accept="fileAccept(item)" @change="setFile(item.field_name, $event)" required />
              <textarea v-else-if="item.type === 'textarea'" :id="`field-${item.id}`" v-model="values[item.field_name]" rows="5" required></textarea>
              <input v-else :id="`field-${item.id}`" v-model="values[item.field_name]" type="text" required />
            </template>
            <p v-else class="reviewing">Your update was received and is waiting for review.</p>
          </article>

          <p v-if="submitError" class="form-error">{{ submitError }}</p>
          <button v-if="pendingItems.length" class="submit-button" type="submit" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit Updated Requirements' }}
          </button>
          <p v-else class="state-message">All requested requirements are currently under review.</p>
        </form>
      </template>
    </section>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../plugins/axios';

const route = useRoute();
const loading = ref(true);
const loadError = ref('');
const submitError = ref('');
const submitting = ref(false);
const vendorName = ref('Vendor');
const items = ref([]);
const values = ref({});
const files = ref({});

const token = computed(() => route.params.token);
const pendingItems = computed(() => items.value.filter((item) => item.status === 'pending_resubmission'));

const load = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await api.get(`/vendor/resubmission/${token.value}`);
    vendorName.value = data.vendor_name || 'Vendor';
    items.value = data.items || [];
    values.value = Object.fromEntries(items.value.filter((item) => item.type !== 'file').map((item) => [item.field_name, item.resubmitted_value || '']));
  } catch (error) {
    loadError.value = error.response?.data?.message || 'This resubmission link is unavailable.';
  } finally {
    loading.value = false;
  }
};

const setFile = (field, event) => { files.value[field] = event.target.files?.[0] || null; };
const fileAccept = (item) => item.label.includes('Selfie') || item.label.includes('Logo') ? '.jpg,.jpeg,.png' : '.jpg,.jpeg,.png,.pdf';
const statusLabel = (status) => ({ pending_resubmission: 'Needs update', resubmitted: 'Under review' }[status] || status);

const submit = async () => {
  submitError.value = '';
  const form = new FormData();
  for (const item of pendingItems.value) {
    const value = item.type === 'file' ? files.value[item.field_name] : values.value[item.field_name];
    if (!value) {
      submitError.value = `Please complete ${item.label}.`;
      return;
    }
    form.append(`fields[${item.field_name}]`, value);
  }
  submitting.value = true;
  try {
    await api.post(`/vendor/resubmission/${token.value}`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
    await load();
  } catch (error) {
    submitError.value = error.response?.data?.message || 'Unable to submit the updated requirements.';
  } finally {
    submitting.value = false;
  }
};

onMounted(load);
</script>

<style scoped>
.resubmission-page { min-height: 100vh; padding: 48px 20px; background: #f7f2eb; font-family: Arial, sans-serif; color: #2f241b; }
.resubmission-card { max-width: 720px; margin: auto; padding: 34px; background: #fff; border: 1px solid #e8ddd2; border-radius: 20px; box-shadow: 0 12px 32px rgba(74, 51, 32, .08); }
.eyebrow { margin: 0 0 8px; color: #9e7250; font-size: 12px; font-weight: 700; letter-spacing: .11em; text-transform: uppercase; }
h1 { margin: 0 0 12px; font-size: 30px; } .intro { color: #5f4c3d; line-height: 1.6; }
.requirement-card { margin-top: 18px; padding: 18px; border: 1px solid #eadfd5; border-radius: 12px; background: #fffdfa; }
.requirement-heading { display: flex; justify-content: space-between; gap: 12px; align-items: center; } h2 { margin: 0; font-size: 18px; }
.status { padding: 5px 9px; border-radius: 999px; font-size: 12px; font-weight: 700; } .pending_resubmission { background: #fff3cd; color: #815b00; } .resubmitted { background: #dbeafe; color: #1e4f91; }
.reason { margin: 12px 0; padding: 10px; border-radius: 8px; background: #fff5f2; color: #6f443d; line-height: 1.5; }
.input-label { display: block; margin: 13px 0 6px; font-size: 14px; font-weight: 700; } input, textarea { box-sizing: border-box; width: 100%; padding: 11px; border: 1px solid #d7c7b8; border-radius: 8px; font: inherit; } textarea:focus, input:focus { outline: 2px solid #d8b28d; border-color: #9e7250; }
.submit-button { width: 100%; margin-top: 22px; padding: 13px; border: 0; border-radius: 8px; background: #9e7250; color: #fff; font-weight: 700; cursor: pointer; } .submit-button:disabled { opacity: .65; cursor: wait; }
.state-message { padding: 24px 0; color: #5f4c3d; text-align: center; } .error, .form-error { color: #a13f35; } .reviewing { color: #526579; font-style: italic; } .form-error { margin: 15px 0 0; }
@media (max-width: 600px) { .resubmission-page { padding: 20px 12px; } .resubmission-card { padding: 22px; } .requirement-heading { align-items: flex-start; flex-direction: column; } }
</style>
