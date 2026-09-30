import { useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { payments } from "../api/payments";
import { mutate } from "../api/billing";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { useLanguage } from "../i18n";
import { formatMoney } from "../lib/format";
export default function FakeCheckout() {
  const { paymentId = "" } = useParams();
  const { language } = useLanguage();
  const bn = language === "bn";
  const navigate = useNavigate();
  const r = useResource(() => payments.get(paymentId), paymentId);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(false);
  async function complete(outcome: string) {
    setBusy(true);
    try {
      await mutate("/api/v1/dev/gateway/" + paymentId + "/complete", {
        outcome,
      });
      navigate("/payments/" + paymentId);
    } catch {
      setError(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <h1>{bn ? "পরীক্ষামূলক গেটওয়ে" : "Development gateway"}</h1>
      <Resource {...r}>
        {r.data && (
          <section className="panel state">
            <span className="badge">
              {bn ? "শুধু স্থানীয় পরীক্ষা" : "Local simulation only"}
            </span>
            <strong className="amount">
              {formatMoney(r.data.amount_minor, language)}
            </strong>
            <p>
              {bn
                ? "বাস্তব ব্যাংক বা কার্ডের তথ্য দেবেন না।"
                : "Do not enter real bank or card details."}
            </p>
            <div className="toolbar">
              {(["succeeded", "failed", "cancelled"] as const).map((s, i) => (
                <button
                  key={s}
                  className={i === 0 ? "button" : "button secondary"}
                  disabled={busy}
                  onClick={() => complete(s)}
                >
                  {bn
                    ? ["সফল পরীক্ষা", "ব্যর্থ পরীক্ষা", "বাতিল পরীক্ষা"][i]
                    : [
                        "Simulate success",
                        "Simulate failure",
                        "Simulate cancel",
                      ][i]}
                </button>
              ))}
            </div>
            {error && (
              <p role="alert">
                {bn
                  ? "পরীক্ষা সম্পন্ন করা যায়নি"
                  : "Simulation could not complete"}
              </p>
            )}
          </section>
        )}
      </Resource>
    </>
  );
}
