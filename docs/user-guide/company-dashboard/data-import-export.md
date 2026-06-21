---
title: Import & Export
parent: Company Dashboard
nav_order: 21
---

# Import & Export

Bulk data operations for products, FAQs, and business records.

## CSV import

### Products

1. Download sample: `docs/sample-data/products_sample.csv`
2. Fill columns: name, price, description, category, availability, etc.
3. **Dashboard → Products → Import** (or Settings export section)
4. Upload CSV
5. Review import summary (created/updated/skipped rows)

### FAQs

1. Download sample: `docs/sample-data/faqs_sample.csv`
2. Columns: question, answer, keywords (semicolon-separated), active (true/false)
3. Upload via FAQ import

Import validates rows and reports errors per line.

## Export

From dashboard export dialog:

| Data type | Formats |
|-----------|---------|
| Products | CSV, JSON, XLSX |
| FAQs | CSV, JSON, XLSX |
| Orders | CSV, JSON, XLSX |
| Customers | CSV, JSON, XLSX |
| Chats | CSV, JSON |
| Growth attribution | CSV |

1. Select data types and format
2. Click **Export**
3. Download file when ready (async generation for large datasets)

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/company/import/products` | CSV upload |
| POST | `/api/company/import/faqs` | CSV upload |
| POST | `/api/company/export` | Request export |
| GET | `/api/company/export/download/{filename}` | Download file |

## Tips

- Use UTF-8 encoding for CSV with special characters
- Keep price as numeric without currency symbols in CSV
- Keywords in FAQs: use semicolons (`hours;opening;time`)
- Re-importing updates existing records matched by name/question

See [Sample Data Import (legacy)](../../SAMPLE_DATA_IMPORT.md) for column reference.
