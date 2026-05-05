# Changelog

All notable changes to the WooCommerce MPGS plugin will be documented in this file.

## [1.5.3] - 2026-05-05

### Added
- Added comprehensive error handling for API responses (`is_wp_error` checks) to prevent fatal errors during network failures.
- Added validation to ensure transaction data exists before accessing it in the response payload.

### Changed
- **Security:** Hardened the `receipt_page` against Cross-Site Scripting (XSS) by properly escaping all customer and order data outputs (`esc_js`).
- **Security:** Sanitized all usages of `$_REQUEST['sessionId']` before processing or outputting to prevent potential injection attacks.
- **Security:** Updated the "Authentication Password" setting field from a plain text input to a secure password input field.
- **Security:** Wrapped the missing WooCommerce admin notice with `wp_kses` for safer HTML output.
- **Compatibility:** Updated "Tested up to" headers for WordPress 6.8 and WooCommerce 9.8.5.
- **Compatibility:** Verified and declared support for WooCommerce High-Performance Order Storage (HPOS).
- **Compatibility:** Changed the text domain loading hook from `plugins_loaded` (deprecated in WP 6.7) to `init`.
- **Compatibility:** Replaced the deprecated `utf8_decode()` function with `mb_convert_encoding()` for PHP 8.2+ support.
- **Refactoring:** General code formatting and style improvements applied across the codebase.
