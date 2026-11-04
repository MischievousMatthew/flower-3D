import QRCode from "qrcode";
import { initialsOf } from "./hrUi";

const QR_OPTIONS = { errorCorrectionLevel: "H", margin: 2, color: { dark: "#111827", light: "#ffffff" } };

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = "anonymous"; // so the canvas can still be downloaded
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
  });
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

async function drawBase(canvas, text, size) {
  await QRCode.toCanvas(canvas, text, { ...QR_OPTIONS, width: size });
  const ctx = canvas.getContext("2d");
  const tile = size * 0.24;
  const x = (size - tile) / 2;
  ctx.fillStyle = "#ffffff";
  roundRect(ctx, x, x, tile, tile, tile * 0.22);
  ctx.fill();
  return ctx;
}

/**
 * A QR code with high error correction and the shop's logo on a white tile in the
 * middle (about 22% wide). Without a usable logo, the shop's initials go there instead.
 * Returns true when the logo itself was drawn.
 */
export async function drawQrWithLogo(canvas, text, { logo = null, name = "", size = 1024 } = {}) {
  let ctx = await drawBase(canvas, text, size);
  const inner = size * 0.24 * 0.8;
  const ix = (size - inner) / 2;
  let logoUsed = false;

  if (logo) {
    try {
      const img = await loadImage(logo);
      ctx.save();
      roundRect(ctx, ix, ix, inner, inner, inner * 0.2);
      ctx.clip();
      // Cover the square, whatever the logo's shape
      const scale = Math.max(inner / img.width, inner / img.height);
      ctx.drawImage(img, size / 2 - (img.width * scale) / 2, size / 2 - (img.height * scale) / 2, img.width * scale, img.height * scale);
      ctx.restore();
      canvas.toDataURL(); // throws if the logo made the canvas unreadable
      logoUsed = true;
    } catch (_) {
      ctx = await drawBase(canvas, text, size);
    }
  }

  if (!logoUsed) {
    const accent = getComputedStyle(document.documentElement).getPropertyValue("--bc-accent").trim() || "#48bb78";
    ctx.fillStyle = accent;
    roundRect(ctx, ix, ix, inner, inner, inner * 0.2);
    ctx.fill();
    ctx.fillStyle = "#ffffff";
    ctx.font = `700 ${inner * 0.42}px Poppins, sans-serif`;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(initialsOf(name) || "BC", size / 2, size / 2 + inner * 0.03);
  }

  // The QR library pins a pixel size on the element; let CSS size it (square) instead
  canvas.style.width = "";
  canvas.style.height = "";
  return logoUsed;
}
