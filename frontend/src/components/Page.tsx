import type { ReactNode } from "react";
import { useLanguage } from "../i18n";
import type { TranslationKey } from "../i18n";
import { EmptyState } from "./States";
export function Page({
  title,
  description,
  children,
}: {
  title: TranslationKey;
  description: TranslationKey;
  children?: ReactNode;
}) {
  const { t } = useLanguage();
  return (
    <>
      <header className="page-heading">
        <span className="eyebrow">{t("municipality")}</span>
        <h1>{t(title)}</h1>
        <p>{t(description)}</p>
      </header>
      {children}
    </>
  );
}
export function Placeholder({
  title,
  description,
}: {
  title: TranslationKey;
  description: TranslationKey;
}) {
  return (
    <Page title={title} description={description}>
      <section className="panel">
        <EmptyState />
      </section>
    </Page>
  );
}
