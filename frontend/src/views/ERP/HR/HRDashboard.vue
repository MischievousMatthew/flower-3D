<template>
  <div class="hr-dashboard">
    <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />

    <!-- Top Bar -->
    <header class="top-bar">
      <div class="search-container">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        >
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input type="text" placeholder="Search..." v-model="searchQuery" />
      </div>

      <div class="top-right-actions">
        <button class="icon-btn">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
        </button>
        <button class="icon-btn">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="9" y1="3" x2="9" y2="21"></line>
          </svg>
        </button>
      </div>
    </header>

    <!-- Info Banner -->
    <div class="info-banner" v-if="showBanner">
      <div class="banner-content">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="currentColor"
        >
          <path
            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"
          />
        </svg>
        <span>
          Explore how to navigate the dashboard and make use of its full
          potential!
          <a href="#" class="smart-link">Get Started the Smart Way</a>
        </span>
      </div>
      <button class="banner-close" @click="showBanner = false">×</button>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Employee Overview</h1>
        <div class="last-updated">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span>Last updated {{ lastUpdated }}</span>
        </div>
      </div>

      <div class="header-actions">
        <div class="segmented-control">
          <button
            v-for="p in periodOptions"
            :key="p.value"
            :class="['segment-btn', { active: selectedPeriod === p.value }]"
            @click="changePeriod(p.value)"
          >
            {{ p.label }}
          </button>
        </div>

        <button class="action-btn" @click="refreshData">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path
              d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"
            />
          </svg>
          Refresh
        </button>

        <div class="dropdown-wrapper" @click.stop>
          <button
            class="action-btn primary-btn"
            @click="showExportOptions = !showExportOptions"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Export
          </button>
          <div class="dropdown-menu export-menu" v-if="showExportOptions">
            <div class="menu-label">EXPORT CATEGORY</div>
            <button @click="exportData('employee_list')">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              Employee List
            </button>
            <button @click="exportData('attendance_summary')">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              Attendance Summary
            </button>
            <button @click="exportData('leave_records')">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path
                  d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                ></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
              Leave Records
            </button>
            <hr />
            <button @click="exportData('all')" class="full-export">
              Export All (Standard)
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card" v-for="stat in stats" :key="stat.label">
        <div class="stat-header">
          <h3>{{ stat.label }}</h3>
          <button class="more-btn">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="currentColor"
            >
              <circle cx="12" cy="5" r="2"></circle>
              <circle cx="12" cy="12" r="2"></circle>
              <circle cx="12" cy="19" r="2"></circle>
            </svg>
          </button>
        </div>
        <div class="stat-value">
          <span class="number">{{ stat.value }}</span>
          <span :class="['trend', stat.trendDirection]" v-if="stat.trend">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <polyline
                :points="
                  stat.trendDirection === 'up'
                    ? '18 15 12 9 6 15'
                    : '6 9 12 15 18 9'
                "
              ></polyline>
            </svg>
            {{ stat.trend }}
          </span>
        </div>
        <p class="stat-subtitle">{{ stat.subtitle }}</p>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="main-content-grid">
      <!-- 1. Real-time Attendance Monitoring -->
      <div class="card monitoring-card">
        <div class="card-header">
          <div class="header-with-badge">
            <h2>Real-time Attendance</h2>
            <span class="live-dot-container">
              <span class="live-dot"></span>
              LIVE
            </span>
          </div>
          <button class="more-btn">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="currentColor"
            >
              <circle cx="12" cy="5" r="2"></circle>
              <circle cx="12" cy="12" r="2"></circle>
              <circle cx="12" cy="19" r="2"></circle>
            </svg>
          </button>
        </div>
        <div class="monitoring-scroll">
          <div
            v-for="emp in realTimeAttendance"
            :key="emp.id"
            class="monitor-row"
          >
            <div class="emp-profile">
              <img :src="emp.avatar" class="avatar-sm" :alt="emp.name" />
              <div class="emp-details">
                <span class="name">{{ emp.name }}</span>
                <span class="time">{{ emp.time }}</span>
              </div>
            </div>
            <span :class="['status-chip', emp.statusClass]">
              {{ emp.statusText }}
            </span>
          </div>
          <div v-if="realTimeAttendance.length === 0" class="empty-message">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="48"
              height="48"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <p>No attendance records today</p>
          </div>
        </div>
      </div>

      <!-- 2. Compact Attendance Report -->
      <div class="card report-card-mini">
        <div class="card-header">
          <h2>Attendance Summary</h2>
        </div>

        <div class="quick-stats-row">
          <div class="radial-stat">
            <svg class="radial-svg" viewBox="0 0 36 36">
              <path
                class="circle-bg"
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
              />
              <path
                class="circle"
                :stroke-dasharray="`${attendanceRate}, 100`"
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
              />
              <text x="18" y="20.35" class="percentage">
                {{ attendanceRate }}%
              </text>
            </svg>
            <span class="radial-label">Attendance Rate</span>
          </div>

          <div class="mini-trend-box">
            <span class="trend-val up">{{ attendanceRate }}%</span>
            <span class="trend-label">{{ periodLabel }}</span>
          </div>
        </div>

        <div class="horizontal-heatmap">
          <div class="heatmap-header">
            <span>Last 7 Days</span>
            <div class="mini-legend">
              <div class="l-dot lvl-0"></div>
              <div class="l-dot lvl-2"></div>
              <div class="l-dot lvl-4"></div>
            </div>
          </div>
          <div class="heatmap-strip">
            <div
              v-for="day in heatmapData"
              :key="day.name"
              class="strip-col"
              :title="day.name"
            >
              <div class="strip-cell" :class="`lvl-${day.level}`"></div>
              <span class="strip-label">{{ day.name[0] }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Leave Applications -->
      <div class="card applications-card">
        <div class="card-header">
          <h2>Leave Applications</h2>
          <button
            class="text-link"
            @click="$router.push('/erp/hr/leave/management-requests')"
          >
            View Requests
          </button>
        </div>
        <div class="leave-list">
          <div v-for="app in applications" :key="app.id" class="leave-item">
            <div class="leave-profile">
              <img :src="app.avatar" class="leave-avatar" :alt="app.name" />
              <div class="leave-main">
                <span class="applicant">{{ app.name }}</span>
                <span class="duration">{{ app.reason }}</span>
              </div>
            </div>
            <div :class="['status-badge-v2', app.statusClass]">
              {{ app.statusText }}
            </div>
          </div>
          <div v-if="applications.length === 0" class="empty-message">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="48"
              height="48"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            >
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <p>No leave applications</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Employees Table -->
    <div class="card table-card">
      <div class="card-header">
        <h2>Employees</h2>
        <div class="table-actions">
          <button class="filter-btn">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <polygon
                points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"
              ></polygon>
            </svg>
            Filter
          </button>

          <div class="search-box">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input
              type="text"
              placeholder="Search employee"
              v-model="employeeSearch"
            />
          </div>

          <button class="export-btn" @click="exportEmployees">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            Export
          </button>
        </div>
      </div>

      <div class="table-wrapper">
        <table class="employees-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Employment ID</th>
              <th>Department</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="employee in filteredEmployees" :key="employee.id">
              <td class="name-cell">
                <img
                  :src="getEmployeeAvatar(employee)"
                  :alt="employee.full_name"
                  class="employee-avatar"
                />
                <span>{{ employee.full_name }}</span>
              </td>
              <td>{{ employee.employee_id }}</td>
              <td>{{ employee.department }}</td>
              <td>{{ employee.work_email }}</td>
              <td>{{ employee.position }}</td>
              <td>
                <span
                  :class="[
                    'status-badge',
                    getStatusClass(employee.employment_status),
                  ]"
                >
                  {{ employee.employment_status }}
                </span>
              </td>
              <td>
                <button class="more-btn" @click="viewEmployee(employee)">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                  >
                    <circle cx="12" cy="5" r="2"></circle>
                    <circle cx="12" cy="12" r="2"></circle>
                    <circle cx="12" cy="19" r="2"></circle>
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import LoadingOverlay from "../../../layouts/components/LoadingOverlay.vue";
import api from "../../../plugins/axios";
import attendanceApi from "../../../services/attendanceApi";

const router = useRouter();

// State
const searchQuery = ref("");
const employeeSearch = ref("");
const showBanner = ref(true);
const isLoading = ref(false);
const isLoadingMessage = ref("Loading...");
const selectedPeriod = ref("today");
const showExportOptions = ref(false);
const lastUpdated = ref("just now");

// Period options
const periodOptions = ref([
  { value: "today", label: "Day" },
  { value: "week", label: "Week" },
  { value: "month", label: "Month" },
  { value: "year", label: "Year" },
]);

// Stats Data
const stats = ref([
  {
    label: "Total Employee",
    value: "0",
    trend: "",
    trendDirection: "up",
    subtitle: "Active employees",
  },
  {
    label: "Today Presents",
    value: "00",
    trend: "",
    trendDirection: "up",
    subtitle: "Checked in today",
  },
  {
    label: "Today Absents",
    value: "00",
    trend: "",
    trendDirection: "down",
    subtitle: "Not checked in",
  },
  {
    label: "Today Leave",
    value: "00",
    trend: "",
    trendDirection: "down",
    subtitle: "Approved leaves",
  },
]);

// Real-time Data
const attendanceRate = ref(0);
const realTimeAttendance = ref([]);
const applications = ref([]);
const employees = ref([]);
const heatmapData = ref([]);

// Auto-refresh interval
let refreshInterval = null;

// Computed
const periodLabel = computed(() => {
  const period = periodOptions.value.find(
    (p) => p.value === selectedPeriod.value,
  );
  return period ? period.label : "Today";
});

const toLocalDate = (utcStr) => new Date(utcStr).toLocaleDateString("en-CA");

const filteredEmployees = computed(() => {
  if (!employeeSearch.value) return employees.value;

  const search = employeeSearch.value.toLowerCase();
  return employees.value.filter(
    (emp) =>
      emp.full_name?.toLowerCase().includes(search) ||
      emp.work_email?.toLowerCase().includes(search) ||
      emp.department?.toLowerCase().includes(search) ||
      emp.position?.toLowerCase().includes(search) ||
      emp.employee_id?.toLowerCase().includes(search),
  );
});

// Functions
const getDateRange = () => {
  const now = new Date();
  let startDate, endDate;

  switch (selectedPeriod.value) {
    case "today":
      startDate = endDate = now.toISOString().split("T")[0];
      break;
    case "week":
      const weekStart = new Date(now);
      weekStart.setDate(now.getDate() - now.getDay());
      startDate = weekStart.toISOString().split("T")[0];
      endDate = now.toISOString().split("T")[0];
      break;
    case "month":
      startDate = new Date(now.getFullYear(), now.getMonth(), 1)
        .toISOString()
        .split("T")[0];
      endDate = now.toISOString().split("T")[0];
      break;
    case "year":
      startDate = new Date(now.getFullYear(), 0, 1).toISOString().split("T")[0];
      endDate = now.toISOString().split("T")[0];
      break;
  }

  return { startDate, endDate };
};

const changePeriod = (period) => {
  if (selectedPeriod.value === period) return;
  selectedPeriod.value = period;

  fetchDashboardData();
};

const getEmployeeAvatar = (employee) => {
  if (!employee)
    return "https://ui-avatars.com/api/?name=NA&background=4f46e5&color=fff";
  if (employee.avatar_url) return employee.avatar_url;
  const name = employee.full_name || employee.first_name || "User";
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=4f46e5&color=fff`;
};

const getStatusClass = (status) => {
  const statusMap = {
    Regular: "regular",
    Probationary: "probationary",
    Contractual: "contractual",
    Active: "active",
    Inactive: "inactive",
    "Part-time": "part-time",
  };
  return statusMap[status] || "default";
};

const formatTime = (time) => {
  if (!time) return "N/A";
  try {
    return new Date(`2000-01-01 ${time}`).toLocaleTimeString("en-US", {
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return time;
  }
};

const formatLeaveType = (type) => {
  if (!type) return "N/A";
  return type.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatDateRange = (start, end) => {
  try {
    const startDate = new Date(start);
    const endDate = new Date(end);

    if (start === end) {
      return startDate.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
      });
    }

    return `${startDate.toLocaleDateString("en-US", { month: "short", day: "numeric" })} - ${endDate.toLocaleDateString("en-US", { month: "short", day: "numeric" })}`;
  } catch {
    return `${start} - ${end}`;
  }
};

const getAttendanceStatus = (attendance) => {
  const timeIn = attendance.time_in
    ? new Date(`2000-01-01 ${attendance.time_in}`)
    : null;

  if (!timeIn) return { statusClass: "absent", statusText: "Absent" };

  const expectedStart = new Date(
    `2000-01-01 ${attendance.employee?.shift_start || "08:00:00"}`,
  );
  const lateThreshold = 15 * 60 * 1000; // 15 minutes

  if (timeIn - expectedStart > lateThreshold) {
    return { statusClass: "late", statusText: "Late" };
  }

  return { statusClass: "checked-in", statusText: "Present" };
};

const getLeaveStatus = (status) => {
  const statusMap = {
    pending: { statusClass: "pending", statusText: "Pending" },
    approved: { statusClass: "approved", statusText: "Approved" },
    rejected: { statusClass: "rejected", statusText: "Rejected" },
    denied: { statusClass: "rejected", statusText: "Denied" },
  };

  return (
    statusMap[status.toLowerCase()] || {
      statusClass: "pending",
      statusText: status,
    }
  );
};

const fetchDashboardData = async () => {
  try {
    isLoading.value = true;
    isLoadingMessage.value = `Loading Dashboard Data for ${periodLabel.value}...`;

    const { startDate, endDate } = getDateRange();
    const todayStr = new Date().toLocaleDateString("en-CA");

    // --- 1. Fetch employees ---
    const employeesResponse = await api.get("/employees-info", {
      params: { per_page: 1000 },
    });
    if (employeesResponse.data.success) {
      employees.value = employeesResponse.data.data || [];
      stats.value[0].value = employees.value.length.toString();
    }
    const totalEmployees = employees.value.length;

    // --- 2. Fetch attendance records ---
    // --- 2. Fetch attendance records ---
    const allAttendanceResponse = await attendanceApi.getRecords({
      per_page: 10000,
    });
    let allAttendance = [];
    if (allAttendanceResponse.success) {
      allAttendance = allAttendanceResponse.data || [];
    }

    // ← ADD THESE TEMPORARILY
    console.log("Total attendance records:", allAttendance.length);
    console.log("First record sample:", allAttendance[0]);
    console.log("Today string:", todayStr);
    console.log(
      "Today attendance:",
      allAttendance.filter((a) => a.attendance_date?.startsWith(todayStr)),
    );

    // Filter attendance for today:
    const todayAttendance = allAttendance.filter(
      (a) => toLocalDate(a.attendance_date) === todayStr,
    );

    // Update Present/Absent stats
    const presentToday = todayAttendance.filter((a) => a.time_in).length;
    const absentToday = Math.max(0, totalEmployees - presentToday);
    stats.value[1].value = presentToday.toString().padStart(2, "0");
    stats.value[2].value = absentToday.toString().padStart(2, "0");

    // --- 3. Fetch leave applications ---
    const leaveResponse = await api.get("/leaves", {
      params: { per_page: 1000 },
    });
    let allLeaves = [];
    if (leaveResponse.data.success) {
      allLeaves = leaveResponse.data.data || [];
    }

    const onLeaveToday = allLeaves.filter(
      (l) =>
        l.status === "approved" &&
        l.start_date <= todayStr &&
        l.end_date >= todayStr,
    ).length;
    stats.value[3].value = onLeaveToday.toString().padStart(2, "0");

    // --- 4. Calculate trends for Today cards ---
    const calcChange = (current, previous) => {
      if (previous === 0) return current === 0 ? 0 : 100;
      return Math.round(((current - previous) / previous) * 100);
    };

    let prevPresent = 0;
    let prevAbsent = 0;
    let prevLeave = 0;

    if (selectedPeriod.value === "today") {
      const yesterday = new Date();
      yesterday.setDate(yesterday.getDate() - 1);
      const yesterdayStr = yesterday.toLocaleDateString("en-CA");

      const yesterdayAttendance = allAttendance.filter(
        (a) => toLocalDate(a.attendance_date) === yesterdayStr,
      );
      prevPresent = yesterdayAttendance.filter((a) => a.time_in).length;
      prevAbsent = Math.max(0, totalEmployees - prevPresent);

      prevLeave = allLeaves.filter(
        (l) =>
          l.status === "approved" &&
          l.start_date <= yesterdayStr &&
          l.end_date >= yesterdayStr,
      ).length;
    }

    stats.value[1].trend = calcChange(presentToday, prevPresent);
    stats.value[1].trendDirection = presentToday >= prevPresent ? "up" : "down";

    stats.value[2].trend = calcChange(absentToday, prevAbsent);
    stats.value[2].trendDirection = absentToday >= prevAbsent ? "up" : "down";

    stats.value[3].trend = calcChange(onLeaveToday, prevLeave);
    stats.value[3].trendDirection = onLeaveToday >= prevLeave ? "up" : "down";

    // --- 5. Attendance rate for the period ---
    const periodAttendance = allAttendance.filter((a) => {
      const dateOnly = toLocalDate(a.attendance_date);
      return dateOnly >= startDate && dateOnly <= endDate;
    });

    if (selectedPeriod.value === "today") {
      attendanceRate.value =
        totalEmployees > 0
          ? Math.round((presentToday / totalEmployees) * 100)
          : 0;
    } else {
      const start = new Date(startDate);
      const end = new Date(endDate);
      const periodDays = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;
      const expectedAttendance = totalEmployees * periodDays;
      const actualAttendance = periodAttendance.filter((a) => a.time_in).length;
      attendanceRate.value =
        expectedAttendance > 0
          ? Math.round((actualAttendance / expectedAttendance) * 100)
          : 0;
    }

    // --- 6. Build realTimeAttendance list for UI ---
    realTimeAttendance.value = todayAttendance
      .filter((a) => a.time_in)
      .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
      .slice(0, 10)
      .map((a) => {
        let statusText = a.time_in && !a.time_out ? "Timed In" : "Timed Out";
        let statusClass = a.time_in && !a.time_out ? "working" : "completed";

        return {
          id: a.id,
          name: a.employee_name || "Unknown Employee",
          time: formatTime(a.time_out || a.time_in),
          avatar: getEmployeeAvatar(a.employee),
          statusText,
          statusClass,
        };
      });

    generateHeatmap(periodAttendance, totalEmployees);

    // --- 7. Prepare applications for display ---
    const periodLeaves = allLeaves.filter((leave) => {
      const leaveStart = new Date(leave.start_date);
      const leaveEnd = new Date(leave.end_date);
      const periodStart = new Date(startDate);
      const periodEnd = new Date(endDate);
      return leaveStart <= periodEnd && leaveEnd >= periodStart;
    });

    applications.value = periodLeaves
      .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
      .slice(0, 5)
      .map((leave) => {
        const statusInfo = getLeaveStatus(leave.status);
        return {
          id: leave.id,
          name: leave.employee?.full_name || "Unknown",
          reason: `${formatLeaveType(leave.leave_type)} • ${formatDateRange(
            leave.start_date,
            leave.end_date,
          )}`,
          avatar: getEmployeeAvatar(leave.employee),
          ...statusInfo,
        };
      });

    lastUpdated.value = "just now";
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
    toast.error("Failed to load dashboard data");
  } finally {
    isLoading.value = false;
  }
};

const generateHeatmap = (attendanceRecords, totalEmployees) => {
  const today = new Date();
  const weekStart = new Date(today);
  weekStart.setDate(today.getDate() - today.getDay());

  const weekDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

  const strip = weekDays.map((dayName, index) => {
    const date = new Date(weekStart);
    date.setDate(weekStart.getDate() + index);
    const dateStr = date.toISOString().split("T")[0];

    const presentCount = attendanceRecords.filter(
      (a) => toLocalDate(a.attendance_date) === dateStr && a.time_in,
    ).length;

    const percentage =
      totalEmployees > 0 ? (presentCount / totalEmployees) * 100 : 0;

    let level = 0;
    if (percentage === 0) level = 0;
    else if (percentage <= 25) level = 1;
    else if (percentage <= 50) level = 2;
    else if (percentage <= 75) level = 3;
    else level = 4;

    return {
      name: dayName,
      level,
      presentCount,
      date: dateStr,
    };
  });

  heatmapData.value = strip;
};

const handleNewAttendance = (newAttendance) => {
  realTimeAttendance.value = [newAttendance, ...realTimeAttendance.value].slice(
    0,
    10,
  );

  const presentToday = realTimeAttendance.value.filter(
    (a) => a.statusClass === "working" || a.statusClass === "completed",
  ).length;
  const absentToday = Math.max(0, employees.value.length - presentToday);
  stats.value[1].value = presentToday.toString().padStart(2, "0");
  stats.value[2].value = absentToday.toString().padStart(2, "0");

  generateHeatmap([...realTimeAttendance.value], employees.value.length);
};

const refreshData = async () => {
  isLoading.value = true;
  isLoadingMessage.value = "Refreshing...";
  await fetchDashboardData();
  toast.success("Dashboard refreshed");
  isLoading.value = false;
};

const exportData = async (category = "all") => {
  showExportOptions.value = false;
  isLoading.value = true;
  isLoadingMessage.value = `Exporting ${category}...`;

  try {
    const { startDate, endDate } = getDateRange();
    let csvContent = "";
    let filename = "";

    switch (category) {
      case "employee_list":
        const headers = [
          "Name",
          "Employee ID",
          "Department",
          "Position",
          "Email",
          "Status",
        ];
        csvContent = [
          headers.join(","),
          ...employees.value.map((emp) =>
            [
              `"${emp.full_name}"`,
              emp.employee_id,
              `"${emp.department}"`,
              `"${emp.position}"`,
              emp.work_email,
              emp.employment_status,
            ].join(","),
          ),
        ].join("\n");
        filename = `employees-${new Date().toISOString().split("T")[0]}.csv`;
        break;

      case "attendance_summary":
        const attendanceResponse = await attendanceApi.getRecords({
          start_date: startDate,
          end_date: endDate,
          per_page: 10000,
        });

        if (attendanceResponse.success) {
          const records = attendanceResponse.data || [];

          const attendanceHeaders = [
            "Date",
            "Employee",
            "Employee ID",
            "Time In",
            "Time Out",
            "Total Hours",
            "Status",
          ];

          csvContent = [
            attendanceHeaders.join(","),
            ...records.map((att) =>
              [
                att.attendance_date,
                `"${(att.employee?.full_name || "N/A").replace(/"/g, '""')}"`,
                att.employee?.employee_id || "N/A",
                att.time_in || "N/A",
                att.time_out || "N/A",
                att.total_hours || "0",
                att.status,
              ].join(","),
            ),
          ].join("\n");
        }

        filename = `attendance-${startDate}-to-${endDate}.csv`;
        break;

      case "leave_records":
        const leaveResponse = await api.get("/leaves", {
          params: { start_date: startDate, end_date: endDate, per_page: 10000 },
        });

        if (leaveResponse.data.success) {
          const leaveHeaders = [
            "Employee",
            "Employee ID",
            "Leave Type",
            "Start Date",
            "End Date",
            "Days",
            "Status",
          ];
          csvContent = [
            leaveHeaders.join(","),
            ...(leaveResponse.data.data || []).map((leave) =>
              [
                `"${leave.employee?.full_name || "N/A"}"`,
                leave.employee?.employee_id || "N/A",
                formatLeaveType(leave.leave_type),
                leave.start_date,
                leave.end_date,
                leave.total_days,
                leave.status,
              ].join(","),
            ),
          ].join("\n");
        }
        filename = `leave-${startDate}-to-${endDate}.csv`;
        break;

      default:
        const summaryHeaders = ["Metric", "Value"];
        csvContent = [
          summaryHeaders.join(","),
          ["Total Employees", stats.value[0].value],
          ["Present Today", stats.value[1].value],
          ["Absent Today", stats.value[2].value],
          ["On Leave", stats.value[3].value],
          ["Attendance Rate", `${attendanceRate.value}%`],
          ["Period", periodLabel.value],
          ["Date Range", `${startDate} to ${endDate}`],
        ]
          .map((row) => row.join(","))
          .join("\n");
        filename = `dashboard-${new Date().toISOString().split("T")[0]}.csv`;
    }

    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);

    toast.success("Export successful");
  } catch (error) {
    console.error("Export error:", error);
    toast.error("Failed to export");
  } finally {
    isLoading.value = false;
  }
};

const exportEmployees = () => {
  exportData("employee_list");
};

const viewEmployee = (employee) => {
  router.push(`/erp/hr/employees/profiles?id=${employee.id}`);
};

const handleClickOutside = (e) => {
  if (!e.target.closest(".dropdown-wrapper")) {
    showExportOptions.value = false;
  }
};

onMounted(async () => {
  await fetchDashboardData();

  refreshInterval = setInterval(() => {
    fetchDashboardData();
  }, 30000);

  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval);
  document.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.hr-dashboard {
  min-height: 100vh;
  background: #fafafa;
  padding: 0;
}

/* Top Bar */
.top-bar {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  padding: 12px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-container {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 8px 12px;
  width: 280px;
}

.search-container svg {
  color: #9ca3af;
  flex-shrink: 0;
}

.search-container input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  width: 100%;
  color: #111827;
}

.search-container input::placeholder {
  color: #9ca3af;
}

.top-right-actions {
  display: flex;
  gap: 8px;
}

.icon-btn {
  width: 40px;
  height: 40px;
  border: 1px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: #374151;
}

.icon-btn:hover {
  background: #f9fafb;
}

/* Info Banner */
.info-banner {
  margin: 16px 24px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.banner-content {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #1e40af;
  font-size: 14px;
}

.banner-content svg {
  flex-shrink: 0;
}

.smart-link {
  color: #2563eb;
  font-weight: 600;
  text-decoration: none;
}

.smart-link:hover {
  text-decoration: underline;
}

.banner-close {
  background: transparent;
  border: none;
  color: #3b82f6;
  font-size: 24px;
  cursor: pointer;
  padding: 0 8px;
  line-height: 1;
  font-weight: 300;
}

.banner-close:hover {
  opacity: 0.7;
}

/* Page Header */
.page-header {
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.page-title {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 6px;
}

.last-updated {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #6b7280;
}

.last-updated svg {
  color: #9ca3af;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  border: 1px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  color: #374151;
}

.action-btn:hover {
  background: #f9fafb;
}

.primary-btn {
  background: #4f46e5;
  color: white;
  border-color: #4f46e5;
}

.primary-btn:hover {
  background: #4338ca;
  border-color: #4338ca;
}

/* Segmented Control */
.segmented-control {
  display: flex;
  background: #f4f6f8;
  padding: 4px;
  border-radius: 8px;
  gap: 2px;
}

.segment-btn {
  padding: 6px 14px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  color: #697386;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.segment-btn.active {
  background: white;
  color: #1a1f36;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Dropdown */
.dropdown-wrapper {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 100;
  min-width: 220px;
  padding: 8px;
}

.menu-label {
  font-size: 10px;
  font-weight: 700;
  color: #9ca3af;
  padding: 8px 12px 4px;
  letter-spacing: 0.05em;
}

.dropdown-menu button {
  width: 100%;
  padding: 10px 12px;
  text-align: left;
  background: transparent;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 10px;
}

.dropdown-menu button:hover {
  background: #f9fafb;
}

.dropdown-menu button svg {
  flex-shrink: 0;
}

.dropdown-menu hr {
  border: none;
  border-top: 1px solid #f3f4f6;
  margin: 6px 0;
}

.full-export {
  color: #4f46e5 !important;
  font-weight: 600 !important;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  padding: 0 24px 16px;
}

.stat-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 18px;
  transition: all 0.2s;
}

.stat-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.stat-header h3 {
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
}

.more-btn {
  background: transparent;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
  border-radius: 4px;
  transition: all 0.2s;
}

.more-btn:hover {
  background: #f3f4f6;
  color: #6b7280;
}

.stat-value {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.stat-value .number {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
}

.trend {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  padding: 3px 7px;
  border-radius: 4px;
}

.trend.up {
  background: #d1fae5;
  color: #065f46;
}

.trend.down {
  background: #fee2e2;
  color: #991b1b;
}

.stat-subtitle {
  font-size: 12px;
  color: #9ca3af;
}

/* Main Content Grid */
.main-content-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr 1fr;
  gap: 20px;
  padding: 0 24px 24px;
  align-items: stretch;
}

@media (max-width: 1400px) {
  .main-content-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 900px) {
  .main-content-grid {
    grid-template-columns: 1fr;
  }
}

.card {
  background: white;
  border: 1px solid #f0f1f3;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
  transition:
    transform 0.2s,
    box-shadow 0.2s;
  display: flex;
  flex-direction: column;
}

.card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-header h2 {
  font-size: 15px;
  font-weight: 700;
  color: #1a1f36;
  letter-spacing: -0.01em;
}

/* Monitoring Card */
.header-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
}

.live-dot-container {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 10px;
  font-weight: 800;
  color: #ef4444;
  background: #fee2e2;
  padding: 2px 8px;
  border-radius: 99px;
}

.live-dot {
  width: 6px;
  height: 6px;
  background: #ef4444;
  border-radius: 50%;
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.4);
    opacity: 0.5;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.monitoring-scroll {
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-height: 400px;
  overflow-y: auto;
}

.monitor-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 12px;
  border-bottom: 1px solid #f7f8f9;
}

.monitor-row:last-child {
  border-bottom: none;
}

.emp-profile {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar-sm {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  object-fit: cover;
  border: 2px solid #f0f1f3;
}

.emp-details {
  display: flex;
  flex-direction: column;
}

.emp-details .name {
  font-size: 14px;
  font-weight: 600;
  color: #1a1f36;
}

.emp-details .time {
  font-size: 12px;
  color: #8792a2;
}

.status-chip {
  font-size: 10px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-chip.checked-in {
  background: #e1fdf4;
  color: #027a48;
}
.status-chip.late {
  background: #fff9e6;
  color: #b47d00;
}
.status-chip.on-leave {
  background: #f0f5ff;
  color: #004ecc;
}
.status-chip.absent {
  background: #fff1f0;
  color: #cf1322;
}

.empty-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #9ca3af;
  text-align: center;
}

.empty-message svg {
  margin-bottom: 12px;
}

.empty-message p {
  font-size: 14px;
}

/* Report Card Mini */
.quick-stats-row {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-bottom: 24px;
}

.radial-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.radial-svg {
  width: 70px;
  height: 70px;
}

.circle-bg {
  fill: none;
  stroke: #f4f6f8;
  stroke-width: 3;
}

.circle {
  fill: none;
  stroke: #4f46e5;
  stroke-width: 3;
  stroke-linecap: round;
  transition: stroke-dasharray 1s ease;
}

.percentage {
  fill: #1a1f36;
  font-size: 8px;
  font-weight: 700;
  text-anchor: middle;
}

.radial-label {
  font-size: 11px;
  font-weight: 600;
  color: #697386;
}

.mini-trend-box {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.trend-val {
  font-size: 18px;
  font-weight: 700;
}

.trend-val.up {
  color: #10b981;
}

.trend-label {
  font-size: 12px;
  color: #8792a2;
}

/* Horizontal Heatmap */
.horizontal-heatmap {
  background: #f9fafb;
  border-radius: 10px;
  padding: 16px;
}

.heatmap-header {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  font-weight: 600;
  color: #697386;
  margin-bottom: 12px;
}

.mini-legend {
  display: flex;
  gap: 3px;
}

.l-dot {
  width: 8px;
  height: 8px;
  border-radius: 2px;
}

.l-dot.lvl-0 {
  background: #e5e7eb;
}
.l-dot.lvl-2 {
  background: #a7f3d0;
}
.l-dot.lvl-4 {
  background: #10b981;
}

.heatmap-strip {
  display: flex;
  justify-content: space-between;
  gap: 6px;
}

.strip-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  flex: 1;
}

.strip-cell {
  width: 100%;
  aspect-ratio: 1;
  border-radius: 4px;
}

.strip-cell.lvl-0 {
  background: #ebedf0;
}
.strip-cell.lvl-1 {
  background: #d1fae5;
}
.strip-cell.lvl-2 {
  background: #a7f3d0;
}
.strip-cell.lvl-3 {
  background: #6ee7b7;
}
.strip-cell.lvl-4 {
  background: #10b981;
}

.strip-label {
  font-size: 10px;
  font-weight: 600;
  color: #9ca3af;
}

/* Leave List Card */
.leave-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 400px;
  overflow-y: auto;
}

.leave-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border-radius: 8px;
  background: #fff;
  border: 1px solid #f0f1f3;
}

.leave-profile {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.leave-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  object-fit: cover;
  border: 2px solid #f0f1f3;
}

.leave-main {
  display: flex;
  flex-direction: column;
}

.applicant {
  font-size: 14px;
  font-weight: 600;
  color: #1a1f36;
}

.duration {
  font-size: 12px;
  color: #8792a2;
}

.status-badge-v2 {
  font-size: 11px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 6px;
  text-transform: capitalize;
  white-space: nowrap;
}

.status-badge-v2.approved {
  background: #e1fdf4;
  color: #027a48;
}
.status-badge-v2.pending {
  background: #fff9e6;
  color: #b47d00;
}
.status-badge-v2.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.text-link {
  font-size: 12px;
  font-weight: 700;
  color: #4f46e5;
  background: transparent;
  border: none;
  cursor: pointer;
}

.text-link:hover {
  color: #4338ca;
}

/* Table Card */
.table-card {
  padding: 20px 24px;
  margin: 0 24px 24px;
}

.table-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.filter-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border: 1px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  color: #374151;
}

.filter-btn:hover {
  background: #f9fafb;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  background: white;
  border-radius: 6px;
  transition: all 0.2s;
}

.search-box:focus-within {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.search-box svg {
  color: #9ca3af;
  flex-shrink: 0;
}

.search-box input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  width: 200px;
  color: #111827;
}

.search-box input::placeholder {
  color: #9ca3af;
}

.export-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #4f46e5;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.export-btn:hover {
  background: #4338ca;
}

.table-wrapper {
  overflow-x: auto;
  margin-top: 16px;
}

.employees-table {
  width: 100%;
  border-collapse: collapse;
}

.employees-table thead {
  background: #f9fafb;
  border-top: 1px solid #e5e7eb;
  border-bottom: 1px solid #e5e7eb;
}

.employees-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: capitalize;
}

.employees-table td {
  padding: 14px 16px;
  font-size: 14px;
  color: #374151;
  border-bottom: 1px solid #f3f4f6;
}

.employees-table tbody tr:hover {
  background: #fafafa;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 600;
  color: #111827;
}

.employee-avatar {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  object-fit: cover;
  border: 2px solid #f0f1f3;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 5px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.status-badge.regular {
  background: #d1fae5;
  color: #065f46;
}
.status-badge.probationary {
  background: #fed7aa;
  color: #92400e;
}
.status-badge.contractual {
  background: #dbeafe;
  color: #1e40af;
}
.status-badge.active {
  background: #d1fae5;
  color: #065f46;
}
.status-badge.inactive {
  background: #f3f4f6;
  color: #6b7280;
}
.status-badge.part-time {
  background: #e0e7ff;
  color: #4338ca;
}

@media (max-width: 1400px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .page-header {
    flex-direction: column;
    gap: 16px;
  }

  .header-actions {
    width: 100%;
    flex-wrap: wrap;
  }

  .top-bar {
    flex-direction: column;
    gap: 12px;
  }

  .search-container {
    width: 100%;
  }
}
</style>
