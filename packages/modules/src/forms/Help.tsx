// Help tab: the documentation the classic panel links to.
import { Icon } from '@fluxwp/ui';
import type { Translate } from './i18n';

const DOC = 'https://whitestudio.team/document/';

export function Help({ t }: { t: Translate }) {
  const links: [string, string][] = [
    [t('How to set up form notification emails'), `${DOC}how-to-set-up-form-notification-emails-in-easy-form-builder/#settingUp-Notification`],
    [t('How to get Google reCAPTCHA and use it in a form'), `${DOC}how-to-get-google-recaptcha-and-implement-it-into-easy-form-builder/`],
    [t('How to activate the alert email'), `${DOC}how-to-set-up-form-notification-emails-in-easy-form-builder/#email-notification`],
    [t('How to create your first form'), `${DOC}how-to-create-your-first-form-with-easy-form-builder/`],
    [t('How to activate the confirmation (tracking) code'), `${DOC}how-to-activate-confirmation-code-in-easy-form-builder/`],
    [t('How to work with form entries and manage forms'), `${DOC}complete-guide-of-form-entries-and-mange-forms/`],
    [t('How to add the confirmation code finder'), `${DOC}how-to-add-the-confirmation-code-finder/`],
    [t('How to find a response with a confirmation code'), `${DOC}how-to-find-a-response-through-a-confirmation-code/`],
  ];
  return (
    <section className="flxf-card">
      <div className="flxf-card__head">
        <h2>
          <Icon name="info" size={16} />
          {t('Learn more')}
        </h2>
      </div>
      <p className="flxf-muted">{t('Flux Forms is based on Easy Form Builder. These guides explain the form builder, notifications and the response box.')}</p>
      <ul className="flxf-links">
        {links.map(([label, href]) => (
          <li key={href}>
            <a href={href} target="_blank" rel="noopener noreferrer">
              {label}
            </a>
          </li>
        ))}
      </ul>
    </section>
  );
}
