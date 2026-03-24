// composables/useFormSubmit.js
//
// ROOT CAUSE OF THE BUG:
//   Returning a plain object { successMsg: ref(''), ... } means Vue only
//   auto-unwraps refs at the TOP level of the component setup() return.
//   When you do `const generalForm = useFormSubmit()` and access
//   `generalForm.successMsg` in the template, it sees a raw Ref object —
//   v-if treats it as always truthy, and `generalForm.successMsg = ''`
//   replaces the ref with an empty string instead of clearing its value.
//
// FIX:
//   Wrap the return value in reactive(). Vue then auto-unwraps nested refs,
//   so `generalForm.successMsg` in the template works like a plain string,
//   and `generalForm.successMsg = ''` correctly calls ref.value = ''.

import { reactive, ref } from "vue";

export function useFormSubmit() {
  const isSubmitting = ref(false);
  const errors = reactive({});
  const successMsg = ref("");
  const errorMsg = ref("");

  const reset = () => {
    Object.keys(errors).forEach((k) => delete errors[k]);
    successMsg.value = "";
    errorMsg.value = "";
  };

  /** Wire to @input / @change to clear a single field error. */
  const clearError = (field) => {
    if (errors[field]) delete errors[field];
  };

  /**
   * @param {Function} apiFn  - () => axios promise
   * @param {Object}   opts   - { successMsg, onSuccess, onError }
   * @returns {boolean} true on success
   */
  const submit = async (apiFn, opts = {}) => {
    if (isSubmitting.value) return false;
    reset();
    isSubmitting.value = true;
    try {
      const res = await apiFn();

      if (res?.data?.success === false) {
        errorMsg.value = res.data.message || "Something went wrong.";
        return false;
      }

      successMsg.value =
        opts.successMsg ?? res?.data?.message ?? "Saved successfully!";
      if (opts.onSuccess) opts.onSuccess(res);

      // Auto-clear success message after 4 seconds
      setTimeout(() => {
        successMsg.value = "";
      }, 4000);
      return true;
    } catch (err) {
      const data = err?.response?.data;
      if (data?.errors) {
        Object.entries(data.errors).forEach(([k, v]) => {
          errors[k] = Array.isArray(v) ? v[0] : v;
        });
        errorMsg.value =
          Object.values(errors)[0] ?? "Please fix the errors below.";
      } else {
        errorMsg.value = data?.message ?? "An unexpected error occurred.";
      }
      if (opts.onError) opts.onError(err);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  };

  // ─── KEY FIX: wrap in reactive() ─────────────────────────────────────────
  // This makes nested refs (successMsg, errorMsg, isSubmitting) auto-unwrap
  // in templates, so `generalForm.successMsg` behaves like a plain string
  // and `generalForm.successMsg = ''` correctly clears it.
  return reactive({
    isSubmitting,
    errors,
    successMsg,
    errorMsg,
    submit,
    reset,
    clearError,
  });
}
