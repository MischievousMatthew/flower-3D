<template>
  <div class="employee-directory">
    

    <div class="directory-content">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Employee Directory</h1>

        <div class="header-actions">
          <button
            class="add-employee-btn"
            @click="fetchEmployees"
            :disabled="isLoading"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path d="M23 4v6h-6" />
              <path d="M1 20v-6h6" />
              <path
                d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"
              />
            </svg>
            {{ isLoading ? "Refreshing..." : "Refresh" }}
          </button>
          <div class="search-box">
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
            <input
              type="text"
              placeholder="Search Employee"
              v-model="searchQuery"
            />
          </div>
        </div>
      </div>

      <!-- Loading Overlay -->
      <div v-if="isLoading" class="loading-overlay">
        <div class="loader"></div>
        <p>Loading employees...</p>
      </div>

      <!-- Employee Cards Grid -->
      <div
        class="employee-grid"
        v-if="!isLoading && filteredEmployees.length > 0"
      >
        <div
          class="employee-card"
          v-for="employee in filteredEmployees"
          :key="employee.id"
        >
          <div class="card-header-actions">
            <button class="card-menu" @click.stop="toggleMenu(employee.id)">
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
            <div
              v-if="openMenuId === employee.id"
              class="dropdown-menu"
              @click.stop
            >
              <button @click="viewDetails(employee)">View Details</button>
              <button @click="editProfile(employee)">Edit Profile</button>
              <button @click="toggleStatus(employee)">
                Mark as
                {{
                  employee.employment_status === "Active"
                    ? "Inactive"
                    : "Active"
                }}
              </button>
              <button class="delete-btn" @click="deleteEmployee(employee)">
                Delete
              </button>
            </div>
          </div>

          <div class="employee-avatar-wrapper">
            <img
              :src="employee.avatar_url || 'https://i.pravatar.cc/150?img=1'"
              :alt="employee.full_name"
              class="employee-avatar"
              :class="{
                'is-inactive': employee.employment_status === 'Inactive',
              }"
            />
            <span
              class="status-indicator"
              :class="employee.employment_status?.toLowerCase() || 'offline'"
            ></span>
          </div>

          <h3 class="employee-name">{{ employee.full_name }}</h3>
          <p class="employee-role">{{ employee.position }}</p>
          <p class="employee-location">{{ employee.work_location }}</p>

          <div class="social-links">
            <a href="#" class="social-link" title="X (Twitter)">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path
                  d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                />
              </svg>
            </a>
            <a href="#" class="social-link" title="LinkedIn">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path
                  d="M20.5 2h-17A1.5 1.5 0 002 3.5v17A1.5 1.5 0 003.5 22h17a1.5 1.5 0 001.5-1.5v-17A1.5 1.5 0 0020.5 2zM8 19H5v-9h3zM6.5 8.25A1.75 1.75 0 118.3 6.5a1.78 1.78 0 01-1.8 1.75zM19 19h-3v-4.74c0-1.42-.6-1.93-1.38-1.93A1.74 1.74 0 0013 14.19a.66.66 0 000 .14V19h-3v-9h2.9v1.3a3.11 3.11 0 012.7-1.4c1.55 0 3.36.86 3.36 3.66z"
                />
              </svg>
            </a>
            <a href="#" class="social-link" title="Instagram">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="currentColor"
              >
                <path
                  d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"
                />
              </svg>
            </a>
          </div>

          <div class="employee-details">
            <div class="detail-row">
              <span class="detail-label">Reports To</span>
              <span class="detail-value">{{
                employee.reporting_manager || "N/A"
              }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Team</span>
              <span class="detail-value">{{ employee.department }}</span>
            </div>
          </div>

          <div class="contact-info">
            <div class="contact-row">
              <span class="contact-label">Phone</span>
              <span class="contact-value">{{ employee.mobile_number }}</span>
            </div>
            <div class="contact-row">
              <span class="contact-label">Mail</span>
              <span class="contact-value">{{ employee.work_email }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- No Data State -->
      <div v-else-if="!isLoading" class="no-data-container">
        <div class="no-data-card">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="48"
            height="48"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <h3>No employees found</h3>
          <p>
            We couldn't find any employees matching your search or no employees
            have been added yet.
          </p>
        </div>
      </div>
    </div>

    <!-- Employee Details Modal -->
    <div
      class="modal-overlay"
      v-if="showDetailsModal && selectedEmployee"
      @click="showDetailsModal = false"
    >
      <div class="modal details-modal" @click.stop>
        <div class="modal-header">
          <h2>Employee Details</h2>
          <button class="close-btn" @click="showDetailsModal = false">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
        <div class="modal-body profile-details">
          <div class="profile-header">
            <img
              :src="
                selectedEmployee.avatar_url || 'https://i.pravatar.cc/150?img=1'
              "
              class="large-avatar"
            />
            <div class="header-info">
              <h3>{{ selectedEmployee.full_name }}</h3>
              <p class="position">{{ selectedEmployee.position }}</p>
              <span
                class="status-badge"
                :class="selectedEmployee.employment_status?.toLowerCase()"
              >
                {{ selectedEmployee.employment_status }}
              </span>
            </div>
          </div>

          <div class="details-grid">
            <div class="detail-item">
              <label>Employee ID</label>
              <span>{{ selectedEmployee.employee_id }}</span>
            </div>
            <div class="detail-item">
              <label>Department</label>
              <span>{{ selectedEmployee.department }}</span>
            </div>
            <div class="detail-item">
              <label>Work Location</label>
              <span>{{ selectedEmployee.work_location }}</span>
            </div>
            <div class="detail-item">
              <label>Manager</label>
              <span>{{ selectedEmployee.reporting_manager || "N/A" }}</span>
            </div>
            <div class="detail-item">
              <label>Email</label>
              <span>{{ selectedEmployee.work_email }}</span>
            </div>
            <div class="detail-item">
              <label>Mobile</label>
              <span>{{ selectedEmployee.mobile_number }}</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-submit" @click="editProfile(selectedEmployee)">
            Edit Profile
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onActivated, onUnmounted, watch } from "vue";
import { useRouter, useRoute } from "vue-router";

import employeeInfoService from "../../../../services/employeeInfoService";
import { toast } from "vue3-toastify";

const router = useRouter();
const route = useRoute();

const searchQuery = ref("");
const employees = ref([]);
const isLoading = ref(false);
const openMenuId = ref(null);
const showDetailsModal = ref(false);
const selectedEmployee = ref(null);

const closeMenu = () => {
  openMenuId.value = null;
};

const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id;
};

// Fetch employees from API
const fetchEmployees = async () => {
  try {
    isLoading.value = true;
    const response = await employeeInfoService.getAll();

    if (response.success) {
      employees.value = response.data;
    } else {
      toast.error("Failed to load employees");
    }
  } catch (error) {
    console.error("Error fetching employees:", error);
    toast.error("Failed to load employees");
  } finally {
    isLoading.value = false;
  }
};

const toggleStatus = async (employee) => {
  const newStatus =
    employee.employment_status === "Active" ? "Inactive" : "Active";
  try {
    const response = await employeeInfoService.updateStatus(
      employee.id,
      newStatus,
    );
    if (response.success) {
      employee.employment_status = newStatus;
      toast.success(`${employee.full_name} is now ${newStatus}`);
    }
  } catch (error) {
    toast.error("Failed to update status");
  }
};

const viewDetails = (employee) => {
  selectedEmployee.value = employee;
  showDetailsModal.value = true;
};

const editProfile = (employee) => {
  router.push({ name: "EmployeeProfiles", query: { edit: employee.id } });
};

const deleteEmployee = async (employee) => {
  if (confirm(`Are you sure you want to delete ${employee.full_name}?`)) {
    try {
      const response = await employeeInfoService.delete(employee.id);
      if (response.success) {
        employees.value = employees.value.filter((e) => e.id !== employee.id);
        toast.success("Employee deleted successfully");
      }
    } catch (error) {
      toast.error("Failed to delete employee");
    }
  }
};

const filteredEmployees = computed(() => {
  if (!searchQuery.value) return employees.value;

  const query = searchQuery.value.toLowerCase();
  return employees.value.filter(
    (emp) =>
      emp.full_name?.toLowerCase().includes(query) ||
      emp.position?.toLowerCase().includes(query) ||
      emp.department?.toLowerCase().includes(query) ||
      emp.work_location?.toLowerCase().includes(query),
  );
});

onMounted(() => {
  fetchEmployees();
  window.addEventListener("click", closeMenu);
});

onActivated(() => {
  fetchEmployees();
});

watch(
  () => route.fullPath,
  () => {
    fetchEmployees();
  },
);

onUnmounted(() => {
  window.removeEventListener("click", closeMenu);
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.employee-directory {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
  font-family: "Archivo", sans-serif;
}

.directory-content {
  flex: 1;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
  gap: 24px;
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  letter-spacing: -0.3px;
}

.header-actions {
  display: flex;
  gap: 16px;
  align-items: center;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  min-width: 300px;
  transition: all 0.2s;
}

.search-box:focus-within {
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
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
  color: #2d3748;
  font-family: "Archivo", sans-serif;
}

.search-box input::placeholder {
  color: #718096;
}

.add-employee-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Archivo", sans-serif;
  white-space: nowrap;
}

.add-employee-btn:hover {
  background: #2d3748;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.3);
}

/* Employee Grid */
.employee-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  align-items: stretch;
}

.employee-card {
  background: #fff;
  border: 1px solid #e8e8e8;
  border-radius: 10px;
  padding: 28px 24px 24px;
  position: relative;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.employee-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

.card-header-actions {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 10;
}

.card-menu {
  background: transparent;
  border: none;
  color: #718096;
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.2s;
}

.card-menu:hover {
  background: #f7fafc;
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  padding: 8px;
  min-width: 150px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  z-index: 100;
  margin-top: 4px;
}

.dropdown-menu button {
  padding: 8px 12px;
  text-align: left;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 13px;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.2s;
}

.dropdown-menu button:hover {
  background: #f7fafc;
}

.dropdown-menu button.delete-btn {
  color: #e53e3e;
}

.dropdown-menu button.delete-btn:hover {
  background: #fff5f5;
}

.employee-avatar-wrapper {
  position: relative;
  margin-bottom: 16px;
}

.employee-avatar {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.status-indicator {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 3px solid #ffffff;
}

.status-indicator.online,
.status-indicator.active {
  background: #48bb78;
}

.status-indicator.busy {
  background: #ed8936;
}

.status-indicator.offline,
.status-indicator.inactive {
  background: #a0aec0;
}

.employee-avatar.is-inactive {
  filter: grayscale(100%);
  opacity: 0.6;
}

.employee-name {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 6px;
  text-align: center;
}

.employee-role {
  font-size: 13px;
  color: #718096;
  margin-bottom: 4px;
  text-align: center;
  font-weight: 400;
}

.employee-location {
  font-size: 13px;
  color: #718096;
  margin-bottom: 12px;
  text-align: center;
  font-weight: 400;
}

.social-links {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}

.social-link {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #718096;
  transition: all 0.2s;
  text-decoration: none;
}

.social-link:hover {
  background: #48bb78;
  border-color: #48bb78;
  color: white;
  transform: translateY(-2px);
}

.employee-details {
  width: 100%;
  display: flex;
  gap: 16px;
  margin-bottom: 12px;
  padding-bottom: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.detail-row {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-label {
  font-size: 10px;
  color: #718096;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.detail-value {
  font-size: 13px;
  color: #2d3748;
  font-weight: 500;
}

.contact-info {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.contact-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.contact-label {
  font-size: 11px;
  color: #718096;
  font-weight: 500;
}

.contact-value {
  font-size: 12px;
  color: #2d3748;
  font-weight: 400;
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
  animation: fadeIn 0.2s;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal {
  background-color: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s;
}

@keyframes slideUp {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 28px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  font-size: 22px;
  font-weight: 700;
  color: #2d3748;
}

.close-btn {
  background: transparent;
  border: none;
  color: #718096;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #f7fafc;
  color: #2d3748;
}

.modal-body {
  padding: 28px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #2d3748;
}

.form-group input,
.form-group select {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
  font-family: "Archivo", sans-serif;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.form-group select {
  cursor: pointer;
  background: #ffffff;
}

.modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.btn-cancel,
.btn-submit {
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Archivo", sans-serif;
}

.btn-cancel {
  background: transparent;
  border: 1px solid #e2e8f0;
  color: #2d3748;
}

.btn-cancel:hover {
  background: #f7fafc;
}

.btn-submit {
  background: #48bb78;
  border: 1px solid #48bb78;
  color: white;
}

.btn-submit:hover {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}

/* Loading Overlay */
.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  gap: 16px;
  margin-top: 20px;
}

.loader {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #48bb78;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.loading-overlay p {
  color: #718096;
  font-size: 15px;
  font-weight: 500;
}

/* Details Modal Styles */
.details-modal {
  max-width: 600px;
}

.profile-details {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.profile-header {
  display: flex;
  align-items: center;
  gap: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid #e2e8f0;
}

.large-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #f7fafc;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.header-info h3 {
  font-size: 24px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 4px;
}

.header-info .position {
  color: #718096;
  font-size: 16px;
  margin-bottom: 12px;
}

.details-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.detail-item label {
  font-size: 12px;
  font-weight: 600;
  color: #a0aec0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-item span {
  font-size: 15px;
  color: #2d3748;
  font-weight: 500;
}

/* Status Badges */
.status-badge {
  display: inline-flex;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.active,
.status-badge.regular {
  background: #f0fff4;
  color: #38a169;
}

.status-badge.inactive {
  background: #fff5f5;
  color: #e53e3e;
}

.status-badge.probationary {
  background: #fffaf0;
  color: #dd6b20;
}

/* No Data State */
.no-data-container {
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.no-data-card {
  max-width: 400px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.no-data-card svg {
  color: #cbd5e0;
}

.no-data-card h3 {
  font-size: 20px;
  font-weight: 600;
  color: #2d3748;
}

.no-data-card p {
  color: #718096;
  font-size: 15px;
  line-height: 1.5;
}

/* Responsive */
@media (max-width: 1400px) {
  .employee-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1200px) {
  .directory-content {
    margin-left: 0;
  }
}

@media (max-width: 900px) {
  .employee-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .directory-content {
    padding: 20px;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    flex-direction: column;
  }

  .search-box {
    min-width: 100%;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
