import { request } from "./client";
import { decode, page } from "./billing";
export type ServiceRequest = {
  id: string;
  reference: string;
  kind: "application" | "complaint";
  category: string | null;
  subject: string;
  description: string | null;
  status: string;
  form_data: Record<string, string> | null;
  external_account_id: string | null;
  external_bill_id: string | null;
  created_at: string;
};
export type RequestDetail = {
  request: ServiceRequest;
  events: { id: number; status: string; message: string; created_at: string }[];
  attachments: { id: string; original_name: string; scan_status: string }[];
};
export const services = {
  list: (kind: string, pageNumber = 1) =>
    request(
      `/api/v1/service-requests?kind=${kind}&page=${pageNumber}`,
      page<ServiceRequest>,
      {},
      true,
    ),
  get: (id: string) =>
    request("/api/v1/service-requests/" + id, decode<RequestDetail>),
};
export async function upload(id: string, file: File) {
  await request("/sanctum/csrf-cookie", () => null);
  const body = new FormData();
  body.set("file", file);
  return request("/api/v1/service-requests/" + id + "/attachments", decode, {
    method: "POST",
    body,
  });
}
