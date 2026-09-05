# AWS End User Messaging SMS for Mautic

Secure, consent-aware SMS delivery for Mautic 7 through AWS End User Messaging SMS.

The plugin adds a native Mautic SMS transport and exposes the normal SMS area under **Channels** when it is enabled. It does not create a public webhook or store AWS access keys. AWS authentication uses the IAM role attached to the Mautic host.

## Security model

- Disabled by default. The integration must be enabled in **Settings > Plugins**.
- Delivery starts in `locked` mode. No text is sent until an administrator deliberately selects `canary` or `production`.
- Canary mode can send only to one configured E.164 test number.
- Production mode requires a phone field that can be normalized to E.164, administrator confirmation that the approved audience opted in to SMS, and membership in one of the configured segment IDs. US 10- and 11-digit NANP values are normalized to E.164 at send time. Mautic's native DNC/opt-out checks remain active.
- Mautic continues to enforce its existing SMS Do Not Contact / opt-out records before this transport is called.
- The plugin rejects emoji by default and limits message length to control multi-part SMS cost.
- AWS credentials are never entered in the Mautic modal and are never committed to this repository.
- Do not enable the Twilio SMS plugin at the same time. Mautic uses one active SMS transport for campaign delivery.

Mautic 7.2 provides `Mautic\CoreBundle\Helper\EncryptionHelper` as a core service. The plugin references that service directly and does not redefine `mautic.helper.encryption`, so Mautic's core cipher dependencies remain intact.

## Requirements

- Mautic 7.x (verified with Mautic 7.2.0) and PHP 8.2, 8.3, or 8.4.
- AWS SDK for PHP.
- An EC2 instance profile or other AWS default credential provider with `sms-voice:SendTextMessage` permission limited to the approved origination identity, pool, configuration set, or protect configuration.
- An approved AWS End User Messaging SMS origination identity and configuration set.

## Install

For a production Mautic installation managed by Composer, install the published package:

```bash
composer require beto2l/mautic-aws-eum-sms:^1.0
php bin/console mautic:plugins:reload
```

Until the Packagist listing is active, add the GitHub repository as a VCS repository and require the release tag:

```bash
composer config repositories.mautic-aws-eum-sms vcs https://github.com/beto2l/mautic-aws-eum-sms.git
composer require beto2l/mautic-aws-eum-sms:^1.0
```

For a non-Composer Mautic deployment, copy this repository into:

```text
plugins/AwsEndUserMessagingSmsBundle/
```

Then run:

```bash
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
php bin/console mautic:plugins:reload
```

Open **Settings > Plugins > AWS End User Messaging SMS** and configure the integration modal. Keep the delivery mode in `locked` until a canary test succeeds.

See the [installation and operation guide](docs/INSTALLATION.md) for the complete setup and sending workflow.

## Configuration data

- AWS region.
- AWS origination identity: phone number, pool, or ARN.
- AWS configuration set name or ARN.
- Normalized phone field alias, usually `phone`.
- SMS consent field alias, for example `course_sms_optin`.
- Administrator confirmation that the approved audience has opted in to SMS.
- Approved Mautic segment IDs.
- Canary test number, daily limit, per-minute limit, maximum message length, and message type.

## Release scope

The current 1.x release supports text SMS only. MMS, RCS, inbound replies, and delivery-event webhooks are intentionally out of scope until the SMS transport is verified in production.

## Development

```bash
composer install
composer lint
```

Run the test suite in an environment that includes Mautic 7 dependencies:

```bash
vendor/bin/phpunit
```

## Consulting and support

Need help configuring your server and getting the most out of Mautic and AWS End User Messaging SMS, including secure SMS delivery to your authorized contact database?

- Configuration consultation: **$200 USD**. Payment is required before scheduling.
- Pay for a configuration consultation: [PayPal](https://www.paypal.com/ncp/payment/UYW7R5DVZ95AL)
- Schedule a consultation: [Google Calendar](https://calendar.app.google/cVrPWqf1tWfrk1UB8)
- Contact: [contact@opin-x.com](mailto:contact@opin-x.com)
- Report an error: [Support and error reporting](SUPPORT.md)

For donations or configuration services, contact us by email.

## License

GPL-3.0-or-later.
