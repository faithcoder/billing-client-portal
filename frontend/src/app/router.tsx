import { lazy, Suspense } from "react";
import { createBrowserRouter, Outlet } from "react-router-dom";
import { Layout, AdminGuard, ClientGuard } from "./Layout";
import { LoadingState, ErrorState } from "../components/States";
const Home = lazy(() => import("../pages/Home"));
const Login = lazy(() => import("../pages/Login"));
const Register = lazy(() => import("../pages/Register"));
const Reset = lazy(() => import("../pages/Reset"));
const LinkAccount = lazy(() => import("../pages/LinkAccount"));
const Bills = lazy(() => import("../pages/Bills"));
const BillDetail = lazy(() => import("../pages/BillDetail"));
const Payments = lazy(() => import("../pages/Payments"));
const PaymentDetail = lazy(() => import("../pages/PaymentDetail"));
const Receipt = lazy(() => import("../pages/Receipt"));
const Pay = lazy(() => import("../pages/Pay"));
const FakeCheckout = lazy(() => import("../pages/FakeCheckout"));
const More = lazy(() => import("../pages/More"));
const Profile = lazy(() => import("../pages/Profile"));
const Connection = lazy(() => import("../pages/Connection"));
const Complaints = lazy(() => import("../pages/Complaints"));
const RequestDetail = lazy(() => import("../pages/RequestDetail"));
const Admin = lazy(() => import("../pages/Admin"));
const NotFound = lazy(() => import("../pages/NotFound"));
function Boundary() {
  return (
    <Suspense fallback={<LoadingState />}>
      <Outlet />
    </Suspense>
  );
}
export const router = createBrowserRouter([
  {
    element: <Boundary />,
    errorElement: <ErrorState onRetry={() => window.location.reload()} />,
    children: [
      {
        element: <Layout />,
        children: [
          { index: true, element: <Home /> },
          { path: "login", element: <Login /> },
          { path: "register", element: <Register /> },
          { path: "reset-password", element: <Reset /> },
          { path: "more", element: <More /> },
          {
            element: <ClientGuard />,
            children: [
              { path: "link-account", element: <LinkAccount /> },
              { path: "bills", element: <Bills /> },
              { path: "bills/:billId", element: <BillDetail /> },
              { path: "payments", element: <Payments /> },
              { path: "payments/:paymentId", element: <PaymentDetail /> },
              { path: "payments/:paymentId/receipt", element: <Receipt /> },
              { path: "pay/:billId", element: <Pay /> },
              { path: "fake-checkout/:paymentId", element: <FakeCheckout /> },
              { path: "profile", element: <Profile /> },
              { path: "new-connection", element: <Connection /> },
              { path: "requests", element: <Complaints /> },
              { path: "requests/:requestId", element: <RequestDetail /> },
            ],
          },
          { path: "*", element: <NotFound /> },
        ],
      },
      {
        path: "admin",
        element: <Layout admin />,
        children: [
          {
            element: <AdminGuard />,
            children: [
              { index: true, element: <Admin /> },
              { path: "bills", element: <Bills admin /> },
              { path: "bills/:billId", element: <BillDetail admin /> },
              ...[
                "users",
                "account-links",
                "payments",
                "sync-failures",
                "applications",
                "complaints",
                "audit-logs",
                "integration-health",
              ].map((path) => ({ path, element: <Admin /> })),
            ],
          },
        ],
      },
    ],
  },
]);
