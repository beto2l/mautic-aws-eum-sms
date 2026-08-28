# Security Policy

## Reporting a vulnerability

Do not open a public issue for a security vulnerability. Contact the repository owner privately with the affected version, reproduction details, and potential impact.

## Operational requirements

- Use an IAM role attached to the Mautic server. Do not create long-lived AWS access keys for this plugin.
- Scope IAM to `sms-voice:SendTextMessage` and the exact approved AWS SMS resources.
- Keep the plugin in `locked` mode except during a documented canary or approved production campaign.
- Restrict Mautic plugin configuration permissions to trusted administrators.
- Do not commit account IDs, phone numbers, ARNs, contact data, API keys, screenshots containing data, or Mautic `local.php`.
