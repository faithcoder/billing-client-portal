import { useEffect, useState } from "react";
import { useLanguage } from "../i18n";
import { Icon } from "./Icon";
export function RetryButton({ onRetry }: { onRetry: () => void }) {
  const { t } = useLanguage();
  return (
    <button className="button secondary" onClick={onRetry}>
      {t("retry")}
    </button>
  );
}
export function LoadingState() {
  const { t } = useLanguage();
  return (
    <div className="state" role="status">
      <span className="spinner" aria-hidden="true" />
      {t("loading")}
    </div>
  );
}
export function EmptyState() {
  const { t } = useLanguage();
  return (
    <div className="state">
      <span className="icon-tile">
        <Icon name="bills" />
      </span>
      <h2>{t("empty")}</h2>
      <p>{t("emptyText")}</p>
    </div>
  );
}
export function ErrorState({
  onRetry,
  requestId,
}: {
  onRetry: () => void;
  requestId?: string;
}) {
  const { t } = useLanguage();
  return (
    <div className="state" role="alert">
      <h2>{t("error")}</h2>
      {requestId && (
        <p className="reference">
          {t("details")}: {requestId}
        </p>
      )}
      <RetryButton onRetry={onRetry} />
    </div>
  );
}
export function useOnline() {
  const [online, setOnline] = useState(navigator.onLine);
  useEffect(() => {
    const update = () => setOnline(navigator.onLine);
    window.addEventListener("online", update);
    window.addEventListener("offline", update);
    return () => {
      window.removeEventListener("online", update);
      window.removeEventListener("offline", update);
    };
  }, []);
  return online;
}
export function OfflineState() {
  const { t } = useLanguage();
  return (
    <div className="state offline-state" role="status">
      <span className="icon-tile">
        <Icon name="shield" />
      </span>
      <h2>{t("offline")}</h2>
      <p>{t("offlineText")}</p>
      <button
        className="button secondary"
        onClick={() => window.location.reload()}
      >
        {t("reconnect")}
      </button>
    </div>
  );
}
