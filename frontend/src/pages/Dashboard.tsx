import { Link } from "react-router-dom";
import { billing } from "../api/billing";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { useLanguage } from "../i18n";
import { formatMoney, formatDate } from "../lib/format";
import { ApiError } from "../api/client";
import { payments } from "../api/payments";
import { StatusBadge } from "../components/StatusBadge";
export default function Dashboard() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const recent = useResource(
    () => payments.list("?status=succeeded&page=1"),
    "recent-payments",
  );
  const r = useResource(
    async () => ({
      account: await billing.account(),
      bills: await billing.bills("?per_page=1&page=1"),
    }),
    "dashboard",
  );
  return (
    <>
      <h1>{bn ? "আপনার পানির হিসাব" : "Your water account"}</h1>
      {r.error instanceof ApiError && r.error.status === 404 ? (
        <div className="panel state">
          <h2>{bn ? "স্বাগতম" : "Welcome"}</h2>
          <p>
            {bn
              ? "বিল দেখতে পানির হিসাবের মালিকানা যাচাই করুন।"
              : "Verify account ownership to access bills."}
          </p>
          <Link className="button" to="/link-account">
            {bn ? "হিসাব যুক্ত করুন" : "Link account"}
          </Link>
        </div>
      ) : (
        <Resource {...r}>
          {r.data && (
            <>
              <div className="service-grid dashboard-grid">
                <section className="panel content-card">
                  <h2>{r.data.account.name}</h2>
                  <p>
                    {bn ? "হিসাব" : "Account"}:{" "}
                    {r.data.account.external_account_id}
                  </p>
                  <p>
                    {bn ? "গ্রাহক" : "Customer"}:{" "}
                    {r.data.account.external_customer_id}
                  </p>
                </section>
                <section className="panel content-card">
                  <p>
                    {bn
                      ? "বিলিং সিস্টেমের বর্তমান বকেয়া"
                      : "Authoritative outstanding balance"}
                  </p>
                  <strong className="amount">
                    {formatMoney(
                      r.data.account.outstanding_balance_minor,
                      language,
                    )}
                  </strong>
                  <small>
                    {formatDate(r.data.account.source_updated_at, language)}
                  </small>
                </section>
                <section className="panel content-card">
                  <p>{bn ? "সর্বশেষ বিল" : "Latest bill"}</p>
                  {r.data.bills.data[0] ? (
                    <>
                      <h2>{r.data.bills.data[0].bill_month}</h2>
                      <p>
                        {formatDate(r.data.bills.data[0].due_date, language)}
                      </p>
                      <Link
                        className="button"
                        to={
                          "/bills/" +
                          encodeURIComponent(
                            r.data.bills.data[0].external_bill_id,
                          )
                        }
                      >
                        {bn ? "বিল দেখুন" : "View bill"}
                      </Link>
                    </>
                  ) : (
                    <p>—</p>
                  )}
                </section>
              </div>
            </>
          )}
        </Resource>
      )}
      <div className="service-grid dashboard-grid">
        {[
          ["/bills", bn ? "বিল পরিশোধ" : "Pay bill"],
          ["/payments", bn ? "রসিদ" : "Receipts"],
          ["/new-connection", bn ? "নতুন সংযোগ" : "New connection"],
          ["/requests", bn ? "অভিযোগ" : "Complaints"],
        ].map(([to, label]) => (
          <Link className="service-card" to={to} key={to}>
            {label} →
          </Link>
        ))}
      </div>
      <section className="panel content-card">
        <h2>
          {bn ? "সাম্প্রতিক যাচাইকৃত পেমেন্ট" : "Recent verified payments"}
        </h2>
        <Resource {...recent}>
          {recent.data?.data.slice(0, 3).map((p) => (
            <Link key={p.id} className="record-link" to={"/payments/" + p.id}>
              <strong>{formatMoney(p.amount_minor, language)}</strong>
              <span>{p.external_bill_id}</span>
              <StatusBadge status={p.sync_status} />
            </Link>
          ))}
        </Resource>
        <Link to="/payments" className="button secondary">
          {bn ? "পেমেন্ট ও রসিদ দেখুন" : "View payments and receipts"}
        </Link>
      </section>
    </>
  );
}
