import { createContext, useContext, useEffect, useState } from "react";
import type { ReactNode } from "react";
import { bn } from "./bn";
import { en } from "./en";
export type Language = "bn" | "en";
export type TranslationKey = keyof typeof en;
const Context = createContext<{
  language: Language;
  setLanguage: (language: Language) => void;
  t: (key: TranslationKey) => string;
} | null>(null);
export function LanguageProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>("bn");
  useEffect(() => {
    document.documentElement.lang = language;
    document.title =
      language === "bn"
        ? "পানি সেবা | নাগরিক পোর্টাল"
        : "Water services | Client portal";
  }, [language]);
  return (
    <Context.Provider
      value={{
        language,
        setLanguage,
        t: (key) => (language === "bn" ? bn : en)[key],
      }}
    >
      {children}
    </Context.Provider>
  );
}
export function useLanguage() {
  const value = useContext(Context);
  if (!value) throw new Error("Missing language provider");
  return value;
}
