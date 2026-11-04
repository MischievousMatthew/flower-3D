import test from "node:test";
import assert from "node:assert/strict";
import { sizedImage, sizedSrcset } from "../src/utils/cloudinaryImage.js";
import { productPreview, rememberProduct } from "../src/utils/productPreview.js";

const URL = "https://res.cloudinary.com/demo/image/upload/v1793622007/product_images/rose.jpg";

test("Cloudinary photos are asked for at the size they're shown", () => {
  assert.equal(sizedImage(URL, 600), "https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_600/v1793622007/product_images/rose.jpg");
  assert.equal(sizedSrcset(URL, 300), `${sizedImage(URL, 300)} 1x, ${sizedImage(URL, 600)} 2x`);
});

test("other links and already-sized photos are left alone", () => {
  assert.equal(sizedImage("https://images.unsplash.com/photo.jpg", 600), "https://images.unsplash.com/photo.jpg");
  const sized = sizedImage(URL, 600);
  assert.equal(sizedImage(sized, 300), sized);
  assert.equal(sizedImage(null, 300), null);
  assert.equal(sizedSrcset("https://example.com/a.jpg", 300), undefined);
});

test("a clicked product is remembered for its page", () => {
  rememberProduct({ id: 6, product_name: "Sunflower" });
  assert.equal(productPreview("6").product_name, "Sunflower");
  assert.equal(productPreview(7), null);
});
