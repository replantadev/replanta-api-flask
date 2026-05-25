# Replanta Lite Theme

Ultra-light classic WordPress theme focused on:
- semantic HTML output
- high performance defaults
- Customizer-managed header/footer rows and columns

## Features

- Semantic templates with landmarks and skip link
- Header and footer with 3 configurable rows: top, main, bottom
- Per-row dynamic columns (1 to 4)
- Per-column module selection (brand, menus, search, CTA, social, text, widget)
- Widget-ready cells in each row and column
- Primary and footer menu fallback slots
- Minimal CSS runtime
- Core frontend cleanup (emoji/head bloat and core block style dequeue on public pages)

## Customizer Controls

Open Appearance > Customize:
- Header Builder
  - show/hide row
  - columns per row
  - row background color
  - row vertical spacing
  - per-column module selection
  - per-column custom text (when module is Text)
  - per-column button label and URL (when module is Button)
- Footer Builder
  - show/hide row
  - columns per row
  - row background color
  - row vertical spacing
  - per-column module selection
  - per-column custom text (when module is Text)
  - per-column button label and URL (when module is Button)
- Global Layout
  - container max width

## Widget Areas

Widget areas are created as:
- Header Top/Main/Bottom - Column 1..4
- Footer Top/Main/Bottom - Column 1..4

If a column has no widgets, the theme renders useful defaults in key positions.

## Performance Notes

- No framework dependencies
- No frontend JS required for layout
- Minimal CSS focused on layout and typography
- Optimized output intended to beat heavier multipurpose themes on equivalent hosting/cache setup

## Replanta Branding

In Appearance > Customize > Replanta Branding configure:
- CTA label and URL
- Brand text block
- Social links (X, LinkedIn, YouTube, Instagram)

## Installation

1. Copy folder to wp-content/themes/replanta-lite-theme
2. Activate Replanta Lite Theme
3. Configure rows/columns in Customizer
4. Assign menus in Appearance > Menus
5. Add widgets to header/footer column areas as needed
