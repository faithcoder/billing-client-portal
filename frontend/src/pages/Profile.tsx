import { Link } from "react-router-dom";
import { useState } from "react";
import { billing, mutate } from "../api/billing";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { useLanguage } from "../i18n";
import { ApiError } from "../api/client";
export default function Profile() {
  const { language, setLanguage } = useLanguage();
  const bn = language === "bn";
  const r = useResource(billing.account, "profile");
  const [saved, setSaved] = useState(false);
  const [failed, setFailed] = useState(false);
  return (
    <>
      <h1>{bn ? "প্রোফাইল" : "Profile"}</h1>
      {r.error instanceof ApiError && r.error.status === 404 ? (
        <div className="panel state">
          <p>
            {bn
              ? "প্রথমে আপনার পানির হিসাব যাচাই করুন।"
              : "Verify your water account first."}
          </p>
          <Link className="button" to="/link-account">
            {bn ? "হিসাব যুক্ত করুন" : "Link account"}
          </Link>
        </div>
      ) : (
        <Resource {...r}>
          {r.data && (
            <section className="panel content-card">
              <h2>{r.data.name}</h2>
              <dl className="data-grid">
                <div>
                  <dt>{bn ? "গ্রাহক নম্বর" : "Customer ID"}</dt>
                  <dd>{r.data.external_customer_id}</dd>
                </div>
                <div>
                  <dt>{bn ? "হিসাব নম্বর" : "Account ID"}</dt>
                  <dd>{r.data.external_account_id}</dd>
                </div>
                <div>
                  <dt>{bn ? "ঠিকানা" : "Address"}</dt>
                  <dd>{Object.values(r.data.address).join(", ")}</dd>
                </div>
              </dl>
              <p>
                {bn
                  ? "এই তথ্য বিলিং সিস্টেমের। পরিবর্তনের জন্য সংশোধনের অনুরোধ করুন।"
                  : "These details belong to the billing system. Request a correction to change them."}
              </p>
              <Link
                className="button secondary"
                to="/requests?category=profile_correction"
              >
                {bn ? "সংশোধনের অনুরোধ" : "Request correction"}
              </Link>
            </section>
          )}
        </Resource>
      )}
      <section className="panel content-card">
        <h2>{bn ? "পোর্টালের পছন্দ" : "Portal preferences"}</h2>
        <label>
          {bn ? "ভাষা" : "Language"}
          <select
            value={language}
            onChange={(e) => {
              setLanguage(e.target.value as "bn" | "en");
              setSaved(false);
            }}
          >
            <option value="bn">বাংলা</option>
            <option value="en">English</option>
          </select>
        </label>
        <button
          className="button"
          onClick={async () => {
            try {
              await mutate(
                "/api/v1/preferences",
                { language, notifications: false },
                "PATCH",
              );
              setSaved(true);
              setFailed(false);
            } catch {
              setFailed(true);
            }
          }}
        >
          {bn ? "সংরক্ষণ" : "Save"}
        </button>
        {saved && <p role="status">{bn ? "সংরক্ষিত" : "Saved"}</p>}
        {failed && (
          <p role="alert">{bn ? "সংরক্ষণ করা যায়নি" : "Could not save"}</p>
        )}
      </section>
    </>
  );
}
