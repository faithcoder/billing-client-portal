import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import type { FormEvent } from "react";
import { useLanguage } from "../i18n";
import { useSession } from "../app/session";
import { mutate } from "../api/billing";
export default function Register() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const session = useSession();
  const navigate = useNavigate();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(false);
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const d = Object.fromEntries(new FormData(e.currentTarget));
    setBusy(true);
    setError(false);
    try {
      await mutate("/api/v1/auth/register", d);
      session.reload();
      navigate("/link-account");
    } catch {
      setError(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <h1>{bn ? "নতুন পোর্টাল হিসাব" : "Create a portal account"}</h1>
      <form className="panel login-form" onSubmit={submit}>
        {[
          ["name", bn ? "নাম" : "Name", "text"],
          ["email", bn ? "ইমেইল" : "Email", "email"],
          [
            "password",
            bn
              ? "পাসওয়ার্ড (কমপক্ষে ১২ অক্ষর ও সংখ্যা)"
              : "Password (12+ characters, letters and numbers)",
            "password",
          ],
          [
            "password_confirmation",
            bn ? "পাসওয়ার্ড আবার লিখুন" : "Confirm password",
            "password",
          ],
        ].map(([name, label, type]) => (
          <label key={name}>
            {label}
            <input
              name={name}
              type={type}
              required
              autoComplete={type === "password" ? "new-password" : name}
              minLength={type === "password" ? 12 : undefined}
              maxLength={name === "name" ? 120 : 254}
            />
          </label>
        ))}
        {error && (
          <p role="alert">
            {bn
              ? "নিবন্ধন করা যায়নি। তথ্য পরীক্ষা করুন।"
              : "Registration failed. Check your details."}
          </p>
        )}
        <button className="button" disabled={busy}>
          {busy ? "…" : bn ? "নিবন্ধন করুন" : "Register"}
        </button>
        <Link to="/login">{bn ? "প্রবেশ করুন" : "Sign in"}</Link>
      </form>
    </>
  );
}
