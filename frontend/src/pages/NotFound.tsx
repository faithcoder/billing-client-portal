import { Link } from "react-router-dom";
import { useLanguage } from "../i18n";
import { Page } from "../components/Page";
export default function NotFound() {
  const { t } = useLanguage();
  return (
    <Page title="notFound" description="notFoundText">
      <Link className="button" to="/">
        {t("back")}
      </Link>
    </Page>
  );
}
