export type IconName =
  | "home"
  | "bills"
  | "payments"
  | "more"
  | "water"
  | "arrow"
  | "shield"
  | "connection"
  | "complaints"
  | "profile"
  | "logout"
  | "admin";
const paths: Record<IconName, string> = {
  home: "m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z",
  bills: "M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6",
  payments: "M3 6h18v14H3V6Zm0 5h18m-5 5h2M6 3h12",
  more: "M5 5h4v4H5Zm10 0h4v4h-4ZM5 15h4v4H5Zm10 0h4v4h-4Z",
  water: "M12 2C9 6 5 10 5 14a7 7 0 0 0 14 0c0-4-4-8-7-12Zm-3 12a3 3 0 0 0 3 3",
  arrow: "M5 12h14m-5-5 5 5-5 5",
  shield: "m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-6",
  connection: "M4 9h16v5H4Zm3 5v7m10-7v7M12 3v6m-4-6h8",
  complaints: "M4 4h16v12H9l-5 4V4Zm4 4h8m-8 4h5",
  profile: "M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2",
  logout: "M10 3H4v18h6m4-14 5 5-5 5m-6-5h13",
  admin: "M3 21h18M5 21V9h14v12M3 9l9-6 9 6M9 12v6m6-6v6",
};
export function Icon({
  name,
  className = "",
}: {
  name: IconName;
  className?: string;
}) {
  return (
    <svg
      className={className}
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d={paths[name]} />
    </svg>
  );
}
