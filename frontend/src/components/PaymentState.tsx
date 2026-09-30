import { useLanguage } from "../i18n";
import type { Payment } from "../api/payments";
export function PaymentState({ payment: p }: { payment: Payment }) {
  const { language } = useLanguage();
  const bn = language === "bn";
  const text =
    p.sync_status === "needs_review"
      ? bn
        ? "পেমেন্ট পর্যালোচনা প্রয়োজন। আবার অর্থ প্রদান করবেন না।"
        : "Payment requires review. Do not pay again."
      : p.status === "succeeded"
        ? p.sync_status === "synced"
          ? bn
            ? "পেমেন্ট যাচাইকৃত এবং বিলিং হালনাগাদ হয়েছে।"
            : "Payment verified and billing updated."
          : bn
            ? "পেমেন্ট যাচাইকৃত; বিলিং হালনাগাদ অপেক্ষমাণ। আবার অর্থ প্রদান করবেন না।"
            : "Payment verified; billing update pending. Do not pay again."
        : ["failed", "cancelled", "expired"].includes(p.status)
          ? bn
            ? "গেটওয়ে পেমেন্ট সম্পন্ন করেনি। সর্বশেষ অবস্থা এখানে দেখানো হচ্ছে।"
            : "The gateway did not complete payment. This is the latest verified status."
          : bn
            ? "পেমেন্ট অপেক্ষমাণ। গেটওয়ে নিশ্চিতকরণের জন্য অপেক্ষা করুন।"
            : "Payment pending. Waiting for gateway confirmation.";
  return (
    <div className="notice" role="status">
      <p>{text}</p>
    </div>
  );
}
