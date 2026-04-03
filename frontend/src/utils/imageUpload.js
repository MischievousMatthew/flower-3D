const BYTES_PER_MB = 1024 * 1024;

export function getSelectedFile(event) {
  return event?.target?.files?.[0] ?? null;
}

export function validateImageFile(
  file,
  { fieldLabel = "Image", maxSizeMB = 2 } = {},
) {
  if (!file) {
    return null;
  }

  const isFileLike = typeof file === "object" && typeof file.size === "number";
  if (!isFileLike) {
    throw new Error(`${fieldLabel} upload is invalid.`);
  }

  if (!file.type || !file.type.startsWith("image/")) {
    throw new Error(`${fieldLabel} must be an image file.`);
  }

  if (maxSizeMB && file.size > maxSizeMB * BYTES_PER_MB) {
    throw new Error(`${fieldLabel} must be smaller than ${maxSizeMB}MB.`);
  }

  return file;
}

export function readImagePreview(file) {
  return new Promise((resolve, reject) => {
    if (!file) {
      resolve("");
      return;
    }

    const reader = new FileReader();
    reader.onload = () => resolve(typeof reader.result === "string" ? reader.result : "");
    reader.onerror = () => reject(new Error("Failed to read the selected image."));
    reader.readAsDataURL(file);
  });
}

export function buildMultipartFormData(
  values,
  { skipFields = [], fileFields = [], jsonFields = [] } = {},
) {
  const payload = new FormData();

  Object.entries(values).forEach(([key, value]) => {
    if (skipFields.includes(key)) {
      return;
    }

    if (fileFields.includes(key)) {
      if (value) {
        payload.append(key, value);
      }
      return;
    }

    if (jsonFields.includes(key)) {
      payload.append(key, JSON.stringify(value ?? null));
      return;
    }

    if (value === null || value === undefined || value === "") {
      return;
    }

    payload.append(key, value);
  });

  return payload;
}

export function clearFileInput(inputOrRef) {
  const target =
    inputOrRef &&
    typeof inputOrRef === "object" &&
    "value" in inputOrRef &&
    inputOrRef.value &&
    typeof inputOrRef.value === "object" &&
    "value" in inputOrRef.value
      ? inputOrRef.value
      : inputOrRef;

  if (target && typeof target === "object" && "value" in target) {
    target.value = "";
  }
}
