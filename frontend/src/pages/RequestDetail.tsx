import { useState } from "react";
import type { FormEvent } from "react";
import { useParams } from "react-router-dom";
import { services, upload } from "../api/requests";
import { mutate } from "../api/billing";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { StatusBadge } from "../components/StatusBadge";
import { useLanguage } from "../i18n";
import { useSession } from "../app/session";
import { formatDate } from "../lib/format";
export default function RequestDetail() {
  const { requestId = "" } = useParams();
  const { language } = useLanguage();
  const { user } = useSession();
  const bn = language === "bn";
  const r = useResource(() => services.get(requestId), requestId);
  const [error, setError] = useState(false);
  const [busy, setBusy] = useState(false);
  async function action(path: string, data: unknown = {}, method = "POST") {
    setBusy(true);
    setError(false);
    try {
      await mutate("/api/v1/" + path, data, method);
      r.reload();
    } catch {
      setError(true);
    } finally {
      setBusy(false);
    }
  }
  async function edit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const d = new FormData(e.currentTarget);
    const form = Object.fromEntries(
      [...d]
        .filter(([k]) => k.startsWith("field:"))
        .map(([k, v]) => [k.slice(6), v]),
    );
    await action(
      "service-requests/" + requestId,
      {
        subject: d.get("subject"),
        description: d.get("description"),
        form_data: form,
      },
      "PATCH",
    );
  }
  return (
    <Resource {...r}>
      {r.data && (
        <>
          <h1>{r.data.request.subject}</h1>
          <p className="reference">{r.data.request.reference}</p>
          <StatusBadge status={r.data.request.status} />
          <section className="panel content-card">
            <p>{r.data.request.description}</p>
            <dl className="data-grid">
              {Object.entries(r.data.request.form_data ?? {}).map(
                ([key, value]) => (
                  <div key={key}>
                    <dt>{key.replaceAll("_", " ")}</dt>
                    <dd>{value}</dd>
                  </div>
                ),
              )}
            </dl>
            {["draft", "more_information_required"].includes(
              r.data.request.status,
            ) &&
              user?.role === "client" && (
                <>
                  <form className="form-grid" onSubmit={edit}>
                    <label>
                      {bn ? "বিষয়" : "Subject"}
                      <input
                        name="subject"
                        defaultValue={r.data.request.subject}
                        maxLength={180}
                        required
                      />
                    </label>
                    <label>
                      {bn ? "বিবরণ" : "Description"}
                      <textarea
                        name="description"
                        defaultValue={r.data.request.description ?? ""}
                        maxLength={5000}
                      />
                    </label>
                    {r.data.request.kind === "application" &&
                      [
                        "applicant_name",
                        "guardian_name",
                        "contact_phone",
                        "contact_email",
                        "service_address",
                        "ward",
                        "holding_number",
                        "connection_type",
                        "pipe_size",
                        "notes",
                      ].map((k) => (
                        <label key={k}>
                          {k.replaceAll("_", " ")}
                          <input
                            name={"field:" + k}
                            defaultValue={r.data!.request.form_data?.[k] ?? ""}
                            maxLength={1000}
                          />
                        </label>
                      ))}
                    <button className="button secondary" disabled={busy}>
                      {bn ? "খসড়া সংরক্ষণ" : "Save draft"}
                    </button>
                  </form>
                  <button
                    className="button"
                    disabled={busy}
                    onClick={() =>
                      action("service-requests/" + requestId + "/submit")
                    }
                  >
                    {bn ? "জমা দিন" : "Submit"}
                  </button>
                  <label>
                    {bn ? "সংযুক্তি যোগ করুন" : "Add attachment"}
                    <input
                      type="file"
                      accept=".pdf,.jpg,.jpeg,.png"
                      onChange={async (e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                          setBusy(true);
                          try {
                            await upload(requestId, file);
                            r.reload();
                          } catch {
                            setError(true);
                          } finally {
                            setBusy(false);
                          }
                        }
                      }}
                    />
                  </label>
                </>
              )}
            {error && (
              <p role="alert">
                {bn
                  ? "কাজটি সম্পন্ন করা যায়নি। তথ্য ও অনুমতি পরীক্ষা করুন।"
                  : "Action could not complete. Check fields and permissions."}
              </p>
            )}
            <h2>{bn ? "নথি" : "Documents"}</h2>
            {r.data.attachments.map((a) => (
              <a
                className="download-link"
                key={a.id}
                href={"/api/v1/attachments/" + a.id}
              >
                {a.original_name} ↓
              </a>
            ))}
          </section>
          <section className="panel content-card">
            <h2>{bn ? "অবস্থার ইতিহাস ও উত্তর" : "Timeline and replies"}</h2>
            <ol className="timeline">
              {r.data.events.map((e) => (
                <li key={e.id}>
                  <StatusBadge status={e.status} />
                  <small>
                    {formatDate(
                      e.created_at.replace(" ", "T") +
                        (/[Z+]/.test(e.created_at) ? "" : "Z"),
                      language,
                    )}
                  </small>
                  <p>{e.message}</p>
                </li>
              ))}
            </ol>
            {!["draft", "closed", "approved", "rejected"].includes(
              r.data.request.status,
            ) && (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  const d = new FormData(e.currentTarget);
                  void action("service-requests/" + requestId + "/replies", {
                    message: d.get("message"),
                  });
                }}
              >
                <label>
                  {bn ? "উত্তর" : "Reply"}
                  <textarea name="message" required maxLength={5000} />
                </label>
                <button className="button" disabled={busy}>
                  {bn ? "পাঠান" : "Send"}
                </button>
              </form>
            )}
          </section>
          {user && user.role !== "client" && (
            <form
              className="panel content-card form-grid"
              onSubmit={(e) => {
                e.preventDefault();
                const d = Object.fromEntries(new FormData(e.currentTarget));
                void action(
                  "admin/service-requests/" + requestId + "/review",
                  d,
                );
              }}
            >
              <h2>{bn ? "কর্মকর্তার পর্যালোচনা" : "Staff review"}</h2>
              <label>
                {bn ? "পরবর্তী অবস্থা" : "Next status"}
                <select name="status">
                  {(r.data.request.kind === "application"
                    ? [
                        "under_review",
                        "more_information_required",
                        "approved",
                        "rejected",
                      ]
                    : [
                        "under_review",
                        "in_progress",
                        "more_information_required",
                        "resolved",
                        "closed",
                      ]
                  )
                    .filter(
                      (s) =>
                        user.role === "admin" ||
                        !["approved", "rejected"].includes(s),
                    )
                    .map((s) => (
                      <option key={s}>{s}</option>
                    ))}
                </select>
              </label>
              <label>
                {bn ? "পর্যালোচনার মন্তব্য" : "Review note"}
                <textarea name="message" required minLength={3} />
              </label>
              <button className="button" disabled={busy}>
                {bn ? "হালনাগাদ" : "Update"}
              </button>
            </form>
          )}
        </>
      )}
    </Resource>
  );
}
