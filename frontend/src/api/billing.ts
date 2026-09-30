import { request, ApiError } from "./client";
export type Account = {
  external_account_id: string;
  external_customer_id: string;
  name: string;
  address: Record<string, string>;
  phone: string | null;
  currency: "BDT";
  outstanding_balance_minor: string;
  source_updated_at: string | null;
};
export type BillSummary = {
  external_bill_id: string;
  external_account_id: string;
  external_customer_id: string;
  bill_number: string;
  bill_month: string;
  type: { label_bn: string; label_en: string };
  issue_date: string;
  due_date: string;
  amount_minor: string;
  paid_amount_minor: string;
  outstanding_balance_minor: string;
  paid_date: string | null;
  status: string;
  source_updated_at: string | null;
};
export type Bill = {
  external_bill_id: string;
  bill_number: string;
  bill_month: string;
  municipality: { name_bn: string; name_en: string };
  customer: {
    external_customer_id: string;
    name: string;
    guardian_name: string | null;
    address: Record<string, string>;
    mobile_number: string | null;
    old_customer_reference: string | null;
  };
  connection: {
    external_account_id: string;
    meter_number: string | null;
    category: string;
    customer_type: { label_bn: string; label_en: string };
    pipe_size: { value: string; unit: string | null };
    service_address: Record<string, string>;
  };
  meter_readings: Record<string, string | null> | null;
  breakdown: Record<string, string>;
  deadlines: {
    issue_date: string;
    due_date: string;
    payment_cutoff_at: string | null;
  };
  authoritative_totals: {
    before_deadline_minor: string;
    after_deadline_minor: string;
    paid_amount_minor: string;
    outstanding_balance_minor: string;
    as_of: string;
  };
  previous_payment: { payment_date: string; amount_minor: string } | null;
  source_updated_at: string | null;
  source_version: string | null;
  status: string;
  is_snapshot?: boolean;
  payment_instructions?: string;
};
export type PageData<T> = {
  data: T[];
  meta: {
    pagination: {
      page: number;
      per_page: number;
      total: number | null;
      has_next: boolean;
    };
    mode?: string;
    fetched_at?: string;
    is_snapshot?: boolean;
  };
};
export function record(value: unknown): Record<string, unknown> {
  if (value === null || typeof value !== "object" || Array.isArray(value))
    throw new ApiError(502, "INVALID_RESPONSE");
  return value as Record<string, unknown>;
}
export function decode<T>(value: unknown): T {
  return record(value) as T;
}
export function page<T>(value: unknown): PageData<T> {
  const p = record(value);
  const meta = record(p.meta);
  const pagination = record(meta.pagination);
  if (
    !Array.isArray(p.data) ||
    typeof pagination.page !== "number" ||
    typeof pagination.has_next !== "boolean"
  )
    throw new ApiError(502, "INVALID_RESPONSE");
  return p as PageData<T>;
}
export function bill(value: unknown): Bill {
  const b = record(value);
  if (
    typeof b.external_bill_id !== "string" ||
    typeof record(b.authoritative_totals).outstanding_balance_minor !== "string"
  )
    throw new ApiError(502, "INVALID_RESPONSE");
  return b as Bill;
}
export const billing = {
  account: () => request("/api/v1/profile", decode<Account>),
  bills: (query: string) =>
    request("/api/v1/bills" + query, page<BillSummary>, {}, true),
  bill: (id: string) =>
    request("/api/v1/bills/" + encodeURIComponent(id), bill),
};
export async function mutate<T>(
  path: string,
  body: unknown = {},
  method = "POST",
): Promise<T> {
  await request("/sanctum/csrf-cookie", () => null);
  return request(path, (d) => d as T, { method, body: JSON.stringify(body) });
}
