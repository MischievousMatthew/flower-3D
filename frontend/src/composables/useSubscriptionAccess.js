import api from "../plugins/axios";
import { ref } from "vue";
import { subscriptionModuleFor } from "../constants/subscriptionModules";

const subscriptionAccess = ref(null);

/**
 * Retrieves the company-level access snapshot. Plans stay defined server-side;
 * the client only consumes the module keys needed for route navigation.
 */
export const loadSubscriptionAccess = async () => {
  const { data } = await api.get("/subscription/access");
  subscriptionAccess.value = data?.data ?? { subscription_active: false, modules: [] };
  return subscriptionAccess.value;
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
};

export const useSubscriptionAccess = () => ({
  subscriptionAccess,
  loadSubscriptionAccess,
  subscriptionAllowsModule,
  requiredPlanForModule,
  subscriptionUpgradeMessage,
});
