import { useState } from "react";
import type { FormEvent } from "react";
import { Link } from "react-router-dom";
import { billing, page as decodePage } from "../api/billing";
import type { BillSummary } from "../api/billing";
import { request } from "../api/client";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { StatusBadge } from "../components/StatusBadge";
import { useLanguage } from "../i18n";
import { formatMoney, formatDate } from "../lib/format";
export default function Bills({ admin = false }: { admin?: boolean }) {
  const { language } = useLanguage();
  const bn = language === "bn";
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const r = useResource(
    () =>
      admin
        ? request(
            `/api/v1/admin/bills?${query}&page=${page}&per_page=10`,
            decodePage<BillSummary>,
            {},
            true,
          )
        : billing.bills(`?${query}&page=${page}&per_page=10`),
    query + page,
  );
  function search(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const d = new FormData(e.currentTarget);
    const p = new URLSearchParams();
    if (d.get("identifier"))
      p.set(String(d.get("kind")), String(d.get("identifier")));
    for (const key of ["status", "month_from", "month_to"])
      if (d.get(key)) p.set(key, String(d.get(key)));
    setPage(1);
    setQuery(p.toString());
  }
  return (
    <>
      <header className="page-heading">
        <h1>{bn ? "বিলের ইতিহাস" : "Billing history"}</h1>
        <p>
          {admin
            ? bn
              ? "অনুমোদিত কর্মকর্তার অনুসন্ধান অডিট করা হয়।"
              : "Staff searches are audited."
            : bn
              ? "শুধু আপনার যাচাইকৃত হিসাবের বিল দেখা যাবে।"
              : "Search is restricted to your verified account."}
        </p>
      </header>
      <form className="panel filter-form" onSubmit={search}>
        <label>
          {bn ? "খোঁজার ধরন" : "Search by"}
          <select name="kind">
            <option value="external_bill_id">
              {bn ? "বিল নম্বর" : "Bill ID"}
            </option>
            <option value="external_account_id">
              {bn ? "হিসাব নম্বর" : "Account ID"}
            </option>
            <option value="external_customer_id">
              {bn ? "গ্রাহক নম্বর" : "Customer ID"}
            </option>
          </select>
        </label>
        <label>
          {bn ? "নম্বর" : "Identifier"}
          <input name="identifier" maxLength={128} />
        </label>
        <label>
          {bn ? "অবস্থা" : "Status"}
          <select name="status">
            <option value="">{bn ? "সব" : "All"}</option>
            {["unpaid", "paid", "partially_paid"].map((s) => (
              <option key={s} value={s}>
                {bn
                  ? {
                      unpaid: "অপরিশোধিত",
                      paid: "পরিশোধিত",
                      partially_paid: "আংশিক",
                    }[s]
                  : s}
              </option>
            ))}
          </select>
        </label>
        <label>
          {bn ? "মাস থেকে" : "From month"}
          <input name="month_from" type="month" />
        </label>
        <label>
          {bn ? "মাস পর্যন্ত" : "To month"}
          <input name="month_to" type="month" />
        </label>
        <button className="button">{bn ? "খুঁজুন" : "Search"}</button>
      </form>
      <Resource {...r}>
        {r.data && (
          <>
            {r.data.data.length === 0 ? (
              <div className="panel state">
                {bn ? "কোনো বিল পাওয়া যায়নি" : "No matching bills"}
              </div>
            ) : (
              <>
                <div className="bill-table panel">
                  <table>
                    <thead>
                      <tr>
                        {(bn
                          ? [
                              "বিল / হিসাব",
                              "ধরন / মাস",
                              "ইস্যু / শেষ তারিখ",
                              "টাকা",
                              "পরিশোধিত / তারিখ",
                              "অবস্থা",
                              "করণীয়",
                            ]
                          : [
                              "Bill / Account",
                              "Type / Month",
                              "Issue / Due",
                              "Amount",
                              "Paid / Date",
                              "Status",
                              "Action",
                            ]
                        ).map((h) => (
                          <th key={h}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {r.data.data.map((b) => (
                        <tr key={b.external_bill_id}>
                          <td>
                            {b.external_bill_id}
                            <small>{b.external_account_id}</small>
                          </td>
                          <td>
                            {bn ? b.type.label_bn : b.type.label_en}
                            <small>{b.bill_month}</small>
                          </td>
                          <td>
                            {formatDate(b.issue_date, language)}
                            <small>{formatDate(b.due_date, language)}</small>
                          </td>
                          <td>{formatMoney(b.amount_minor, language)}</td>
                          <td>
                            {formatMoney(b.paid_amount_minor, language)}
                            <small>{formatDate(b.paid_date, language)}</small>
                          </td>
                          <td>
                            <StatusBadge status={b.status} />
                          </td>
                          <td>
                            <Link
                              className="button secondary"
                              to={
                                (admin ? "/admin/bills/" : "/bills/") +
                                encodeURIComponent(b.external_bill_id)
                              }
                            >
                              {bn ? "দেখুন" : "View"}
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="bill-cards">
                  {r.data.data.map((b) => (
                    <article
                      className="panel content-card"
                      key={b.external_bill_id}
                    >
                      <div className="row-between">
                        <h2>{b.bill_month}</h2>
                        <StatusBadge status={b.status} />
                      </div>
                      <p>{b.external_bill_id}</p>
                      <strong className="amount">
                        {formatMoney(b.amount_minor, language)}
                      </strong>
                      <p>
                        {bn ? "শেষ তারিখ" : "Due"}:{" "}
                        {formatDate(b.due_date, language)}
                      </p>
                      <details>
                        <summary>{bn ? "বিস্তারিত" : "More details"}</summary>
                        <p>
                          {bn ? "হিসাব" : "Account"}: {b.external_account_id}
                        </p>
                        <p>
                          {bn ? "ইস্যু" : "Issued"}:{" "}
                          {formatDate(b.issue_date, language)}
                        </p>
                        <p>
                          {bn ? "পরিশোধিত" : "Paid"}:{" "}
                          {formatMoney(b.paid_amount_minor, language)} ·{" "}
                          {formatDate(b.paid_date, language)}
                        </p>
                        <p>{bn ? b.type.label_bn : b.type.label_en}</p>
                      </details>
                      <Link
                        className="button"
                        to={
                          (admin ? "/admin/bills/" : "/bills/") +
                          encodeURIComponent(b.external_bill_id)
                        }
                      >
                        {bn ? "বিল দেখুন" : "View bill"}
                      </Link>
                    </article>
                  ))}
                </div>
              </>
            )}
            <nav
              className="pagination"
              aria-label={bn ? "বিলের পৃষ্ঠা" : "Bill pages"}
            >
              <button
                className="button secondary"
                disabled={page === 1}
                onClick={() => setPage((p) => p - 1)}
              >
                {bn ? "আগের" : "Previous"}
              </button>
              <span>
                {bn ? "পৃষ্ঠা" : "Page"} {page}
                {r.data.meta.pagination.total !== null &&
                  ` · ${r.data.meta.pagination.total}`}
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
    </>
  );
}
