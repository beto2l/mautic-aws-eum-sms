# Changelog

All notable changes to this project are documented here.

## [1.1.0] - 2026-09-13

- Added outbound MMS support through Mautic 7.2's native `MMSTransportInterface` and AWS `SendMediaMessage`.
- Added fail-closed MMS controls for campaign approval, MMS-capable identity confirmation, and AWS-managed opt-outs.
- Added safe local-image validation and content-addressed S3 preparation for one JPEG, PNG, or GIF up to 2 MB.
- Preserved the existing SMS transport and safety controls.
- Removed contact identifiers from transport logs and added non-PII AWS message-ID correlation guidance.
- Added MMS activation, monitoring, incident-response, and rollback documentation and regression tests.

## [1.0.3] - 2026-09-05

- Updated the Mautic integration description to use a generic account-linked email instruction instead of a project-specific address.
- Updated the installation screenshot to show version 1.0.3 without account-specific information.

## [1.0.2] - 2026-09-03

- Fixed Mautic 7.2 service wiring by aliasing the core `EncryptionHelper` service through a scalar plugin service ID.
- Preserved the core encryption service dependencies during container dumping.
- Verified the plugin on the production Mautic 7.2.0 installation running PHP 8.4.
- Added PHP 8.4 to the continuous integration matrix.

## [1.0.1] - 2026-08-28

- Corrected the published installation guide to use the release tag and documented Packagist installation.

## [1.0.0] - 2026-08-28

- Added a native AWS End User Messaging SMS transport for Mautic 7.
- Added IAM-role authentication without storing AWS access keys in Mautic.
- Added locked, canary, and approved-audience production delivery modes.
- Added consent, approved-segment, phone normalization, message length, emoji, daily, and per-minute safeguards.
- Added installation, operation, support, and privacy documentation.
