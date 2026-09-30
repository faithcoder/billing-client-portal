import { request } from "./client";
import { decode, page } from "./billing";
export type Payment = {
  id: string;
  reference: string;
  external_bill_id: string;
  external_account_id: string;
  amount_minor: string;
  currency: string;
  status: string;
  sync_status: string;
  checkout_url: string | null;
  verified_at: string | null;
  review_reason: string | null;
  created_at: string;
};
export type Receipt = {
  receipt: {
    receipt_reference: string;
    portal_payment_id: string;
    external_bill_id: string;
    external_account_id: string;
    external_customer_id: string;
    amount_minor: string;
    currency: string;
    verified_at: string;
    gateway_reference: string;
    external_transaction_reference: string;
    billing_sync_status_at_issue: string;
  };
  sync_status: string;
  upstream_receipt_reference: string | null;
  billing_system_payment_id: string | null;
};
export const payments = {
  list: (q = "") => request("/api/v1/payments" + q, page<Payment>, {}, true),
  get: (id: string) =>
    request("/api/v1/payments/" + encodeURIComponent(id), decode<Payment>),
  receipt: (id: string) =>
    request(
      "/api/v1/payments/" + encodeURIComponent(id) + "/receipt",
      decode<Receipt>,
    ),
};
