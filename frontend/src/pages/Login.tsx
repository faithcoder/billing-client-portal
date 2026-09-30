import { useState } from "react";
import type { FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useLanguage } from "../i18n";
import { useSession } from "../app/session";
import { ApiError } from "../api/client";
import { Page } from "../components/Page";
export default function Login() {
  const { t, language } = useLanguage();
  const { login, user } = useSession();
  const navigate = useNavigate();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setBusy(true);
    setError(null);
    try {
      await login(String(data.get("email")), String(data.get("password")));
      form.reset();
      navigate("/", { replace: true });
    } catch (e) {
      setError(e instanceof ApiError ? (e.requestId ?? "") : "");
    } finally {
      setBusy(false);
    }
  }
  return (
    <Page title="loginRequired" description="signInText">
      {user ? (
        <div className="panel state">
          <h2>{t("signedIn")}</h2>
          <Link to="/more" className="button secondary">
            {t("more")}
          </Link>
          {user.role !== "client" && (
            <Link to="/admin" className="button">
              {t("admin")}
            </Link>
          )}
        </div>
      ) : (
        <form className="panel login-form" onSubmit={submit}>
          <label htmlFor="email">{t("email")}</label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="username"
            required
            maxLength={254}
          />
          <label htmlFor="password">{t("password")}</label>
          <input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
            maxLength={1024}
          />
          {error !== null && (
            <div role="alert">
              <p>{t("signInFailed")}</p>
              {error && (
                <small className="reference">
                  {t("details")}: {error}
                </small>
              )}
            </div>
          )}
          <button className="button" disabled={busy}>
            {t(busy ? "loading" : "login")}
          </button>
          <Link to="/register">
            {language === "bn" ? "নিবন্ধন করুন" : "Create account"}
          </Link>
          <Link to="/reset-password">
            {language === "bn" ? "পাসওয়ার্ড ভুলে গেছেন?" : "Forgot password?"}
          </Link>
        </form>
      )}
    </Page>
  );
}
