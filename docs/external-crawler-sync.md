# External crawler sync

Use this when Shopee blocks the server IP/browser. Run the crawler on a cleaner machine/browser, export products as JSON, then push them to the MMO server.

## Product JSON format

```json
[
  {
    "source_product_id": "SH-123-456",
    "product_name": "Tên sản phẩm",
    "product_url": "https://shopee.vn/product/123/456",
    "price": 99000,
    "sold_count": 1200,
    "notes": "Shopee browser crawler"
  }
]
```

Accepted sales fields: `sold_count`, `order_count`, or `sales_count`.

## Push to server

```bash
PRODUCT_IMPORT_TOKEN="<token from backend/app/config/local.php>" \
php scripts/push_scraped_products.php products.json https://mmo.sys-erp.id.vn shopee
```

The server endpoint is:

```http
POST /api/products/import
X-Import-Token: <token>
Content-Type: application/json

{"platform":"shopee","products":[...]}
```

Do not commit or share the token.
