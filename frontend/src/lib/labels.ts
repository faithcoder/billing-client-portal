import type { Language } from "../i18n";
const labels: Record<string, string> = {
  applicant_name: "আবেদনকারীর নাম",
  guardian_name: "অভিভাবকের নাম",
  contact_phone: "যোগাযোগের ফোন",
  contact_email: "যোগাযোগের ইমেইল",
  service_address: "সেবার ঠিকানা",
  ward: "ওয়ার্ড",
  holding_number: "হোল্ডিং নম্বর",
  connection_type: "সংযোগের ধরন",
  pipe_size: "পাইপের মাপ",
  notes: "অতিরিক্ত তথ্য",
  user_id: "পোর্টাল ব্যবহারকারী",
  kind: "সেবার ধরন",
  category: "বিভাগ",
  external_account_id: "হিসাব নম্বর",
  external_customer_id: "গ্রাহক নম্বর",
  external_bill_id: "বিল নম্বর",
  created_at: "তৈরি হয়েছে",
  updated_at: "হালনাগাদ হয়েছে",
  verified_at: "যাচাইয়ের সময়",
  revoked_at: "সংযোগ বাতিলের সময়",
  review_reason: "পর্যালোচনার কারণ",
  email: "ইমেইল",
  name: "নাম",
  role: "ভূমিকা",
};
export function fieldLabel(key: string, language: Language): string {
  return language === "bn"
    ? (labels[key] ?? key.replaceAll("_", " "))
    : key.replaceAll("_", " ");
}
