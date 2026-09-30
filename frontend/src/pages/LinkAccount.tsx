import { useState } from "react";
import { useNavigate } from "react-router-dom";
import type { FormEvent } from "react";
import { useLanguage } from "../i18n";
import { mutate } from "../api/billing";
export default function LinkAccount() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const [challenge, setChallenge] = useState("");
  const [error, setError] = useState(false);
  const [busy, setBusy] = useState(false);
  const navigate = useNavigate();
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError(false);
    const d = Object.fromEntries(new FormData(e.currentTarget));
    try {
      if (challenge) {
        await mutate("/api/v1/account-links/verify", {
          ...d,
          challenge_id: challenge,
        });
        navigate("/profile");
      } else {
        const r = await mutate<{ challenge_id: string }>(
          "/api/v1/account-links/challenges",
          d,
        );
        setChallenge(r.challenge_id);
      }
    } catch {
      setError(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <header className="page-heading">
        <h1>{bn ? "পানির হিসাব যুক্ত করুন" : "Link your water account"}</h1>
        <p>
          {bn
            ? "কোডটি বিলিং সিস্টেমে থাকা ফোনে পাঠানো হবে। এখানে নতুন ফোন নম্বর দিয়ে মালিকানা প্রমাণ করা যাবে না। আপাতত একটি হিসাব যুক্ত করা যাবে।"
            : "A code is sent to the phone already held by the billing system. Entering a new phone cannot establish ownership. One account is supported initially."}
        </p>
      </header>
      <form className="panel login-form" onSubmit={submit}>
        {challenge ? (
          <>
            <p>
              {bn
                ? "হিসাবটি উপযুক্ত হলে নিবন্ধিত ফোনে কোড পাঠানো হবে। কোড ৫ মিনিট কার্যকর।"
                : "If eligible, a code will arrive on the registered phone. It expires in 5 minutes."}
            </p>
            <label>
              {bn ? "যাচাই কোড" : "Verification code"}
              <input
                name="code"
                inputMode="numeric"
                pattern="[0-9]{6}"
                maxLength={6}
                required
                autoComplete="one-time-code"
              />
            </label>
          </>
        ) : (
          <label>
            {bn ? "বাহ্যিক হিসাব নম্বর" : "External account ID"}
            <input
              name="external_account_id"
              required
              maxLength={128}
              autoComplete="off"
            />
          </label>
        )}
        {error && (
          <p role="alert">
            {bn
              ? "যাচাই করা যায়নি। তথ্য পরীক্ষা করুন বা নতুন কোড নিন।"
              : "Verification failed. Check details or request a new code."}
          </p>
        )}
        <button className="button" disabled={busy}>
          {busy ? "…" : bn ? "চালিয়ে যান" : "Continue"}
        </button>
        {challenge && (
          <button
            type="button"
            className="button secondary"
            onClick={() => setChallenge("")}
          >
            {bn ? "নতুন কোড" : "New code"}
          </button>
        )}
      </form>
    </>
  );
}
