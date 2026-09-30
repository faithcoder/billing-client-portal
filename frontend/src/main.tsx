import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { RouterProvider } from "react-router-dom";
import { LanguageProvider } from "./i18n";
import { SessionProvider } from "./app/session";
import { router } from "./app/router";

import "./styles.css";
createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <LanguageProvider>
      <SessionProvider>
        <RouterProvider router={router} />
      </SessionProvider>
    </LanguageProvider>
  </StrictMode>,
);
if (import.meta.env.PROD && "serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker.register("/sw.js").catch(() => {
      /* Online functionality remains available without installation. */
    });
  });
}

import "./billing.css";
