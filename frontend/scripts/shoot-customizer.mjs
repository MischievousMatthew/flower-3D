// Dev-only: screenshot a page with headless Edge for visual checks.
// node scripts/shoot-customizer.mjs --url http://localhost:5173/customize/1 --width 1440 --height 900 --out shot.png
import { execFileSync } from "node:child_process";
import { existsSync } from "node:fs";
import { resolve } from "node:path";

const args = Object.fromEntries(
  process.argv.slice(2).reduce((pairs, value, index, all) => (value.startsWith("--") ? [...pairs, [value.slice(2), all[index + 1]]] : pairs), []),
);
const url = args.url || "http://localhost:5173/customize/1";
const width = Number(args.width || 1440);
const height = Number(args.height || 900);
const out = resolve(args.out || "customizer.png");
const wait = Number(args.wait || 15000);

const EDGE = [
  "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe",
  "C:/Program Files/Microsoft/Edge/Application/msedge.exe",
].find((path) => existsSync(path));
if (!EDGE) throw new Error("Microsoft Edge not found");

execFileSync(
  EDGE,
  [
    "--headless=new",
    "--use-angle=swiftshader",
    "--enable-unsafe-swiftshader",
    "--hide-scrollbars",
    `--window-size=${width},${height}`,
    `--virtual-time-budget=${wait}`,
    `--screenshot=${out}`,
    url,
  ],
  { stdio: "ignore", timeout: wait + 60000 },
);
console.log(out);
