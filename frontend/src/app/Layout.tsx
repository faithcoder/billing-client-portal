import { useEffect } from "react";
import { Link, NavLink, Outlet, useLocation } from "react-router-dom";
import { useLanguage } from "../i18n";
import type { TranslationKey } from "../i18n";
import { Icon } from "../components/Icon";
import type { IconName } from "../components/Icon";
import {
  LoadingState,
  ErrorState,
  OfflineState,
  useOnline,
} from "../components/States";
import { useSession } from "./session";
type Item = { to: string; label: TranslationKey; icon: IconName };
const clientNav: Item[] = [
  { to: "/", label: "home", icon: "home" },
  { to: "/bills", label: "bills", icon: "bills" },
  { to: "/payments", label: "payments", icon: "payments" },
  { to: "/more", label: "more", icon: "more" },
];
const adminNav: Item[] = [
  { to: "/admin/account-links", label: "accountLinks", icon: "connection" },
  { to: "/admin/bills", label: "bills", icon: "bills" },
  { to: "/admin/integration-health", label: "status", icon: "shield" },
  { to: "/admin", label: "dashboard", icon: "home" },
  { to: "/admin/users", label: "users", icon: "profile" },
  { to: "/admin/payments", label: "payments", icon: "payments" },
  { to: "/admin/sync-failures", label: "sync", icon: "connection" },
  { to: "/admin/applications", label: "applications", icon: "bills" },
  { to: "/admin/complaints", label: "complaints", icon: "complaints" },
  { to: "/admin/audit-logs", label: "audit", icon: "shield" },
];
export function Layout({ admin = false }: { admin?: boolean }) {
  const { t, language, setLanguage } = useLanguage();
  const { user } = useSession();
  const location = useLocation();
  const online = useOnline();
  const items = admin ? adminNav : clientNav;
  useEffect(() => {
    window.scrollTo(0, 0);
    document.getElementById("main")?.focus();
  }, [location.pathname]);
  const nav = (item: Item) => (
    <NavLink
      key={item.to}
      to={item.to}
      end={item.to === "/" || item.to === "/admin"}
      className={({ isActive }) =>
        isActive ||
        (item.to === "/more" &&
          ["/profile", "/new-connection", "/requests"].includes(
            location.pathname,
          ))
          ? "nav-item active"
          : "nav-item"
      }
    >
      <Icon name={item.icon} />
      <span>{t(item.label)}</span>
    </NavLink>
  );
  return (
    <div className={admin ? "app-shell admin-shell" : "app-shell"}>
      <a className="skip-link" href="#main">
        {t("skip")}
      </a>
      <aside className="sidebar">
        <Link to={admin ? "/admin" : "/"} className="brand">
          <span className="brand-mark">
            <Icon name="water" />
          </span>
          <span>
            <strong>{t("brand")}</strong>
            <small>{t(admin ? "admin" : "municipality")}</small>
          </span>
        </Link>
        <span className="nav-caption">
          {t(admin ? "adminIntro" : "client")}
        </span>
        <nav aria-label={t("nav")}>{items.map(nav)}</nav>
        <div className="sidebar-bottom">
          <Icon name="shield" />
          <p>{t("privacy")}</p>
          <span className="badge">{t("phase")}</span>
          <Link to={admin ? "/" : "/admin"}>
            {t(admin ? "client" : "admin")} ↗
          </Link>
        </div>
      </aside>
      <div className="workspace">
        <header className="topbar">
          <Link className="mobile-brand" to="/">
            <span className="brand-mark">
              <Icon name="water" />
            </span>
            <strong>{t("brand")}</strong>
          </Link>
          <span className="desktop-title">{t(admin ? "admin" : "client")}</span>
          <div className="header-actions">
            <button
              className="language-button"
              aria-label={t("language")}
              onClick={() => setLanguage(language === "bn" ? "en" : "bn")}
            >
              {language === "bn" ? "English" : "বাংলা"}
            </button>
            <Link
              className="account-button"
              aria-label={t(user ? "profile" : "login")}
              to={user ? "/profile" : "/login"}
            >
              <Icon name="profile" />
              <span>{t(user ? "profile" : "login")}</span>
            </Link>
          </div>
        </header>
        {admin && (
          <nav className="admin-mobile-nav" aria-label={t("nav")}>
            {items.map(nav)}
          </nav>
        )}
        <main id="main" tabIndex={-1}>
          {online ? <Outlet /> : <OfflineState />}
        </main>
        <footer>
          <span>{t("footer")}</span>
          <span>
            <Icon name="shield" />
            {t("privacy")}
          </span>
        </footer>
      </div>
      {!admin && (
        <nav className="bottom-nav" aria-label={t("nav")}>
          {clientNav.map(nav)}
        </nav>
      )}
    </div>
  );
}
export function AdminGuard() {
  const { user, loading, error, reload } = useSession();
  const { t } = useLanguage();
  if (loading) return <LoadingState />;
  if (error) return <ErrorState onRetry={reload} />;
  if (!user || user.role === "client")
    return (
      <div className="state panel">
        <Icon name="shield" />
        <h1>{t("forbidden")}</h1>
        <p>{t("forbiddenText")}</p>
        <Link className="button" to="/login">
          {t("login")}
        </Link>
      </div>
    );
  return <Outlet />;
}

export function ClientGuard() {
  const { user, loading, error, reload } = useSession();
  const { t } = useLanguage();
  if (loading) return <LoadingState />;
  if (error) return <ErrorState onRetry={reload} />;
  if (!user)
    return (
      <div className="panel state">
        <h1>{t("loginRequired")}</h1>
        <Link className="button" to="/login">
          {t("login")}
        </Link>
      </div>
    );
  return <Outlet />;
}
