<template>
  <div class="employee-profiles">
    

    <div class="profiles-content">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Employee Profiles</h1>

        <div class="header-actions">
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
              placeholder="Search"
              v-model="searchQuery"
              @input="debouncedSearch"
            />
          </div>

          <button class="filter-btn" @click="toggleFilter('location')">
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
                d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M2 14h4M10 8h4M18 16h4"
              />
            </svg>
            Location
          </button>

          <button class="filter-btn" @click="toggleFilter('department')">
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
                d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M2 14h4M10 8h4M18 16h4"
              />
            </svg>
            Department
          </button>

          <button class="filter-btn" @click="toggleFilter('status')">
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
                d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M2 14h4M10 8h4M18 16h4"
              />
            </svg>
            Status
          </button>

          <button
            class="add-employee-btn"
            @click="openAddModal"
            :disabled="isReadOnlyEmployees"
            :title="isReadOnlyEmployees ? 'You do not have permission to add employees' : 'Add employee'"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
          </button>
        </div>
        <p v-if="isReadOnlyEmployees" class="permission-banner">
          Read-only mode. You can review employee records, but only employees with edit access can create or update profiles.
        </p>
      </div>

      <!-- Loading Overlay -->
      <LoadingOverlay :visible="isLoading" :message="loadingMessage" />

      <!-- Employee Table -->
      <div class="table-container">
        <table class="employee-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Location</th>
              <th>Department</th>
              <th>Position</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="employee in employees"
              :key="employee.id"
              @click="selectEmployee(employee)"
              :class="{ selected: selectedEmployee?.id === employee.id }"
            >
              <td class="name-cell">
                <img
                  :src="
                    employee.avatar_url || 'https://i.pravatar.cc/150?img=1'
                  "
                  :alt="employee.full_name"
                  class="avatar"
                  @error="handleImageError"
                />
                <span>{{ employee.full_name }}</span>
              </td>
              <td>{{ employee.work_location }}</td>
              <td>{{ employee.department }}</td>
              <td>{{ employee.position }}</td>
              <td>
                <span
                  class="status-badge"
                  :class="employee.employment_status.toLowerCase()"
                >
                  {{ employee.employment_status }}
                </span>
              </td>
              <td class="action-cell" @click.stop>
                <button
                  class="action-btn"
                  @click="toggleMenu(employee.id)"
                  :disabled="isReadOnlyEmployees"
                  :title="isReadOnlyEmployees ? 'You do not have permission to modify employees' : 'Open actions'"
                >
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
                <div v-if="openMenuId === employee.id" class="dropdown-menu">
                  <button @click="selectEmployee(employee)">
                    View Details
                  </button>
                  <button :disabled="isReadOnlyEmployees" @click="openEditModal(employee)">Edit</button>
                  <button :disabled="isReadOnlyEmployees" @click="updateEmployeeStatus(employee, 'Regular')">
                    Mark as Regular
                  </button>
                  <button
                    :disabled="isReadOnlyEmployees"
                    @click="updateEmployeeStatus(employee, 'Probationary')"
                  >
                    Mark as Probationary
                  </button>
                  <button class="delete-btn" :disabled="isReadOnlyEmployees" @click="deleteEmployee(employee)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="employees.length === 0">
              <td colspan="6" class="no-data">
                <div class="no-data-message">
                  <p>No employees found</p>
                  <button @click="openAddModal" class="btn-add-first" :disabled="isReadOnlyEmployees">
                    Add First Employee
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination" v-if="pagination.last_page > 1">
          <button
            class="page-btn"
            :disabled="pagination.current_page === 1"
            @click="goToPage(pagination.current_page - 1)"
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
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>
          <span class="page-info">
            Showing {{ pagination.from }}-{{ pagination.to }} of
            {{ pagination.total }}
          </span>
          <button
            class="page-btn"
            :disabled="pagination.current_page === pagination.last_page"
            @click="goToPage(pagination.current_page + 1)"
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
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Right Sidebar -->
    <transition name="slide">
      <div v-if="selectedEmployee" class="employee-sidebar">
        <button class="close-sidebar" @click="selectedEmployee = null">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>

        <div class="sidebar-content">
          <div class="employee-profile">
            <img
              :src="
                selectedEmployee.avatar_url || 'https://i.pravatar.cc/150?img=1'
              "
              :alt="selectedEmployee.full_name"
              class="profile-avatar"
              @error="handleImageError"
            />
            <h2>{{ selectedEmployee.full_name }}</h2>
            <p class="employee-id">{{ selectedEmployee.employee_id }}</p>
            <span
              class="status-badge large"
              :class="selectedEmployee.employment_status.toLowerCase()"
            >
              {{ selectedEmployee.employment_status }}
            </span>
          </div>

          <div class="contact-actions">
            <button
              class="contact-btn"
              @click="sendEmail(selectedEmployee.work_email)"
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
                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                <path d="m3 7 9 6 9-6"></path>
              </svg>
              <span>Email</span>
            </button>
            <button
              class="contact-btn"
              @click="callNumber(selectedEmployee.mobile_number)"
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
                <path
                  d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"
                ></path>
              </svg>
              <span>Call</span>
            </button>
            <button class="contact-btn">
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
                  d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                ></path>
              </svg>
              <span>Message</span>
            </button>
          </div>

          <div class="tabs">
            <button
              :class="{ active: activeTab === 'basic' }"
              @click="activeTab = 'basic'"
            >
              Basic Info
            </button>
            <button
              :class="{ active: activeTab === 'employment' }"
              @click="activeTab = 'employment'"
            >
              Employment
            </button>
            <button
              :class="{ active: activeTab === 'payroll' }"
              @click="activeTab = 'payroll'"
            >
              Payroll
            </button>
            <button
              :class="{ active: activeTab === 'qrcode' }"
              @click="activeTab = 'qrcode'"
            >
              QR Code
            </button>
          </div>

          <!-- Basic Information Tab -->
          <div v-if="activeTab === 'basic'" class="tab-content">
            <div class="info-section">
              <h3>Personal Information</h3>
              <div class="info-row">
                <span class="label">Full Name</span>
                <span class="value">{{ selectedEmployee.full_name }}</span>
              </div>
              <div class="info-row">
                <span class="label">Gender</span>
                <span class="value">{{ selectedEmployee.gender }}</span>
              </div>
              <div class="info-row">
                <span class="label">Date of Birth</span>
                <span class="value">{{
                  formatDate(selectedEmployee.date_of_birth)
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Civil Status</span>
                <span class="value">{{ selectedEmployee.civil_status }}</span>
              </div>
            </div>

            <div class="info-section">
              <h3>Contact Information</h3>
              <div class="info-row">
                <span class="label">Personal Email</span>
                <span class="value">{{ selectedEmployee.personal_email }}</span>
              </div>
              <div class="info-row">
                <span class="label">Work Email</span>
                <span class="value">{{ selectedEmployee.work_email }}</span>
              </div>
              <div class="info-row">
                <span class="label">Mobile Number</span>
                <span class="value">{{ selectedEmployee.mobile_number }}</span>
              </div>
              <div class="info-row">
                <span class="label">Address</span>
                <span class="value">{{ selectedEmployee.address }}</span>
              </div>
            </div>

            <div class="info-section">
              <h3>Emergency Contact</h3>
              <div class="info-row">
                <span class="label">Contact Name</span>
                <span class="value">{{
                  selectedEmployee.emergency_contact_name
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Contact Number</span>
                <span class="value">{{
                  selectedEmployee.emergency_contact_number
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Relationship</span>
                <span class="value">{{
                  selectedEmployee.emergency_relationship
                }}</span>
              </div>
            </div>
          </div>

          <!-- Employment Tab -->
          <div v-if="activeTab === 'employment'" class="tab-content">
            <div class="info-section">
              <h3>Employment Details</h3>
              <div class="info-row">
                <span class="label">Employee ID</span>
                <span class="value">{{ selectedEmployee.employee_id }}</span>
              </div>
              <div class="info-row">
                <span class="label">Employment Status</span>
                <span class="value">{{
                  selectedEmployee.employment_status
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Position</span>
                <span class="value">{{ selectedEmployee.position }}</span>
              </div>
              <div class="info-row">
                <span class="label">Department</span>
                <span class="value">{{ selectedEmployee.department }}</span>
              </div>
              <div class="info-row">
                <span class="label">Employment Type</span>
                <span class="value">{{
                  selectedEmployee.employment_type
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Date Hired</span>
                <span class="value">{{
                  formatDate(selectedEmployee.date_hired)
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Work Location</span>
                <span class="value">{{ selectedEmployee.work_location }}</span>
              </div>
              <div class="info-row">
                <span class="label">Reporting Manager</span>
                <span class="value">{{
                  selectedEmployee.reporting_manager
                }}</span>
              </div>
            </div>

            <div class="info-section">
              <h3>Work Schedule</h3>
              <div class="info-row">
                <span class="label">Work Schedule</span>
                <span class="value">{{ selectedEmployee.work_schedule }}</span>
              </div>
              <div class="info-row">
                <span class="label">Shift Time</span>
                <span class="value">
                  {{ selectedEmployee.shift_start }} -
                  {{ selectedEmployee.shift_end }}
                </span>
              </div>
              <div class="info-row">
                <span class="label">Rest Days</span>
                <span class="value">{{ selectedEmployee.rest_days }}</span>
              </div>
            </div>
          </div>

          <!-- Payroll Tab -->
          <div v-if="activeTab === 'payroll'" class="tab-content">
            <div class="info-section">
              <h3>Payroll Information</h3>
              <div class="info-row">
                <span class="label">Basic Salary</span>
                <span class="value">
                  {{
                    selectedEmployee.basic_salary
                      ? `₱${Number(selectedEmployee.basic_salary).toLocaleString()}`
                      : "N/A"
                  }}
                </span>
              </div>
              <div class="info-row">
                <span class="label">Salary Type</span>
                <span class="value">{{
                  selectedEmployee.salary_type || "N/A"
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Payment Method</span>
                <span class="value">{{
                  selectedEmployee.payment_method || "N/A"
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Bank Name</span>
                <span class="value">{{
                  selectedEmployee.bank_name || "N/A"
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Account Number</span>
                <span class="value">{{
                  selectedEmployee.account_number || "N/A"
                }}</span>
              </div>
              <div class="info-row">
                <span class="label">Tax Status</span>
                <span class="value">{{
                  selectedEmployee.tax_status || "N/A"
                }}</span>
              </div>
              <div class="info-row" v-if="selectedEmployee.allowance">
                <span class="label">Allowance</span>
                <span class="value"
                  >₱{{
                    Number(selectedEmployee.allowance).toLocaleString()
                  }}</span
                >
              </div>
            </div>
          </div>
          <!-- QR Code Tab -->
          <div v-if="activeTab === 'qrcode'" class="tab-content qr-tab">
            <EmployeeQRCode
              v-if="selectedEmployee"
              :employee_id="selectedEmployee.id"
              :owner_id="selectedEmployee.owner_id"
              :employee_name="selectedEmployee.full_name"
            />
          </div>
        </div>
      </div>
    </transition>

    <!-- Add/Edit Employee Modal -->
    <div class="modal-overlay" v-if="showModal" @click="closeModal">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <h2>{{ isEditMode ? "Edit Employee" : "Add New Employee" }}</h2>
          <button class="close-btn" @click="closeModal">
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

        <form @submit.prevent="saveEmployee" class="modal-body">
          <p v-if="isReadOnlyEmployees" class="permission-banner">
            Read-only mode. Form fields are disabled because you only have view access to Employees.
          </p>
          <fieldset :disabled="isReadOnlyEmployees" class="permission-fieldset">
          <!-- Profile Picture -->
          <div class="form-section">
            <h3 class="section-title">Profile Photo</h3>
            <div class="image-upload">
              <div class="image-preview">
                <img
                  v-if="formData.avatarPreview"
                  :src="formData.avatarPreview"
                  alt="Preview"
                />
                <div v-else class="placeholder">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="40"
                    height="40"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                  >
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              </div>
              <div class="upload-actions">
                <input
                  type="file"
                  ref="fileInput"
                  @change="handleFileUpload"
                  accept="image/*"
                  style="display: none"
                />
                <button
                  type="button"
                  @click="$refs.fileInput.click()"
                  class="upload-btn"
                >
                  Choose File
                </button>
              </div>
            </div>
          </div>

          <!-- Basic Information -->
          <div class="form-section">
            <h3 class="section-title">Basic Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label>First Name *</label>
                <input
                  type="text"
                  v-model="formData.first_name"
                  placeholder="Enter first name"
                  required
                />
                <span v-if="validationErrors.first_name" class="error-message">
                  {{ validationErrors.first_name[0] }}
                </span>
              </div>
              <div class="form-group">
                <label>Middle Name</label>
                <input
                  type="text"
                  v-model="formData.middle_name"
                  placeholder="Enter middle name"
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Last Name *</label>
                <input
                  type="text"
                  v-model="formData.last_name"
                  placeholder="Enter last name"
                  required
                />
                <span v-if="validationErrors.last_name" class="error-message">
                  {{ validationErrors.last_name[0] }}
                </span>
              </div>
              <div class="form-group">
                <label>Gender *</label>
                <select v-model="formData.gender" required>
                  <option value="">Select gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Date of Birth *</label>
                <input type="date" v-model="formData.date_of_birth" required />
              </div>
              <div class="form-group">
                <label>Civil Status *</label>
                <select v-model="formData.civil_status" required>
                  <option value="">Select status</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Divorced">Divorced</option>
                  <option value="Widowed">Widowed</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Contact Information -->
          <div class="form-section">
            <h3 class="section-title">Contact Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Personal Email *</label>
                <input
                  type="email"
                  v-model="formData.personal_email"
                  placeholder="personal@email.com"
                  required
                />
              </div>
              <div class="form-group">
                <label>Work Email *</label>
                <input
                  type="email"
                  v-model="formData.work_email"
                  placeholder="work@company.com"
                  required
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Mobile Number *</label>
                <input
                  type="tel"
                  v-model="formData.mobile_number"
                  placeholder="+63 XXX XXX XXXX"
                  required
                />
              </div>
              <div class="form-group">
                <label>Address *</label>
                <input
                  type="text"
                  v-model="formData.address"
                  placeholder="Complete address"
                  required
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Emergency Contact Name *</label>
                <input
                  type="text"
                  v-model="formData.emergency_contact_name"
                  placeholder="Contact person"
                  required
                />
              </div>
              <div class="form-group">
                <label>Emergency Contact Number *</label>
                <input
                  type="tel"
                  v-model="formData.emergency_contact_number"
                  placeholder="+63 XXX XXX XXXX"
                  required
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Relationship *</label>
                <input
                  type="text"
                  v-model="formData.emergency_relationship"
                  placeholder="e.g., Spouse, Parent"
                  required
                />
              </div>
            </div>
          </div>

          <!-- Employment Details -->
          <div class="form-section">
            <h3 class="section-title">Employment Details</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Employment Status *</label>
                <select v-model="formData.employment_status" required>
                  <option value="">Select status</option>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                  <option value="Probationary">Probationary</option>
                  <option value="Regular">Regular</option>
                  <option value="Contractual">Contractual</option>
                  <option value="Part-time">Part-time</option>
                </select>
              </div>
              <div class="form-group">
                <label>Position *</label>
                <input
                  type="text"
                  v-model="formData.position"
                  placeholder="Job title"
                  required
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Department *</label>
                <input
                  type="text"
                  v-model="formData.department"
                  placeholder="e.g., IT, HR"
                  required
                />
              </div>
              <div class="form-group">
                <label>Employment Type *</label>
                <select v-model="formData.employment_type" required>
                  <option value="">Select type</option>
                  <option value="Full-time">Full-time</option>
                  <option value="Part-time">Part-time</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Date Hired *</label>
                <input type="date" v-model="formData.date_hired" required />
              </div>
              <div class="form-group">
                <label>Work Location *</label>
                <input
                  type="text"
                  v-model="formData.work_location"
                  placeholder="Office/Branch"
                  required
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Reporting Manager *</label>
                <input
                  type="text"
                  v-model="formData.reporting_manager"
                  placeholder="Manager name"
                  required
                />
              </div>
            </div>
          </div>

          <!-- Attendance & Work Setup -->
          <div class="form-section">
            <h3 class="section-title">Attendance & Work Setup</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Work Schedule *</label>
                <select v-model="formData.work_schedule" required>
                  <option value="">Select schedule</option>
                  <option value="Fixed">Fixed</option>
                  <option value="Shifting">Shifting</option>
                </select>
              </div>
              <div class="form-group">
                <label>Standard Work Hours/Day *</label>
                <input
                  type="number"
                  v-model.number="formData.standard_work_hours_per_day"
                  placeholder="8.00"
                  step="0.01"
                  min="0.01"
                  max="24"
                  required
                />
              </div>
            </div>

            <!-- NEW: Multiple Rest Days Selection -->
            <div class="form-group-full">
              <label>Rest Days *</label>
              <div class="rest-days-selector">
                <div class="selected-days">
                  <div
                    v-for="(day, index) in formData.rest_days_array"
                    :key="index"
                    class="day-chip"
                  >
                    {{ day }}
                    <button
                      type="button"
                      @click="removeRestDay(index)"
                      class="remove-day"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
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
                  <button
                    v-if="formData.rest_days_array.length < 7"
                    type="button"
                    @click="showDayPicker = true"
                    class="add-day-btn"
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
                      <line x1="12" y1="5" x2="12" y2="19"></line>
                      <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Day
                  </button>
                </div>

                <!-- Day Picker Dropdown -->
                <div v-if="showDayPicker" class="day-picker-dropdown">
                  <button
                    v-for="day in availableRestDays"
                    :key="day"
                    type="button"
                    @click="addRestDay(day)"
                    class="day-option"
                  >
                    {{ day }}
                  </button>
                </div>
              </div>
              <span class="field-hint"
                >Select one or more rest days (e.g., Saturday, Sunday)</span
              >
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Shift Start Time *</label>
                <input type="time" v-model="formData.shift_start" required />
              </div>
              <div class="form-group">
                <label>Shift End Time *</label>
                <input type="time" v-model="formData.shift_end" required />
              </div>
            </div>
          </div>

          <!-- Payroll Information -->
          <div class="form-section">
            <h3 class="section-title">Payroll Configuration</h3>

            <div class="info-alert">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
              <div>
                <strong>Required for Payroll Generation</strong>
                <p>
                  Configure these fields to enable automatic payroll calculation
                  for this employee.
                </p>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Basic Salary *</label>
                <input
                  type="number"
                  v-model="formData.basic_salary"
                  placeholder="0.00"
                  step="0.01"
                  min="0"
                  required
                />
                <span class="field-hint">Base salary amount per period</span>
                <span
                  v-if="validationErrors.basic_salary"
                  class="error-message"
                >
                  {{ validationErrors.basic_salary[0] }}
                </span>
              </div>

              <div class="form-group">
                <label>Salary Type *</label>
                <select v-model="formData.salary_type" required>
                  <option value="">Select type</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
                <span class="field-hint">How often the employee is paid</span>
                <span v-if="validationErrors.salary_type" class="error-message">
                  {{ validationErrors.salary_type[0] }}
                </span>
              </div>
            </div>

            <!-- Conditional: Working Days per Week (for weekly salary) -->
            <div v-if="formData.salary_type === 'weekly'" class="form-row">
              <div class="form-group">
                <label>Working Days per Week *</label>
                <input
                  type="number"
                  v-model.number="formData.working_days_per_week"
                  placeholder="5"
                  min="1"
                  max="7"
                  required
                />
                <span class="field-hint"
                  >Number of working days in a week (e.g., 5 for Mon-Fri)</span
                >
                <span
                  v-if="validationErrors.working_days_per_week"
                  class="error-message"
                >
                  {{ validationErrors.working_days_per_week[0] }}
                </span>
              </div>
            </div>

            <!-- Conditional: Working Days per Month (for monthly salary) -->
            <div v-if="formData.salary_type === 'monthly'" class="form-row">
              <div class="form-group">
                <label>Working Days per Month *</label>
                <input
                  type="number"
                  v-model.number="formData.working_days_per_month"
                  placeholder="22"
                  min="1"
                  max="31"
                  required
                />
                <span class="field-hint"
                  >Average working days per month (typically 22)</span
                >
                <span
                  v-if="validationErrors.working_days_per_month"
                  class="error-message"
                >
                  {{ validationErrors.working_days_per_month[0] }}
                </span>
              </div>
            </div>

            <!-- Salary Calculation Preview -->
            <div
              v-if="
                formData.basic_salary &&
                formData.salary_type &&
                formData.standard_work_hours_per_day
              "
              class="salary-preview"
            >
              <div class="preview-header">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <line x1="12" y1="1" x2="12" y2="23"></line>
                  <path
                    d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                  ></path>
                </svg>
                <span>Hourly Rate Preview</span>
              </div>
              <div class="preview-value">₱{{ calculateHourlyRate() }}/hour</div>
              <div class="preview-note">
                This is automatically calculated based on salary type and work
                hours.
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-cancel" @click="closeModal">
              Cancel
            </button>
            <button type="submit" class="btn-submit" :disabled="isReadOnlyEmployees || isSaving">
              {{
                isSaving
                  ? "Saving..."
                  : isEditMode
                    ? "Update Employee"
                    : "Add Employee"
              }}
            </button>
          </div>
          </fieldset>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { toast } from "vue3-toastify";

import LoadingOverlay from "../../../../layouts/components/LoadingOverlay.vue";
import EmployeeQRCode from "../../../../layouts/components/EmployeeQRCode.vue";
import employeeInfoService from "../../../../services/employeeInfoService";
import { useAssignment } from "../../../../composables/useAssignment";
import {
  buildMultipartFormData,
  clearFileInput,
  getSelectedFile,
  readImagePreview,
  validateImageFile,
} from "../../../../utils/imageUpload";

// State
const searchQuery = ref("");
const { canEdit, isReadOnly } = useAssignment();
const selectedEmployee = ref(null);
const activeTab = ref("basic");
const showModal = ref(false);
const isEditMode = ref(false);
const openMenuId = ref(null);
const isLoading = ref(false);
const loadingMessage = ref("Loading employees...");
const isSaving = ref(false);
const employees = ref([]);
const validationErrors = ref({});
const showDayPicker = ref(false);
const canEditEmployees = computed(() => canEdit("employees"));
const isReadOnlyEmployees = computed(() => isReadOnly("employees"));

const allWeekDays = [
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
  "Sunday",
];

const pagination = ref({
  total: 0,
  per_page: 15,
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
});

// Form
const formData = ref({
  first_name: "",
  middle_name: "",
  last_name: "",
  gender: "",
  date_of_birth: "",
  civil_status: "",
  avatar: null,
  avatarPreview: "",
  personal_email: "",
  work_email: "",
  mobile_number: "",
  address: "",
  emergency_contact_name: "",
  emergency_contact_number: "",
  emergency_relationship: "",
  employment_status: "",
  position: "",
  department: "",
  employment_type: "",
  date_hired: "",
  work_location: "",
  reporting_manager: "",
  work_schedule: "",
  rest_days: "",
  rest_days_array: [],
  shift_start: "",
  shift_end: "",
  rest_days: "",
  basic_salary: null,
  salary_type: "",
  payment_method: "",
  bank_name: "",
  account_number: "",
  tax_status: "",
  allowance: null,
});

const availableRestDays = computed(() => {
  return allWeekDays.filter(
    (day) => !formData.value.rest_days_array.includes(day),
  );
});

// Calculate hourly rate preview
function calculateHourlyRate() {
  const salary = parseFloat(formData.value.basic_salary) || 0;
  const hoursPerDay =
    parseFloat(formData.value.standard_work_hours_per_day) || 8;
  const salaryType = formData.value.salary_type;

  if (!salary || !salaryType || !hoursPerDay) {
    return "0.00";
  }

  let hourlyRate = 0;

  switch (salaryType) {
    case "daily":
      hourlyRate = salary / hoursPerDay;
      break;
    case "weekly":
      const daysPerWeek = parseFloat(formData.value.working_days_per_week) || 5;
      hourlyRate = salary / (hoursPerDay * daysPerWeek);
      break;
    case "monthly":
      const daysPerMonth =
        parseFloat(formData.value.working_days_per_month) || 22;
      hourlyRate = salary / (hoursPerDay * daysPerMonth);
      break;
  }

  return hourlyRate.toFixed(2);
}

onMounted(async () => {
  const route = useRoute();
  const editId = route.query.id || route.query.edit;

  if (editId) {
    // If we have an ID to edit, search for it specifically
    searchQuery.value = editId;
  }

  await loadEmployees();

  // If we came here to edit a specific person, find them in the results and open modal
  if (editId && employees.value.length > 0) {
    const employeeToEdit = employees.value.find(
      (e) => e.id == editId || e.employee_id == editId,
    );
    if (employeeToEdit) {
      openEditModal(employeeToEdit);
    }
  }
});

// Load employees
async function loadEmployees(page = 1) {
  try {
    isLoading.value = true;
    loadingMessage.value = "Loading employees...";

    const params = {
      page,
      per_page: pagination.value.per_page,
      search: searchQuery.value,
    };

    const response = await employeeInfoService.getAll(params);

    if (response.success) {
      employees.value = response.data;
      pagination.value = response.pagination;
    }
  } catch (error) {
    toast.error(error.message || "Failed to load employees");
    console.error("Error loading employees:", error);
  } finally {
    isLoading.value = false;
  }
}

let searchTimeout;
function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadEmployees(1);
  }, 500);
}

function goToPage(page) {
  loadEmployees(page);
}

function addRestDay(day) {
  if (!formData.value.rest_days_array.includes(day)) {
    formData.value.rest_days_array.push(day);
    formData.value.rest_days = formData.value.rest_days_array.join(", ");
  }
  showDayPicker.value = false;
}

function removeRestDay(index) {
  formData.value.rest_days_array.splice(index, 1);
  formData.value.rest_days = formData.value.rest_days_array.join(", ");
}

const selectEmployee = (employee) => {
  selectedEmployee.value = employee;
  activeTab.value = "basic";
  openMenuId.value = null;
};

const toggleMenu = (id) => {
  if (!canEditEmployees.value) {
    openMenuId.value = null;
    return;
  }
  openMenuId.value = openMenuId.value === id ? null : id;
};

async function updateEmployeeStatus(employee, status) {
  if (!canEditEmployees.value) {
    toast.error("You do not have permission to update employee status.");
    return;
  }
  try {
    loadingMessage.value = "Updating employee status...";
    isLoading.value = true;

    const response = await employeeInfoService.updateStatus(
      employee.id,
      status,
    );

    if (response.success) {
      toast.success("Employment status updated successfully");
      employee.employment_status = status;
      if (selectedEmployee.value?.id === employee.id) {
        selectedEmployee.value.employment_status = status;
      }
    }
  } catch (error) {
    toast.error(error.message || "Failed to update status");
  } finally {
    isLoading.value = false;
    openMenuId.value = null;
  }
}

async function deleteEmployee(employee) {
  if (!canEditEmployees.value) {
    toast.error("You do not have permission to delete employees.");
    return;
  }
  if (!confirm(`Are you sure you want to delete ${employee.full_name}?`)) {
    return;
  }

  try {
    loadingMessage.value = "Deleting employee...";
    isLoading.value = true;

    const response = await employeeInfoService.delete(employee.id);

    if (response.success) {
      toast.success("Employee deleted successfully");

      if (selectedEmployee.value?.id === employee.id) {
        selectedEmployee.value = null;
      }

      await loadEmployees(pagination.value.current_page);
    }
  } catch (error) {
    toast.error(error.message || "Failed to delete employee");
    isLoading.value = false;
  } finally {
    openMenuId.value = null;
  }
}

// Open modal
const openAddModal = () => {
  if (!canEditEmployees.value) {
    toast.error("You do not have permission to add employees.");
    return;
  }
  isEditMode.value = false;
  validationErrors.value = {};
  showDayPicker.value = false;
  formData.value = {
    first_name: "",
    middle_name: "",
    last_name: "",
    gender: "",
    date_of_birth: "",
    civil_status: "",
    avatar: null,
    avatarPreview: "",
    personal_email: "",
    work_email: "",
    mobile_number: "",
    address: "",
    emergency_contact_name: "",
    emergency_contact_number: "",
    emergency_relationship: "",
    employment_status: "",
    position: "",
    department: "",
    employment_type: "",
    date_hired: "",
    work_location: "",
    reporting_manager: "",
    work_schedule: "",
    rest_days: "",
    rest_days_array: [],
    shift_start: "",
    shift_end: "",
    rest_days: "",
    basic_salary: null,
    salary_type: "",
    standard_work_hours_per_day: null,
    working_days_per_week: null,
    working_days_per_month: null,
  };
  showModal.value = true;
};

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  return dateString.split("T")[0];
};

const openEditModal = (employee) => {
  if (!canEditEmployees.value) {
    toast.error("You do not have permission to edit employees.");
    return;
  }
  isEditMode.value = true;
  validationErrors.value = {};
  showDayPicker.value = false;

  const restDaysArray = employee.rest_days
    ? employee.rest_days.split(",").map((d) => d.trim())
    : [];

  formData.value = {
    id: employee.id,
    first_name: employee.first_name,
    middle_name: employee.middle_name,
    last_name: employee.last_name,
    gender: employee.gender,
    date_of_birth: formatDateForInput(employee.date_of_birth),
    civil_status: employee.civil_status,
    avatar: null,
    avatarPreview: employee.avatar_url || "https://i.pravatar.cc/150?img=1",
    personal_email: employee.personal_email,
    work_email: employee.work_email,
    mobile_number: employee.mobile_number,
    address: employee.address,
    emergency_contact_name: employee.emergency_contact_name,
    emergency_contact_number: employee.emergency_contact_number,
    emergency_relationship: employee.emergency_relationship,
    employment_status: employee.employment_status,
    position: employee.position,
    department: employee.department,
    employment_type: employee.employment_type,
    date_hired: formatDateForInput(employee.date_hired),
    work_location: employee.work_location,
    reporting_manager: employee.reporting_manager,
    work_schedule: employee.work_schedule,
    est_days: employee.rest_days,
    rest_days_array: restDaysArray,
    shift_start: employee.shift_start,
    shift_end: employee.shift_end,
    rest_days: employee.rest_days,
    basic_salary: employee.basic_salary,
    salary_type: employee.salary_type,
    payment_method: employee.payment_method,
    bank_name: employee.bank_name,
    account_number: employee.account_number,
    tax_status: employee.tax_status,
    allowance: employee.allowance,
  };

  showModal.value = true;
  openMenuId.value = null;
};

if (typeof document !== "undefined") {
  document.addEventListener("click", (e) => {
    if (!e.target.closest(".rest-days-selector")) {
      showDayPicker.value = false;
    }
    openMenuId.value = null;
  });
}

const closeModal = () => {
  showModal.value = false;
  isEditMode.value = false;
  validationErrors.value = {};
};

// File upload with preview
const handleFileUpload = async (event) => {
  if (!canEditEmployees.value) {
    return;
  }

  try {
    const file = validateImageFile(getSelectedFile(event), {
      fieldLabel: "Employee avatar",
      maxSizeMB: 5,
    });

    if (!file) {
      return;
    }

    formData.value.avatar = file;
    formData.value.avatarPreview = await readImagePreview(file);
  } catch (error) {
    clearFileInput(event.target);
    toast.error(error.message || "Failed to read the selected employee image");
  }
};

async function saveEmployee() {
  if (!canEditEmployees.value) {
    toast.error("You do not have permission to save employee profiles.");
    return;
  }
  try {
    isSaving.value = true;
    loadingMessage.value = isEditMode.value
      ? "Updating employee..."
      : "Creating employee...";
    validationErrors.value = {};

    const payload = buildMultipartFormData(formData.value, {
      skipFields: ["avatarPreview", "id", "rest_days_array", "est_days"],
      fileFields: ["avatar"],
    });

    let response;
    if (isEditMode.value) {
      response = await employeeInfoService.update(formData.value.id, payload);
    } else {
      response = await employeeInfoService.create(payload);
    }

    if (response.success) {
      toast.success(response.message || "Employee saved successfully");
      closeModal();
      await loadEmployees(pagination.value.current_page);

      if (
        isEditMode.value &&
        selectedEmployee.value?.id === formData.value.id
      ) {
        selectedEmployee.value = response.data;
      }
    }
  } catch (error) {
    if (error.status === 422) {
      validationErrors.value = error.errors || {};

      // Show specific validation errors in toast
      if (error.errors && Object.keys(error.errors).length > 0) {
        // Show first 3 validation errors in toast
        const errorFields = Object.keys(error.errors).slice(0, 3);
        errorFields.forEach((field) => {
          const fieldName = field
            .replace(/_/g, " ")
            .replace(/\b\w/g, (l) => l.toUpperCase());
          toast.error(`${fieldName}: ${error.errors[field][0]}`);
        });

        // If there are more than 3 errors, show a summary
        if (Object.keys(error.errors).length > 3) {
          toast.warning(
            `And ${Object.keys(error.errors).length - 3} more validation error(s)`,
          );
        }
      } else {
        toast.error("Please fix the validation errors");
      }
    } else if (error.status === 403) {
      toast.error(
        error.message || "You do not have permission to perform this action",
      );
    } else {
      toast.error(error.message || "Failed to save employee");
    }
  } finally {
    isSaving.value = false;
  }
}

// Helpers
function formatDate(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function handleImageError(event) {
  event.target.src = "https://i.pravatar.cc/150?img=1";
}

function sendEmail(email) {
  window.location.href = `mailto:${email}`;
}

function callNumber(number) {
  window.location.href = `tel:${number}`;
}

function toggleFilter(type) {
  console.log("Filter by:", type);
  toast.info(`Filter by ${type} - Coming soon!`);
}

// Close menu when clicking outside
if (typeof document !== "undefined") {
  document.addEventListener("click", () => {
    openMenuId.value = null;
  });
}
</script>

<style scoped>
.permission-banner {
  margin: 12px 0 0;
  padding: 12px 14px;
  border: 1px solid #f6ad55;
  border-radius: 10px;
  background: #fffaf0;
  color: #9c4221;
  font-size: 13px;
  line-height: 1.5;
}

.permission-fieldset {
  margin: 0;
  padding: 0;
  border: 0;
  min-width: 0;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

.employee-profiles {
  display: flex;
  min-height: 100vh;
  background: #f7fafc;
}

.profiles-content {
  flex: 1;
  padding: 32px 40px;
  transition: margin-right 0.3s ease;
}

.profiles-content:has(~ .employee-sidebar) {
  margin-right: 420px;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  gap: 24px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #1a202c;
  letter-spacing: -0.5px;
}

.header-actions {
  display: flex;
  gap: 12px;
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
  min-width: 280px;
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
  color: #1a202c;
  font-family: "Poppins", sans-serif;
}

.search-box input::placeholder {
  color: #a0aec0;
}

.filter-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.filter-btn:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

.add-employee-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}

.add-employee-btn:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

/* Table */
.table-container {
  background: #ffffff;
  border-radius: 16px;
  overflow: visible;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.employee-table {
  width: 100%;
  border-collapse: collapse;
}

.employee-table thead {
  background: #f7fafc;
  border-bottom: 2px solid #e2e8f0;
}

.employee-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.employee-table tbody tr {
  border-bottom: 1px solid #f7fafc;
  cursor: pointer;
  transition: all 0.2s;
}

.employee-table tbody tr:hover {
  background: #f7fafc;
}

.employee-table tbody tr.selected {
  background: #f0fff4;
}

.employee-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: #2d3748;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 500;
}

.avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #e2e8f0;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}

.status-badge.regular {
  background: #c6f6d5;
  color: #22543d;
}

.status-badge.probationary {
  background: #feebc8;
  color: #7c2d12;
}

.status-badge.contractual {
  background: #e6fffa;
  color: #234e52;
}

.status-badge.part-time {
  background: #fed7e2;
  color: #702459;
}

.status-badge.large {
  padding: 8px 16px;
  font-size: 13px;
  margin-top: 12px;
}

.action-cell {
  position: relative;
}

.action-btn {
  background: transparent;
  border: none;
  color: #a0aec0;
  cursor: pointer;
  padding: 8px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #f7fafc;
  color: #4a5568;
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 4px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  z-index: 100;
  min-width: 180px;
  overflow: hidden;
}

.dropdown-menu button {
  display: block;
  width: 100%;
  padding: 12px 16px;
  text-align: left;
  background: transparent;
  border: none;
  font-size: 14px;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
  font-weight: 500;
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

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 20px;
  border-top: 1px solid #f7fafc;
}

.page-btn {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: #718096;
}

.page-btn:hover {
  border-color: #48bb78;
  color: #48bb78;
  background: #f0fff4;
}

.page-info {
  font-size: 14px;
  color: #718096;
  font-weight: 500;
}

/* Sidebar */
.employee-sidebar {
  position: fixed;
  right: 0;
  top: 0;
  width: 420px;
  height: 100vh;
  background: #ffffff;
  border-left: 1px solid #e2e8f0;
  overflow-y: auto;
  z-index: 50;
  box-shadow: -4px 0 20px rgba(0, 0, 0, 0.06);
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

.close-sidebar {
  position: absolute;
  top: 20px;
  right: 20px;
  background: #f7fafc;
  border: none;
  border-radius: 8px;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: #718096;
  z-index: 10;
}

.close-sidebar:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.sidebar-content {
  padding: 32px 24px;
}

.employee-profile {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid #f7fafc;
}

.profile-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 16px;
  border: 4px solid #f7fafc;
}

.employee-profile h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 4px;
}

.employee-id {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
  margin-bottom: 8px;
}

.contact-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
}

.contact-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 12px;
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
  color: #4a5568;
}

.contact-btn:hover {
  background: #48bb78;
  border-color: #48bb78;
  color: white;
  transform: translateY(-2px);
}

.contact-btn svg {
  color: inherit;
}

.contact-btn span {
  font-size: 12px;
  font-weight: 600;
}

.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  border-bottom: 2px solid #f7fafc;
}

.tabs button {
  flex: 1;
  padding: 12px 16px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  font-size: 14px;
  font-weight: 600;
  color: #a0aec0;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
  margin-bottom: -2px;
}

.tabs button.active {
  color: #48bb78;
  border-bottom-color: #48bb78;
}

.tab-content {
  animation: fadeIn 0.3s;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.info-section {
  margin-bottom: 28px;
}

.info-section h3 {
  font-size: 13px;
  font-weight: 700;
  color: #718096;
  margin-bottom: 16px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 12px 0;
  border-bottom: 1px solid #f7fafc;
}

.info-row:last-child {
  border-bottom: none;
}

.info-row .label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
  flex: 0 0 40%;
}

.info-row .value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 500;
  text-align: right;
  flex: 1;
}

.info-alert {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #ebf8ff;
  border: 1px solid #90cdf4;
  border-radius: 10px;
  margin-bottom: 20px;
}

.info-alert svg {
  color: #2b6cb0;
  flex-shrink: 0;
  margin-top: 2px;
}

.info-alert strong {
  display: block;
  color: #2c5282;
  font-size: 14px;
  margin-bottom: 4px;
}

.info-alert p {
  color: #2c5282;
  font-size: 13px;
  margin: 0;
  line-height: 1.5;
}

/* Salary Preview */
.salary-preview {
  background: #f0fff4;
  border: 1px solid #9ae6b4;
  border-radius: 10px;
  padding: 16px;
  margin-top: 20px;
}

.preview-header {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #22543d;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 8px;
}

.preview-header svg {
  color: #48bb78;
}

.preview-value {
  font-size: 24px;
  font-weight: 700;
  color: #22543d;
  margin-bottom: 4px;
}

.preview-note {
  font-size: 12px;
  color: #2f855a;
  font-style: italic;
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

.modal {
  background-color: #ffffff;
  border-radius: 20px;
  width: 100%;
  max-width: 800px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s;
}

@keyframes slideUp {
  from {
    transform: translateY(30px);
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
  padding: 24px 32px;
  border-bottom: 2px solid #f7fafc;
}

.modal-header h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1a202c;
}

.close-btn {
  background: #f7fafc;
  border: none;
  color: #718096;
  cursor: pointer;
  padding: 8px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #e2e8f0;
  color: #2d3748;
}

.modal-body {
  padding: 32px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
  padding-bottom: 12px;
  border-bottom: 2px solid #f7fafc;
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
  color: #4a5568;
}

.form-group input,
.form-group select {
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
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

.form-group-full {
  display: flex;
  flex-direction: column;
  gap: 8px;
  grid-column: 1 / -1;
}

.rest-days-selector {
  position: relative;
}

.selected-days {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 44px;
  padding: 8px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #ffffff;
}

.day-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #48bb78;
  color: white;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
}

.remove-day {
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.2);
  border: none;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  cursor: pointer;
  transition: all 0.2s;
  padding: 0;
}

.remove-day:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.1);
}

.add-day-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #f7fafc;
  border: 1px dashed #cbd5e0;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  cursor: pointer;
  transition: all 0.2s;
}

.add-day-btn:hover {
  background: #e2e8f0;
  border-color: #48bb78;
  color: #48bb78;
}

.day-picker-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 4px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  z-index: 100;
  max-height: 250px;
  overflow-y: auto;
}

.day-option {
  display: block;
  width: 100%;
  padding: 12px 16px;
  text-align: left;
  background: transparent;
  border: none;
  font-size: 14px;
  font-weight: 500;
  color: #2d3748;
  cursor: pointer;
  transition: all 0.2s;
}

.day-option:hover {
  background: #f7fafc;
  color: #48bb78;
}

.field-hint {
  font-size: 11px;
  color: #a0aec0;
  font-style: italic;
  margin-top: 2px;
}

/* Image Upload */
.image-upload {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

no-data {
  padding: 60px 20px;
  text-align: center;
}

.no-data-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.no-data-message p {
  font-size: 16px;
  color: #718096;
  margin: 0;
}

.btn-add-first {
  padding: 10px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add-first:hover {
  background: #38a169;
  transform: translateY(-1px);
}

.error-message {
  color: #e53e3e;
  font-size: 12px;
  margin-top: 4px;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.image-preview {
  width: 120px;
  height: 120px;
  border-radius: 16px;
  border: 2px dashed #e2e8f0;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f7fafc;
  flex-shrink: 0;
}

.image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.placeholder {
  color: #a0aec0;
}

.upload-actions {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.upload-btn {
  padding: 12px 24px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  align-self: flex-start;
  margin-top: 40px;
}

.upload-btn:hover {
  background: #38a169;
  transform: translateY(-1px);
}

.url-input {
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  color: #2d3748;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.url-input:focus {
  outline: none;
  border-color: #48bb78;
  box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

.modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 24px;
  border-top: 2px solid #f7fafc;
}

.btn-cancel,
.btn-submit {
  padding: 12px 28px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-family: "Poppins", sans-serif;
}

.btn-cancel {
  background: #f7fafc;
  border: 1px solid #e2e8f0;
  color: #4a5568;
}

.btn-cancel:hover {
  background: #e2e8f0;
}

.btn-submit {
  background: #48bb78;
  border: 1px solid #48bb78;
  color: white;
}

.btn-submit:hover {
  background: #38a169;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(72, 187, 120, 0.35);
}

.qr-tab {
  padding: 0;
}

/* Adjust tabs layout for 4 buttons */
.tabs {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
}

/* Responsive */
@media (max-width: 1400px) {
  .employee-sidebar {
    width: 380px;
  }

  .profiles-content:has(~ .employee-sidebar) {
    margin-right: 380px;
  }

  .tabs {
    grid-template-columns: repeat(2, 2fr);
  }
}

@media (max-width: 1200px) {
  .employee-sidebar {
    width: 100%;
    max-width: 420px;
  }

  .profiles-content:has(~ .employee-sidebar) {
    margin-right: 0;
  }
}

@media (max-width: 768px) {
  .profiles-content {
    margin-left: 0;
    padding: 20px;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    flex-wrap: wrap;
  }

  .search-box {
    min-width: 100%;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .image-upload {
    flex-direction: column;
  }
}
</style>
