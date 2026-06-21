# Sample data for companies – FAQ & product format

Use the files in **`docs/sample-data/`** as a reference for how to arrange your data. You can open them in Excel, Google Sheets, or any spreadsheet app.

---

## 1. FAQs sample – `faqs_sample.csv`

Use this layout when preparing FAQ data (e.g. for manual entry or future bulk import).

| Column      | Required | Description |
|------------|----------|-------------|
| **question** | Yes     | The customer question (e.g. “What are your opening hours?”). |
| **answer**   | Yes     | The reply the bot should give. |
| **category** | No      | Grouping (e.g. General, Delivery, Payment, Policies). |
| **keywords** | No      | Extra words that help the bot match this FAQ. Use **pipe `|`** to separate multiple keywords (e.g. `hours\|opening\|time`). No spaces around `\|`. |
| **is_active** | No     | `1` = active (bot can use it), `0` = inactive. Default: `1`. |

- **Encoding:** UTF-8 (so special characters display correctly in Excel).
- **Commas in text:** If a cell contains commas, wrap the whole value in double quotes, e.g. `"We are open Mon–Fri, 9am–6pm."`
- **Header row:** First row must be the column names exactly as above.

**Example row:**

```text
What are your opening hours?,We are open Monday–Friday 9am–6pm.,General,hours|opening|time,1
```

---

## 2. Products sample – `products_sample.csv`

Use this layout when preparing product/catalog data.

| Column        | Required | Description |
|---------------|----------|-------------|
| **name**      | Yes      | Product name. |
| **description** | No     | Short description (optional). |
| **price**     | Yes      | Numeric price, e.g. `1200.00` (no currency symbol in the cell). |
| **category**  | No       | e.g. Apparel, Electronics, Food. |
| **status**    | No       | `active` or `inactive`. Default: `active`. |

- **Encoding:** UTF-8.
- **Header row:** First row must be the column names as above.

**Example row:**

```text
T-Shirt Blue,Comfortable cotton t-shirt. Sizes S–XL.,1200.00,Apparel,active
```

---

## 3. Where to use this data

- **FAQs:** Add or edit FAQs in the company dashboard under **FAQ Automation** (`/dashboard/faq`). The sample shows the same columns you use there (question, answer, category, keywords, active).
- **Products:** Add or edit products in the company dashboard (Products/Catalog). The sample matches the product fields (name, description, price, category, status).

You can copy rows from the sample CSVs into your own spreadsheet, adjust the content for your business, and then either:

- Enter items manually in the dashboard, or  
- Use a future bulk-import feature (if added) that expects these columns.

---

## 4. File locations

| File                 | Path |
|----------------------|------|
| FAQs sample          | `docs/sample-data/faqs_sample.csv` |
| Products sample      | `docs/sample-data/products_sample.csv` |

Open in Excel: **File → Open** and choose the CSV; ensure “UTF-8” or “Unicode” is selected if you see encoding options.
