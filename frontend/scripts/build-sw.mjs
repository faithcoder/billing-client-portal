import { readdir, readFile, writeFile } from "node:fs/promises";
import { createHash } from "node:crypto";
const files = (await readdir("dist/assets"))
  .filter((name) => /\.(js|css|woff2?)$/.test(name))
  .map((name) => `/assets/${name}`);
const offline = await readFile("public/offline.html", "utf8");
const hash = createHash("sha256")
  .update(JSON.stringify(files))
  .update(offline)
  .digest("hex")
  .slice(0, 16);
const offlinePath = `/offline-${hash}.html`;
await writeFile(`dist${offlinePath}`, offline);
const template = await readFile("scripts/sw-template.js", "utf8");
await writeFile(
  "dist/sw.js",
  template
    .replace("__ASSETS__", JSON.stringify(files))
    .replaceAll("__VERSION__", hash)
    .replaceAll("__OFFLINE__", offlinePath),
);
console.log(
  `Built privacy-first service worker: ${files.length} versioned assets + generic offline page`,
);
