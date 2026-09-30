import { test } from "node:test";
import assert from "node:assert/strict";
import { formatDate, formatMoney } from "../src/lib/format.ts";
import { parseUser, parseStatus } from "../src/api/client.ts";
import { bn } from "../src/i18n/bn.ts";
import { en } from "../src/i18n/en.ts";
test("money stays exact above JS safe integer and preserves paisa", () => {
  assert.equal(formatMoney("25001", "en"), "৳250.01");
  assert.equal(
    formatMoney("900719925474099301", "en").replaceAll(",", ""),
    "৳9007199254740993.01",
  );
  assert.equal(formatMoney("5", "bn"), "৳০.০৫");
  for (const invalid of ["1.5", "-1", "1e2", ""])
    assert.throws(() => formatMoney(invalid));
});
test("dates use Dhaka business date, validate calendar input and preserve null", () => {
  assert.equal(
    formatDate("2026-09-30T20:00:00Z", "en"),
    formatDate("2026-10-01", "en"),
  );
  assert.equal(formatDate(null), "—");
  assert.throws(() => formatDate("2026-02-30"));
  assert.throws(() => formatDate("2026-09-30T20:00:00"));
});
test("runtime payload guards reject numeric IDs and unrecognized roles", () => {
  assert.equal(
    parseUser({ id: "0001", name: "Demo", role: "client" }).id,
    "0001",
  );
  assert.throws(() => parseUser({ id: 1, name: "Demo", role: "admin" }));
  assert.throws(() => parseUser({ id: "1", name: "Demo", role: "root" }));
  assert.throws(() => parseStatus({ status: "ready", mode: "live", phase: 2 }));
});
test("Bangla and English have identical translation keys", () =>
  assert.deepEqual(Object.keys(bn).sort(), Object.keys(en).sort()));
