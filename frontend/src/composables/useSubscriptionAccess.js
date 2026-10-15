import api from "../plugins/axios";
import { ref } from "vue";
import { subscriptionModuleFor } from "../constants/subscriptionModules";

const subscriptionAccess = ref(null);
let loadedAt = 0;
let inFlight = null;
let generation = 0;

// How long navigation may reuse the snapshot; the API still enforces plans itself
export const ACCESS_REUSE_MS = 30000;

/**
 * Retrieves the company-level access snapshot. Plans stay defined server-side;
 * the client only consumes the module keys needed for route navigation.
 *
 * Pass `maxAge` (ms) to reuse a snapshot that recent instead of refetching —
 * route changes and layout mounts do this so a page click doesn't wait on it.
 * Calls made while a request is already running share that request.
 */
export const loadSubscriptionAccess = async ({ maxAge = 0 } = {}) => {
  if (maxAge > 0 && subscriptionAccess.value && Date.now() - loadedAt < maxAge) {
    return subscriptionAccess.value;
  }
  if (inFlight) return inFlight;

  const gen = generation;
  const request = api
    .get("/subscription/access")
    .then(({ data }) => {
      // Signed out while this was loading: don't bring the old account's plan back
      if (gen !== generation) return null;
      subscriptionAccess.value = data?.data ?? { subscription_active: false, modules: [] };
      loadedAt = Date.now();
      return subscriptionAccess.value;
    })
    .finally(() => {
      if (inFlight === request) inFlight = null;
    });
  inFlight = request;
  return request;
};

export const subscriptionAllowsModule = (access, module) =>
  access?.subscription_active === true && access?.modules?.includes(subscriptionModuleFor(module));

export const requiredPlanForModule = (access, module) =>
  access?.required_plans?.[subscriptionModuleFor(module)] ?? null;

export const subscriptionUpgradeMessage = (access, module) => {
  const plan = requiredPlanForModule(access, module);
  if (!plan) return "Your current plan does not include this feature.";
  return `You need the ${plan.name} plan${plan.or_higher ? " or higher" : ""} to access this.`;
};

export const clearSubscriptionAccess = () => {
  subscriptionAccess.value = null;
  loadedAt = 0;
  inFlight = null;
  generation += 1;
};

export const useSubscriptionAccess = () => ({
  ACCESS_REUSE_MS,
  subscriptionAccess,
  loadSubscriptionAccess,
  subscriptionAllowsModule,
  requiredPlanForModule,
  subscriptionUpgradeMessage,
});
