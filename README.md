# Tapbuy Adyen Module

A Magento 2 module that extends the Adyen Payment functionality to modify origin data for Tapbuy's GraphQL payment processing system.

## Overview

This module provides a plugin that intercepts and modifies the origin data for Adyen payments, specifically to handle custom origin URLs when requests are made through Tapbuy's system.

## Features

- **Origin URL Extraction**: Automatically extracts origin data from Tapbuy GraphQL requests and applies it to Adyen payment requests
- **Request Header Validation**: Validates Tapbuy requests using the `X-Tapbuy-Call` header for enhanced security
- **GraphQL Integration**: Designed to work seamlessly with Magento 2 GraphQL API
- **State Data Parsing**: Extracts origin information from nested JSON state data in GraphQL variables
- **URL Normalization**: Validates and normalizes origin URLs to ensure proper format
- **Error Handling**: Graceful handling of malformed JSON or missing data without interrupting payment flow

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
2. **Extracts GraphQL Request Data**: Parses the request body to extract GraphQL variables
3. **Navigates Nested JSON**: Follows the path `variables.paymentMethod.adyen_additional_data_cc.stateData` to find state data
4. **Parses State Data**: Deserializes the nested JSON state data to extract origin information
5. **Validates Origin URL**: Ensures the origin URL has proper scheme and host components
6. **Normalizes URL**: Constructs a normalized origin URL (scheme://host:port format)
7. **Updates Payment Data**: Modifies the Adyen payment request with the extracted origin URL

### Data Flow

The plugin expects GraphQL requests with the following structure:
```json
{
  "variables": {
    "paymentMethod": {
      "adyen_additional_data_cc": {
        "stateData": "{\"origin\":\"https://example.com:3000\",\"...\":\"...\"}"
      }
    }
  }
}
```

### Error Handling

The plugin includes robust error handling:
- Validates request headers to ensure legitimate Tapbuy requests
- Gracefully handles malformed JSON in request body or state data
- Validates URL structure and components before applying changes
- Maintains original payment flow if extraction fails or data is missing
- Prevents payment process interruption through comprehensive exception handling

## Configuration

### Request Headers

The module validates requests using the `X-Tapbuy-Call` header. This header must be present for the plugin to process Tapbuy payment modifications:

```http
X-Tapbuy-Call: 1
```

### GraphQL Request Format

The module expects Tapbuy GraphQL requests to include state data in the following format:

```json
{
  "variables": {
    "paymentMethod": {
      "adyen_additional_data_cc": {
        "stateData": "{\"origin\":\"https://your-domain.com:3000\"}"
      }
    }
  }
}
```

The `stateData` field should contain a JSON string with an `origin` property that specifies the origin URL for the Adyen payment.

### Origin Data Modification

When a Tapbuy request is detected, the plugin:
- Extracts the origin URL from the nested JSON state data
- Validates the URL format (must include scheme and host)
- Normalizes the URL to `scheme://host:port` format
- Applies the normalized origin to the Adyen payment request

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
- **Purpose**: Extracts origin data from Tapbuy GraphQL requests and applies it to Adyen payments
- **Method**: `afterBuild()` - Plugin method that runs after the original build method
- **Dependencies**: 
  - `RequestInterface` - For accessing HTTP request headers and body content
  - `Json` - For parsing JSON data from GraphQL requests and state data
- **Key Methods**:
  - `extractOriginFromTapbuyRequest()` - Extracts and validates origin from GraphQL request
  - `getNestedValue()` - Safely navigates nested array structures

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
   - Verify that the GraphQL request includes the correct state data structure
   - Check that the origin URL in state data has valid scheme and host
   - Ensure the `stateData` field contains valid JSON with an `origin` property

4. **JSON Parsing Errors**
   - Validate that the GraphQL request body is properly formatted JSON
   - Check that the nested `stateData` field contains valid JSON
   - Verify the origin URL format in the state data

5. **Headers Not Detected**
   - Ensure the `X-Tapbuy-Call` header is included in HTTP requests
   - Verify server configuration allows custom headers
   - Check that headers are not being stripped by proxies or load balancers