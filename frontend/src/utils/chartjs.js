// Chart.js, loaded once from the CDN the first time a page draws a chart.
// If it can't load, pages still show their numbers; only the chart is missing.
export const loadChartJs = () =>
  new Promise((resolve, reject) => {
    if (window.Chart) return resolve(window.Chart);
    const existing = document.querySelector("script[data-chartjs]");
    const script = existing || document.createElement("script");
    script.addEventListener("load", () => resolve(window.Chart));
    script.addEventListener("error", reject);
    if (!existing) {
      script.src = "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js";
      script.dataset.chartjs = "true";
      document.head.appendChild(script);
    }
  });
