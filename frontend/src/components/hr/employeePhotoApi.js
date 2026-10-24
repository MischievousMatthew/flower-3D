// Upload or remove an employee's profile picture (EmployeeInfoController@updatePhoto)
import api from "../../plugins/axios";

export const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

const reason = (err, fallback) => err?.response?.data?.errors?.photo?.[0] || err?.response?.data?.message || fallback;

/** Returns the new photo's URL */
export async function uploadPhoto(profileId, file) {
  const body = new FormData();
  body.append("photo", file);
  try {
    const res = await api.post(`employees-info/${profileId}/photo`, body, { headers: { "Content-Type": "multipart/form-data" } });
    return res.data?.data?.avatar_url ?? "";
  } catch (err) {
    throw new Error(reason(err, "Couldn't save the photo."));
  }
}

export async function removePhoto(profileId) {
  try {
    await api.delete(`employees-info/${profileId}/photo`);
  } catch (err) {
    throw new Error(reason(err, "Couldn't remove the photo."));
  }
}
