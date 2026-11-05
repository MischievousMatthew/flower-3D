// The ERP sidebar's numbers: how many things wait for someone on each page
// (leave to approve, questions to answer, orders to scan…). One small request
// for all of them, again every minute and after each page change, so a number
// drops soon after the work is done. Pages with nothing waiting have no number.
import { ref } from "vue";
import api from "../plugins/axios";

const counts = ref({});
let timer = null;
let users = 0;
let inFlight = null;

async function refresh() {
  if (inFlight) return inFlight;
  inFlight = api
    .get("erp/badges")
    .then((res) => {
      counts.value = res?.data?.data && typeof res.data.data === "object" ? res.data.data : {};
    })
    .catch(() => {
      // Keep the last numbers; a badge is a hint, not something to show an error for
    })
    .finally(() => {
      inFlight = null;
    });
  return inFlight;
}

export function useErpBadges() {
  function start() {
    users += 1;
    if (users === 1) {
      refresh();
      timer = setInterval(() => document.visibilityState === "visible" && refresh(), 60000);
    }
  }
  function stop() {
    users = Math.max(0, users - 1);
    if (!users && timer) {
      clearInterval(timer);
      timer = null;
    }
  }
  return { badgeCounts: counts, refreshBadges: refresh, startBadges: start, stopBadges: stop };
}
