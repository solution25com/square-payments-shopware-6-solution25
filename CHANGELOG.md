# Changelog

All notable changes to this project will be documented in this file.

## [1.0.6] - 2026-01-14

### Added
- Extended the Square Payments plugin to fully integrate with the subscription infrastructure and improve the recurring payment flow
- Added a new storefront page allowing customers to view, add, and remove saved cards, and select the card used for the next recurring payment per subscription
- Introduced a dedicated recurring payment method in the Square service layer, supporting both authorize and capture modes with proper transaction state handling
- Implemented additional controllers, providers, and helper services to correctly link subscriptions, orders, and saved cards
### Improvements
- Improved logging and error management for recurring Square payments

---

## [1.0.5] - 2026-01-12

### Technical fixes
- **Fixed**: Environment configuration and error handling for Square API test .

---

## [1.0.4] - 2025-11-07

### Enhancements & Fixes
- Fixed: SCSS and package dependency issues to ensure smoother builds and improved styling consistency.
- Enhanced: Saved cards functionality for a more reliable and user-friendly checkout experience.
- Improved: Event listener setup for better performance and maintainability.
- Updated: CSS and HTML structure to enhance card styling and visibility in the storefront.

---

## [1.0.3] - 2025-10-29

### Initial Release
- This is the first official release of the **Square Payments Plugin** for **Shopware 6**.