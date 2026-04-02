<template>
  <vendorHeader />
  <div class="employee-management">
    <LoadingOverlay :visible="isLoading" :message="isLoadingMessage" />
    <VendorSidebar />
    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1>Employee Management</h1>
        <p class="subtitle">Manage your team members and their roles</p>
      </div>
    </div>

    <!-- Recent Activity Cards -->
    <div class="activity-section">
      <h2>Recent Activity</h2>
      <div class="activity-cards">
        <div class="activity-card">
          <h3>Recent Hires</h3>
          <div class="activity-list">
            <div
              v-for="hire in recentHires"
              :key="hire.email"
              class="activity-item"
            >
              <div class="activity-avatar">{{ hire.initials }}</div>
              <div class="activity-details">
                <span class="activity-name">{{ hire.name }}</span>
                <span class="activity-email">{{ hire.email }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="activity-card">
          <h3>Resignation</h3>
          <div class="activity-list">
            <div
              v-for="resign in resignations"
              :key="resign.email"
              class="activity-item"
            >
              <div class="activity-avatar">{{ resign.initials }}</div>
              <div class="activity-details">
                <span class="activity-name">{{ resign.name }}</span>
                <span class="activity-email">{{ resign.email }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="activity-card">
          <h3>Leave Approval</h3>
          <div class="activity-list">
            <div
              v-for="leave in leaveApprovals"
              :key="leave.email"
              class="activity-item"
            >
              <div class="activity-avatar">{{ leave.initials }}</div>
              <div class="activity-details">
                <span class="activity-name">{{ leave.name }}</span>
                <span class="activity-email">{{ leave.email }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="activity-row">
        <div class="activity-card half">
          <h3>On leave</h3>
          <div class="activity-list">
            <div
              v-for="person in onLeave"
              :key="person.email"
              class="activity-item"
            >
              <div class="activity-avatar">{{ person.initials }}</div>
              <div class="activity-details">
                <span class="activity-name">{{ person.name }}</span>
                <span class="activity-email">{{ person.email }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="activity-card half">
          <h3>New Joins this month</h3>
          <div class="activity-list">
            <div
              v-for="join in newJoins"
              :key="join.email"
              class="activity-item"
            >
              <div class="activity-avatar">{{ join.initials }}</div>
              <div class="activity-details">
                <span class="activity-name">{{ join.name }}</span>
                <span class="activity-date">Date: {{ join.date }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Employee Table Section -->
    <div class="table-section">
      <div class="table-header">
        <div class="table-title">
          <h2>
            Total Employees:
            <span class="employee-count">{{ filteredEmployees.length }}</span>
          </h2>
        </div>
        <div class="table-actions">
          <div class="search-box">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5A6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5S14 7.01 14 9.5S11.99 14 9.5 14z"
              />
            </svg>
            <input
              type="text"
              placeholder="Search employees..."
              v-model="searchTerm"
            />
          </div>
          <button class="btn-filter" @click="showFilters = !showFilters">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"
              />
            </svg>
            Filter
          </button>
          <button class="btn-add-employee" @click="openAddModal">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"
              />
            </svg>
            Add Member
          </button>
        </div>
      </div>

      <div v-if="showFilters" class="filters-panel">
        <div class="filter-group">
          <label>Status</label>
          <select v-model="filterStatus">
            <option value="all">All Status</option>
            <option value="Active">Active</option>
            <option value="On Leave">On Leave</option>
            <option value="Resign">Resign</option>
          </select>
        </div>
        <div class="filter-group">
          <label>Module Group</label>
          <select v-model="filterGroup">
            <option value="all">All Groups</option>
            <option value="HR">HR</option>
            <option value="Finance">Finance</option>
            <option value="Procurement">Procurement</option>
            <option value="Supply Chain">Supply Chain</option>
          </select>
        </div>
        <button class="btn-clear-filters" @click="clearFilters">
          Clear Filters
        </button>
      </div>

      <div class="table-container">
        <table class="employee-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Modules</th>
              <th>Joining Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="employee in filteredEmployees" :key="employee.id">
              <td>
                <div class="employee-info">
                  <div class="employee-avatar">{{ employee.initials }}</div>
                  <div class="employee-details">
                    <span class="employee-name">{{ employee.name }}</span>
                    <span class="employee-email">{{ employee.email }}</span>
                  </div>
                </div>
              </td>
              <td>
                <div class="module-pills">
                  <span
                    v-if="(employee.module_permissions || []).length > 0"
                    class="module-pill"
                    :class="
                      'pill-' +
                      getModuleGroup(employee.module_permissions[0].module)
                        .toLowerCase()
                        .replace(' ', '-')
                    "
                    :title="
                      employee.module_permissions[0].module +
                      ' (' +
                      employee.module_permissions[0].access +
                      ')'
                    "
                    >{{
                      getModuleLabel(employee.module_permissions[0].module)
                    }}</span
                  >
                  <span
                    v-if="(employee.module_permissions || []).length > 1"
                    class="module-pill pill-more"
                    :title="
                      (employee.module_permissions || [])
                        .slice(1)
                        .map((p) => getModuleLabel(p.module))
                        .join(', ')
                    "
                    >+{{ employee.module_permissions.length - 1 }} more</span
                  >
                  <span
                    v-if="!(employee.module_permissions || []).length"
                    class="no-modules"
                    >No modules</span
                  >
                </div>
              </td>
              <td>{{ employee.joiningDate }}</td>
              <td>
                <span
                  :class="['status-badge', getStatusClass(employee.status)]"
                  >{{ employee.status }}</span
                >
              </td>
              <td>
                <div class="action-menu">
                  <button
                    class="btn-action"
                    @click="toggleActionMenu(employee.id)"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="currentColor"
                        d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2s-2 .9-2 2s.9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2s2-.9 2-2s-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2s2-.9 2-2s-.9-2-2-2z"
                      />
                    </svg>
                  </button>
                  <div
                    v-if="activeMenuId === employee.id"
                    class="action-dropdown"
                  >
                    <button @click="editEmployee(employee)">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                      >
                        <path
                          fill="currentColor"
                          d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83l3.75 3.75l1.83-1.83z"
                        />
                      </svg>
                      Edit
                    </button>
                    <button @click="changeStatus(employee, 'Active')">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                      >
                        <path
                          fill="currentColor"
                          d="M9 16.17L4.83 12l-1.42 1.41L9 19L21 7l-1.41-1.41z"
                        />
                      </svg>
                      Set Active
                    </button>
                    <button @click="changeStatus(employee, 'On Leave')">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                      >
                        <path
                          fill="currentColor"
                          d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"
                        />
                      </svg>
                      On Leave
                    </button>
                    <button
                      @click="changeStatus(employee, 'Resign')"
                      class="danger"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                      >
                        <path
                          fill="currentColor"
                          d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM8 9h8v10H8V9zm7.5-5l-1-1h-5l-1 1H5v2h14V4z"
                        />
                      </svg>
                      Resign
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div
      v-if="showAddModal || showEditModal"
      class="modal-overlay"
      @click="closeModal"
    >
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ showEditModal ? "Edit Employee" : "Add New Employee" }}</h2>
          <button class="btn-close" @click="closeModal">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
            >
              <path
                fill="currentColor"
                d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12z"
              />
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label>Full Name *</label>
              <input
                type="text"
                v-model="formData.name"
                placeholder="Enter full name"
                required
              />
            </div>
            <div class="form-group">
              <label>Email *</label>
              <input
                type="email"
                v-model="formData.email"
                placeholder="employee@company.com"
                required
              />
            </div>
            <div class="form-group">
              <label>Username *</label>
              <input
                type="text"
                v-model="formData.username"
                placeholder="username"
                required
              />
            </div>
            <div class="form-group">
              <label>Password *</label>
              <input
                type="password"
                v-model="formData.password"
                placeholder="••••••••"
                :required="!showEditModal"
              />
              <span class="form-hint" v-if="showEditModal"
                >Leave blank to keep current password</span
              >
            </div>

            <!-- ── Module Permissions ─────────────────────────────── -->
            <div class="permissions-section full-width">
              <div class="permissions-header">
                <div>
                  <h3>Module Permissions *</h3>
                  <p class="permissions-hint">
                    Grant access to specific ERP modules
                  </p>
                </div>
                <div class="perm-legend">
                  <span class="legend-item"
                    ><span class="legend-dot blue"></span>View only</span
                  >
                  <span class="legend-item"
                    ><span class="legend-dot violet"></span>Can edit</span
                  >
                </div>
              </div>

              <div class="perm-table-wrapper">
                <table class="perm-table">
                  <thead>
                    <tr class="perm-col-header">
                      <th>Module</th>
                      <th class="center">View</th>
                      <th class="center">Edit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template
                      v-for="(group, groupName) in modulesByGroup"
                      :key="groupName"
                    >
                      <tr class="perm-group-row">
                        <td colspan="3">{{ groupName }}</td>
                      </tr>
                      <tr
                        v-for="mod in group"
                        :key="mod.key"
                        class="perm-mod-row"
                      >
                        <td class="mod-name-cell">{{ mod.label }}</td>
                        <td class="perm-toggle-cell">
                          <button
                            type="button"
                            class="perm-btn"
                            :class="{
                              'perm-btn--view':
                                isModuleEnabled(mod.key) &&
                                getModuleAccess(mod.key) === 'view',
                              'perm-btn--locked':
                                isModuleEnabled(mod.key) &&
                                getModuleAccess(mod.key) === 'edit',
                            }"
                            @click="handleViewClick(mod.key)"
                          >
                            <svg
                              v-if="isModuleEnabled(mod.key)"
                              viewBox="0 0 12 12"
                              fill="none"
                              xmlns="http://www.w3.org/2000/svg"
                            >
                              <polyline
                                points="2,6.5 5,9.5 10,2.5"
                                stroke="white"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                              />
                            </svg>
                          </button>
                        </td>
                        <td class="perm-toggle-cell">
                          <button
                            type="button"
                            class="perm-btn"
                            :class="{
                              'perm-btn--edit':
                                isModuleEnabled(mod.key) &&
                                getModuleAccess(mod.key) === 'edit',
                            }"
                            @click="handleEditClick(mod.key)"
                          >
                            <svg
                              v-if="
                                isModuleEnabled(mod.key) &&
                                getModuleAccess(mod.key) === 'edit'
                              "
                              viewBox="0 0 12 12"
                              fill="none"
                              xmlns="http://www.w3.org/2000/svg"
                            >
                              <polyline
                                points="2,6.5 5,9.5 10,2.5"
                                stroke="white"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                              />
                            </svg>
                          </button>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="form-group">
              <label>Joining Date *</label>
              <input type="date" v-model="formData.joiningDate" required />
            </div>
            <div class="form-group">
              <label>Status *</label>
              <select v-model="formData.status" required>
                <option value="Active">Active</option>
                <option value="On Leave">On Leave</option>
                <option value="Resign">Resign</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label>Phone Number</label>
              <input
                type="tel"
                v-model="formData.phone"
                placeholder="09491234569"
              />
            </div>
            <div class="form-group full-width">
              <label>Address</label>
              <textarea
                v-model="formData.address"
                placeholder="Enter address"
                rows="3"
              ></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="closeModal">Cancel</button>
          <button class="btn-save" @click="saveEmployee">
            {{ showEditModal ? "Update Employee" : "Add Employee" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import vendorHeader from "../../layouts/vendorHeader.vue";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import LoadingOverlay from "../../layouts/components/LoadingOverlay.vue";
import { toast } from "vue3-toastify";
import api from "../../plugins/axios";
import {
  ERP_MODULES,
  getModulesByGroup,
  findModule,
} from "../../constants/erpModules";

// State
const showAddModal = ref(false);
const showEditModal = ref(false);
const showFilters = ref(false);
const activeMenuId = ref(null);
const searchTerm = ref("");
const filterStatus = ref("all");
const filterGroup = ref("all");
const isLoading = ref(false);
const isLoadingMessage = ref("Loading...");

// Data
const employees = ref([]);
const recentHires = ref([]);
const resignations = ref([]);
const leaveApprovals = ref([]);
const onLeave = ref([]);
const newJoins = ref([]);

// Module helpers
const modulesByGroup = computed(() => getModulesByGroup());

function getModuleLabel(key) {
  return findModule(key)?.label ?? key;
}
function getModuleGroup(key) {
  return findModule(key)?.group ?? "";
}

// Form Data
const formData = ref({
  name: "",
  email: "",
  username: "",
  password: "",
  permissions: [],
  joiningDate: "",
  status: "Active",
  phone: "",
  address: "",
});

// Permissions helpers
function isModuleEnabled(moduleKey) {
  return formData.value.permissions.some((p) => p.module === moduleKey);
}
function getModuleAccess(moduleKey) {
  return (
    formData.value.permissions.find((p) => p.module === moduleKey)?.access ??
    "view"
  );
}
function toggleModule(moduleKey) {
  const idx = formData.value.permissions.findIndex(
    (p) => p.module === moduleKey,
  );
  if (idx > -1) {
    formData.value.permissions.splice(idx, 1);
  } else {
    formData.value.permissions.push({ module: moduleKey, access: "view" });
  }
}
function setModuleAccess(moduleKey, access) {
  const perm = formData.value.permissions.find((p) => p.module === moduleKey);
  if (perm) perm.access = access;
}

// View click: toggle on/off (disabled when edit is active)
function handleViewClick(moduleKey) {
  const isEdit =
    isModuleEnabled(moduleKey) && getModuleAccess(moduleKey) === "edit";
  if (isEdit) return;
  toggleModule(moduleKey);
}

// Edit click: if off → enable with edit; if view → upgrade to edit; if edit → downgrade to view
function handleEditClick(moduleKey) {
  if (!isModuleEnabled(moduleKey)) {
    formData.value.permissions.push({ module: moduleKey, access: "edit" });
  } else if (getModuleAccess(moduleKey) === "edit") {
    setModuleAccess(moduleKey, "view");
  } else {
    setModuleAccess(moduleKey, "edit");
  }
}

// Filters
const filteredEmployees = computed(() => {
  return employees.value.filter((emp) => {
    const matchesSearch =
      emp.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
      emp.email.toLowerCase().includes(searchTerm.value.toLowerCase());
    const matchesStatus =
      filterStatus.value === "all" || emp.status === filterStatus.value;
    const matchesGroup =
      filterGroup.value === "all" ||
      (emp.module_permissions || []).some(
        (p) => getModuleGroup(p.module) === filterGroup.value,
      );
    return matchesSearch && matchesStatus && matchesGroup;
  });
});

const fetchEmployees = async () => {
  const response = await api.get("/vendor/employees");
  if (response.data.success) {
    employees.value = response.data.data.map((emp) => ({
      ...emp,
      initials: emp.initials,
      joiningDate: emp.formatted_joining_date,
      module_permissions: emp.module_permissions || [],
    }));
  }
};

const fetchStatistics = async () => {
  const response = await api.get("/vendor/employees/statistics");
  if (response.data.success) {
    const stats = response.data.data;
    recentHires.value = (stats.recent_hires || []).map((hire) => ({
      ...hire,
      initials:
        hire.initials ||
        hire.name
          .split(" ")
          .map((n) => n[0])
          .join("")
          .toUpperCase(),
    }));
    resignations.value = stats.resignations || [];
    onLeave.value = stats.on_leave_list || [];
    newJoins.value = (stats.new_joins || []).map((join) => ({
      ...join,
      initials:
        join.initials ||
        join.name
          .split(" ")
          .map((n) => n[0])
          .join("")
          .toUpperCase(),
      date: new Date(join.joining_date).toLocaleDateString("en-GB"),
    }));
  }
};

const openAddModal = () => {
  resetForm();
  showAddModal.value = true;
};

const editEmployee = (employee) => {
  formData.value = {
    id: employee.id,
    name: employee.name,
    email: employee.email,
    username: employee.username,
    password: "",
    permissions: (employee.module_permissions || []).map((p) => ({
      module: p.module,
      access: p.access,
    })),
    joiningDate: employee.joining_date,
    status: employee.status,
    phone: employee.phone || "",
    address: employee.address || "",
  };
  activeMenuId.value = null;
  showEditModal.value = true;
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  resetForm();
};

const resetForm = () => {
  formData.value = {
    name: "",
    email: "",
    username: "",
    password: "",
    permissions: [],
    joiningDate: "",
    status: "Active",
    phone: "",
    address: "",
  };
};

const saveEmployee = async () => {
  if (
    !formData.value.name ||
    !formData.value.email ||
    !formData.value.username ||
    !formData.value.joiningDate
  ) {
    toast.error("Please fill in all required fields");
    return;
  }
  if (!formData.value.permissions || formData.value.permissions.length === 0) {
    toast.error("At least one module permission must be assigned");
    return;
  }
  if (!showEditModal.value && !formData.value.password) {
    toast.error("Password is required for new employees");
    return;
  }

  const payload = {
    name: formData.value.name,
    email: formData.value.email,
    username: formData.value.username,
    joining_date: formatDateForAPI(formData.value.joiningDate),
    status: formData.value.status,
    phone: formData.value.phone,
    address: formData.value.address,
    permissions: formData.value.permissions,
  };
  if (formData.value.password) payload.password = formData.value.password;

  try {
    isLoading.value = true;
    isLoadingMessage.value = showEditModal.value
      ? "Updating employee..."
      : "Adding employee...";

    if (showEditModal.value) {
      const response = await api.put(
        `/vendor/employees/${formData.value.id}`,
        payload,
      );
      if (response.data.success) {
        toast.success("Employee updated successfully!");
        await Promise.all([fetchEmployees(), fetchStatistics()]);
      }
    } else {
      const response = await api.post("/vendor/employees", payload);
      if (response.data.success) {
        toast.success("Employee added successfully!");
        await Promise.all([fetchEmployees(), fetchStatistics()]);
      }
    }
    closeModal();
  } catch (error) {
    console.error("Error saving employee:", error);
    if (error.response?.data?.errors) {
      Object.values(error.response.data.errors).forEach((err) =>
        toast.error(err[0]),
      );
    } else {
      toast.error(error.response?.data?.message || "Failed to save employee");
    }
  } finally {
    isLoading.value = false;
  }
};

const formatDateForAPI = (dateString) => {
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) return dateString;
  if (dateString.includes("-")) {
    const parts = dateString.split("-");
    if (parts.length === 3) {
      if (parts[0].length === 2 && parts[2].length === 4)
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
      if (parts[0].length === 2 && parts[2].length === 2)
        return `20${parts[2]}-${parts[1]}-${parts[0]}`;
    }
  }
  const date = new Date(dateString);
  if (!isNaN(date.getTime())) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
  }
  return dateString;
};

const changeStatus = async (employee, newStatus) => {
  try {
    isLoading.value = true;
    isLoadingMessage.value = `Updating status to ${newStatus}...`;
    const response = await api.patch(
      `/vendor/employees/${employee.id}/status`,
      { status: newStatus },
    );
    if (response.data.success) {
      toast.success(`Status updated to ${newStatus}`);
      await Promise.all([fetchEmployees(), fetchStatistics()]);
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to update status");
  } finally {
    isLoading.value = false;
  }
  activeMenuId.value = null;
};

const toggleActionMenu = (id) => {
  activeMenuId.value = activeMenuId.value === id ? null : id;
};

const clearFilters = () => {
  filterStatus.value = "all";
  filterGroup.value = "all";
  searchTerm.value = "";
  fetchEmployees();
};

const getStatusClass = (status) => status.toLowerCase().replace(" ", "-");

onMounted(async () => {
  isLoading.value = true;
  isLoadingMessage.value = "Loading page data...";
  try {
    await Promise.allSettled([fetchEmployees(), fetchStatistics()]);
  } catch (error) {
    console.error("Critical error during page load:", error);
  } finally {
    isLoading.value = false;
  }
});
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.employee-management {
  margin-left: 250px;
  min-height: 100vh;
  background: #f5f7fa;
  padding: 24px;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  padding: 20px 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.header-left h1 {
  font-size: 28px;
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 4px;
}
.subtitle {
  font-size: 14px;
  color: #718096;
}

/* Activity Section */
.activity-section {
  margin-bottom: 32px;
}
.activity-section h2 {
  font-size: 20px;
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 16px;
}
.activity-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}
.activity-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}
.activity-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.activity-card h3 {
  font-size: 16px;
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 16px;
}
.activity-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.activity-item {
  display: flex;
  align-items: center;
  gap: 12px;
}
.activity-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  flex-shrink: 0;
}
.activity-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.activity-name {
  font-size: 14px;
  font-weight: 500;
  color: #1a202c;
}
.activity-email,
.activity-date {
  font-size: 12px;
  color: #718096;
}

/* Table Section */
.table-section {
  position: relative;
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  z-index: 1;
}
.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.table-title h2 {
  font-size: 18px;
  font-weight: 600;
  color: #1a202c;
}
.employee-count {
  color: #ef4444;
  font-weight: 700;
}
.table-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  height: 40px;
  min-width: 280px;
}
.search-box svg {
  color: #718096;
  flex-shrink: 0;
}
.search-box input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #1a202c;
}
.search-box input::placeholder {
  color: #a0aec0;
}
.btn-filter {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 16px;
  height: 40px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-filter:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}
.btn-add-employee {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 20px;
  height: 40px;
  background: #ef4444;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: white;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-add-employee:hover {
  background: #dc2626;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Filters */
.filters-panel {
  display: flex;
  gap: 16px;
  align-items: flex-end;
  margin-bottom: 24px;
  padding: 16px;
  background: #f7fafc;
  border-radius: 8px;
}
.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}
.filter-group label {
  font-size: 13px;
  font-weight: 500;
  color: #4a5568;
}
.filter-group select {
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  color: #1a202c;
  background: white;
  cursor: pointer;
}
.btn-clear-filters {
  padding: 8px 16px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.3s;
}

/* Table */
.table-container {
  overflow: visible;
}
.employee-table {
  width: 100%;
  border-collapse: collapse;
}
.employee-table thead tr {
  border-bottom: 2px solid #e2e8f0;
}
.employee-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.employee-table tbody tr {
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.2s;
}
.employee-table tbody tr:hover {
  background: #f7fafc;
}
.employee-table td {
  padding: 16px;
  font-size: 14px;
  color: #1a202c;
}
.employee-info {
  display: flex;
  align-items: center;
  gap: 12px;
}
.employee-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  flex-shrink: 0;
}
.employee-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.employee-name {
  font-weight: 500;
  color: #1a202c;
}
.employee-email {
  font-size: 13px;
  color: #718096;
}
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}
.status-badge.active {
  background: #d1fae5;
  color: #065f46;
}
.status-badge.on-leave {
  background: #fef3c7;
  color: #92400e;
}
.status-badge.resign {
  background: #fee2e2;
  color: #991b1b;
}
.action-menu {
  position: relative;
}
.btn-action {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}
.btn-action:hover {
  background: #f7fafc;
}
.action-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  min-width: 160px;
  z-index: 100;
  overflow: hidden;
}
.action-dropdown button {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border: none;
  background: white;
  font-size: 14px;
  color: #1a202c;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}
.action-dropdown button:hover {
  background: #f7fafc;
}
.action-dropdown button.danger {
  color: #ef4444;
}
.action-dropdown button.danger:hover {
  background: #fef2f2;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}
.modal-content {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid #e2e8f0;
}
.modal-header h2 {
  font-size: 20px;
  font-weight: 600;
  color: #1a202c;
}
.btn-close {
  width: 32px;
  height: 32px;
  border: none;
  background: #f7fafc;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}
.btn-close:hover {
  background: #edf2f7;
}
.modal-body {
  padding: 24px;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.form-group.full-width {
  grid-column: 1 / -1;
}
.form-group label {
  font-size: 14px;
  font-weight: 500;
  color: #4a5568;
}
.form-group input,
.form-group select,
.form-group textarea {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #1a202c;
  transition: all 0.3s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
.form-group textarea {
  resize: vertical;
  font-family: inherit;
}
.form-hint {
  font-size: 12px;
  color: #718096;
  font-style: italic;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 24px;
  border-top: 1px solid #e2e8f0;
}
.btn-cancel {
  padding: 10px 20px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-cancel:hover {
  background: #f7fafc;
}
.btn-save {
  padding: 10px 20px;
  background: #667eea;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: white;
  cursor: pointer;
  transition: all 0.3s;
}
.btn-save:hover {
  background: #5568d3;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

/* ── Module Pill Capsules (table) ────────────────── */
.module-pills {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: nowrap;
}

.module-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
  letter-spacing: 0.2px;
}

/* HR — blue */
.pill-hr {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

/* Finance — emerald */
.pill-finance {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

/* Procurement — amber */
.pill-procurement {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
}

/* Supply Chain — violet */
.pill-supply-chain {
  background: #f5f3ff;
  color: #5b21b6;
  border: 1px solid #ddd6fe;
}

/* +N more pill */
.pill-more {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.no-modules {
  font-size: 12px;
  color: #a0aec0;
  font-style: italic;
}

/* ── Module Permissions ───────────────────────────── */
.permissions-section {
  grid-column: 1 / -1;
  margin-top: 4px;
}

.permissions-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.permissions-header h3 {
  font-size: 14px;
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 2px;
}

.permissions-hint {
  font-size: 12px;
  color: #94a3b8;
  margin: 0;
}

.perm-legend {
  display: flex;
  gap: 14px;
  align-items: center;
  padding-top: 2px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: #94a3b8;
  font-weight: 500;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.legend-dot.blue {
  background: #3b82f6;
}
.legend-dot.violet {
  background: #a78bfa;
}

.perm-table-wrapper {
  border: 1px solid #e8ecf0;
  border-radius: 10px;
  overflow: hidden;
}

.perm-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.perm-col-header th {
  padding: 10px 16px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: #94a3b8;
  background: #f8fafc;
  border-bottom: 1px solid #e8ecf0;
}

.perm-col-header th.center {
  text-align: center;
  width: 80px;
}

.perm-group-row td {
  padding: 8px 16px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.9px;
  color: #64748b;
  background: #f1f5f9;
  border-top: 1px solid #e8ecf0;
  border-bottom: 1px solid #e8ecf0;
}

.perm-mod-row {
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.12s;
}

.perm-mod-row:last-child {
  border-bottom: none;
}

.perm-mod-row:hover {
  background: #fafbff;
}

.mod-name-cell {
  padding: 12px 16px;
  font-size: 13px;
  color: #334155;
  font-weight: 400;
}

.perm-toggle-cell {
  text-align: center;
  vertical-align: middle;
  padding: 12px 0;
}

/* The toggle button */
.perm-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid #d1d5db; /* always visible gray border */
  background: transparent;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition:
    border-color 0.15s,
    background 0.15s,
    transform 0.12s,
    box-shadow 0.15s;
  outline: none;
  padding: 0;
}

/* hover on empty state */
.perm-btn:hover {
  border-color: #9ca3af;
  transform: scale(1.1);
  background: #f3f4f6;
}

/* View only — filled blue */
.perm-btn--view {
  border-color: #3b82f6 !important;
  background: #3b82f6 !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.perm-btn--view:hover {
  border-color: #2563eb !important;
  background: #2563eb !important;
  transform: scale(1.1);
}

/* View locked (edit is active) — pale blue, non-interactive */
.perm-btn--locked {
  border-color: #93c5fd !important;
  background: #93c5fd !important;
  box-shadow: none;
  cursor: default;
  pointer-events: none;
  opacity: 0.75;
}

/* Edit — filled light violet */
.perm-btn--edit {
  border-color: #a78bfa !important;
  background: #a78bfa !important;
  box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.18);
}
.perm-btn--edit:hover {
  border-color: #8b5cf6 !important;
  background: #8b5cf6 !important;
  transform: scale(1.1);
}

.perm-btn svg {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
}

/* Responsive */
@media (max-width: 968px) {
  .activity-cards {
    grid-template-columns: repeat(2, 1fr);
  }
  .activity-row {
    grid-template-columns: 1fr;
  }
  .table-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  .table-actions {
    width: 100%;
    flex-wrap: wrap;
  }
  .search-box {
    min-width: 100%;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
  .permissions-header {
    flex-direction: column;
    gap: 10px;
  }
}

@media (max-width: 640px) {
  .employee-management {
    padding: 16px;
  }
  .page-header {
    padding: 16px;
  }
  .header-left h1 {
    font-size: 22px;
  }
  .table-section {
    padding: 16px;
  }
  .employee-table th,
  .employee-table td {
    padding: 12px 8px;
    font-size: 13px;
  }
  .modal-content {
    max-width: 100%;
  }
  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 16px;
  }
}
</style>
