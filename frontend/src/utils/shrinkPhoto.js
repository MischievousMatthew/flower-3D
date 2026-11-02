// Phone photos are often 4–12 MB. Redrawn at this size as JPEG, documents stay
// readable and uploads are several times quicker on mobile data (and lighter
// for the server, which forwards every file to Cloudinary).
const MAX_SIDE = 2400;
const SHRINK_OVER = 1.5 * 1024 * 1024;

/** A smaller JPEG copy of a large JPG/PNG photo; anything else comes back as it is */
export async function shrinkPhoto(file) {
  if (!(file instanceof File) || !/^image\/(jpeg|png)$/.test(file.type) || file.size <= SHRINK_OVER) return file;
  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext("2d");
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.85));
    if (!blob || blob.size >= file.size) return file;
    return new File([blob], file.name.replace(/\.\w+$/, "") + ".jpg", { type: "image/jpeg" });
  } catch {
    return file;
  }
}
