# Tapbuy Adyen Module

A Magento 2 module that extends the Adyen Payment functionality to modify origin data for Tapbuy's GraphQL payment processing system.

## Overview

This module provides a plugin that intercepts and modifies the origin data for Adyen payments, specifically to handle custom origin URLs when requests are made through Tapbuy's system.

## Features

- **Origin URL Modification**: Automatically modifies origin data for Adyen payment requests when initiated through Tapbuy
- **Request Header Validation**: Validates Tapbuy requests using the `X-Tapbuy-Call` header for enhanced security
- **GraphQL Integration**: Designed to work seamlessly with Magento 2 GraphQL API
- **Flexible Configuration**: Customizes origin data based on Tapbuy request context

## Requirements

- Magento 2.x
- PHP 7.4+ or 8.x
- Adyen Payment module
- Magento GraphQL module

## Installation

### Composer Installation (Recommended)

1. Add the module to your Magento project:
```bash
composer require tapbuy/adyen
```

2. Enable the module:
```bash
php bin/magento module:enable Tapbuy_Adyen
```

3. Run setup upgrade:
```bash
php bin/magento setup:upgrade
```

4. Compile if needed:
```bash
php bin/magento setup:di:compile
```

5. Clear cache:
```bash
php bin/magento cache:clean
```

### Manual Installation

1. Create the directory structure:
```
app/code/Tapbuy/Adyen/
```

2. Copy all module files to the directory
3. Follow steps 2-5 from the Composer installation

## How It Works

### Plugin Architecture

The module uses Magento's plugin system to intercept the `Adyen\Payment\Gateway\Request\OriginDataBuilder::build()` method using an `afterBuild` plugin.

### Origin Data Modification

When a payment is processed, the plugin:

1. **Validates Request Headers**: Checks for the `X-Tapbuy-Call` header to ensure the request originates from Tapbuy
2. **Modifies Origin Data**: Updates the origin information in Adyen payment requests when the request comes from Tapbuy's system
3. **Maintains Security**: Ensures only legitimate Tapbuy requests can modify the origin data

### Error Handling

The plugin includes robust error handling:
- Validates request headers to ensure legitimate Tapbuy requests
- Maintains original payment flow if Tapbuy headers are missing
- Prevents payment process interruption

## Configuration

### Request Headers

The module validates requests using the `X-Tapbuy-Call` header. This header must be present for the plugin to process Tapbuy payment modifications:

```http
X-Tapbuy-Call: 1
```

### Payment Additional Information Format

The module detects Tapbuy requests using the `X-Tapbuy-Call` header and modifies the origin data accordingly.

### Origin Data Modification

When a Tapbuy request is detected, the plugin modifies the origin data to use Tapbuy-specific origin URLs for proper payment processing.

## File Structure

```
Tapbuy/Adyen/
├── Plugin/
│   └── OriginDataBuilderPlugin.php        # Main plugin class
├── etc/
│   ├── di.xml                             # Dependency injection configuration
│   └── module.xml                         # Module declaration
├── composer.json                          # Composer configuration
├── registration.php                       # Module registration
└── README.md                              # This file
```

## Dependencies

### Module Dependencies
- `Magento_GraphQl`: Required for GraphQL functionality

### Plugin Target
- `Adyen\Payment\Gateway\Request\OriginDataBuilder`: The Adyen module's origin data builder

## Development

### Plugin Configuration

The plugin is configured in `etc/di.xml`:

```xml
<type name="Adyen\Payment\Gateway\Request\OriginDataBuilder">
    <plugin name="tapbuy_adyen_origin_data_builder_plugin" 
            type="Tapbuy\Adyen\Plugin\OriginDataBuilderPlugin"
            sortOrder="10"/>
</type>
```

### Key Classes

#### OriginDataBuilderPlugin
- **Namespace**: `Tapbuy\Adyen\Plugin`
- **Purpose**: Modifies Adyen origin data with Tapbuy-specific origin URLs
- **Method**: `afterBuild()` - Plugin method that runs after the original build method
- **Dependencies**: 
  - `RequestInterface` - For accessing HTTP request headers

## Troubleshooting

### Common Issues

1. **Module Not Loading**
   - Verify module is enabled: `php bin/magento module:status Tapbuy_Adyen`
   - Check registration.php path is correct

2. **Plugin Not Working**
   - Ensure DI compilation is up to date: `php bin/magento setup:di:compile`
   - Verify Adyen module is installed and enabled
   - Check that the `X-Tapbuy-Call` header is being sent with requests

3. **Origin Data Issues**
   - Check that Tapbuy data is properly configured for origin URLs
   - Verify that the correct origin is being set for payment requests

4. **Headers Not Detected**
   - Ensure the `X-Tapbuy-Call` header is included in HTTP requests
   - Verify server configuration allows custom headers
   - Check that headers are not being stripped by proxies or load balancers

### Logging

The module handles requests gracefully. For debugging, you can modify the plugin in `OriginDataBuilderPlugin.php` to add logging:

```php
// Add logging to track origin data modifications
$this->logger->info('Tapbuy Adyen plugin: Origin data modified for Tapbuy request');
```