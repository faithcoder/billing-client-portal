import { defineConfig, loadEnv } from "vite";
import tailwindcss from "@tailwindcss/vite";
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const proxy = Object.fromEntries(
    ["/api", "/sanctum"].map((path) => [
      path,
      {
        target: env.PORTAL_DEV_API_TARGET || "http://127.0.0.1:8000",
        changeOrigin: false,
      },
    ]),
  );
  return {
    plugins: [tailwindcss()],
    server: { port: 5173, strictPort: true, proxy },
    preview: { port: 4173, strictPort: true, proxy },
    build: { manifest: true },
  };
});
