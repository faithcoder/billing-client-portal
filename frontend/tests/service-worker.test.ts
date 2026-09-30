import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";
test("service worker intercepts only allowed assets and generic navigation fallback", async () => {
  const handlers: Record<string, (event: Record<string, unknown>) => void> = {};
  const cached: string[] = [];
  const cache = {
    addAll: async (urls: string[]) => {
      cached.push(...urls);
    },
    match: async () => "cached",
  };
  const source = readFileSync("scripts/sw-template.js", "utf8").replace(
    "__ASSETS__",
    '["/assets/app-HASH.js"]',
  );
  runInNewContext(source, {
    URL,
    Set,
    self: {
      location: { origin: "https://portal.test" },
      addEventListener: (
        name: string,
        fn: (event: Record<string, unknown>) => void,
      ) => {
        handlers[name] = fn;
      },
      clients: { claim: async () => {} },
    },
    caches: {
      open: async () => cache,
      keys: async () => [],
      delete: async () => true,
    },
    fetch: async () => {
      throw new Error("offline");
    },
  });
  let install: Promise<unknown> | undefined;
  handlers.install({
    waitUntil: (promise: Promise<unknown>) => {
      install = promise;
    },
  });
  await install;
  assert.deepEqual(cached, ["/assets/app-HASH.js", "__OFFLINE__"]);
  for (const path of [
    "/api/v1/bills",
    "/api/v1/auth/login",
    "/sanctum/csrf-cookie",
    "/payments/123/receipt",
    "/documents/bill.pdf",
    "/storage/private",
    "/profile.json",
    "/assets/not-allowlisted.js",
    "/assets/app-HASH.js?user=1",
  ]) {
    let intercepted = false;
    handlers.fetch({
      request: {
        url: `https://portal.test${path}`,
        method: "GET",
        mode: "cors",
      },
      respondWith: () => {
        intercepted = true;
      },
    });
    assert.equal(intercepted, false, path);
  }
  let response: Promise<unknown> | undefined;
  handlers.fetch({
    request: {
      url: "https://portal.test/profile",
      method: "GET",
      mode: "navigate",
    },
    respondWith: (promise: Promise<unknown>) => {
      response = promise;
    },
  });
  assert.equal(await response, "cached");
});
