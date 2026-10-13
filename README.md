# WebSecretaries.com

Static website for WebSecretaries.com, hosted on GitHub Pages.

- Pages: index, about, services, contact, thanks, terms, privacy, refund, cookies, 404
- Old WordPress URLs redirect via small folders (e.g. `contact-us/`)
- The contact form posts to `https://forms.websecretaries.com/send.php`, a PHP handler hosted on HostGator. Its source is in `_server/forms/` (folders starting with `_` are not published by GitHub Pages). It emails each request, with attachments, to info@WebSecretaries.com.
- Preview locally from the parent folder: `powershell -ExecutionPolicy Bypass -File tools\serve.ps1 -Root websecretaries`
