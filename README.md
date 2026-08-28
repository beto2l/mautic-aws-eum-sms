# AWS End User Messaging SMS for Mautic

Secure, consent-aware SMS delivery for Mautic 7 through AWS End User Messaging SMS.

The plugin adds a native Mautic SMS transport and exposes the normal SMS area under **Channels** when it is enabled. It does not create a public webhook or store AWS access keys. AWS authentication uses the IAM role attached to the Mautic host.

## Security model

- Disabled by default. The integration must be enabled in **Settings > Plugins**.
- Delivery starts in `locked` mode. No text is sent until an administrator deliberately selects `canary` or `production`.
- Canary mode can send only to one configured E.164 test number.
- Production mode requires a normalized E.164 phone field, an affirmative consent field, and membership in one of the configured segment IDs.
- Mautic continues to enforce its existing SMS Do Not Contact / opt-out records before this transport is called.
- The plugin rejects emoji by default and limits message length to control multi-part SMS cost.
- AWS credentials are never entered in the Mautic modal and are never committed to this repository.

## Requirements

- Mautic 7.x and PHP 8.2 or newer.
- AWS SDK for PHP.
- An EC2 instance profile or other AWS default credential provider with `sms-voice:SendTextMessage` permission limited to the approved origination identity, pool, configuration set, or protect configuration.
- An approved AWS End User Messaging SMS origination identity and configuration set.

## Install

For a production Mautic installation managed by Composer, add this GitHub repository as a VCS repository until the package is published to Packagist:

```bash
composer config repositories.mautic-aws-eum-sms vcs https://github.com/beto2l/mautic-aws-eum-sms.git
composer require beto2l/mautic-aws-eum-sms:dev-main
php bin/console mautic:plugins:reload
```

For a non-Composer Mautic deployment, copy this repository into:

```text
plugins/AwsEndUserMessagingSmsBundle/
```

Then run:

```bash
php bin/console mautic:plugins:reload
```

Open **Settings > Plugins > AWS End User Messaging SMS** and configure the integration modal. Keep the delivery mode in `locked` until a canary test succeeds.

## Configuration data

- AWS region.
- AWS origination identity: phone number, pool, or ARN.
- AWS configuration set name or ARN.
- Normalized phone field alias, usually `phone`.
- SMS consent field alias, for example `course_sms_optin`.
- Approved Mautic segment IDs.
- Canary test number, daily limit, maximum message length, and message type.

## Release scope

Version 0.1 supports text SMS only. MMS, RCS, inbound replies, and delivery-event webhooks are intentionally out of scope until the SMS transport is verified in production.

## Development

```bash
composer install
composer lint
```

Run the test suite in an environment that includes Mautic 7 dependencies:

```bash
vendor/bin/phpunit
```

## License

GPL-3.0-or-later.
