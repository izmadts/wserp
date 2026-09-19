Fonts embedded in the invoice PDF (admin/sales/{id}/invoice).

- NotoSans-*.ttf        Latin text and digits
- NotoNaskhArabic-*.ttf Urdu / Arabic text (carries the Arabic presentation forms
                        that App\Support\ArabicShaper produces)

Both are Noto fonts (SIL Open Font License 1.1), the same files the sale agent app
bundles for its own invoices. dompdf converts them on first use and caches the
result in storage/fonts.
