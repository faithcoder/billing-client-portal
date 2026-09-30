import { useState } from "react";
import type { FormEvent } from "react";
import { useNavigate, Link, useSearchParams } from "react-router-dom";
import { useLanguage } from "../i18n";
import { mutate } from "../api/billing";
import { services, upload } from "../api/requests";
import type { ServiceRequest } from "../api/requests";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { StatusBadge } from "../components/StatusBadge";
export default function Complaints() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const [params] = useSearchParams();
  const [error, setError] = useState(false);
  const [busy, setBusy] = useState(false);
  const [page, setPage] = useState(1);
  const navigate = useNavigate();
  const r = useResource(
    () => services.list("complaint", page),
    "complaints" + page,
  );
  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const d = new FormData(e.currentTarget);
    setBusy(true);
    setError(false);
    try {
      const s = await mutate<ServiceRequest>("/api/v1/service-requests", {
        kind: "complaint",
        category: d.get("category"),
        subject: d.get("subject"),
        description: d.get("description"),
        external_account_id: d.get("external_account_id") || null,
        external_bill_id: d.get("external_bill_id") || null,
      });
      const file = d.get("file");
      if (file instanceof File && file.size) await upload(s.id, file);
      await mutate("/api/v1/service-requests/" + s.id + "/submit");
      navigate("/requests/" + s.id);
    } catch {
      setError(true);
      r.reload();
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <h1>{bn ? "অনুরোধ ও অভিযোগ" : "Requests and complaints"}</h1>
      <form className="panel content-card form-grid" onSubmit={submit}>
        <label>
          {bn ? "বিভাগ" : "Category"}
          <select
            name="category"
            defaultValue={params.get("category") ?? "water_supply"}
          >
            {[
              "billing",
              "meter",
              "water_supply",
              "profile_correction",
              "other",
            ].map((s, i) => (
              <option key={s} value={s}>
                {bn
                  ? [
                      "বিল",
                      "মিটার",
                      "পানি সরবরাহ",
                      "প্রোফাইল সংশোধন",
                      "অন্যান্য",
                    ][i]
                  : s.replaceAll("_", " ")}
              </option>
            ))}
          </select>
        </label>
        <label>
          {bn ? "বিষয়" : "Subject"}
          <input name="subject" required maxLength={180} />
        </label>
        <label>
          {bn ? "বিবরণ" : "Description"}
          <textarea
            name="description"
            required
            minLength={10}
            maxLength={5000}
          />
        </label>
        <label>
          {bn
            ? "হিসাব নম্বর (ঐচ্ছিক, যাচাইকৃত হতে হবে)"
            : "Account ID (optional; must be verified)"}
          <input name="external_account_id" maxLength={128} />
        </label>
        <label>
          {bn ? "বিল নম্বর (ঐচ্ছিক)" : "Bill ID (optional)"}
          <input name="external_bill_id" maxLength={128} />
        </label>
        <label>
          {bn
            ? "সংযুক্তি (PDF/JPG/PNG, সর্বোচ্চ ৫ MB)"
            : "Attachment (PDF/JPG/PNG, max 5 MB)"}
          <input name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" />
        </label>
        {error && (
          <p role="alert">
            {bn
              ? "জমা সম্পন্ন হয়নি। নিচে সংরক্ষিত খসড়া পরীক্ষা করুন।"
              : "Submission did not complete. Check the saved draft below."}
          </p>
        )}
        <button className="button" disabled={busy}>
          {busy ? "…" : bn ? "জমা দিন" : "Submit"}
        </button>
      </form>
      <h2 className="section-title">{bn ? "আপনার অনুরোধ" : "Your requests"}</h2>
      <Resource {...r}>
        {r.data && (
          <>
            {r.data.data.map((s) => (
              <Link
                className="panel content-card record-link"
                key={s.id}
                to={"/requests/" + s.id}
              >
                <strong>{s.subject}</strong>
                <StatusBadge status={s.status} />
                <small>{s.reference}</small>
              </Link>
            ))}
            <nav className="pagination">
              <button
                disabled={page === 1}
                className="button secondary"
                onClick={() => setPage((p) => p - 1)}
              >
                {bn ? "আগের" : "Previous"}
              </button>
              <span>{page}</span>
              <button
                disabled={!r.data.meta.pagination.has_next}
                className="button secondary"
                onClick={() => setPage((p) => p + 1)}
              >
                {bn ? "পরের" : "Next"}
              </button>
            </nav>
          </>
        )}
      </Resource>
    </>
  );
}
