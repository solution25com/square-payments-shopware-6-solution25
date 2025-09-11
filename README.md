composer require square/square "^31.0" in the root directory of your Shopware installation.

## Running Shopware Extension Verifier locally

To verify this plugin against Shopware Marketplace requirements, you can use the **Shopware CLI Extension Verifier**.

### 1. Navigate to the plugin directory
For example, if your plugin is located in `custom/plugins/square-payments-shopware-6-solution25`:

```bash
cd custom/plugins/square-payments-shopware-6-solution25
```


### To validate an extension, you can use the following command:

#### Docker:
```bash
docker run --rm -v $(pwd):/ext ghcr.io/shopware/shopware-cli extension validate --full /ext
```
#### or without Docker : 
```bash
shopware-cli extension validate /path/to/your/extension
```

#### For more details you can check the documentation of shopware : [Shopware CLI Extension Verifier](https://developer.shopware.com/docs/products/cli/validation.html?_gl=1*11lyefy*_gcl_au*NTUyMzUxMDk2LjE3NTM3MDI3MTg.*FPAU*MTEzNDU5MDMyMC4xNzUyOTEyNDA2*_ga*MTM2Nzc3NzA0OS4xNzUyOTEyNDA1*_ga_9JLJ6GGB76*czE3NTc0ODgzNDIkbzMyJGcxJHQxNzU3NDkwOTM2JGo1OCRsMCRoMjA4ODY5MzI2MA..*_fplc*d2hWTCUyRldETjBzbWhHMFFNbDRPcEtRQyUyRlVCbG9GUE1iUkNaNCUyRlEyY2RibUFpVTMlMkJXaDFHWDJLZDNQem5tZEdGUTcySWt3UExpMXVIdmtEcGpVdGg3WElreFhSJTJGZWRmWSUyQiUyQlhNUENhb0FMMEtXcXVnUHpFNU5mcjBJaTdCTlElM0QlM0Q)
