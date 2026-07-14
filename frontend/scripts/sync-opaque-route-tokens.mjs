import { createHmac } from "node:crypto";
import { readFile, writeFile } from "node:fs/promises";

const routerPath = new URL("../src/router/index.js", import.meta.url);
const tokensPath = new URL("../src/router/opaqueRoutes.js", import.meta.url);
const excludedNames = new Set(["NotFound"]);

const routerSource = await readFile(routerPath, "utf8");
const namedRoutes = [...routerSource.matchAll(/name:\s*["']([^"']+)["']/g)]
  .map((match) => match[1])
  .filter((name) => !excludedNames.has(name));

const tokensSource = await readFile(tokensPath, "utf8");
const existingNames = new Set(
  [...tokensSource.matchAll(/^\s{2}([A-Za-z_$][\w$]*):\s*"[A-Za-z0-9_-]{32,}"/gm)]
    .map((match) => match[1]),
);
const missingNames = [...new Set(namedRoutes)].filter((name) => !existingNames.has(name));

if (!missingNames.length) {
  process.exit(0);
}

const secret = process.env.ROUTE_TOKEN_SECRET;
if (!secret) {
  throw new Error(
    `Missing ROUTE_TOKEN_SECRET. Set it once in local and Vercel environments before adding routes: ${missingNames.join(", ")}`,
  );
}

const newEntries = missingNames
  .map((name) => {
    const token = createHmac("sha256", secret).update(`bloomcraft-route:${name}`).digest("base64url");
    return `  ${name}: "${token}",`;
  })
  .join("\n");

const marker = "});\n\nexport const opaquePathFor";
if (!tokensSource.includes(marker)) {
  throw new Error("Unable to update opaque route registry; expected registry marker is missing.");
}

await writeFile(tokensPath, tokensSource.replace(marker, `${newEntries}\n${marker}`));
console.log(`Registered opaque route token(s): ${missingNames.join(", ")}`);
