# Changelog

All notable changes to this project will be documented in this file.

## [1.1.2] - 2026-04-16

### Fixes  

- **Payment fields rendering issue**  
  Fixed an issue where Square payment input fields were incorrectly rendered on the payment methods page.

---

## [1.1.1] - 2026-03-13

### Added
- Improved compatibility with the **Shopware Out-of-the-Box Subscriptions plugin** for Square payments.

### Changed
- Refactored subscription payment token resolution logic.
- The plugin now resolves the recurring payment token from `order_transaction.customFields.recurringPayment.reference` when no override exists in `*_subscription_card_choice`.

### Fixed
- Ensured recurring payments work correctly when no explicit subscription card choice is stored.
- Improved stability of recurring payment processing with Square when used together with the default Shopware Subscriptions plugin.

---

## [1.1.0] - 2026-03-02
- Updated the subscription modal saved-card labels to display in the format “VISA •••• 1111 (12/2028)”.
- Removed the Subscriptions grid from /account/squarepayments/saved-cards.

---

## [1.0.7] - 2026-01-22

### Added
- Added SquareConfigService integration for payment method configuration checks

---

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
