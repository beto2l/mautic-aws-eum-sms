# Changelog

All notable changes to this project are documented here.

## [1.0.2] - 2026-09-03

- Fixed Mautic 7.2 service wiring by referencing the core `EncryptionHelper` service directly.
- Removed the need to redefine Mautic's encryption service in the plugin configuration.

## [1.0.1] - 2026-08-28

- Corrected the published installation guide to use the release tag and documented Packagist installation.

## [1.0.0] - 2026-08-28

- Added a native AWS End User Messaging SMS transport for Mautic 7.
- Added IAM-role authentication without storing AWS access keys in Mautic.
- Added locked, canary, and approved-audience production delivery modes.
- Added consent, approved-segment, phone normalization, message length, emoji, daily, and per-minute safeguards.
- Added installation, operation, support, and privacy documentation.
