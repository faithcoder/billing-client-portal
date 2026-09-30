import { useState } from "react";
import type { FormEvent } from "react";
import { Link } from "react-router-dom";
import { useLanguage } from "../i18n";
import { mutate } from "../api/billing";
export default function Reset() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const [challenge, setChallenge] = useState("");
  const [done, setDone] = useState(false);
  const [error, setError] = useState(false);
  const [busy, setBusy] = useState(false);
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const d = Object.fromEntries(new FormData(e.currentTarget));
    setBusy(true);
    setError(false);
    try {
      if (challenge) {
        await mutate("/api/v1/auth/reset/finish", {
          ...d,
          challenge_id: challenge,
        });
        setDone(true);
      } else {
        const r = await mutate<{ challenge_id: string }>(
          "/api/v1/auth/reset/start",
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
      <h1>{bn ? "পাসওয়ার্ড পুনরুদ্ধার" : "Reset password"}</h1>
      {done ? (
        <div className="panel state">
          <p>{bn ? "পাসওয়ার্ড পরিবর্তিত হয়েছে।" : "Password updated."}</p>
          <Link to="/login" className="button">
            {bn ? "প্রবেশ করুন" : "Sign in"}
          </Link>
        </div>
      ) : (
        <form onSubmit={submit} className="panel login-form">
          {!challenge ? (
            <label>
              {bn ? "নিবন্ধিত ইমেইল" : "Registered email"}
              <input name="email" type="email" required autoComplete="email" />
            </label>
          ) : (
            <>
              <p>
                {bn
                  ? "হিসাবটি উপযুক্ত হলে নিবন্ধিত মাধ্যমে কোড পাঠানো হবে।"
                  : "If eligible, a code will be sent through the registered channel."}
              </p>
              <label>
                {bn ? "যাচাই কোড" : "Verification code"}
                <input
                  name="code"
                  inputMode="numeric"
                  pattern="[0-9]{6}"
                  autoComplete="one-time-code"
                  required
                />
              </label>
              <label>
                {bn ? "নতুন পাসওয়ার্ড" : "New password"}
                <input
                  name="password"
                  type="password"
                  minLength={12}
                  autoComplete="new-password"
                  required
                />
              </label>
              <label>
                {bn ? "পাসওয়ার্ড আবার লিখুন" : "Confirm password"}
                <input
                  name="password_confirmation"
                  type="password"
                  minLength={12}
                  autoComplete="new-password"
                  required
                />
              </label>
            </>
          )}
          {error && (
            <p role="alert">
              {bn
                ? "অনুরোধ ব্যর্থ। কোড ও তথ্য পরীক্ষা করুন।"
                : "Request failed. Check the code and details."}
            </p>
          )}
          <button className="button" disabled={busy}>
            {busy ? "…" : bn ? "চালিয়ে যান" : "Continue"}
          </button>
        </form>
      )}
    </>
  );
}
