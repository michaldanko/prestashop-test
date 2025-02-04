# Custom Module - Stock Notifications

## Overview
The `cmstocknotifications` module for PrestaShop automatically generates a CSV file and sends an email notification when a product's stock reaches zero. It also logs the event for future reference.

## Features
- Monitors stock levels when an order is created or updated.
- Sends an email with a CSV file containing product details when stock reaches 0.
- Logs stock depletion events.
- Provides a configurable email recipient in the module settings.

## Installation
1. Upload the module to the `modules/` directory of your PrestaShop installation.
2. Go to **Back Office > Modules** and search for `Custom Module - Stock Notifications`.
3. Click **Install** and confirm.
4. After installation, configure the recipient email in the module settings.

## Configuration
To set the recipient email for stock alerts:
1. Navigate to **Modules > Module Manager**.
2. Find `Custom Module - Stock Notifications` and click **Configure**.
3. Enter the desired email address and click **Save**.

## How It Works
- Checks stock levels when an order is created or updated.
- If a product's stock reaches 0, it:
  - Generates a CSV file with product details.
  - Sends an email notification with the CSV file attached.
  - Logs the event to a log file.

## Log File
All stock depletion events are logged in:
```
/modules/cmstocknotifications/logs/stock_notifications.log
```

## Uninstallation
To uninstall the module:
1. Go to **Back Office > Modules**.
2. Find `Custom Module - Stock Notifications` and click **Uninstall**.
3. The module will be removed, and its configuration setting will be deleted.
