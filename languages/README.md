# Orders Jet Integration - Translation Files

This directory contains translation files for the Orders Jet Integration plugin.

## File Types

- **`orders-jet.pot`** - Template file containing all translatable strings (source file)
- **`orders-jet-{locale}.po`** - Translation files for specific languages
- **`orders-jet-{locale}.mo`** - Compiled binary files (auto-generated)

## Available Translations

- **Arabic (ar)**: `orders-jet-ar.po` - Complete Arabic translation

## How to Create New Translations

### Method 1: Using Poedit (Recommended)

1. Download and install [Poedit](https://poedit.net/)
2. Open Poedit and select "File" → "New from POT/PO file"
3. Select the `orders-jet.pot` file
4. Choose your target language
5. Translate all the strings
6. Save as `orders-jet-{locale}.po` (e.g., `orders-jet-fr_FR.po` for French)
7. Poedit will automatically generate the `.mo` file

### Method 2: Using WP-CLI

```bash
# Generate POT file (if not exists)
wp i18n make-pot . languages/orders-jet.pot

# Create new translation from POT
cp languages/orders-jet.pot languages/orders-jet-{locale}.po

# Edit the .po file with your translations
# Then compile to .mo
msgfmt languages/orders-jet-{locale}.po -o languages/orders-jet-{locale}.mo
```

## Language Codes

Use standard WordPress locale codes:
- English (US): `en_US`
- Arabic: `ar`
- French: `fr_FR`
- Spanish: `es_ES`
- German: `de_DE`

## Testing Translations

1. Upload your `.po` and `.mo` files to the `languages/` directory
2. Change your WordPress site language to the target locale
3. Verify that all plugin strings are translated correctly

## Contributing Translations

If you create a new translation, please:

1. Test it thoroughly
2. Follow WordPress translation guidelines
3. Use proper RTL styling for RTL languages
4. Submit your translation files for inclusion

## Notes

- The plugin uses the text domain `orders-jet`
- All user-facing strings are wrapped with WordPress translation functions
- The plugin automatically loads the appropriate translation based on the site's locale
- RTL languages (like Arabic) will automatically get RTL styling when WordPress locale is set
