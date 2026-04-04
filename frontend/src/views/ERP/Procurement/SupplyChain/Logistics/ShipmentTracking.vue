<template>
  <div class="tracking-page" v-if="shipment">
    
    <div class="page-header">
      <router-link
        to="/erp/procurement/supply-chain/logistics"
        class="back-link"
      >
        <svg
          viewBox="0 0 20 20"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          width="16"
        >
          <path d="M13 16l-6-6 6-6" />
        </svg>
        Logistic Management
      </router-link>
      <div class="header-row">
        <h1 class="tracking-id">{{ shipment.tracking_number }}</h1>
        <div class="header-actions">
          <button
            v-if="shipment.status === 'pending'"
            class="btn-action"
            @click="markShipped"
          >
            📦 Mark as Shipped
          </button>
          <button
            v-if="['in_transit', 'out_for_delivery'].includes(shipment.status)"
            class="btn-primary"
            @click="markReceived"
          >
            ✓ Mark Received
          </button>
        </div>
      </div>
    </div>

    <!-- Delivery Progress Bar -->
    <div class="progress-card">
      <div
        v-for="(step, i) in steps"
        :key="step.status"
        class="progress-step"
        :class="stepClass(step.status)"
      >
        <div class="prog-circle">
          <svg
            v-if="isCompleted(step.status)"
            viewBox="0 0 20 20"
            fill="currentColor"
            width="12"
          >
            <path
              d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"
            />
          </svg>
          <div v-else-if="isCurrent(step.status)" class="prog-pulse"></div>
        </div>
        <div
          v-if="i < steps.length - 1"
          class="prog-line"
          :class="{
            filled:
              isCompleted(steps[i + 1]?.status) ||
              isCurrent(steps[i + 1]?.status),
          }"
        ></div>
        <span class="prog-label">{{ step.label }}</span>
      </div>
    </div>

    <!-- Two Column Layout -->
    <div class="detail-layout">
      <!-- Left: Shipper + Timeline -->
      <div class="left-panel">
        <!-- Shipper Details -->
        <div class="info-card">
          <div class="card-header">
            <h3>Shipper Details</h3>
            <button class="menu-btn">⋯</button>
          </div>
          <div class="shipper-grid">
            <div class="sh-row">
              <span class="sh-lbl">Shipper Code</span
              ><span class="sh-val mono">{{ shipment.id }}</span>
            </div>
            <div class="sh-row">
              <span class="sh-lbl">{{
                shipment.purchase_order?.supplier?.contact_person
              }}</span>
              <div class="sh-person">
                <div class="person-avatar">
                  {{ shipment.purchase_order?.supplier?.company_name?.[0] }}
                </div>
                <span>{{
                  shipment.purchase_order?.supplier?.company_name
                }}</span>
              </div>
            </div>
            <div class="sh-row">
              <span class="sh-lbl">Contact</span
              ><span class="sh-val">{{
                shipment.purchase_order?.supplier?.phone || "—"
              }}</span>
            </div>
            <div class="sh-row">
              <span class="sh-lbl">Email</span
              ><span class="sh-val">{{
                shipment.purchase_order?.supplier?.email || "—"
              }}</span>
            </div>
          </div>
        </div>

        <!-- Stock Availability -->
        <div class="info-card">
          <div class="card-header">
            <h3>Stock Availability</h3>
            <button class="menu-btn">⋯</button>
          </div>
          <div
            class="availability-banner"
            :class="{ positive: shipment.status !== 'failed' }"
          >
            <svg viewBox="0 0 20 20" fill="currentColor" width="14">
              <path
                d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z"
              />
            </svg>
            Estimated Order Delivery
            <span class="avail-date">{{ estimatedDelivery }}</span>
          </div>

          <div class="from-to">
            <div class="ft-col">
              <div class="ft-lbl">From</div>
              <div class="ft-addr">
                {{
                  shipment.purchase_order?.supplier?.address ||
                  "1801 Thornridge Cir."
                }}
              </div>
            </div>
            <div class="ft-arrow">→</div>
            <div class="ft-col">
              <div class="ft-lbl">To</div>
              <div class="ft-addr">{{ destinationAddress }}</div>
            </div>
          </div>

          <div class="carrier-badge">
            <div class="carrier-icon">🚚</div>
            <div>
              <div class="carrier-name">{{ shipment.carrier }}</div>
              <div class="carrier-id">
                Tracking: {{ shipment.tracking_number }}
              </div>
            </div>
          </div>

          <div class="timeline-events">
            <div
              v-for="(event, i) in deliveryEvents"
              :key="i"
              class="tl-event"
              :class="{ done: event.done, future: !event.done }"
            >
              <div class="tl-dot-sm"></div>
              <div v-if="i < deliveryEvents.length - 1" class="tl-vert"></div>
              <div class="event-content">
                <div class="event-name">{{ event.name }}</div>
                <div class="event-addr">{{ event.address }}</div>
                <div class="event-time">
                  <span class="event-date">{{ event.date }}</span>
                  <span class="event-hour">{{ event.time }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Map -->
      <div class="map-panel">
        <div class="card-header">
          <h3>Map Over View</h3>
          <button class="menu-btn">⋯</button>
        </div>
        <div class="map-placeholder">
          <div class="map-grid">
            <div
              v-for="i in 100"
              :key="i"
              class="map-cell"
              :class="mapCellClass(i)"
            ></div>
          </div>
          <div class="map-marker">
            <svg viewBox="0 0 24 32" fill="none" width="24">
              <path
                d="M12 0C5.4 0 0 5.4 0 12c0 8.4 12 20 12 20S24 20.4 24 12C24 5.4 18.6 0 12 0zm0 17a5 5 0 110-10 5 5 0 010 10z"
                fill="#ef4444"
              />
            </svg>
          </div>
          <div class="map-controls">
            <button class="map-ctrl">+</button>
            <button class="map-ctrl">−</button>
            <button class="map-ctrl">⊕</button>
          </div>
          <div class="map-layers">⊞</div>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="loading-state"><div class="spinner"></div></div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { shipmentService } from "../../../../../services/shipmentService";


const route = useRoute();
const shipment = ref(null);

const STATUS_ORDER = ["pending", "in_transit", "out_for_delivery", "delivered"];

const steps = [
  { status: "pending", label: "Processing" },
  { status: "in_transit", label: "In Transit" },
  { status: "out_for_delivery", label: "Delivery" },
  { status: "delivered", label: "Order Delivered" },
];

function statusIndex(s) {
  return STATUS_ORDER.indexOf(s);
}
function isCompleted(s) {
  return statusIndex(s) < statusIndex(shipment.value?.status);
}
function isCurrent(s) {
  return s === shipment.value?.status;
}
function stepClass(s) {
  return { done: isCompleted(s), current: isCurrent(s) };
}

const estimatedDelivery = computed(() => {
  const d = shipment.value?.shipped_date;
  if (!d) return "TBD";
  const date = new Date(d);
  date.setDate(date.getDate() + 7);
  return date.toLocaleDateString("en-US", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
});

const destinationAddress = computed(
  () => "6391 Elgin St. Celina, Delaware 10299",
);

const deliveryEvents = computed(() => {
  if (!shipment.value) return [];
  return [
    {
      name: "In Transit",
      address: "2118 Thornridge Cir Syracuse, Connecticut 35624",
      date: "09 June 2025",
      time: "08:00 AM",
      done: true,
    },
    {
      name: "Delivery",
      address: "4517 Washington Ave. Manchester, Kentucky 39495",
      date: "10 June 2025",
      time: "08:00 AM",
      done:
        shipment.value.status === "out_for_delivery" ||
        shipment.value.status === "delivered",
    },
    {
      name: "Order Delivered",
      address: "6391 Elgin St. Celina, Delaware 10299",
      date: "12 June 2025",
      time: "08:00 AM",
      done: shipment.value.status === "delivered",
    },
  ];
});

async function markShipped() {
  await shipmentService.markShipped(shipment.value.id);
  shipment.value.status = "in_transit";
  shipment.value.shipped_date = new Date().toISOString();
}

async function markReceived() {
  await shipmentService.markReceived(shipment.value.id);
  shipment.value.status = "delivered";
  shipment.value.received_date = new Date().toISOString();
}

function mapCellClass(i) {
  const road = [
    12, 13, 14, 22, 23, 24, 32, 33, 34, 42, 43, 44, 46, 47, 56, 57, 66, 67, 76,
    77, 86, 87,
  ];
  return road.includes(i) ? "road" : i % 17 === 0 ? "building" : "";
}

onMounted(async () => {
  const res = await shipmentService.find(route.params.id);
  shipment.value = res.data || res;
});
</script>

<style scoped>
* {
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.tracking-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.loading-state {
  display: flex;
  justify-content: center;
  padding: 80px;
}
.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #10b981;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #6b7280;
  text-decoration: none;
  font-size: 13px;
}
.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}
.tracking-id {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin: 0;
  font-family: monospace;
}
.header-actions {
  display: flex;
  gap: 10px;
}
.btn-primary {
  padding: 9px 18px;
  background: #10b981;
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}
.btn-action {
  padding: 9px 18px;
  background: #fff;
  color: #374151;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}

/* Progress */
.progress-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  padding: 20px 24px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0;
  overflow-x: auto;
}
.progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  position: relative;
  flex: 1;
}
.prog-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 2.5px solid #e5e7eb;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  z-index: 1;
}
.progress-step.done .prog-circle {
  background: #10b981;
  border-color: #10b981;
}
.progress-step.current .prog-circle {
  border-color: #10b981;
  background: #ecfdf5;
}
.prog-pulse {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #10b981;
  animation: pulse 1.5s infinite;
}
@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.3);
    opacity: 0.7;
  }
}
.prog-line {
  position: absolute;
  top: 16px;
  left: calc(50% + 16px);
  right: calc(-50% + 16px);
  height: 2.5px;
  background: #e5e7eb;
  z-index: 0;
}
.prog-line.filled {
  background: #10b981;
}
.prog-label {
  font-size: 12px;
  font-weight: 500;
  color: #9ca3af;
  white-space: nowrap;
}
.progress-step.done .prog-label {
  color: #10b981;
}
.progress-step.current .prog-label {
  color: #111827;
  font-weight: 700;
}

/* Layout */
.detail-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.left-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-card {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  overflow: hidden;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #f0f2f5;
}
.card-header h3 {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}
.menu-btn {
  border: none;
  background: none;
  font-size: 16px;
  color: #9ca3af;
  cursor: pointer;
}

.shipper-grid {
  padding: 14px 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.sh-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13.5px;
}
.sh-lbl {
  color: #6b7280;
}
.sh-val {
  font-weight: 500;
  color: #374151;
}
.sh-val.mono {
  font-family: monospace;
}
.sh-person {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: #111827;
}
.person-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #ede9fe;
  color: #6d28d9;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.availability-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 12px 18px;
  padding: 8px 14px;
  border-radius: 8px;
  background: #f3f4f6;
  font-size: 13px;
  color: #374151;
}
.availability-banner.positive {
  background: #dcfce7;
  color: #16a34a;
}
.avail-date {
  margin-left: auto;
  font-weight: 600;
  font-size: 12px;
}

.from-to {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 0 18px 12px;
}
.ft-col {
  flex: 1;
}
.ft-lbl {
  font-size: 11px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 3px;
}
.ft-addr {
  font-size: 12.5px;
  color: #374151;
  line-height: 1.5;
}
.ft-arrow {
  color: #9ca3af;
  padding-top: 20px;
  flex-shrink: 0;
}

.carrier-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 18px 14px;
  padding: 10px 14px;
  background: #f9fafb;
  border-radius: 10px;
}
.carrier-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #ecfdf5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}
.carrier-name {
  font-weight: 600;
  color: #111827;
  font-size: 13.5px;
}
.carrier-id {
  font-size: 11.5px;
  color: #9ca3af;
}

.timeline-events {
  padding: 0 18px 16px;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.tl-event {
  display: flex;
  gap: 12px;
  position: relative;
}
.tl-dot-sm {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #e5e7eb;
  flex-shrink: 0;
  margin-top: 5px;
}
.tl-event.done .tl-dot-sm {
  background: #10b981;
}
.tl-vert {
  position: absolute;
  left: 4.5px;
  top: 14px;
  width: 1px;
  height: calc(100% + 8px);
  background: #e5e7eb;
}
.tl-event.done .tl-vert {
  background: #10b981;
}
.event-content {
  padding-bottom: 20px;
}
.event-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #374151;
}
.event-name.future {
  color: #9ca3af;
}
.event-addr {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 2px;
}
.event-time {
  display: flex;
  gap: 10px;
  margin-top: 4px;
}
.event-date,
.event-hour {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

/* Map Panel */
.map-panel {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.map-placeholder {
  flex: 1;
  position: relative;
  min-height: 380px;
  background: #f0f4f8;
  overflow: hidden;
}
.map-grid {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  height: 100%;
  position: absolute;
  inset: 0;
}
.map-cell {
  border: 0.5px solid rgba(255, 255, 255, 0.3);
}
.map-cell.road {
  background: rgba(255, 255, 255, 0.7);
}
.map-cell.building {
  background: rgba(180, 180, 190, 0.5);
  border-radius: 2px;
}
.map-marker {
  position: absolute;
  top: 40%;
  left: 55%;
  transform: translate(-50%, -100%);
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}
.map-controls {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.map-ctrl {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}
.map-layers {
  position: absolute;
  bottom: 14px;
  right: 14px;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
</style>
