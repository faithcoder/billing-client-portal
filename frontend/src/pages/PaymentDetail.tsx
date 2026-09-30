import { useEffect, useState, useRef } from "react";
import { Link, useParams } from "react-router-dom";
import { payments } from "../api/payments";
import type { Payment } from "../api/payments";
import { useLanguage } from "../i18n";
import { formatMoney } from "../lib/format";
import { LoadingState, ErrorState } from "../components/States";
import { StatusBadge } from "../components/StatusBadge";
import { PaymentState } from "../components/PaymentState";
export default function PaymentDetail() {
  const { paymentId = "" } = useParams();
  const { language } = useLanguage();
  const bn = language === "bn";
  const [payment, setPayment] = useState<Payment | null>(null);
  const [error, setError] = useState(false);
  const [count, setCount] = useState(0);
  const refresh = useRef(() => {});
  useEffect(() => {
    let alive = true;
    let timer: ReturnType<typeof setTimeout>;
    let attempts = 0;
    let inFlight = false;
    async function poll() {
      if (!alive || document.hidden || inFlight || attempts >= 20) return;
      clearTimeout(timer);
      inFlight = true;
      attempts++;
      try {
        const p = await payments.get(paymentId);
        if (!alive) return;
        setPayment(p);
        setError(false);
        setCount(attempts);
        if (
          !["failed", "cancelled", "expired"].includes(p.status) &&
          p.sync_status !== "synced" &&
          p.sync_status !== "needs_review"
        )
          timer = setTimeout(poll, 5000);
      } catch {
        if (alive) setError(true);
      } finally {
        inFlight = false;
      }
    }
    function visible() {
      if (!document.hidden) void poll();
      else clearTimeout(timer);
    }
    refresh.current = () => {
      clearTimeout(timer);
      void poll();
    };
    void poll();
    document.addEventListener("visibilitychange", visible);
    return () => {
      alive = false;
      clearTimeout(timer);
      document.removeEventListener("visibilitychange", visible);
    };
  }, [paymentId]);
  if (error) return <ErrorState onRetry={() => refresh.current()} />;
  if (!payment) return <LoadingState />;
  return (
    <>
      <h1>{bn ? "পেমেন্টের অবস্থা" : "Payment status"}</h1>
      <PaymentState payment={payment} />
      <section className="panel content-card">
        <strong className="amount">
          {formatMoney(payment.amount_minor, language)}
        </strong>
        <p className="reference">{payment.reference}</p>
        <div className="toolbar">
          <StatusBadge status={payment.status} />
          <StatusBadge status={payment.sync_status} />
        </div>
        {payment.review_reason && (
          <p>
            {bn ? "পর্যালোচনার কারণ" : "Review reason"}: {payment.review_reason}
          </p>
        )}
        <div className="toolbar">
          {payment.status === "succeeded" && (
            <Link className="button" to={"/payments/" + paymentId + "/receipt"}>
              {bn ? "রসিদ দেখুন" : "View receipt"}
            </Link>
          )}
          <button
            className="button secondary"
            disabled={count >= 20}
            onClick={() => refresh.current()}
          >
            {bn ? "অবস্থা দেখুন" : "Refresh status"}
          </button>
          <Link to="/payments">{bn ? "সব পেমেন্ট" : "All payments"}</Link>
        </div>
        {count >= 20 && (
          <p>
            {bn
              ? "স্বয়ংক্রিয় পরীক্ষা বন্ধ হয়েছে। পরে আবার এই পাতায় আসুন।"
              : "Automatic checks have stopped. Return to this page later."}
          </p>
        )}
      </section>
    </>
  );
}
