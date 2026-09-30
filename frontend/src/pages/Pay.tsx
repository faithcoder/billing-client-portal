import { useState } from "react";
import { useParams, useNavigate, Link } from "react-router-dom";
import { billing, mutate } from "../api/billing";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { useLanguage } from "../i18n";
import { formatMoney } from "../lib/format";
import type { Payment } from "../api/payments";
export default function Pay() {
  const { billId = "" } = useParams();
  const navigate = useNavigate();
  const { language } = useLanguage();
  const bn = language === "bn";
  const r = useResource(() => billing.bill(billId), billId);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(false);
  async function start() {
    setBusy(true);
    setError(false);
    try {
      const p = await mutate<Payment>(
        "/api/v1/bills/" + encodeURIComponent(billId) + "/payments",
      );
      navigate(
        p.checkout_url && p.status === "pending"
          ? p.checkout_url
          : "/payments/" + p.id,
      );
    } catch {
      setError(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <h1>{bn ? "বিল পরিশোধ" : "Pay bill"}</h1>
      <Resource {...r}>
        {r.data && (
          <div className="panel content-card">
            <span className="badge">
              {bn
                ? "ডেমো পেমেন্ট — বাস্তব টাকা নয়"
                : "Demo payment — no real money"}
            </span>
            <h2>{r.data.bill_number}</h2>
            <strong className="amount">
              {formatMoney(
                r.data.authoritative_totals.outstanding_balance_minor,
                language,
              )}
            </strong>
            <p>
              {bn
                ? "এটি প্রদর্শিত বকেয়া। গেটওয়ে সেশন তৈরির আগে সার্ভার বর্তমান মূল্য ও যোগ্যতা যাচাই করবে।"
                : "This is the displayed balance. The server checks current pricing and eligibility before creating a gateway session."}
            </p>
            {error && (
              <p role="alert">
                {bn
                  ? "পেমেন্ট শুরু করা যায়নি। বিলের বর্তমান অবস্থা পরীক্ষা করুন।"
                  : "Payment could not start. Check the latest bill status."}
              </p>
            )}
            <div className="toolbar">
              <button
                className="button"
                onClick={start}
                disabled={
                  busy ||
                  r.data.authoritative_totals.outstanding_balance_minor === "0"
                }
              >
                {busy ? "…" : bn ? "নিরাপদ চেকআউট" : "Continue to checkout"}
              </button>
              <Link
                to={"/bills/" + encodeURIComponent(billId)}
                className="button secondary"
              >
                {bn ? "বিলে ফিরুন" : "Back to bill"}
              </Link>
            </div>
          </div>
        )}
      </Resource>
    </>
  );
}
