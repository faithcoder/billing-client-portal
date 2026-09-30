import { fieldLabel } from "../lib/labels";
import { useState } from "react";
import type { FormEvent } from "react";
import { Link, useLocation } from "react-router-dom";
import { request } from "../api/client";
import { decode, page, mutate } from "../api/billing";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { StatusBadge } from "../components/StatusBadge";
import { useLanguage } from "../i18n";
import { useSession } from "../app/session";
import { formatMoney } from "../lib/format";
const modules: Record<string, string> = {
  users: "users",
  "account-links": "account-links",
  payments: "payments",
  "sync-failures": "payments",
  applications: "service-requests",
  complaints: "service-requests",
  "audit-logs": "audit-logs",
};
function Summary() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const r = useResource(
    () =>
      request(
        "/api/v1/admin/summary",
        decode<Record<string, string | number | null>>,
      ),
    "summary",
  );
  return (
    <>
      <h1>{bn ? "কার্যক্রমের সারসংক্ষেপ" : "Operational overview"}</h1>
      <div className="notice">
        <p>
          {bn
            ? "পোর্টালের সংগ্রহ পৌরসভার মোট সংগ্রহ নয়। ব্যাংক ও অফলাইন চ্যানেল এখানে অন্তর্ভুক্ত নয়।"
            : "Portal collections are not total municipal collections. Bank and offline channels are excluded."}
        </p>
      </div>
      <Resource {...r}>
        {r.data && (
          <div className="service-grid dashboard-grid">
            {[
              [
                "verified_collections_minor",
                bn ? "গেটওয়ে-যাচাইকৃত সংগ্রহ" : "Gateway-verified collections",
              ],
              [
                "posted_upstream_minor",
                bn ? "বিলিংয়ে নিশ্চিত পোস্ট" : "Confirmed upstream postings",
              ],
              ["pending_gateway", bn ? "গেটওয়ে অপেক্ষমাণ" : "Pending gateway"],
              [
                "awaiting_sync",
                bn ? "সফল, সিঙ্ক অপেক্ষমাণ" : "Successful, awaiting sync",
              ],
              ["open_applications", bn ? "খোলা আবেদন" : "Open applications"],
              ["open_complaints", bn ? "খোলা অভিযোগ" : "Open complaints"],
            ].map(([key, label]) => (
              <section className="panel content-card" key={key}>
                <p>{label}</p>
                <strong className="amount">
                  {key.endsWith("_minor")
                    ? formatMoney(String(r.data![key]), language)
                    : String(r.data![key])}
                </strong>
              </section>
            ))}
          </div>
        )}
      </Resource>
      <section className="panel content-card">
        <h2>
          {bn
            ? "পৌরসভার সামগ্রিক বিলিং মোট"
            : "Municipality-wide billing totals"}
        </h2>
        <p>
          {bn
            ? "উৎস থেকে সরবরাহ করা হয়নি।"
            : "Not supplied by the authoritative source."}
        </p>
        <Link className="button secondary" to="/admin/integration-health">
          {bn ? "সংযোগের অবস্থা" : "Integration health"}
        </Link>
      </section>
    </>
  );
}
function Health() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const r = useResource(
    () =>
      request(
        "/api/v1/admin/integration-health",
        decode<{
          status: string;
          mode: string;
          capabilities: Record<string, boolean>;
        }>,
      ),
    "health",
  );
  return (
    <>
      <h1>{bn ? "ইন্টিগ্রেশনের অবস্থা" : "Integration health"}</h1>
      <Resource {...r}>
        {r.data && (
          <section className="panel content-card">
            <p>
              {r.data.mode} · {r.data.status}
            </p>
            <dl className="data-grid">
              {Object.entries(r.data.capabilities).map(([k, v]) => (
                <div key={k}>
                  <dt>{fieldLabel(k, language)}</dt>
                  <dd>{v ? "✓" : "—"}</dd>
                </div>
              ))}
            </dl>
            <p>
              {bn
                ? "মক সমর্থন বাস্তব বিলিং API-এর সক্ষমতার প্রমাণ নয়।"
                : "Mock capabilities are not evidence of real vendor support."}
            </p>
          </section>
        )}
      </Resource>
    </>
  );
}
export default function Admin() {
  const key = useLocation().pathname.split("/")[2] ?? "";
  return !key ? (
    <Summary />
  ) : key === "integration-health" ? (
    <Health />
  ) : (
    <Listing key={key} section={key} />
  );
}
function Listing({ section }: { section: string }) {
  const { language } = useLanguage();
  const { user } = useSession();
  const bn = language === "bn";
  const [pageNumber, setPage] = useState(1);
  const [filters, setFilters] = useState("");
  const [actionError, setActionError] = useState(false);
  const [busy, setBusy] = useState(false);
  const [events, setEvents] = useState<Record<string, unknown>[] | null>(null);
  const module = modules[section];
  const base =
    section === "applications"
      ? "&kind=application"
      : section === "complaints"
        ? "&kind=complaint"
        : section === "sync-failures"
          ? "&sync_status=failed"
          : "";
  const r = useResource(
    () =>
      request(
        `/api/v1/admin/data/${module}?page=${pageNumber}${base}&${filters}`,
        page<Record<string, unknown>>,
        {},
        true,
      ),
    section + pageNumber + filters,
  );
  const title = {
    users: bn ? "ব্যবহারকারী" : "Users",
    "account-links": bn ? "যাচাইকৃত হিসাব" : "Verified links",
    payments: bn ? "পেমেন্ট" : "Payments",
    "sync-failures": bn ? "সিঙ্ক সমস্যা" : "Sync failures",
    applications: bn ? "আবেদন" : "Applications",
    complaints: bn ? "অভিযোগ" : "Complaints",
    "audit-logs": bn ? "অডিট লগ" : "Audit logs",
  }[section];
  async function action(path: string, data: unknown = {}, method = "POST") {
    setBusy(true);
    setActionError(false);
    try {
      await mutate("/api/v1/admin/" + path, data, method);
      r.reload();
    } catch {
      setActionError(true);
    } finally {
      setBusy(false);
    }
  }
  function search(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const d = new FormData(e.currentTarget);
    const p = new URLSearchParams();
    for (const [k, v] of d) if (v) p.set(k, String(v));
    setFilters(p.toString());
    setPage(1);
  }
  return (
    <>
      <h1>{title}</h1>
      <form className="panel filter-form" onSubmit={search}>
        <label>
          {bn ? "খুঁজুন" : "Search"}
          <input name="q" maxLength={128} />
        </label>
        {["payments", "service-requests", "account-links"].includes(module) && (
          <label>
            {bn ? "অবস্থা" : "Status"}
            <input name="status" maxLength={32} />
          </label>
        )}
        {module === "payments" && (
          <label>
            {bn ? "বিলিং সিঙ্ক" : "Billing sync"}
            <select name="sync_status">
              <option value="">{bn ? "সব" : "All"}</option>
              {["pending", "syncing", "synced", "failed", "needs_review"].map(
                (s) => (
                  <option key={s}>{s}</option>
                ),
              )}
            </select>
          </label>
        )}
        <button className="button">{bn ? "ফিল্টার" : "Filter"}</button>
      </form>
      {module === "payments" && user?.role === "admin" && (
        <a
          className="button secondary"
          href={"/api/v1/admin/exports/payments?" + filters}
        >
          {bn ? "CSV (সর্বোচ্চ ১০০০ সারি)" : "Export CSV (up to 1,000 rows)"}
        </a>
      )}
      {actionError && (
        <p role="alert">
          {bn
            ? "কাজটি সম্পন্ন হয়নি। অনুমতি বা বর্তমান অবস্থা পরীক্ষা করুন।"
            : "Action failed. Check permissions and current state."}
        </p>
      )}
      <Resource {...r}>
        {r.data && (
          <>
            {r.data.data.length === 0 && (
              <div className="panel state">
                {bn ? "কোনো তথ্য নেই" : "No records"}
              </div>
            )}
            {r.data.data.map((row) => (
              <article
                key={String(row.id)}
                className="panel content-card admin-record"
              >
                <div className="row-between">
                  <strong>
                    {String(
                      row.name ??
                        row.subject ??
                        row.reference ??
                        row.event ??
                        row.external_account_id ??
                        row.id,
                    )}
                  </strong>
                  {Boolean(row.status) && (
                    <StatusBadge status={String(row.status)} />
                  )}
                </div>
                <dl className="data-grid">
                  {Object.entries(row)
                    .filter(
                      ([k]) =>
                        ![
                          "id",
                          "name",
                          "subject",
                          "reference",
                          "event",
                          "status",
                          "form_data",
                          "description",
                        ].includes(k),
                    )
                    .map(([k, v]) => (
                      <div key={k}>
                        <dt>{fieldLabel(k, language)}</dt>
                        <dd>
                          {k === "amount_minor"
                            ? formatMoney(String(v), language)
                            : typeof v === "object"
                              ? v === null
                                ? "\u2014"
                                : JSON.stringify(v)
                              : String(v ?? "—")}
                        </dd>
                      </div>
                    ))}
                </dl>
                <div className="toolbar">
                  {module === "users" && user?.role === "admin" && (
                    <label>
                      {bn ? "ভূমিকা" : "Role"}
                      <select
                        disabled={busy}
                        value={String(row.role)}
                        onChange={(e) =>
                          void action(
                            "users/" + row.id + "/role",
                            { role: e.target.value },
                            "PATCH",
                          )
                        }
                      >
                        {["client", "support", "admin"].map((s) => (
                          <option key={s}>{s}</option>
                        ))}
                      </select>
                    </label>
                  )}
                  {module === "account-links" &&
                    user?.role === "admin" &&
                    row.status === "verified" && (
                      <button
                        className="button secondary"
                        disabled={busy}
                        onClick={() =>
                          action("account-links/" + row.id + "/revoke")
                        }
                      >
                        {bn ? "লিংক প্রত্যাহার" : "Revoke link"}
                      </button>
                    )}
                  {module === "service-requests" && (
                    <Link className="button" to={"/requests/" + row.id}>
                      {bn ? "খুলুন ও পর্যালোচনা" : "Open & review"}
                    </Link>
                  )}
                  {module === "payments" && (
                    <>
                      <Link
                        className="button secondary"
                        to={"/payments/" + row.id}
                      >
                        {bn ? "পেমেন্ট ও রসিদ" : "Payment & receipt"}
                      </Link>
                      {user?.role === "admin" && (
                        <>
                          <button
                            disabled={busy}
                            className="button secondary"
                            onClick={() =>
                              action("payments/" + row.id + "/reconcile")
                            }
                          >
                            {bn ? "মিলিয়ে দেখুন" : "Reconcile"}
                          </button>
                          {row.sync_status === "failed" && (
                            <button
                              disabled={busy}
                              className="button"
                              onClick={() =>
                                action("payments/" + row.id + "/retry")
                              }
                            >
                              {bn
                                ? "নিরাপদ সিঙ্ক পুনরায়"
                                : "Retry sync safely"}
                            </button>
                          )}
                        </>
                      )}
                      <button
                        className="button secondary"
                        onClick={async () => {
                          try {
                            const p = await request(
                              "/api/v1/admin/payments/" + row.id + "/events",
                              page<Record<string, unknown>>,
                              {},
                              true,
                            );
                            setEvents(p.data);
                          } catch {
                            setActionError(true);
                          }
                        }}
                      >
                        {bn ? "সাম্প্রতিক প্রচেষ্টা" : "Recent attempts"}
                      </button>
                    </>
                  )}
                </div>
              </article>
            ))}
            <nav className="pagination">
              <button
                className="button secondary"
                disabled={pageNumber === 1}
                onClick={() => setPage((p) => p - 1)}
              >
                {bn ? "আগের" : "Previous"}
              </button>
              <span>
                {pageNumber} · {r.data.meta.pagination.total}
              </span>
              <button
                className="button secondary"
                disabled={!r.data.meta.pagination.has_next}
                onClick={() => setPage((p) => p + 1)}
              >
                {bn ? "পরের" : "Next"}
              </button>
            </nav>
          </>
        )}
      </Resource>
      {events && (
        <section className="panel content-card">
          <h2>{bn ? "সাম্প্রতিক ২০টি প্রচেষ্টা" : "Latest 20 attempts"}</h2>
          {events.map((e) => (
            <p key={String(e.id)}>
              {String(e.event)} · {String(e.metadata)}
            </p>
          ))}
        </section>
      )}
    </>
  );
}
