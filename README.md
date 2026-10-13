# WebSecretaries.com

Static website for WebSecretaries.com, hosted on GitHub Pages.

- Pages: index, about, services, contact, terms, privacy, refund, cookies, 404
- Old WordPress URLs redirect via small folders (e.g. `contact-us/`)
- Contact form posts to Basin (usebasin.com); set the form ID in `contact.html`
- Preview locally from the parent folder: `powershell -ExecutionPolicy Bypass -File tools\serve.ps1 -Root websecretaries`
