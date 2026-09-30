import { useState } from "react";
import { Link } from "react-router-dom";
import { payments } from "../api/payments";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { StatusBadge } from "../components/StatusBadge";
import { PaymentState } from "../components/PaymentState";
import { useLanguage } from "../i18n";
import { formatMoney, formatDate } from "../lib/format";
export default function Payments() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("");
  const r = useResource(
    () => payments.list(`?page=${page}&status=${status}`),
    String(page) + status,
  );
  return (
    <>
      <h1>{bn ? "পেমেন্ট ও রসিদ" : "Payments and receipts"}</h1>
      <p>
        {bn
          ? "শুধু এই পোর্টালের পেমেন্ট। ব্যাংক ও অফলাইন লেনদেন অন্তর্ভুক্ত নয়।"
          : "Portal payments only. Bank and offline collections are not included."}
      </p>
      <div className="toolbar">
        <label>
          {bn ? "অবস্থা" : "Status"}
          <select
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">{bn ? "সব" : "All"}</option>
            {["pending", "succeeded", "failed", "cancelled"].map((s) => (
              <option key={s}>{s}</option>
            ))}
          </select>
        </label>
        <a className="button secondary" href="/api/v1/exports/payments">
          {bn ? "নিজের CSV ডাউনলোড" : "Export my payments (CSV)"}
        </a>
      </div>
      <Resource {...r}>
        {r.data && (
          <>
            {r.data.data.length === 0 && (
              <div className="panel state">
                {bn ? "এখনও কোনো পেমেন্ট নেই" : "No payments yet"}
              </div>
            )}
            {r.data.data.map((p) => (
              <article className="panel content-card" key={p.id}>
                <div className="row-between">
                  <strong>{formatMoney(p.amount_minor, language)}</strong>
                  <StatusBadge status={p.status} />
                </div>
                <p className="reference">{p.external_bill_id}</p>
                <p>{formatDate(p.created_at, language)}</p>
                <PaymentState payment={p} />
                <div className="toolbar">
                  <Link className="button secondary" to={"/payments/" + p.id}>
                    {bn ? "বিস্তারিত" : "Details"}
                  </Link>
                  {p.status === "succeeded" && (
                    <Link
                      className="button"
                      to={"/payments/" + p.id + "/receipt"}
                    >
                      {bn ? "রসিদ" : "Receipt"}
                    </Link>
                  )}
                </div>
              </article>
            ))}
            <nav className="pagination">
              <button
                className="button secondary"
                disabled={page === 1}
                onClick={() => setPage((p) => p - 1)}
              >
                {bn ? "আগের" : "Previous"}
              </button>
              <span>{page}</span>
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
    </>
  );
}
