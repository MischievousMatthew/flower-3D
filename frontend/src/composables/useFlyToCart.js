const CART_TARGET_SELECTOR = "#cart-icon";

const waitForTransition = (duration) =>
  new Promise((resolve) => window.setTimeout(resolve, duration));

export function useFlyToCart() {
  const flyToCart = async (sourceEl, options = {}) => {
    if (typeof window === "undefined") return;

    const target = document.querySelector(
      options.targetSelector || CART_TARGET_SELECTOR,
    );

    if (!sourceEl || !target) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    const sourceRect = sourceEl.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();

    if (!sourceRect.width || !sourceRect.height) return;

    const clone = sourceEl.cloneNode(true);
    const duration = options.duration ?? 700;
    const translateX =
      targetRect.left + targetRect.width / 2 - (sourceRect.left + sourceRect.width / 2);
    const translateY =
      targetRect.top + targetRect.height / 2 - (sourceRect.top + sourceRect.height / 2);

    Object.assign(clone.style, {
      position: "fixed",
      left: `${sourceRect.left}px`,
      top: `${sourceRect.top}px`,
      width: `${sourceRect.width}px`,
      height: `${sourceRect.height}px`,
      margin: "0",
      pointerEvents: "none",
      zIndex: "1600",
      borderRadius: "18px",
      transformOrigin: "center center",
      willChange: "transform, opacity, filter",
      transition: `transform ${duration}ms cubic-bezier(0.22, 1, 0.36, 1), opacity ${duration}ms ease, filter ${duration}ms ease`,
      boxShadow: "0 18px 44px rgba(15, 23, 42, 0.18)",
    });

    document.body.appendChild(clone);

    requestAnimationFrame(() => {
      clone.style.transform = `translate(${translateX}px, ${translateY}px) scale(0.18)`;
      clone.style.opacity = "0.3";
      clone.style.filter = "saturate(1.15) blur(0.4px)";
    });

    await waitForTransition(duration);
    clone.remove();
  };

  return {
    flyToCart,
  };
}
