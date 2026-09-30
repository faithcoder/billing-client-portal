import { useLanguage } from "../i18n";
const bn: Record<string, string> = {
  paid: "পরিশোধিত",
  unpaid: "অপরিশোধিত",
  partially_paid: "আংশিক",
  unknown: "অজানা",
  void: "বাতিল",
  initiated: "শুরু হয়েছে",
  pending: "অপেক্ষমাণ",
  succeeded: "যাচাইকৃত",
  failed: "ব্যর্থ",
  cancelled: "বাতিল",
  expired: "মেয়াদ শেষ",
  synced: "বিলিং হালনাগাদ",
  syncing: "হালনাগাদ চলছে",
  needs_review: "পর্যালোচনা প্রয়োজন",
  draft: "খসড়া",
  submitted: "জমা হয়েছে",
  under_review: "পর্যালোচনাধীন",
  more_information_required: "তথ্য প্রয়োজন",
  approved: "অনুমোদিত",
  rejected: "প্রত্যাখ্যাত",
  open: "খোলা",
  in_progress: "চলমান",
  resolved: "সমাধান হয়েছে",
  closed: "বন্ধ",
};
export function statusLabel(status: string, language: string): string {
  return language === "bn"
    ? (bn[status] ?? status.replaceAll("_", " "))
    : status.replaceAll("_", " ");
}
export function StatusBadge({ status }: { status: string }) {
  const { language } = useLanguage();
  return (
    <span className={`badge status-${status}`}>
      {statusLabel(status, language)}
    </span>
  );
}
