import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useLanguage } from "../i18n";
import { useSession } from "../app/session";
import { Page } from "../components/Page";
import { Icon } from "../components/Icon";
export default function More() {
  const { t } = useLanguage();
  const { user, logout } = useSession();
  const navigate = useNavigate();
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  async function signOut() {
    setBusy(true);
    setFailed(false);
    try {
      await logout();
      navigate("/login", { replace: true });
    } catch {
      setFailed(true);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Page title="more" description="helpText">
      <div className="menu-list panel">
        {(
          [
            { to: "/profile", label: "profile", icon: "profile" },
            { to: "/new-connection", label: "connection", icon: "connection" },
            { to: "/requests", label: "complaints", icon: "complaints" },
          ] as const
        ).map((item) => (
          <Link key={item.to} to={item.to}>
            <span className="icon-tile">
              <Icon name={item.icon} />
            </span>
            <span>{t(item.label)}</span>
            <Icon name="arrow" />
          </Link>
        ))}
        <button disabled={!user || busy} onClick={signOut}>
          <span className="icon-tile">
            <Icon name="logout" />
          </span>
          <span>{t(busy ? "logoutBusy" : "logout")}</span>
        </button>
      </div>
      {!user && (
        <p className="muted-text">
          {t("signedOut")} · <Link to="/login">{t("login")}</Link>
        </p>
      )}
      {failed && <p role="alert">{t("logoutFailed")}</p>}
    </Page>
  );
}
