/*
 * BloomCraft's delivery area: Cavite and Metro Manila.
 *
 * src/assets/serviceArea.json is one merged outline of Cavite province and
 * the four Metro Manila districts (PSA boundaries via
 * faeldon/philippines-json-maps, 2023, medium resolution), simplified to
 * about 30 m. Coordinates are GeoJSON [longitude, latitude].
 */
import serviceArea from "../assets/serviceArea.json";

const { type, coordinates } = serviceArea.geometry;
// Outer rings only; the outline has no holes
const outerRings = (type === "Polygon" ? [coordinates] : coordinates).map((polygon) => polygon[0]);

// Ray casting: does the ring contain the point?
const ringContains = (ring, longitude, latitude) => {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i];
    const [xj, yj] = ring[j];
    if (yi > latitude !== yj > latitude && longitude < ((xj - xi) * (latitude - yi)) / (yj - yi) + xi) {
      inside = !inside;
    }
  }
  return inside;
};

export const isInServiceArea = (latitude, longitude) =>
  outerRings.some((ring) => ringContains(ring, longitude, latitude));

/** The outline as Leaflet [latitude, longitude] rings */
export const serviceAreaRings = outerRings.map((ring) => ring.map(([lng, lat]) => [lat, lng]));

/** [[south, west], [north, east]] around the whole area */
export const serviceAreaBounds = (() => {
  const points = outerRings.flat();
  const lats = points.map((p) => p[1]);
  const lngs = points.map((p) => p[0]);
  return [
    [Math.min(...lats), Math.min(...lngs)],
    [Math.max(...lats), Math.max(...lngs)],
  ];
})();

export const SERVICE_AREA_MESSAGE =
  "This area isn't available. Please choose a location within Cavite or Metro Manila.";
