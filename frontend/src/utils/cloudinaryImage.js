// Cloudinary photos at the size they're shown: the best format the browser
// takes (f_auto), good-enough quality (q_auto) and no wider than needed
// (c_limit never enlarges a small original). Other links are returned as is.
//
//   sizedImage("https://res.cloudinary.com/x/image/upload/v1/p.jpg", 600)
//   → "https://res.cloudinary.com/x/image/upload/f_auto,q_auto,c_limit,w_600/v1/p.jpg"

const UPLOAD = "/image/upload/";

export function sizedImage(url, width) {
  if (typeof url !== "string" || !url.includes("res.cloudinary.com") || !url.includes(UPLOAD)) return url;
  const [head, tail] = url.split(UPLOAD);
  // Already transformed (the first part isn't the version or the file path)
  const first = tail.split("/")[0];
  if (first.includes(",") || /^(f|q|c|w|h|e|t)_/.test(first)) return url;
  const w = Math.max(1, Math.round(Number(width) || 0));
  return `${head}${UPLOAD}f_auto,q_auto,c_limit,w_${w}/${tail}`;
}

/** A srcset with 1× and 2× versions, for sharp photos on high-density screens */
export function sizedSrcset(url, width) {
  const one = sizedImage(url, width);
  if (one === url) return undefined;
  return `${one} 1x, ${sizedImage(url, width * 2)} 2x`;
}
