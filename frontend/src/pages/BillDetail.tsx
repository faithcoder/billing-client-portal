import { Link, useParams } from "react-router-dom";
import { billing, bill as decodeBill } from "../api/billing";
import { request } from "../api/client";
import type { Bill } from "../api/billing";
import { useResource } from "../lib/useResource";
import { Resource } from "../components/Resource";
import { StatusBadge } from "../components/StatusBadge";
import { useLanguage } from "../i18n";
import { formatMoney, formatDate } from "../lib/format";
export function BillCopy({
  bill: b,
  bank = false,
}: {
  bill: Bill;
  bank?: boolean;
}) {
  const { language } = useLanguage();
  const bn = language === "bn";
  const money = (s: string) => formatMoney(s, language);
  const date = (s: string | null) => formatDate(s, language);
  const breakdown: Record<string, string> = bn
    ? {
        current_charges_minor: "চলতি মাসের বিল",
        arrears_minor: "বকেয়া",
        arrears_surcharge_minor: "বকেয়া সারচার্জ",
        rebate_minor: "রিবেট",
        advance_minor: "অগ্রিম",
        late_fee_minor: "বিলম্ব ফি",
      }
    : {
        current_charges_minor: "Current charges",
        arrears_minor: "Arrears",
        arrears_surcharge_minor: "Arrears surcharge",
        rebate_minor: "Rebate",
        advance_minor: "Advance",
        late_fee_minor: "Late fee",
      };
  const meter: Record<string, string> = bn
    ? {
        previous_date: "পূর্ববর্তী তারিখ",
        current_date: "বর্তমান তারিখ",
        previous_reading: "পূর্ববর্তী রিডিং",
        current_reading: "বর্তমান রিডিং",
        old_meter_units: "পুরনো মিটার ইউনিট",
        used_units: "ব্যবহৃত ইউনিট",
        unit: "একক",
      }
    : {
        previous_date: "Previous date",
        current_date: "Current date",
        previous_reading: "Previous reading",
        current_reading: "Current reading",
        old_meter_units: "Old meter units",
        used_units: "Used units",
        unit: "Unit",
      };
  const pairs = [
    [bn ? "গ্রাহক" : "Customer", b.customer.name],
    [bn ? "অভিভাবক" : "Guardian", b.customer.guardian_name],
    [bn ? "ঠিকানা" : "Address", Object.values(b.customer.address).join(", ")],
    [bn ? "মোবাইল" : "Mobile", b.customer.mobile_number],
    [bn ? "গ্রাহক নম্বর" : "Customer ID", b.customer.external_customer_id],
    [bn ? "হিসাব নম্বর" : "Account ID", b.connection.external_account_id],
    [bn ? "বিল নম্বর" : "Bill number", b.bill_number],
    [bn ? "বিল মাস" : "Bill month", b.bill_month],
    [bn ? "ইস্যু তারিখ" : "Issue date", date(b.deadlines.issue_date)],
    [bn ? "শেষ তারিখ" : "Due date", date(b.deadlines.due_date)],
    [bn ? "মিটার" : "Meter", b.connection.meter_number],
    [
      bn ? "ধরন" : "Type",
      bn
        ? b.connection.customer_type.label_bn
        : b.connection.customer_type.label_en,
    ],
    [
      bn ? "পাইপের মাপ" : "Pipe size",
      `${b.connection.pipe_size.value} ${b.connection.pipe_size.unit ?? ""}`,
    ],
    [bn ? "শ্রেণি" : "Category", b.connection.category],
    [
      bn ? "পুরনো রেফারেন্স" : "Old reference",
      b.customer.old_customer_reference,
    ],
  ];
  return (
    <article className={"bill-copy " + (bank ? "bank-copy" : "customer-copy")}>
      <header className="bill-header">
        <h2>{bn ? b.municipality.name_bn : b.municipality.name_en}</h2>
        <p>{bn ? "পানি সরবরাহ বিল" : "Water supply bill"}</p>
        <span className="badge">
          {bank
            ? bn
              ? "ব্যাংক কপি"
              : "Bank copy"
            : bn
              ? "গ্রাহক কপি"
              : "Customer copy"}
        </span>
      </header>
      <dl className="bill-identifiers">
        {pairs.map(([k, v]) => (
          <div key={k}>
            <dt>{k}</dt>
            <dd>{v ?? "—"}</dd>
          </div>
        ))}
      </dl>
      {!bank && (
        <div className="bill-columns">
          <section>
            <h3>{bn ? "মিটার রিডিং" : "Meter readings"}</h3>
            <dl className="bill-lines">
              {b.meter_readings ? (
                Object.entries(meter).map(([key, label]) => (
                  <div key={key}>
                    <dt>{label}</dt>
                    <dd>
                      {key.endsWith("_date")
                        ? date(b.meter_readings![key] ?? null)
                        : (b.meter_readings![key] ?? "—")}
                    </dd>
                  </div>
                ))
              ) : (
                <p>—</p>
              )}
            </dl>
            <h3>{bn ? "পূর্ববর্তী পেমেন্ট" : "Previous payment"}</h3>
            <p>
              {b.previous_payment
                ? `${date(b.previous_payment.payment_date)} · ${money(b.previous_payment.amount_minor)}`
                : "—"}
            </p>
          </section>
          <section>
            <h3>{bn ? "বিলের বিবরণ" : "Charge breakdown"}</h3>
            <dl className="bill-lines">
              {Object.entries(breakdown).map(([key, label]) => (
                <div key={key}>
                  <dt>{label}</dt>
                  <dd>{money(b.breakdown[key])}</dd>
                </div>
              ))}
            </dl>
          </section>
        </div>
      )}
      <section className="bill-totals">
        <div>
          <span>{bn ? "শেষ তারিখের মধ্যে" : "Before deadline"}</span>
          <strong>{money(b.authoritative_totals.before_deadline_minor)}</strong>
        </div>
        <div>
          <span>{bn ? "শেষ তারিখের পরে" : "After deadline"}</span>
          <strong>{money(b.authoritative_totals.after_deadline_minor)}</strong>
        </div>
        <div>
          <span>{bn ? "পরিশোধিত" : "Paid"}</span>
          <strong>{money(b.authoritative_totals.paid_amount_minor)}</strong>
        </div>
        <div>
          <span>{bn ? "বর্তমান বকেয়া" : "Current outstanding"}</span>
          <strong>
            {money(b.authoritative_totals.outstanding_balance_minor)}
          </strong>
        </div>
      </section>
      <p className="bill-instructions">
        {b.payment_instructions ??
          (bn
            ? "অনুমোদিত পেমেন্ট নির্দেশনা এখনও সরবরাহ করা হয়নি।"
            : "Authorized payment instructions have not been supplied.")}
      </p>
      <p className="freshness">
        {bn ? "উৎস হালনাগাদ" : "Source updated"}: {date(b.source_updated_at)} ·{" "}
        {b.is_snapshot
          ? bn
            ? "সংরক্ষিত কপি"
            : "Snapshot"
          : bn
            ? "সরাসরি উৎসের তথ্য"
            : "Retrieved from source"}
      </p>
    </article>
  );
}
export default function BillDetail({ admin = false }: { admin?: boolean }) {
  const { billId = "" } = useParams();
  const { language } = useLanguage();
  const bn = language === "bn";
  const r = useResource(
    () =>
      admin
        ? request(
            "/api/v1/admin/bills/" + encodeURIComponent(billId),
            decodeBill,
          )
        : billing.bill(billId),
    billId,
  );
  return (
    <>
      <div className="page-heading no-print">
        <h1>{bn ? "বিল প্রিভিউ" : "Bill preview"}</h1>
        <p>
          {bn
            ? "পেমেন্টের আগে বর্তমান প্রদেয় টাকা আবার যাচাই করা হবে।"
            : "The authoritative payable amount is checked again before payment."}
        </p>
      </div>
      <Resource {...r}>
        {r.data && (
          <>
            <div className="toolbar no-print">
              <button
                className="button secondary"
                onClick={() => window.print()}
              >
                {bn ? "প্রিন্ট / PDF" : "Print / Save as PDF"}
              </button>
              <StatusBadge status={r.data.status} />
              {!admin &&
                r.data.authoritative_totals.outstanding_balance_minor !==
                  "0" && (
                  <Link
                    className="button"
                    to={"/pay/" + encodeURIComponent(billId)}
                  >
                    {bn ? "বিল পরিশোধ" : "Pay bill"}
                  </Link>
                )}
            </div>
            <div className="bill-document">
              <BillCopy bill={r.data} />
              <BillCopy bill={r.data} bank />
            </div>
          </>
        )}
      </Resource>
    </>
  );
}
