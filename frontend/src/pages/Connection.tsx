import { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { useLanguage } from "../i18n";
import { mutate } from "../api/billing";
import { services, upload } from "../api/requests";
import type { ServiceRequest } from "../api/requests";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { StatusBadge } from "../components/StatusBadge";
export default function Connection() {
  const { language } = useLanguage();
  const bn = language === "bn";
  const [step, setStep] = useState(0);
  const [form, setForm] = useState<Record<string, string>>({
    connection_type: "residential",
  });
  const [files, setFiles] = useState<File[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [draft, setDraft] = useState("");
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const history = useResource(
    () => services.list("application", page),
    "applications:" + page,
  );
  const sections = bn
    ? [
        "আবেদনকারীর তথ্য",
        "যোগাযোগ ও ঠিকানা",
        "সংযোগের তথ্য",
        "সংযুক্তি ও পর্যালোচনা",
      ]
    : ["Applicant", "Contact & address", "Connection", "Documents & review"];
  async function save(submit = false) {
    setBusy(true);
    setError("");
    try {
      const s = await mutate<ServiceRequest>(
        draft
          ? "/api/v1/service-requests/" + draft
          : "/api/v1/service-requests",
        {
          kind: "application",
          subject: bn ? "নতুন পানির সংযোগ" : "New water connection",
          form_data: form,
        },
        draft ? "PATCH" : "POST",
      );
      setDraft(s.id);
      for (const file of files) await upload(s.id, file);
      setFiles([]);
      if (submit) await mutate("/api/v1/service-requests/" + s.id + "/submit");
      navigate("/requests/" + s.id);
    } catch {
      setError(
        bn
          ? "সংরক্ষণ বা জমা সম্পন্ন হয়নি। খসড়া থাকলে সেটি থেকে চালিয়ে যান; তথ্য ও ফাইল পরীক্ষা করুন।"
          : "Could not complete save/submit. Continue from the saved draft if present; check fields and files.",
      );
      history.reload();
    } finally {
      setBusy(false);
    }
  }
  const field = (key: string, label: string, type = "text") => (
    <label key={key}>
      {label}
      <input
        type={type}
        value={form[key] ?? ""}
        maxLength={key === "service_address" ? 1000 : 120}
        onChange={(e) => setForm({ ...form, [key]: e.target.value })}
      />
    </label>
  );
  return (
    <>
      <h1>{bn ? "নতুন সংযোগের আবেদন" : "New connection application"}</h1>
      <div className="notice">
        <p>
          {bn
            ? "প্রস্তাবিত ফর্ম: পৌরসভার আনুষ্ঠানিক প্রয়োজনীয়তা এখনও নিশ্চিত নয়।"
            : "Proposed municipal fields for review; these are not confirmed official requirements."}
        </p>
      </div>
      <ol className="form-steps">
        {sections.map((s, i) => (
          <li key={s} aria-current={step === i ? "step" : undefined}>
            {i + 1}. {s}
          </li>
        ))}
      </ol>
      <section className="panel content-card form-grid">
        {step === 0 && (
          <>
            {field("applicant_name", bn ? "আবেদনকারীর নাম" : "Applicant name")}
            {field(
              "guardian_name",
              bn ? "অভিভাবকের নাম (ঐচ্ছিক)" : "Guardian name (optional)",
            )}
          </>
        )}
        {step === 1 && (
          <>
            {field(
              "contact_phone",
              bn ? "যোগাযোগের ফোন" : "Contact phone",
              "tel",
            )}
            {field(
              "contact_email",
              bn ? "যোগাযোগের ইমেইল" : "Contact email",
              "email",
            )}
            {field("service_address", bn ? "সেবার ঠিকানা" : "Service address")}
            {field("ward", bn ? "ওয়ার্ড" : "Ward")}
            {field("holding_number", bn ? "হোল্ডিং নম্বর" : "Holding number")}
          </>
        )}
        {step === 2 && (
          <>
            <label>
              {bn ? "সংযোগের ধরন" : "Connection type"}
              <select
                value={form.connection_type}
                onChange={(e) =>
                  setForm({ ...form, connection_type: e.target.value })
                }
              >
                <option value="residential">
                  {bn ? "আবাসিক" : "Residential"}
                </option>
                <option value="commercial">
                  {bn ? "বাণিজ্যিক" : "Commercial"}
                </option>
                <option value="other">{bn ? "অন্যান্য" : "Other"}</option>
              </select>
            </label>
            {field(
              "pipe_size",
              bn ? "প্রস্তাবিত পাইপের মাপ" : "Requested pipe size",
            )}
            {field("notes", bn ? "অতিরিক্ত তথ্য" : "Additional notes")}
          </>
        )}
        {step === 3 && (
          <>
            <p>
              {bn
                ? "PDF, JPG বা PNG; প্রতিটি সর্বোচ্চ ৫ MB, সর্বোচ্চ ৫টি। সংযুক্তি ঐচ্ছিক; পৌরসভার তালিকা নিশ্চিত নয়।"
                : "PDF, JPG or PNG; up to 5 MB each, 5 files maximum. Documents are optional until municipal requirements are confirmed."}
            </p>
            <label>
              {bn ? "সহায়ক নথি" : "Supporting documents"}
              <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                multiple
                onChange={(e) => setFiles(Array.from(e.target.files ?? []))}
              />
            </label>
            <dl className="data-grid">
              {Object.entries(form).map(([k, v]) => (
                <div key={k}>
                  <dt>{k.replaceAll("_", " ")}</dt>
                  <dd>{v}</dd>
                </div>
              ))}
            </dl>
          </>
        )}
        {error && <p role="alert">{error}</p>}
        <div className="toolbar">
          <button
            className="button secondary"
            disabled={step === 0 || busy}
            onClick={() => setStep((s) => s - 1)}
          >
            {bn ? "পেছনে" : "Back"}
          </button>
          {step < 3 ? (
            <button className="button" onClick={() => setStep((s) => s + 1)}>
              {bn ? "পরবর্তী" : "Next"}
            </button>
          ) : (
            <button
              className="button"
              disabled={busy}
              onClick={() => save(true)}
            >
              {bn ? "জমা দিন" : "Submit"}
            </button>
          )}
          <button
            className="button secondary"
            disabled={busy}
            onClick={() => save(false)}
          >
            {bn ? "খসড়া সংরক্ষণ" : "Save draft"}
          </button>
        </div>
      </section>
      <h2 className="section-title">
        {bn ? "আপনার আবেদন" : "Your applications"}
      </h2>
      <Resource {...history}>
        {history.data?.data.map((s) => (
          <Link
            className="panel content-card record-link"
            key={s.id}
            to={"/requests/" + s.id}
          >
            <strong>{s.reference}</strong>
            <StatusBadge status={s.status} />
          </Link>
        ))}
      </Resource>
      <div className="toolbar">
        <button
          className="button secondary"
          disabled={page === 1}
          onClick={() => setPage(page - 1)}
        >
          {bn ? "আগের" : "Previous"}
        </button>
        <span>{page}</span>
        <button
          className="button secondary"
          disabled={!history.data?.meta.pagination.has_next}
          onClick={() => setPage(page + 1)}
        >
          {bn ? "পরের" : "Next"}
        </button>
      </div>
    </>
  );
}
