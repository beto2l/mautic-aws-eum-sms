# MMS activation and operations

MMS is fail-closed and disabled by default. These controls are deliberately separate from SMS so an incomplete MMS registration cannot silently enable media sends.

## Supported flow

1. In Mautic 7.2, create or edit a text message and set **Is MMS** to **Yes**.
2. Choose exactly one image from the local Mautic media library.
3. The plugin resolves only a file inside the Mautic installation, validates its real MIME type and size, and creates a content-addressed object under the configured S3 prefix.
4. The plugin calls AWS End User Messaging `SendMediaMessage` with the resulting `s3://bucket/key` URI.
5. Mautic records the transport result. The plugin logs the AWS message ID and channel, without a phone number, contact ID, message body, media URL, bucket, or object key.

Supported media is intentionally narrower than Mautic's editor: one JPEG, PNG, or GIF image, from 1 byte through 2,000,000 bytes. Remote HTTP(S) images, multiple files, PDF, audio, video, vCard, and calendar files are rejected before AWS submission.

## AWS prerequisites

- The AWS SMS/MMS campaign or use case is approved for the intended brand, message category, country, and origination identity.
- The origination identity is active, associated with the approved registration, and its number capabilities include MMS.
- The identity, configuration set, and media bucket are in the same AWS Region. The S3 bucket is in the same AWS account as the MMS identity.
- AWS-managed opt-outs remain enabled. This plugin does not support self-managed opt-outs or process inbound STOP/HELP messages itself.
- The Mautic instance role can call `sms-voice:SendMediaMessage` for the approved messaging resources.
- The instance role can call `s3:GetObject` and `s3:PutObject` only for the configured bucket and prefix. The bucket is private and uses server-side encryption.
- A configuration-set event destination is configured for final MMS status. CloudWatch Logs, Firehose, and SNS are supported by AWS.

Example S3 resource scope (replace placeholders):

```json
{
  "Effect": "Allow",
  "Action": ["s3:GetObject", "s3:PutObject"],
  "Resource": "arn:aws:s3:::YOUR_BUCKET/YOUR_PREFIX/*"
}
```

Do not add AWS access keys to Mautic. The plugin uses the host's default AWS credential provider, normally an EC2 instance profile.

## Activation checklist

Complete this checklist manually after AWS approval. The plugin does not infer campaign approval from a pending registration.

- [ ] AWS registration status is approved, not pending, requires update, rejected, or suspended.
- [ ] The production origination identity is associated with that registration and visibly reports MMS capability.
- [ ] The approved use case and AWS message category match the Mautic message (for example, promotional versus transactional).
- [ ] The consent disclosure identifies the sender brand and covers both SMS and MMS, message purpose, variable or stated frequency, message/data rates, STOP, HELP, terms, privacy, and consent-not-required language where applicable.
- [ ] Consent records are retained and the approved Mautic segments contain only eligible contacts.
- [ ] AWS-managed opt-outs are enabled; self-managed opt-outs are not enabled.
- [ ] A private same-account/same-region S3 bucket and dedicated prefix exist.
- [ ] The Mautic instance role has the least-privilege messaging and S3 permissions described above.
- [ ] An AWS configuration-set event destination captures MMS delivery and failure events.
- [ ] The plugin remains in **Locked** mode while the following MMS fields are saved: bucket, prefix, campaign approval confirmation, identity capability confirmation, opt-out confirmation, and **Enable AWS MMS**.
- [ ] A canary MMS is reviewed and explicitly authorized before any real test. Never use an unconsented number.
- [ ] Only after a successful canary and audience review is delivery changed to **Production - approved audiences**.

The opt-in confirmation sent after signup must identify the actual brand. A generic pattern is: “BRAND: You confirmed that you want promotions and reminders by SMS/MMS from BRAND. Frequency may vary. Reply STOP to cancel or HELP for help.” Adapt it to the approved registration; do not copy this pattern if AWS approved different wording.

## Mautic configuration

In **Settings > Plugins > AWS End User Messaging SMS/MMS > Features**:

- Keep **Require SMS/MMS consent before delivery** enabled.
- Configure the exact consent-field alias and approved segment IDs.
- Select the AWS message type matching the approved use case.
- Enter the S3 bucket name and a dedicated prefix, such as `mautic-mms`.
- Check the campaign, identity, and AWS-managed opt-out confirmations only after independently verifying each item in AWS.
- Turn **Enable AWS MMS** on only after all confirmations are accurate.

The maximum MMS body is 1,600 characters under the AWS API. The plugin's emoji block applies to MMS too when enabled. Carrier rendering and resizing can vary, so use a compact, readable image even when it is within the documented limit.

## Monitoring and incident response

An accepted API response only proves AWS accepted the request. Use the logged AWS `message_id` to correlate with configuration-set events. Monitor at least:

- `MEDIA_DELIVERED` and `MEDIA_SUCCESSFUL` for successful progression.
- `MEDIA_INVALID`, `MEDIA_INVALID_MESSAGE`, `MEDIA_FILE_TYPE_UNSUPPORTED`, `MEDIA_FILE_SIZE_EXCEEDED`, and `MEDIA_FILE_INACCESSIBLE` for content/storage problems.
- `MEDIA_BLOCKED`, `MEDIA_CARRIER_BLOCKED`, `MEDIA_SPAM`, `MEDIA_UNREACHABLE`, `MEDIA_CARRIER_UNREACHABLE`, `MEDIA_TTL_EXPIRED`, and `MEDIA_UNKNOWN` for policy, carrier, reachability, or expiry failures.

AWS may take up to 72 hours to provide final delivery receipts. Do not put phone numbers, message bodies, contact IDs, names, email addresses, media paths, or S3 keys in AWS `Context`, dashboards, screenshots, or support logs.

If failure rates rise, immediately switch Mautic delivery mode to **Locked** or turn **Enable AWS MMS** off. Preserve sanitized message IDs and event types for diagnosis.

## Rollback

MMS can be rolled back without disabling SMS:

1. Turn **Enable AWS MMS** off and save the plugin.
2. Leave SMS configuration unchanged.
3. Remove or pause MMS messages from active Mautic campaigns.
4. Keep the configuration-set event destination active until in-flight final statuses arrive.
5. After the retention period required by your operations, remove unused S3 media objects or apply a lifecycle policy to the dedicated prefix.

For a code rollback, install the previous stable release and reload Mautic plugins/cache. Version 1.0.3 is SMS-only. Verify the active plugin version after rollback; do not send a live message solely to test rollback.

## Official AWS references

- [SendMediaMessage API](https://docs.aws.amazon.com/pinpoint/latest/apireference_smsvoicev2/API_SendMediaMessage.html)
- [Set up and send an MMS message](https://docs.aws.amazon.com/sms-voice/latest/userguide/send-mms-message.html)
- [MMS file types, sizes, and body limits](https://docs.aws.amazon.com/sms-voice/latest/userguide/mms-limitations-character.html)
- [10DLC campaign registration](https://docs.aws.amazon.com/sms-voice/latest/userguide/registrations-10dlc-register-campaign.html)
- [Registration and consent checklist](https://docs.aws.amazon.com/sms-voice/latest/userguide/registration-help-quickstart.html)
- [Opt-out lists](https://docs.aws.amazon.com/sms-voice/latest/userguide/opt-out-list.html)
- [Configuration-set event destinations](https://docs.aws.amazon.com/sms-voice/latest/userguide/configuration-sets-event-destinations.html)
- [MMS event types](https://docs.aws.amazon.com/sms-voice/latest/userguide/configuration-sets-event-types.html)
- [Event format](https://docs.aws.amazon.com/sms-voice/latest/userguide/configuration-sets-event-format.html)
