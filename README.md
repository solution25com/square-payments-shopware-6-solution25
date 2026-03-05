[![Packagist Version](https://img.shields.io/packagist/v/solution25/square-payments.svg)](https://packagist.org/packages/solution25/square-payments)
[![Packagist Downloads](https://img.shields.io/packagist/dt/solution25/square-payments.svg)](https://packagist.org/packages/solution25/square-payments)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](https://github.com/solution25/SquarePayments/blob/main/LICENSE)

# Square Payments

## Introduction

The Square Payments plugin enables secure and seamless payment processing in Shopware stores using Square. It integrates directly with Square’s payment platform to process credit and debit card transactions, supports guest checkout, and allows returning customers to manage their payment methods. The plugin ensures PCI-compliant transactions, real-time payment status updates, and flexible refund and capture functionality.

### Key Features

1. **Secure Card Payments**
   - PCI-compliant payment processing via Square.
2. **Auth & Capture Support**
   - Choose between authorize-only or direct capture transactions.
3. **Guest Checkout**
   - Accept payments without requiring customer registration.
4. **Saved Payment Methods**
   - Returning customers can securely store and reuse cards.
5. **Admin Transaction Management**
   - Capture, refund, or void payments directly from Shopware Admin.
6. **Partial Refund Support**
   - Process full or partial refunds with amount handling.
7. **Multi-Sales-Channel Support**
   - Configure per sales channel.

---

## Compatibility

- ✅ Shopware 6.6.x

---

# Get Started

## Installation & Activation

### 1. Download

## Git

- Clone the Plugin Repository:
```bash
git clone https://github.com/solution25com/square-payments-shopware-6-solution25
```
## Packagist
 ```
  composer require solution25/square-payments
  ```


2. **Install the Plugin in Shopware 6**

- Log in to your Shopware 6 Administration panel.
- Navigate to Extensions > My Extensions.
- Locate the Square Payments plugin and click Install.

3. **Activate the Plugin**

- After installation, click Activate to enable the plugin.
- In your Shopware Admin, go to Settings > Shop > Payment Methods.
- Check if the "Square Payments" is active and make sure the payment methods are also added to the sales channels.

4. **Verify Installation**

- After activation, you will see Square Payments in the list of installed plugins.
- The plugin name, version, and installation date should appear as shown in the screenshot below.

<img width="2940" height="1458" alt="image" src="https://github.com/user-attachments/assets/bfa6ece7-3476-4168-ab8a-353442c7259e" />

## Plugin Configuration

### 1. **Access Plugin Settings**

- Go to Extensions > My Extensions.
- Locate Square Payments and click configure to open the plugin settings.

### 2. **General Settings**

#### **Sales Channel**
- Select the sales channel(s) where you want Square Payments to be active.

#### **Environment**
- You can switch to Production environment or not .

#### **Payment Mode**
- You can select Authorize and Capture or just Authorize .

<img width="2938" height="1456" alt="image" src="https://github.com/user-attachments/assets/39c30f45-ae7a-4b46-ab45-b8b370c806eb" />

#### **Production Account Keys**
- Enter the Production Application ID , Production Access token and Production Location ID

<img width="2934" height="1450" alt="image" src="https://github.com/user-attachments/assets/51856820-4e32-43b1-9a29-7f758e458481" />

#### **Sanbox Account Keys**
- Enter the Sandbox Application ID , Sandbox Access token and Sandbox Location ID.

<img width="2934" height="1450" alt="image" src="https://github.com/user-attachments/assets/3c10363e-ca8b-481c-89f1-3ae8f5fe5d4c" />

#### **3D Secure**
- You can enable 3ds.

#### **Google Pay**
- Merchant Google ID.

#### **Apple Pay**
- Merchant Apple ID.

<img width="2936" height="1456" alt="image" src="https://github.com/user-attachments/assets/405e10e9-5eeb-446f-a6b2-5de7b2c3e645" />

### 3. **Save Configuration**

- Click Save in the top-right corner to store your settings.



---

## Support & Contact

For assistance with the Square Payments plugin:
- **Email:** [info@solution25.com](mailto:info@solution25.com)  
- **Phone:** +49 421 438 1919-0  
- **Website:** [https://www.solution25.com](https://www.solution25.com)
