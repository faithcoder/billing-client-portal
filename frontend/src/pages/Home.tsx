import { useSession } from "../app/session";
import Dashboard from "./Dashboard";
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { useLanguage } from "../i18n";
import { Icon } from "../components/Icon";
function PublicHome() {
  const { t } = useLanguage();
  const [ready, setReady] = useState<boolean | null>(null);
  useEffect(() => {
    const controller = new AbortController();
    api
      .status(controller.signal)
      .then(() => setReady(true))
      .catch((error) => {
        if (error.name !== "AbortError") setReady(false);
      });
    return () => controller.abort();
  }, []);
  return (
    <>
      <div className="page-meta">
        <span className="eyebrow">{t("municipality")}</span>
        <span className="badge">{t("demo")}</span>
      </div>
      <section className="hero">
        <div className="hero-content">
          <div className="hero-kicker">
            <span />
            {t("help")}
          </div>
          <h1>{t("welcome")}</h1>
          <p>{t("intro")}</p>
          <div className="hero-actions">
            <Link className="button" to="/bills">
              {t("viewBills")}
              <Icon name="arrow" />
            </Link>
            <Link className="button secondary" to="/new-connection">
              {t("requestConnection")}
            </Link>
          </div>
        </div>
        <div className="water-art" aria-hidden="true">
          <div className="orbit orbit-one" />
          <div className="orbit orbit-two" />
          <div className="drop">
            <Icon name="water" />
          </div>
          <span className="art-chip">
            <Icon name="shield" />
            {t("brand")}
          </span>
          <div className="wave wave-one" />
          <div className="wave wave-two" />
        </div>
      </section>
      <section className="notice">
        <span className="notice-icon">
          <Icon name="connection" />
        </span>
        <div>
          <strong>{t("notice")}</strong>
          <p>{t("noticeText")}</p>
        </div>
      </section>
      <section className="services-section">
        <div className="section-heading">
          <div>
            <h2>{t("services")}</h2>
            <p>{t("servicesIntro")}</p>
          </div>
        </div>
        <div className="service-grid">
          {(["bills", "payments", "complaints"] as const).map((item, i) => (
            <Link
              key={item}
              to={item === "complaints" ? "/requests" : `/${item}`}
              className="service-card"
            >
              <div className="card-top">
                <span className={`icon-tile tile-${i}`}>
                  <Icon name={item} />
                </span>
                <Icon name="arrow" />
              </div>
              <h3>{t(item)}</h3>
              <p>{t(`${item}Text`)}</p>
            </Link>
          ))}
        </div>
      </section>
      <section className="getting-started">
        <h2>{t("start")}</h2>
        <div className="steps">
          {(["One", "Two", "Three"] as const).map((n, i) => (
            <div className="step" key={n}>
              <span className="step-number">0{i + 1}</span>
              <div>
                <h3>{t(`step${n}`)}</h3>
                <p>{t(`step${n}Text`)}</p>
              </div>
            </div>
          ))}
        </div>
      </section>
      <div className="connection-status" role="status">
        <span className={ready ? "status-dot" : "status-dot muted"} />
        {t("status")}:{" "}
        {t(ready === null ? "loading" : ready ? "ready" : "unavailable")}
        {ready === false && (
          <button onClick={() => window.location.reload()}>{t("retry")}</button>
        )}
      </div>
    </>
  );
}

export default function Home() {
  const { user } = useSession();
  return user ? <Dashboard /> : <PublicHome />;
}
