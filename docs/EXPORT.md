# Export Pipeline

The Sabt export pipeline generates Excel files from Gravity Forms entries.

## Usage

1. Configure column mapping in `config/SmartAlloc_Exporter_Config_v1.json`.
2. Trigger the exporter (CLI or admin UI) to build the workbook.
3. Download the file named `SabtExport-ALLOCATED-YYYY_MM_DD-####-B{nnn}.xlsx`.

## Sheets

- **Summary** – contains all valid rows based on the mapping.
- **Errors** – entries failing validation (invalid mobile numbers, duplicate phones or tracking, etc.).

## Validation Rules

- Mobile numbers must match `09\d{9}` after normalisation.
- Tracking code of `1111111111111111` is rejected.
- Postal code alias overrides the postal code.
- Hakmat fields are cleared unless support status equals `3`.

The exporter writes a deterministic report to
`artifacts/export/export-report.json` describing counts of processed,
valid and error rows.
