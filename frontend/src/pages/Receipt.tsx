import { useParams } from "react-router-dom";
import { payments } from "../api/payments";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { useLanguage } from "../i18n";
import { formatMoney, formatDate } from "../lib/format";
import { StatusBadge } from "../components/StatusBadge";
export default function Receipt() {
  const { paymentId = "" } = useParams();
  const { language } = useLanguage();
  const bn = language === "bn";
  const r = useResource(() => payments.receipt(paymentId), paymentId);
  return (
    <>
      <div className="toolbar no-print">
        <h1>{bn ? "পোর্টাল পেমেন্ট রসিদ" : "Portal payment receipt"}</h1>
        <button className="button" onClick={() => window.print()}>
          {bn ? "প্রিন্ট / PDF" : "Print / Save as PDF"}
        </button>
      </div>
      <Resource {...r}>
        {r.data && (
          <article className="panel content-card receipt-document">
            <h2>{bn ? "পেমেন্ট নিশ্চিতকরণ" : "Payment confirmation"}</h2>
            <p>
              {bn
                ? "এটি পোর্টালের যাচাইকৃত সংগ্রহের রসিদ, অফিসিয়াল বিলিং-সিস্টেম রসিদ নয়।"
                : "This confirms a gateway-verified portal collection. It is not an official billing-system receipt."}
            </p>
            <div className="receipt-total">
              <strong className="amount">
                {formatMoney(r.data.receipt.amount_minor, language)}
              </strong>
              <StatusBadge status={r.data.sync_status} />
            </div>
            <dl className="data-grid">
              {[
                [
                  bn ? "রসিদ রেফারেন্স" : "Receipt reference",
                  r.data.receipt.receipt_reference,
                ],
                [bn ? "বিল নম্বর" : "Bill", r.data.receipt.external_bill_id],
                [bn ? "হিসাব" : "Account", r.data.receipt.external_account_id],
                [
                  bn ? "গ্রাহক" : "Customer",
                  r.data.receipt.external_customer_id,
                ],
                [
                  bn ? "যাচাইকৃত তারিখ" : "Verified date",
                  formatDate(r.data.receipt.verified_at, language),
                ],
                [
                  bn ? "গেটওয়ে রেফারেন্স" : "Gateway reference",
                  r.data.receipt.gateway_reference,
                ],
                [
                  bn ? "লেনদেন রেফারেন্স" : "Transaction reference",
                  r.data.receipt.external_transaction_reference,
                ],
                [
                  bn
                    ? "অফিসিয়াল রসিদ রেফারেন্স"
                    : "Upstream receipt reference",
                  r.data.upstream_receipt_reference ?? "—",
                ],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt>{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
            <p>
              {bn
                ? "রসিদের আর্থিক তথ্য অপরিবর্তনীয়। বিলিং সিঙ্কের বর্তমান অবস্থা আলাদাভাবে দেখানো হয়েছে।"
                : "Financial receipt evidence is immutable. Current billing synchronization status is shown separately."}
            </p>
            {r.data.sync_status !== "synced" && (
              <p>
                {bn
                  ? "বিলিং হালনাগাদ অপেক্ষমাণ। আবার অর্থ প্রদান করবেন না।"
                  : "Billing update is pending. Do not pay again."}
              </p>
            )}
          </article>
        )}
      </Resource>
    </>
  );
}
